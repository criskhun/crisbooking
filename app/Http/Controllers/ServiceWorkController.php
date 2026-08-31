<?php

namespace App\Http\Controllers;

use App\Models\BookingExpense;
use App\Models\ServiceProviderApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceWorkController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = BookingExpense::query()
            ->with(['booking.unit:id,host_id,name,category,location', 'serviceUnit:id,name,category'])
            ->where('provider_user_id', $request->user()->id)
            ->latest('scheduled_at')
            ->latest()
            ->get();
        $active = $assignments->whereIn('status', ['assigned', 'completed', 'paid']);
        $metrics = [
            'active_count' => $active->count(),
            'assigned_total' => $assignments->where('status', '!=', 'cancelled')->sum('amount'),
            'payment_pending' => $assignments->where('status', 'completed')->sum('amount'),
            'paid_total' => $assignments->whereIn('status', ['paid', 'payment_received'])->sum('amount'),
        ];
        $myApplications = ServiceProviderApplication::query()
            ->with('host:id,name')
            ->where('applicant_user_id', $request->user()->id)
            ->latest()
            ->get();
        $receivedApplicationsQuery = ServiceProviderApplication::query()->with('applicant:id,name,email');
        if (! $request->user()->is_admin) {
            $receivedApplicationsQuery->where(
                $request->user()->isHost() ? 'host_id' : 'id',
                $request->user()->isHost() ? $request->user()->id : 0,
            );
        }
        $receivedApplications = $receivedApplicationsQuery->latest()->get();
        $availableHosts = User::query()
            ->where('role', 'host')
            ->where('is_active', true)
            ->whereKeyNot($request->user()->id)
            ->whereHas('units', fn ($units) => $units->whereIn('category', ['car', 'condo'])->where('is_active', true))
            ->withCount(['units' => fn ($units) => $units->whereIn('category', ['car', 'condo'])->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('service-work.index', compact('assignments', 'metrics', 'myApplications', 'receivedApplications', 'availableHosts'));
    }

    public function complete(Request $request, BookingExpense $expense, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($expense->provider_user_id === $request->user()->id, 403);
        abort_unless($expense->status === 'assigned', 422, 'Only assigned work can be marked completed.');
        $validated = $request->validate([
            'completion_images' => ['nullable', 'array', 'max:6'],
            'completion_images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $storedPaths = [];
        try {
            $images = collect($validated['completion_images'] ?? [])->map(function ($image) use ($expense, &$storedPaths) {
                $path = $image->store('booking-expenses/'.$expense->id.'/completion', 'local');
                $storedPaths[] = $path;

                return ['path' => $path, 'name' => $image->getClientOriginalName()];
            })->all();
            DB::transaction(function () use ($expense, $images, $request) {
                $locked = BookingExpense::query()->lockForUpdate()->findOrFail($expense->id);
                abort_unless($locked->provider_user_id === $request->user()->id, 403);
                abort_unless($locked->status === 'assigned', 422, 'Only assigned work can be marked completed.');
                $locked->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completion_images' => $images ?: null,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        $expense->loadMissing('booking.unit.host');
        $notifications->send(
            $expense->booking->unit->host,
            'service_work_completed',
            'Assigned service completed',
            $request->user()->name.' completed '.$expense->categoryLabel().' for booking #'.$expense->booking_id.'.',
            route('bookings.show', $expense->booking_id),
            'booking-expense:'.$expense->id.':completed',
        );

        return back()->with('status', 'Work marked completed. The booking host was notified.');
    }

    public function confirmPayment(Request $request, BookingExpense $expense, AppNotificationService $notifications): RedirectResponse
    {
        abort_unless($expense->provider_user_id === $request->user()->id, 403);
        $result = DB::transaction(function () use ($expense, $request) {
            $locked = BookingExpense::query()->lockForUpdate()->findOrFail($expense->id);
            abort_unless($locked->provider_user_id === $request->user()->id, 403);
            if ($locked->status === 'payment_received') {
                return 'already_confirmed';
            }
            if ($locked->status !== 'paid') {
                return 'not_paid';
            }
            $locked->update(['status' => 'payment_received', 'payment_received_at' => now()]);

            return 'confirmed';
        });
        if ($result === 'already_confirmed') {
            return back()->with('status', 'Payment was already confirmed. This service task is closed.');
        }
        if ($result === 'not_paid') {
            return back()->withErrors(['payment' => 'The host must mark this job paid before you can confirm receipt.']);
        }
        $expense->refresh()->loadMissing('booking.unit.host');
        $notifications->send(
            $expense->booking->unit->host,
            'service_payment_received',
            'Provider confirmed payment',
            $request->user()->name.' confirmed payment for '.$expense->categoryLabel().' on booking #'.$expense->booking_id.'. The task is now closed.',
            route('bookings.show', $expense->booking_id),
            'booking-expense:'.$expense->id.':payment-received',
        );

        return back()->with('status', 'Payment receipt confirmed. This service task is now closed.');
    }

    public function completionImage(Request $request, BookingExpense $expense, int $image): StreamedResponse
    {
        $expense->loadMissing('booking.unit');
        abort_unless(
            $request->user()->is_admin
                || $expense->provider_user_id === $request->user()->id
                || $expense->booking->unit->host_id === $request->user()->id,
            403,
        );
        $file = ($expense->completion_images ?? [])[$image] ?? null;
        abort_unless($file && Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->response($file['path'], $file['name'] ?? null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
