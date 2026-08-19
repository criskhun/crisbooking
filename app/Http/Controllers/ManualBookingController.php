<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Unit;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManualBookingController extends Controller
{
    public function store(Request $request, AppNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'number_of_days' => ['required', 'integer', 'min:1', 'max:365'],
            'source_channel' => ['required', Rule::in(array_keys(Booking::MANUAL_SOURCE_OPTIONS))],
            'source_details' => ['nullable', 'string', 'max:160'],
            'external_customer_name' => ['nullable', 'string', 'max:120'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'affiliate_partnership_id' => ['nullable', 'integer', 'exists:affiliate_partnerships,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actor = $request->user();
        $booking = DB::transaction(function () use ($validated, $actor) {
            $unit = Unit::query()->lockForUpdate()->findOrFail($validated['unit_id']);
            $affiliatePartnership = $this->affiliatePartnershipFor($actor, $unit, $validated['affiliate_partnership_id'] ?? null);
            if (! empty($validated['affiliate_partnership_id']) && ! $affiliatePartnership) {
                throw ValidationException::withMessages([
                    'affiliate_partnership_id' => 'Choose an accepted affiliate assigned to this listing.',
                ]);
            }
            $canCreate = $actor->is_admin
                || ($actor->isHost() && $unit->host_id === $actor->id)
                || $affiliatePartnership?->marketer_id === $actor->id;

            abort_unless($canCreate, 403);

            $partySize = (int) ($validated['party_size'] ?? 1);
            if ($unit->capacity && $partySize > $unit->capacity) {
                throw ValidationException::withMessages([
                    'party_size' => "This listing can accommodate up to {$unit->capacity} people or units.",
                ]);
            }

            $start = Carbon::createFromFormat('!Y-m-d', $validated['start_date'])->startOfDay();
            $days = (int) $validated['number_of_days'];
            $end = $start->copy()->addDays($days);
            $conflict = $unit->bookings()->blocking()
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'start_date' => 'This listing is already occupied during part of that date range.',
                ]);
            }

            $commissionPercentage = $affiliatePartnership?->commission_percentage;
            $totalAmount = round((float) $validated['total_amount'], 2);

            $booking = $unit->bookings()->create([
                'client_id' => $unit->host_id,
                'booked_by_user_id' => $actor->id,
                'booking_origin' => 'manual',
                'source_channel' => $validated['source_channel'],
                'source_details' => $validated['source_details'] ?? null,
                'external_customer_name' => $validated['external_customer_name'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => 'confirmed',
                'rate_period' => 'day',
                'rate_quantity' => $days,
                'total_amount' => $totalAmount,
                'party_size' => $partySize,
                'notes' => $validated['notes'] ?? null,
                'affiliate_partnership_id' => $affiliatePartnership?->id,
                'affiliate_commission_percentage' => $commissionPercentage,
                'affiliate_commission_amount' => $commissionPercentage !== null
                    ? round($totalAmount * (float) $commissionPercentage / 100, 2)
                    : null,
            ]);

            $conflictingRequests = $unit->bookings()
                ->whereKeyNot($booking->id)
                ->where('status', 'pending')
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->lockForUpdate()
                ->get();
            foreach ($conflictingRequests as $conflictingRequest) {
                $conflictingRequest->update(['status' => 'unavailable']);
                $conflictingRequest->inquiry?->update(['status' => 'closed']);
            }

            return $booking;
        });

        $booking->loadMissing('unit.host', 'affiliatePartnership.marketer');
        if ($booking->booked_by_user_id !== $booking->unit->host_id) {
            $notifications->send(
                $booking->unit->host,
                'manual_booking_created',
                'Affiliate added an outside booking',
                $booking->customerDisplayName().' reserved '.$booking->unit->name.' for '.$booking->durationDays().' '.str('day')->plural($booking->durationDays()).'.',
                route('calendar.index', ['mode' => 'manage', 'month' => $booking->start_at->format('Y-m'), 'date' => $booking->start_at->format('Y-m-d')]),
                'booking:'.$booking->id.':manual-created:host',
            );
        }

        return redirect()->route('calendar.index', [
            'mode' => 'manage',
            'month' => $booking->start_at->format('Y-m'),
            'date' => $booking->start_at->format('Y-m-d'),
        ])->with('status', 'Outside booking added. The listing is now blocked for '.$booking->durationDays().' '.str('day')->plural($booking->durationDays()).'.');
    }

    private function affiliatePartnershipFor(mixed $actor, Unit $unit, ?int $requestedPartnershipId): ?AffiliatePartnership
    {
        $query = AffiliatePartnership::query()
            ->where('status', 'accepted')
            ->where('host_id', $unit->host_id)
            ->whereHas('units', fn ($units) => $units->whereKey($unit->id));

        if (! $actor->isHost() && ! $actor->is_admin) {
            return $query->where('marketer_id', $actor->id)->first();
        }

        return $requestedPartnershipId ? $query->find($requestedPartnershipId) : null;
    }
}
