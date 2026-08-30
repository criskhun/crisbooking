<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingFinancialEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingFinancialEntryController extends Controller
{
    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $booking->loadMissing('unit', 'financialEntries');
        abort_unless($request->user()->is_admin || $booking->unit->host_id === $request->user()->id, 403);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['payment', 'charge', 'deposit', 'deposit_refund', 'deposit_application'])],
            'category' => ['nullable', Rule::in(array_keys(BookingFinancialEntry::CATEGORY_LABELS))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $allowedCategories = match ($validated['kind']) {
            'payment' => ['full_payment', 'downpayment', 'balance_payment'],
            'charge' => ['damage', 'late_checkout', 'smoking', 'excessive_cleaning', 'other_penalty'],
            default => [],
        };
        if ($allowedCategories !== [] && isset($validated['category']) && ! in_array($validated['category'], $allowedCategories, true)) {
            throw ValidationException::withMessages(['category' => 'Choose a category that matches this financial action.']);
        }
        $category = match ($validated['kind']) {
            'payment' => $validated['category'] ?? 'balance_payment',
            'charge' => $validated['category'] ?? 'other_penalty',
            'deposit' => 'security_deposit',
            'deposit_refund' => 'security_deposit_refund',
            'deposit_application' => 'security_deposit_application',
        };
        if (in_array($validated['kind'], ['payment', 'deposit_application'], true) && $amount > $booking->outstandingBalance()) {
            throw ValidationException::withMessages(['amount' => 'This amount is greater than the outstanding booking balance.']);
        }
        if (in_array($validated['kind'], ['deposit_refund', 'deposit_application'], true) && $amount > $booking->securityDepositHeld()) {
            throw ValidationException::withMessages(['amount' => 'This amount is greater than the security deposit currently held.']);
        }
        $remainingDeposit = max(0, $booking->securityDepositRequired() - $booking->securityDepositHeld());
        if ($validated['kind'] === 'deposit' && $booking->securityDepositRequired() > 0 && $amount > $remainingDeposit) {
            throw ValidationException::withMessages(['amount' => 'This amount is greater than the uncollected security deposit.']);
        }

        $booking->financialEntries()->create([
            'recorded_by_user_id' => $request->user()->id,
            'kind' => $validated['kind'],
            'category' => $category,
            'amount' => $amount,
            'notes' => $validated['notes'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return back()->with('status', 'Booking financial record added.');
    }
}
