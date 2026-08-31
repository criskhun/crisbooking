<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingExtensionController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $this->normalizeMoneyInput($request, 'additional_amount');
        $validated = $request->validate([
            'duration_unit' => ['required', Rule::in(['day', 'hour'])],
            'duration_quantity' => ['required', 'integer', 'min:1', 'max:8760'],
            'additional_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_status' => ['required', Rule::in(['paid', 'collectible'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['duration_unit'] === 'day' && (int) $validated['duration_quantity'] > 365) {
            throw ValidationException::withMessages(['duration_quantity' => 'An extension can be up to 365 days.']);
        }

        DB::transaction(function () use ($request, $booking, $validated) {
            $lockedBooking = Booking::query()
                ->with(['unit', 'financialEntries'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            abort_unless($request->user()->is_admin || $lockedBooking->unit->host_id === $request->user()->id, 403);
            abort_unless($lockedBooking->isManualBooking(), 422, 'Only outside bookings can be extended here.');

            if ($lockedBooking->status !== 'confirmed') {
                throw ValidationException::withMessages(['duration_quantity' => 'Only a confirmed outside booking can be extended.']);
            }

            $quantity = (int) $validated['duration_quantity'];
            $previousEnd = $lockedBooking->end_at->copy();
            $newEnd = $validated['duration_unit'] === 'hour'
                ? $previousEnd->copy()->addHours($quantity)
                : $previousEnd->copy()->addDays($quantity);

            $hasConflict = $lockedBooking->unit->bookings()->blocking()
                ->where('bookings.id', '!=', $lockedBooking->id)
                ->where('start_at', '<', $newEnd)
                ->where('end_at', '>', $previousEnd)
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'duration_quantity' => 'This extension overlaps another active booking for the listing.',
                ]);
            }

            $amount = round((float) $validated['additional_amount'], 2);
            $durationLabel = $quantity.' '.str($validated['duration_unit'])->plural($quantity);
            $note = 'Extension: '.$durationLabel.'.'.(filled($validated['notes'] ?? null) ? ' '.trim($validated['notes']) : '');
            $charge = $lockedBooking->financialEntries()->create([
                'recorded_by_user_id' => $request->user()->id,
                'kind' => 'charge',
                'category' => 'extension',
                'amount' => $amount,
                'notes' => $note,
                'occurred_at' => now(),
            ]);
            $payment = null;

            if ($validated['payment_status'] === 'paid') {
                $payment = $lockedBooking->financialEntries()->create([
                    'recorded_by_user_id' => $request->user()->id,
                    'kind' => 'payment',
                    'category' => 'balance_payment',
                    'amount' => $amount,
                    'notes' => 'Payment collected for '.$durationLabel.' extension.',
                    'occurred_at' => now(),
                ]);
            }

            $attributes = ['end_at' => $newEnd];
            if ($lockedBooking->rate_period === $validated['duration_unit']) {
                $attributes['rate_quantity'] = max(1, (int) $lockedBooking->rate_quantity) + $quantity;
            }
            $lockedBooking->update($attributes);
            $lockedBooking->extensions()->create([
                'created_by_user_id' => $request->user()->id,
                'duration_unit' => $validated['duration_unit'],
                'duration_quantity' => $quantity,
                'previous_end_at' => $previousEnd,
                'new_end_at' => $newEnd,
                'additional_amount' => $amount,
                'payment_status' => $validated['payment_status'],
                'charge_entry_id' => $charge->id,
                'payment_entry_id' => $payment?->id,
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ]);
        });

        return back()->with('status', $validated['payment_status'] === 'paid'
            ? 'Booking extended and the additional earning was recorded as paid.'
            : 'Booking extended and the additional earning was added to collectibles.');
    }

    private function normalizeMoneyInput(Request $request, string $field): void
    {
        if ($request->filled($field)) {
            $request->merge([$field => str_replace([',', '₱', ' '], '', (string) $request->input($field))]);
        }
    }
}
