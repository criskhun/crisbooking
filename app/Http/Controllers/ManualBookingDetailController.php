<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePartnership;
use App\Models\Booking;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManualBookingDetailController extends Controller
{
    private const PACKAGE_LABELS = [
        '12_hours' => '12 hours',
        'day' => '1 day',
        'week' => '1 week',
        'month' => '1 month',
    ];

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'party_size' => ['required', 'integer', 'min:1', 'max:10000'],
            'source_channel' => ['required', Rule::in(array_keys(Booking::MANUAL_SOURCE_OPTIONS))],
            'source_details' => ['nullable', 'string', 'max:160'],
            'external_customer_name' => ['nullable', 'string', 'max:120'],
            'affiliate_partnership_id' => ['nullable', 'integer', 'exists:affiliate_partnerships,id'],
            'package_period' => ['nullable', Rule::in(array_keys(self::PACKAGE_LABELS))],
            'package_quantity' => ['nullable', 'integer', 'min:1', 'max:365'],
            'correction_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($request, $booking, $validated) {
            $lockedBooking = Booking::query()
                ->with(['affiliatePartnership.marketer'])
                ->lockForUpdate()
                ->findOrFail($booking->id);
            $unit = Unit::query()->with('rates')->lockForUpdate()->findOrFail($lockedBooking->unit_id);

            abort_unless($lockedBooking->isManualBooking(), 422, 'Only outside bookings can be corrected here.');
            abort_unless($request->user()->is_admin || ($request->user()->isHost() && $unit->host_id === $request->user()->id), 403);

            $partySize = (int) $validated['party_size'];
            if ($unit->capacity && $partySize > $unit->capacity) {
                throw ValidationException::withMessages([
                    'party_size' => "This listing can accommodate up to {$unit->capacity} people or units.",
                ]);
            }

            $start = Carbon::parse($validated['start_at']);
            $end = Carbon::parse($validated['end_at']);
            [$start, $end] = $unit->standardizeBookingPeriod($start, $end);

            if ($end->lte($start)) {
                throw ValidationException::withMessages([
                    'end_at' => 'The corrected end must be after the corrected start, including the listing check-in and check-out times.',
                ]);
            }

            if (in_array($lockedBooking->status, ['pre_approved', 'payment_submitted', 'confirmed'], true)) {
                $hasConflict = $unit->bookings()->blocking()
                    ->where('bookings.id', '!=', $lockedBooking->id)
                    ->where('start_at', '<', $end)
                    ->where('end_at', '>', $start)
                    ->exists();

                if ($hasConflict) {
                    throw ValidationException::withMessages([
                        'start_at' => 'This listing already has another active booking during part of the corrected date and time range.',
                    ]);
                }
            }

            $affiliate = $this->affiliateFor($unit, $lockedBooking, $validated['affiliate_partnership_id'] ?? null);
            if (! empty($validated['affiliate_partnership_id']) && ! $affiliate) {
                throw ValidationException::withMessages([
                    'affiliate_partnership_id' => 'Choose an accepted affiliate assigned to this listing.',
                ]);
            }

            $before = $this->auditSnapshot($lockedBooking);
            $attributes = [
                'start_at' => $start,
                'end_at' => $end,
                'party_size' => $partySize,
                'source_channel' => $validated['source_channel'],
                'source_details' => $this->nullableTrim($validated['source_details'] ?? null),
                'external_customer_name' => $this->nullableTrim($validated['external_customer_name'] ?? null),
            ];

            $currentAffiliateId = $lockedBooking->affiliate_partnership_id ? (int) $lockedBooking->affiliate_partnership_id : null;
            if ($affiliate?->id !== $currentAffiliateId) {
                $attributes += [
                    'affiliate_partnership_id' => $affiliate?->id,
                    'affiliate_commission_percentage' => $affiliate?->commission_percentage,
                    'affiliate_commission_amount' => $affiliate
                        ? round((float) $lockedBooking->total_amount * (float) $affiliate->commission_percentage / 100, 2)
                        : null,
                ];
            }

            if ($unit->isPackageRental()) {
                if (empty($validated['package_period']) || empty($validated['package_quantity'])) {
                    throw ValidationException::withMessages([
                        'package_period' => 'Choose the corrected rental package and quantity.',
                    ]);
                }

                $period = $validated['package_period'];
                $quantity = (int) $validated['package_quantity'];
                if ($period !== $lockedBooking->rate_period || $quantity !== (int) $lockedBooking->rate_quantity) {
                    $unitPrice = round((float) $lockedBooking->total_amount / $quantity, 2);
                    $rate = $unit->rates
                        ->where('period', $period)
                        ->when($lockedBooking->rental_coverage, fn ($rates) => $rates->where('coverage', $lockedBooking->rental_coverage))
                        ->first();

                    $attributes += [
                        'unit_rate_id' => $rate?->id,
                        'rate_period' => $period,
                        'rate_quantity' => $quantity,
                        'package_breakdown' => [
                            $period => [
                                'quantity' => $quantity,
                                'unit_price' => $unitPrice,
                                'subtotal' => round((float) $lockedBooking->total_amount, 2),
                            ],
                        ],
                    ];
                }
            }

            $lockedBooking->fill($attributes);
            if (! $lockedBooking->isDirty(array_keys($attributes))) {
                throw ValidationException::withMessages([
                    'correction_reason' => 'Change at least one reservation detail before saving the correction.',
                ]);
            }

            $lockedBooking->save();
            $lockedBooking->load('affiliatePartnership.marketer');
            $lockedBooking->detailRevisions()->create([
                'edited_by_user_id' => $request->user()->id,
                'before_values' => $before,
                'after_values' => $this->auditSnapshot($lockedBooking),
                'reason' => trim($validated['correction_reason']),
            ]);
        });

        return back()->with('status', 'Reservation details corrected. The previous values, new values, editor, and reason were added to the audit trail.');
    }

    private function affiliateFor(Unit $unit, Booking $booking, ?int $partnershipId): ?AffiliatePartnership
    {
        if (! $partnershipId) {
            return null;
        }

        if ((int) $booking->affiliate_partnership_id === $partnershipId) {
            return AffiliatePartnership::query()
                ->with('marketer:id,name')
                ->whereKey($partnershipId)
                ->where('host_id', $unit->host_id)
                ->first();
        }

        return AffiliatePartnership::query()
            ->with('marketer:id,name')
            ->whereKey($partnershipId)
            ->where('status', 'accepted')
            ->where('host_id', $unit->host_id)
            ->whereHas('units', fn ($units) => $units->whereKey($unit->id))
            ->first();
    }

    /** @return array{starts:string, ends:string, guests_pax:string, sales_source:string, external_customer:?string, affiliate:?string, package:?string} */
    private function auditSnapshot(Booking $booking): array
    {
        $booking->loadMissing('affiliatePartnership.marketer');
        $package = null;
        if ($booking->rate_period && $booking->rate_period !== 'mixed') {
            $package = $booking->rate_quantity.' × '.(self::PACKAGE_LABELS[$booking->rate_period] ?? str($booking->rate_period)->replace('_', ' ')->title());
        } elseif ($booking->package_breakdown) {
            $package = collect($booking->package_breakdown)
                ->map(fn ($item, $period) => ($item['quantity'] ?? 0).' × '.(self::PACKAGE_LABELS[$period] ?? str($period)->replace('_', ' ')->title()))
                ->implode(' + ');
        }

        return [
            'starts' => $booking->start_at->format('M j, Y · g:i A'),
            'ends' => $booking->end_at->format('M j, Y · g:i A'),
            'guests_pax' => $booking->party_size.' '.str('person')->plural($booking->party_size),
            'sales_source' => $booking->sourceDisplayLabel(),
            'external_customer' => $booking->external_customer_name,
            'affiliate' => $booking->affiliatePartnership
                ? $booking->affiliatePartnership->marketer->name.' · '.number_format((float) $booking->affiliate_commission_percentage, 2).'%'
                : null,
            'package' => $package,
        ];
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
