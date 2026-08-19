<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingDeletion;
use App\Models\Unit;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBookingController extends Controller
{
    public function index(Request $request): View
    {
        $statuses = ['pending', 'pre_approved', 'payment_submitted', 'confirmed', 'cancelled', 'declined', 'unavailable'];
        $periods = ['active', 'upcoming', 'history'];
        $query = Booking::query()->with(['unit.host', 'client', 'bookedBy', 'affiliatePartnership.marketer']);

        if ($request->filled('host_id')) {
            $query->whereHas('unit', fn ($units) => $units->where('host_id', $request->integer('host_id')));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->integer('unit_id'));
        }

        if ($request->filled('status') && in_array($request->string('status')->toString(), $statuses, true)) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('origin') && in_array($request->string('origin')->toString(), ['platform', 'manual'], true)) {
            $query->where('booking_origin', $request->string('origin'));
        }

        if ($request->filled('period') && in_array($request->string('period')->toString(), $periods, true)) {
            match ($request->string('period')->toString()) {
                'active' => $query->where('start_at', '<=', now())->where('end_at', '>', now()),
                'upcoming' => $query->where('start_at', '>', now()),
                'history' => $query->where('end_at', '<=', now()),
            };
        }

        if ($request->filled('search')) {
            $searchText = $request->string('search')->trim()->toString();
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $searchText).'%';
            $query->where(function ($bookings) use ($search, $searchText) {
                $bookings->whereHas('unit', fn ($units) => $units->where('name', 'like', $search))
                    ->orWhereHas('unit.host', fn ($hosts) => $hosts->where('name', 'like', $search)->orWhere('email', 'like', $search))
                    ->orWhereHas('client', fn ($clients) => $clients->where('name', 'like', $search)->orWhere('email', 'like', $search))
                    ->orWhere('external_customer_name', 'like', $search)
                    ->orWhere('source_details', 'like', $search);

                if (ctype_digit($searchText)) {
                    $bookings->orWhereKey((int) $searchText);
                }
            });
        }

        return view('admin.bookings.index', [
            'bookings' => $query->latest('start_at')->paginate(25)->withQueryString(),
            'deletions' => BookingDeletion::query()->with('remover:id,name')->latest('removed_at')->limit(20)->get(),
            'hosts' => User::query()->whereHas('units')->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->with('host:id,name')->orderBy('name')->get(['id', 'host_id', 'name', 'category']),
            'statuses' => $statuses,
            'periods' => $periods,
            'counts' => [
                'all' => Booking::query()->count(),
                'blocking' => Booking::query()->blocking()->where('end_at', '>', now())->count(),
                'manual' => Booking::query()->where('booking_origin', 'manual')->count(),
                'removed' => BookingDeletion::query()->count(),
            ],
        ]);
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'removal_reason' => ['required', 'string', 'min:5', 'max:1000'],
            'confirmation' => ['required', Rule::in(['remove'])],
        ]);

        $removal = DB::transaction(function () use ($booking, $request, $validated): array {
            $locked = Booking::query()
                ->with(['unit.host', 'client', 'bookedBy', 'affiliatePartnership.marketer'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            $deletion = BookingDeletion::query()->create([
                'original_booking_id' => $locked->id,
                'unit_id' => $locked->unit_id,
                'host_id' => $locked->unit->host_id,
                'client_id' => $locked->isManualBooking() ? null : $locked->client_id,
                'removed_by' => $request->user()->id,
                'booking_origin' => $locked->booking_origin,
                'booking_status' => $locked->status,
                'source_channel' => $locked->source_channel,
                'unit_name' => $locked->unit->name,
                'host_name' => $locked->unit->host->name,
                'customer_name' => $locked->customerDisplayName(),
                'start_at' => $locked->start_at,
                'end_at' => $locked->end_at,
                'total_amount' => $locked->total_amount,
                'removal_reason' => $validated['removal_reason'],
                'booking_snapshot' => collect($locked->getAttributes())->except(['payment_proof_path', 'payment_proof_name'])->all(),
                'removed_at' => now(),
            ]);

            if ($locked->inquiry_id) {
                $locked->inquiry()->update(['status' => 'closed']);
            }

            $proofPath = $locked->payment_proof_path;
            $locked->delete();

            return [
                'deletion_id' => $deletion->id,
                'proof_path' => $proofPath,
                'affiliate_id' => $locked->affiliatePartnership?->marketer_id,
            ];
        });

        if ($removal['proof_path']) {
            Storage::disk('local')->delete($removal['proof_path']);
        }

        $deletion = BookingDeletion::query()->with(['host', 'client'])->findOrFail($removal['deletion_id']);
        $affiliate = $removal['affiliate_id'] ? User::query()->find($removal['affiliate_id']) : null;
        $recipients = collect([$deletion->host, $deletion->client, $affiliate])->filter()->unique('id');
        $recipients->each(fn (User $recipient) => app(AppNotificationService::class)->send(
            $recipient,
            'booking_removed_by_admin',
            'Booking record removed by an administrator',
            '#'.$deletion->original_booking_id.' for '.$deletion->unit_name.' was removed. Reason: '.$deletion->removal_reason,
            route('support.index'),
        ));

        return redirect()->route('admin.bookings.index', $request->query())->with('status', 'Booking #'.$deletion->original_booking_id.' was removed and saved in the deletion ledger.');
    }
}
