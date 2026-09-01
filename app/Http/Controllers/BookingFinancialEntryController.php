<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingFinancialEntry;
use App\Services\FinancialAccountSelection;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingFinancialEntryController extends Controller
{
    public function store(Request $request, Booking $booking, FinancialAccountSelection $accountSelection): RedirectResponse
    {
        $this->normalizeMoneyInput($request, 'amount');
        $booking->loadMissing('unit.host', 'financialEntries');
        abort_unless($request->user()->is_admin || $booking->unit->host_id === $request->user()->id, 403);

        $validated = $request->validate([
            'kind' => ['required', Rule::in(['payment', 'charge', 'deposit', 'deposit_refund', 'deposit_application'])],
            'category' => ['nullable', Rule::in(array_keys(BookingFinancialEntry::CATEGORY_LABELS))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['nullable', 'date'],
            'financial_account_id' => ['nullable', 'integer'],
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

        $financialAccount = in_array($validated['kind'], ['payment', 'deposit', 'deposit_refund'], true)
            ? $accountSelection->resolve($booking->unit->host, $validated['financial_account_id'] ?? null)
            : null;
        $booking->financialEntries()->create([
            'recorded_by_user_id' => $request->user()->id,
            'financial_account_id' => $financialAccount?->id,
            'kind' => $validated['kind'],
            'category' => $category,
            'amount' => $amount,
            'notes' => $validated['notes'] ?? null,
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        return back()->with('status', 'Booking financial record added.');
    }

    public function update(Request $request, Booking $booking, BookingFinancialEntry $financialEntry, FinancialAccountSelection $accountSelection): RedirectResponse
    {
        abort_unless($financialEntry->booking_id === $booking->id, 404);
        $booking->loadMissing('unit.host');
        abort_unless($request->user()->is_admin || $booking->unit->host_id === $request->user()->id, 403);
        $this->normalizeMoneyInput($request, 'amount');

        $validated = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(BookingFinancialEntry::CATEGORY_LABELS))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
            'correction_reason' => ['required', 'string', 'min:5', 'max:500'],
            'financial_account_id' => ['nullable', 'integer'],
        ]);

        $selectedAccount = null;
        if ($financialEntry->movesCash() && array_key_exists('financial_account_id', $validated)) {
            $selectedAccount = $accountSelection->resolve($booking->unit->host, $validated['financial_account_id']);
        }

        DB::transaction(function () use ($booking, $financialEntry, $validated, $request, $selectedAccount) {
            $lockedBooking = Booking::query()->lockForUpdate()->with('financialEntries')->findOrFail($booking->id);
            $entry = BookingFinancialEntry::query()->lockForUpdate()->findOrFail($financialEntry->id);
            abort_unless($entry->booking_id === $lockedBooking->id, 404);

            $category = $this->categoryForEntry($entry, $validated['category'] ?? null);
            $amount = round((float) $validated['amount'], 2);
            $this->validateCorrectedAmount($lockedBooking, $entry, $amount);

            $before = $this->auditSnapshot($entry);
            $entry->fill([
                'category' => $category,
                'amount' => $amount,
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
                'occurred_at' => Carbon::parse($validated['occurred_at']),
                'financial_account_id' => $entry->movesCash() && $selectedAccount ? $selectedAccount->id : $entry->financial_account_id,
            ]);

            if (! $entry->isDirty(['category', 'amount', 'notes', 'occurred_at', 'financial_account_id'])) {
                throw ValidationException::withMessages(['amount' => 'Change at least one ledger detail before saving the correction.']);
            }

            $entry->save();
            $entry->revisions()->create([
                'edited_by_user_id' => $request->user()->id,
                'before_values' => $before,
                'after_values' => $this->auditSnapshot($entry->fresh()),
                'reason' => trim($validated['correction_reason']),
            ]);
        });

        return back()->with('status', 'Financial entry corrected. The original values and reason were added to its audit history.');
    }

    private function categoryForEntry(BookingFinancialEntry $entry, ?string $category): string
    {
        $allowedCategories = match ($entry->kind) {
            'payment' => ['full_payment', 'downpayment', 'balance_payment'],
            'charge' => ['damage', 'late_checkout', 'smoking', 'excessive_cleaning', 'other_penalty'],
            default => [],
        };

        if ($allowedCategories === []) {
            return $entry->category;
        }
        if (! $category || ! in_array($category, $allowedCategories, true)) {
            throw ValidationException::withMessages(['category' => 'Choose a category that matches this financial entry.']);
        }

        return $category;
    }

    private function validateCorrectedAmount(Booking $booking, BookingFinancialEntry $entry, float $amount): void
    {
        $otherEntries = $booking->financialEntries->reject(fn (BookingFinancialEntry $candidate) => $candidate->id === $entry->id);
        $charges = (float) $otherEntries->where('kind', 'charge')->sum('amount') + ($entry->kind === 'charge' ? $amount : 0);
        $revenue = round(max(0, (float) $booking->total_amount - $booking->refundableDepositAmount()) + $charges, 2);
        $otherPayments = (float) $otherEntries->whereIn('kind', ['payment', 'deposit_application'])->sum('amount');
        $balanceBeforeEntry = round(max(0, $revenue - $otherPayments), 2);

        if (in_array($entry->kind, ['payment', 'deposit_application'], true) && $amount > $balanceBeforeEntry) {
            throw ValidationException::withMessages(['amount' => 'This correction would make collected payments greater than the booking balance.']);
        }

        $otherDeposits = (float) $otherEntries->where('kind', 'deposit')->sum('amount');
        $otherReleases = (float) $otherEntries->whereIn('kind', ['deposit_refund', 'deposit_application'])->sum('amount');
        $depositHeldBeforeEntry = round(max(0, $otherDeposits - $otherReleases), 2);

        if (in_array($entry->kind, ['deposit_refund', 'deposit_application'], true) && $amount > $depositHeldBeforeEntry) {
            throw ValidationException::withMessages(['amount' => 'This correction is greater than the security deposit available before this entry.']);
        }

        $remainingRequiredDeposit = max(0, $booking->securityDepositRequired() - $depositHeldBeforeEntry);
        if ($entry->kind === 'deposit' && $booking->securityDepositRequired() > 0 && $amount > $remainingRequiredDeposit) {
            throw ValidationException::withMessages(['amount' => 'This correction is greater than the required security deposit.']);
        }
    }

    /** @return array{category:string, amount:string, notes:?string, occurred_at:string, financial_account_id:?int} */
    private function auditSnapshot(BookingFinancialEntry $entry): array
    {
        return [
            'category' => $entry->category,
            'amount' => number_format((float) $entry->amount, 2, '.', ''),
            'notes' => $entry->notes,
            'occurred_at' => $entry->occurred_at->toIso8601String(),
            'financial_account_id' => $entry->financial_account_id,
        ];
    }

    private function normalizeMoneyInput(Request $request, string $field): void
    {
        if ($request->filled($field)) {
            $request->merge([$field => str_replace([',', '₱', ' '], '', (string) $request->input($field))]);
        }
    }
}
