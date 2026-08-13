<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Unit;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function show(Request $request, Booking $booking): View
    {
        $canView = $request->user()->is_admin
            || $booking->client_id === $request->user()->id
            || ($request->user()->isHost() && $booking->unit()->where('host_id', $request->user()->id)->exists());

        abort_unless($canView, 403);
        $booking->load(['unit.host', 'unit.images', 'unit.rates', 'client', 'inquiry']);

        return view('bookings.show', compact('booking'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'inquiry_id' => ['required', 'integer', 'exists:inquiries,id'],
            'unit_rate_id' => ['nullable', 'integer', 'exists:unit_rates,id'],
            'rental_coverage' => ['nullable', Rule::in(['within_city', 'out_of_town'])],
            'start_at' => ['required', 'date', 'after:now'],
            'end_at' => ['nullable', 'date', 'after:start_at'],
            'duration_pricing' => ['nullable', 'boolean'],
            'package_quantities' => ['nullable', 'array'],
            'package_quantities.*' => ['nullable', 'integer', 'min:0', 'max:365'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = DB::transaction(function () use ($validated, $request) {
            $unit = Unit::query()->lockForUpdate()->findOrFail($validated['unit_id']);
            $inquiry = Inquiry::query()->lockForUpdate()->with('booking')->findOrFail($validated['inquiry_id']);
            $start = Carbon::parse($validated['start_at']);
            $rate = null;
            $ratePeriod = null;
            $rateQuantity = 1;
            $packageBreakdown = null;
            $rentalCoverage = null;
            $partySize = (int) ($validated['party_size'] ?? 1);

            if (! $request->user()->hasCompleteProfile()) {
                throw ValidationException::withMessages(['profile' => 'Complete your verification profile before booking.']);
            }

            if ($unit->host_id === $request->user()->id) {
                throw ValidationException::withMessages(['unit_id' => 'You cannot book your own listing.']);
            }

            if ($inquiry->client_id !== $request->user()->id || $inquiry->unit_id !== $unit->id) {
                throw ValidationException::withMessages(['inquiry_id' => 'Start an inquiry with this host before booking.']);
            }

            if ($inquiry->booking) {
                throw ValidationException::withMessages(['inquiry_id' => 'This inquiry already has a booking request.']);
            }

            if ($unit->capacity && $partySize > $unit->capacity) {
                throw ValidationException::withMessages([
                    'party_size' => "This listing can accommodate up to {$unit->capacity} people.",
                ]);
            }

            if ($unit->isPackageRental()) {
                $rentalCoverage = $this->resolveRentalCoverage($unit, $validated['rental_coverage'] ?? null, 'rental_coverage');
                if (! empty($validated['duration_pricing'])) {
                    if (empty($validated['end_at'])) {
                        throw ValidationException::withMessages([
                            'end_at' => 'Choose a future return date and time before selecting a rate.',
                        ]);
                    }

                    $end = Carbon::parse($validated['end_at']);
                    $packageBreakdown = $this->buildDurationPackageBreakdown($unit, $start, $end, 'package_quantities', $rentalCoverage);
                } else {
                    $quantities = collect($validated['package_quantities'] ?? [])->map(fn ($quantity) => (int) $quantity)->filter()->all();

                    if ($quantities === [] && ! empty($validated['unit_rate_id'])) {
                        $legacyRate = $unit->rates()->where('coverage', $rentalCoverage)->whereKey($validated['unit_rate_id'])->first();

                        if (! $legacyRate) {
                            throw ValidationException::withMessages([
                                'unit_rate_id' => 'The selected rental package is not offered by this listing.',
                            ]);
                        }

                        $quantities = [$legacyRate->period => 1];
                    }

                    $packageBreakdown = $this->buildPackageBreakdown($unit, $quantities, 'package_quantities', $rentalCoverage);
                    $end = $this->packageEnd($start, $packageBreakdown);
                }

                $rateQuantity = collect($packageBreakdown)->sum('quantity');
                $ratePeriod = count($packageBreakdown) === 1 ? array_key_first($packageBreakdown) : 'mixed';
                $rate = count($packageBreakdown) === 1 ? $unit->rates()->where('coverage', $rentalCoverage)->where('period', $ratePeriod)->first() : null;
            } else {
                if (empty($validated['end_at'])) {
                    throw ValidationException::withMessages([
                        'end_at' => 'Choose when this service booking ends.',
                    ]);
                }

                $end = Carbon::parse($validated['end_at']);
            }

            if ($this->hasScheduleConflict($unit, $start, $end)) {
                throw ValidationException::withMessages([
                    'start_at' => 'This unit or service is already booked during the selected time.',
                ]);
            }

            $additionalCharges = $this->carAdditionalCharges($unit);
            $rentalTotal = $packageBreakdown
                ? $this->packageTotal($packageBreakdown)
                : $this->calculateTotal($unit, $start, $end);
            $bookingTotal = round($rentalTotal + $this->additionalChargeTotal($additionalCharges), 2);
            $commissionableTotal = max(0, $bookingTotal - (float) collect($additionalCharges)
                ->filter(fn ($charge) => (bool) ($charge['refundable'] ?? false))
                ->sum('amount'));
            $commissionPercentage = $inquiry->affiliate_commission_percentage;

            return $unit->bookings()->create([
                'inquiry_id' => $inquiry->id,
                'client_id' => $request->user()->id,
                'unit_rate_id' => $rate?->id,
                'start_at' => $start,
                'end_at' => $end,
                'status' => 'pending',
                'rate_period' => $ratePeriod,
                'rental_coverage' => $unit->category === 'car' ? $rentalCoverage : null,
                'rate_quantity' => $rateQuantity,
                'package_breakdown' => $packageBreakdown,
                'additional_charges' => $additionalCharges ?: null,
                'total_amount' => $bookingTotal,
                'party_size' => $partySize,
                'notes' => $validated['notes'] ?? null,
                'affiliate_partnership_id' => $inquiry->affiliate_partnership_id,
                'affiliate_commission_percentage' => $commissionPercentage,
                'affiliate_commission_amount' => $commissionPercentage !== null
                    ? round($commissionableTotal * (float) $commissionPercentage / 100, 2)
                    : null,
            ]);
        });

        $booking->inquiry()->update([
            'status' => 'booking_requested',
            'desired_start_at' => $booking->start_at,
            'desired_end_at' => $booking->end_at,
            'party_size' => $booking->party_size,
        ]);
        $booking->loadMissing('unit.host');
        app(AppNotificationService::class)->send(
            $booking->unit->host,
            'booking_request',
            'New booking request',
            $request->user()->name.' requested to book '.$booking->unit->name.'.',
            route('inquiries.show', $booking->inquiry_id),
        );

        return redirect()->route('calendar.index', [
            'mode' => 'book',
            'month' => $booking->start_at->format('Y-m'),
            'date' => $booking->start_at->format('Y-m-d'),
        ])->with('status', 'Booking request submitted. The host can now confirm it.');
    }

    public function requestChange(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->client_id === $request->user()->id, 403);
        abort_unless(in_array($booking->status, ['pending', 'confirmed'], true) && $booking->end_at->isFuture(), 422, 'Only an active upcoming booking can be changed.');

        $validated = $request->validate([
            'change_start_at' => ['required', 'date', 'after:now'],
            'change_end_at' => ['required', 'date', 'after:change_start_at'],
            'change_duration_pricing' => ['nullable', 'boolean'],
            'change_package_quantities' => ['nullable', 'array'],
            'change_package_quantities.*' => ['nullable', 'integer', 'min:0', 'max:365'],
            'change_party_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'change_request_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($booking, $validated, $request) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($lockedBooking->unit_id);
            abort_unless($lockedBooking->client_id === $request->user()->id, 403);
            abort_unless(in_array($lockedBooking->status, ['pending', 'confirmed'], true) && $lockedBooking->end_at->isFuture(), 422, 'Only an active upcoming booking can be changed.');

            $start = Carbon::parse($validated['change_start_at']);
            $requestedEnd = Carbon::parse($validated['change_end_at']);
            $packageBreakdown = null;

            if ($unit->isPackageRental()) {
                $rentalCoverage = $this->resolveRentalCoverage(
                    $unit,
                    $lockedBooking->rental_coverage ?: $lockedBooking->rate?->coverage,
                    'change_package_quantities',
                );
                if (! empty($validated['change_duration_pricing'])) {
                    $packageBreakdown = $this->buildDurationPackageBreakdown($unit, $start, $requestedEnd, 'change_package_quantities', $rentalCoverage);
                    $end = $requestedEnd;
                } else {
                    $quantities = collect($validated['change_package_quantities'] ?? [])->map(fn ($quantity) => (int) $quantity)->filter()->all();

                    if ($quantities === [] && $lockedBooking->rate_period && $lockedBooking->rate_period !== 'mixed') {
                        $quantities = [$lockedBooking->rate_period => $lockedBooking->packageQuantityFor($start, $requestedEnd)];
                    }

                    $packageBreakdown = $this->buildPackageBreakdown($unit, $quantities, 'change_package_quantities', $rentalCoverage);
                    $end = $this->packageEnd($start, $packageBreakdown);
                }
            } else {
                $end = $requestedEnd;
            }
            $partySize = (int) $validated['change_party_size'];

            if ($unit->capacity && $partySize > $unit->capacity) {
                throw ValidationException::withMessages([
                    'change_party_size' => "This listing can accommodate up to {$unit->capacity} people.",
                ]);
            }

            if ($this->hasScheduleConflict($unit, $start, $end, $lockedBooking->id)) {
                throw ValidationException::withMessages([
                    'change_start_at' => 'The requested schedule conflicts with another booking for this unit.',
                ]);
            }

            $lockedBooking->update([
                'change_start_at' => $start,
                'change_end_at' => $end,
                'change_party_size' => $partySize,
                'change_package_breakdown' => $packageBreakdown,
                'change_request_status' => 'pending',
                'change_request_note' => $validated['change_request_note'] ?? null,
                'change_requested_at' => now(),
                'change_reviewed_at' => null,
            ]);
        });

        $booking->loadMissing('unit.host');
        app(AppNotificationService::class)->send(
            $booking->unit->host,
            'booking_change',
            'Booking change requested',
            $request->user()->name.' requested changes to '.$booking->unit->name.'.',
            route('bookings.show', $booking),
        );

        return back()->with('status', 'Your change request was sent to the host. The current booking stays unchanged until approval.');
    }

    public function reviewChange(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(
            $request->user()->is_admin || ($request->user()->isHost() && $booking->unit()->where('host_id', $request->user()->id)->exists()),
            403
        );

        $validated = $request->validate(['decision' => ['required', Rule::in(['approve', 'decline'])]]);

        DB::transaction(function () use ($booking, $validated, $request) {
            $lockedBooking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $unit = Unit::query()->lockForUpdate()->findOrFail($lockedBooking->unit_id);
            abort_unless($request->user()->is_admin || ($request->user()->isHost() && $unit->host_id === $request->user()->id), 403);
            abort_unless($lockedBooking->hasPendingChangeRequest(), 422, 'This booking does not have a pending change request.');

            if ($validated['decision'] === 'decline') {
                $lockedBooking->update([
                    'change_request_status' => 'declined',
                    'change_reviewed_at' => now(),
                ]);

                return;
            }

            $start = $lockedBooking->change_start_at;
            $end = $lockedBooking->change_end_at;
            $partySize = (int) $lockedBooking->change_party_size;

            if ($unit->capacity && $partySize > $unit->capacity) {
                throw ValidationException::withMessages([
                    'change_party_size' => "This listing can accommodate up to {$unit->capacity} people.",
                ]);
            }

            if ($this->hasScheduleConflict($unit, $start, $end, $lockedBooking->id)) {
                throw ValidationException::withMessages([
                    'change_start_at' => 'The requested schedule now conflicts with another booking and cannot be approved.',
                ]);
            }

            $packageBreakdown = $lockedBooking->change_package_breakdown;
            $rateQuantity = $packageBreakdown ? collect($packageBreakdown)->sum('quantity') : 1;
            $ratePeriod = $packageBreakdown ? (count($packageBreakdown) === 1 ? array_key_first($packageBreakdown) : 'mixed') : null;
            $rentalCoverage = $unit->isPackageRental()
                ? $this->resolveRentalCoverage($unit, $lockedBooking->rental_coverage ?: $lockedBooking->rate?->coverage, 'change_package_quantities')
                : null;
            $unitRateId = $ratePeriod && $ratePeriod !== 'mixed'
                ? $unit->rates()->where('coverage', $rentalCoverage)->where('period', $ratePeriod)->value('id')
                : null;
            $total = $packageBreakdown
                ? $this->packageTotal($packageBreakdown)
                : $this->calculateTotal($unit, $start, $end);
            $total = round($total + $this->additionalChargeTotal($lockedBooking->additional_charges ?? []), 2);
            $commissionableTotal = max(0, $total - (float) collect($lockedBooking->additional_charges ?? [])
                ->filter(fn ($charge) => (bool) ($charge['refundable'] ?? false))
                ->sum('amount'));

            $lockedBooking->update([
                'start_at' => $start,
                'end_at' => $end,
                'party_size' => $partySize,
                'unit_rate_id' => $unitRateId,
                'rate_period' => $ratePeriod,
                'rate_quantity' => $rateQuantity,
                'package_breakdown' => $packageBreakdown,
                'total_amount' => $total,
                'affiliate_commission_amount' => $lockedBooking->affiliate_commission_percentage !== null
                    ? round($commissionableTotal * (float) $lockedBooking->affiliate_commission_percentage / 100, 2)
                    : null,
                'change_request_status' => 'approved',
                'change_reviewed_at' => now(),
            ]);
            $lockedBooking->inquiry()->update([
                'desired_start_at' => $start,
                'desired_end_at' => $end,
                'party_size' => $partySize,
            ]);
        });

        $booking->loadMissing('unit');
        app(AppNotificationService::class)->send(
            $booking->client,
            'booking_change_reviewed',
            $validated['decision'] === 'approve' ? 'Booking change approved' : 'Booking change declined',
            'Your requested changes for '.$booking->unit->name.' were '.$validated['decision'].'d.',
            route('bookings.show', $booking),
        );

        return back()->with('status', $validated['decision'] === 'approve'
            ? 'Booking changes approved and the schedule has been updated.'
            : 'Booking change request declined. The original schedule remains active.');
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(
            $request->user()->is_admin || ($request->user()->isHost() && $booking->unit()->where('host_id', $request->user()->id)->exists()),
            403
        );

        $validated = $request->validate(['status' => ['required', Rule::in(['confirmed', 'cancelled'])]]);
        $booking->update(['status' => $validated['status']]);
        $booking->inquiry()->update(['status' => $validated['status'] === 'confirmed' ? 'confirmed' : 'closed']);
        $booking->loadMissing('unit');
        app(AppNotificationService::class)->send(
            $booking->client,
            'booking_status',
            $validated['status'] === 'confirmed' ? 'Booking approved' : 'Booking declined',
            $booking->unit->name.' was '.($validated['status'] === 'confirmed' ? 'approved.' : 'declined.'),
            route('bookings.show', $booking),
        );

        return back()->with('status', $validated['status'] === 'confirmed' ? 'Booking confirmed.' : 'Booking declined.');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->client_id === $request->user()->id, 403);
        abort_if($booking->status === 'cancelled', 422, 'This booking is already cancelled.');

        $booking->update(['status' => 'cancelled']);
        $booking->inquiry()->update(['status' => 'closed']);
        $booking->loadMissing('unit.host');
        app(AppNotificationService::class)->send(
            $booking->unit->host,
            'booking_cancelled',
            'Booking cancelled',
            $request->user()->name.' cancelled the booking for '.$booking->unit->name.'.',
            route('bookings.show', $booking),
        );

        return back()->with('status', 'Booking cancelled. The schedule is available again.');
    }

    private function buildPackageBreakdown(Unit $unit, array $quantities, string $errorKey, string $coverage = 'standard'): array
    {
        $rates = $unit->rates()->where('coverage', $coverage)->get()->keyBy('period');
        $selected = [];

        foreach ($quantities as $period => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity < 1) {
                continue;
            }

            $rate = $rates->get($period);

            if (! $rate) {
                throw ValidationException::withMessages([$errorKey => 'One of the selected packages is not offered by this listing.']);
            }

            $selected[$period] = [
                'quantity' => $quantity,
                'unit_price' => round((float) $rate->price, 2),
                'subtotal' => round((float) $rate->price * $quantity, 2),
            ];
        }

        if ($selected === []) {
            throw ValidationException::withMessages([$errorKey => 'Select at least one available rental package.']);
        }

        return $selected;
    }

    private function buildDurationPackageBreakdown(Unit $unit, Carbon $start, Carbon $end, string $errorKey, string $coverage = 'standard'): array
    {
        $rates = $unit->rates()->where('coverage', $coverage)->get()->keyBy('period');
        $quantities = [];
        $cursor = $start->copy();

        if ($rates->has('month')) {
            while ($cursor->copy()->addMonthNoOverflow()->lte($end)) {
                $quantities['month'] = ($quantities['month'] ?? 0) + 1;
                $cursor->addMonthNoOverflow();
            }
        }

        $remainingMinutes = max(0, (int) $cursor->diffInMinutes($end));

        foreach (['week' => 10080, 'day' => 1440] as $period => $minutes) {
            if (! $rates->has($period)) {
                continue;
            }

            $quantity = intdiv($remainingMinutes, $minutes);

            if ($quantity > 0) {
                $quantities[$period] = ($quantities[$period] ?? 0) + $quantity;
                $remainingMinutes -= $quantity * $minutes;
            }
        }

        if ($remainingMinutes > 0) {
            if ($remainingMinutes > 720 && $rates->has('day')) {
                $quantities['day'] = ($quantities['day'] ?? 0) + 1;
            } elseif ($rates->has('12_hours')) {
                $quantities['12_hours'] = ($quantities['12_hours'] ?? 0) + (int) ceil($remainingMinutes / 720);
            } elseif ($rates->has('day')) {
                $quantities['day'] = ($quantities['day'] ?? 0) + 1;
            } elseif ($rates->has('week')) {
                $quantities['week'] = ($quantities['week'] ?? 0) + 1;
            } elseif ($rates->has('month')) {
                $quantities['month'] = ($quantities['month'] ?? 0) + 1;
            }
        }

        $orderedQuantities = $rates->keys()
            ->filter(fn ($period) => ($quantities[$period] ?? 0) > 0)
            ->mapWithKeys(fn ($period) => [$period => $quantities[$period]])
            ->all();

        return $this->buildPackageBreakdown($unit, $orderedQuantities, $errorKey, $coverage);
    }

    private function resolveRentalCoverage(Unit $unit, ?string $requestedCoverage, string $errorKey): string
    {
        if ($unit->category !== 'car') {
            return 'standard';
        }

        $availableCoverages = $unit->rates()
            ->reorder()
            ->select('coverage')
            ->distinct()
            ->pluck('coverage')
            ->filter()
            ->values();

        if ($requestedCoverage && $availableCoverages->contains($requestedCoverage)) {
            return $requestedCoverage;
        }

        if ($requestedCoverage) {
            throw ValidationException::withMessages([$errorKey => 'The selected rental coverage is not offered for this car.']);
        }

        if ($availableCoverages->count() <= 1) {
            return (string) ($availableCoverages->first() ?: 'standard');
        }

        throw ValidationException::withMessages([$errorKey => 'Choose within-city or out-of-town use before booking this car.']);
    }

    private function packageEnd(Carbon $start, array $breakdown): Carbon
    {
        $end = $start->copy();
        $end->addMonthsNoOverflow((int) ($breakdown['month']['quantity'] ?? 0));
        $end->addWeeks((int) ($breakdown['week']['quantity'] ?? 0));
        $end->addDays((int) ($breakdown['day']['quantity'] ?? 0));
        $end->addHours(12 * (int) ($breakdown['12_hours']['quantity'] ?? 0));

        return $end;
    }

    private function packageTotal(array $breakdown): float
    {
        return round((float) collect($breakdown)->sum('subtotal'), 2);
    }

    private function calculateTotal(Unit $unit, Carbon $start, Carbon $end): float
    {
        $minutes = max(1, $start->diffInMinutes($end));
        $quantity = match ($unit->pricing_unit) {
            'hour' => (int) ceil($minutes / 60),
            'day' => (int) ceil($minutes / 1440),
            default => 1,
        };

        return round((float) $unit->price * max(1, $quantity), 2);
    }

    private function carAdditionalCharges(Unit $unit): array
    {
        if ($unit->category !== 'car') {
            return [];
        }

        return collect($unit->car_details['charges'] ?? [])
            ->map(fn ($charge, $key) => [
                'key' => $key,
                'label' => (string) ($charge['label'] ?? str($key)->replace('_', ' ')->title()),
                'amount' => round((float) ($charge['amount'] ?? 0), 2),
                'refundable' => (bool) ($charge['refundable'] ?? false),
            ])
            ->values()
            ->all();
    }

    private function additionalChargeTotal(array $charges): float
    {
        return round((float) collect($charges)->sum('amount'), 2);
    }

    private function hasScheduleConflict(Unit $unit, Carbon $start, Carbon $end, ?int $exceptBookingId = null): bool
    {
        return $unit->bookings()->blocking()
            ->when($exceptBookingId, fn ($query) => $query->where('bookings.id', '!=', $exceptBookingId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();
    }
}
