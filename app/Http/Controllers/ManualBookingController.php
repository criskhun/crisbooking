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
        $request->merge([
            'duration_unit' => $request->input('duration_unit', 'day'),
            'duration_quantity' => $request->input('duration_quantity', $request->input('number_of_days')),
        ]);

        foreach (['total_amount', 'initial_payment_amount', 'security_deposit_amount'] as $moneyField) {
            if ($request->filled($moneyField)) {
                $request->merge([$moneyField => str_replace([',', '₱', ' '], '', (string) $request->input($moneyField))]);
            }
        }

        $validated = $request->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            // Outside bookings may be entered after the fact so historical sales can
            // be reflected in both the calendar and the sales dashboard.
            'start_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_unit' => ['required', Rule::in(['day', 'hour'])],
            'duration_quantity' => ['required', 'integer', 'min:1', 'max:8760'],
            'source_channel' => ['required', Rule::in(array_keys(Booking::MANUAL_SOURCE_OPTIONS))],
            'source_details' => ['nullable', 'string', 'max:160'],
            'external_customer_name' => ['nullable', 'string', 'max:120'],
            'fulfillment_method' => ['nullable', Rule::in(['pickup', 'delivery'])],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_option' => ['nullable', Rule::in(['fully_paid', 'downpayment', 'unpaid'])],
            'initial_payment_amount' => ['nullable', 'required_if:payment_option,downpayment', 'numeric', 'min:0.01', 'lte:total_amount'],
            'security_deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'security_deposit_collected' => ['nullable', 'boolean'],
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

            $durationUnit = $validated['duration_unit'];
            $durationQuantity = (int) $validated['duration_quantity'];
            if ($durationUnit === 'day' && $durationQuantity > 365) {
                throw ValidationException::withMessages([
                    'duration_quantity' => 'A daily outside booking can be up to 365 days.',
                ]);
            }
            $startDate = Carbon::createFromFormat('!Y-m-d', $validated['start_date'])->startOfDay();
            $fulfillmentMethod = null;
            $deliveryAddress = null;
            if ($unit->category === 'car') {
                $fulfillmentOptions = collect($unit->car_details['fulfillment_options'] ?? ['pickup']);
                $fulfillmentMethod = $validated['fulfillment_method'] ?? ($fulfillmentOptions->count() === 1 ? $fulfillmentOptions->first() : null);
                if (! $fulfillmentMethod || ! $fulfillmentOptions->contains($fulfillmentMethod)) {
                    throw ValidationException::withMessages(['fulfillment_method' => 'Choose an available pickup or delivery option.']);
                }
                $deliveryAddress = trim((string) ($validated['delivery_address'] ?? ''));
                if ($fulfillmentMethod === 'delivery' && $deliveryAddress === '') {
                    throw ValidationException::withMessages(['delivery_address' => 'Enter where the vehicle should be delivered.']);
                }
            }

            if ($durationUnit === 'day' && $unit->category === 'condo') {
                [$start, $end] = $unit->standardizeBookingPeriod(
                    $startDate,
                    $startDate->copy()->addDays($durationQuantity),
                );
            } else {
                [$startHour, $startMinute] = array_map('intval', explode(':', $validated['start_time']));
                $start = $startDate->setTime($startHour, $startMinute);
                $end = $durationUnit === 'hour'
                    ? $start->copy()->addHours($durationQuantity)
                    : $start->copy()->addDays($durationQuantity);
            }

            $conflict = $unit->bookings()->blocking()
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'start_date' => 'The selected listing already has a booking during part of that date and time range.',
                ]);
            }

            $commissionPercentage = $affiliatePartnership?->commission_percentage;
            $totalAmount = round((float) $validated['total_amount'], 2);
            $externalCustomerName = trim((string) ($validated['external_customer_name'] ?? ''));

            $booking = $unit->bookings()->create([
                'client_id' => $unit->host_id,
                'booked_by_user_id' => $actor->id,
                'booking_origin' => 'manual',
                'source_channel' => $validated['source_channel'],
                'source_details' => $validated['source_details'] ?? null,
                'external_customer_name' => $externalCustomerName !== '' ? $externalCustomerName : null,
                'fulfillment_method' => $fulfillmentMethod,
                'delivery_address' => $fulfillmentMethod === 'delivery' ? $deliveryAddress : null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => 'confirmed',
                'rate_period' => $durationUnit,
                'rate_quantity' => $durationQuantity,
                'total_amount' => $totalAmount,
                'security_deposit_amount' => round((float) ($validated['security_deposit_amount'] ?? 0), 2),
                'party_size' => $partySize,
                'notes' => $validated['notes'] ?? null,
                'affiliate_partnership_id' => $affiliatePartnership?->id,
                'affiliate_commission_percentage' => $commissionPercentage,
                'affiliate_commission_amount' => $commissionPercentage !== null
                    ? round($totalAmount * (float) $commissionPercentage / 100, 2)
                    : null,
            ]);

            $paymentOption = $validated['payment_option'] ?? 'unpaid';
            $initialPayment = match ($paymentOption) {
                'fully_paid' => $totalAmount,
                'downpayment' => round((float) $validated['initial_payment_amount'], 2),
                default => 0,
            };
            if ($initialPayment > 0) {
                $booking->financialEntries()->create([
                    'recorded_by_user_id' => $actor->id,
                    'kind' => 'payment',
                    'category' => $paymentOption === 'fully_paid' ? 'full_payment' : 'downpayment',
                    'amount' => $initialPayment,
                    'occurred_at' => now(),
                ]);
            }
            $depositAmount = round((float) ($validated['security_deposit_amount'] ?? 0), 2);
            if ($depositAmount > 0 && ! empty($validated['security_deposit_collected'])) {
                $booking->financialEntries()->create([
                    'recorded_by_user_id' => $actor->id,
                    'kind' => 'deposit',
                    'category' => 'security_deposit',
                    'amount' => $depositAmount,
                    'occurred_at' => now(),
                ]);
            }

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
                $booking->customerDisplayName().' reserved '.$booking->unit->name.' for '.$booking->durationDisplayLabel().'.',
                route('calendar.index', ['mode' => 'manage', 'month' => $booking->start_at->format('Y-m'), 'date' => $booking->start_at->format('Y-m-d')]),
                'booking:'.$booking->id.':manual-created:host',
            );
        }

        return redirect()->route('calendar.index', [
            'mode' => 'manage',
            'month' => $booking->start_at->format('Y-m'),
            'date' => $booking->start_at->format('Y-m-d'),
        ])->with('status', 'Outside booking added. The listing is now blocked for '.$booking->durationDisplayLabel().'.');
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
