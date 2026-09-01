<?php

namespace App\Services;

use App\Models\BookingExpense;
use App\Models\BookingFinancialEntry;
use App\Models\FinancialAccount;
use App\Models\UnitCost;
use App\Models\UnitObligationPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AccountingLedgerService
{
    /** @return array<string, mixed> */
    public function report(User $owner, ?FinancialAccount $selectedAccount = null, ?Carbon $month = null, ?string $direction = null): array
    {
        $accounts = $owner->financialAccounts()->get();
        $movements = $this->movements($owner);
        $runningBalances = $accounts->mapWithKeys(fn (FinancialAccount $account) => [
            $account->id => round((float) $account->opening_balance, 2),
        ])->all();

        $movements = $movements->sortBy([
            ['occurred_at', 'asc'],
            ['sort_key', 'asc'],
        ])->values()->map(function (array $movement) use (&$runningBalances) {
            $accountId = $movement['account']?->id;
            if ($accountId) {
                $runningBalances[$accountId] ??= 0;
                $runningBalances[$accountId] += $movement['direction'] === 'in'
                    ? $movement['amount']
                    : -$movement['amount'];
                $movement['balance_after'] = round($runningBalances[$accountId], 2);
            } else {
                $movement['balance_after'] = null;
            }

            return $movement;
        });

        $accountSummaries = $accounts->map(function (FinancialAccount $account) use ($movements) {
            $accountMovements = $movements->where('account.id', $account->id);
            $moneyIn = round((float) $accountMovements->where('direction', 'in')->sum('amount'), 2);
            $moneyOut = round((float) $accountMovements->where('direction', 'out')->sum('amount'), 2);

            return [
                'account' => $account,
                'money_in' => $moneyIn,
                'money_out' => $moneyOut,
                'balance' => round((float) $account->opening_balance + $moneyIn - $moneyOut, 2),
                'transaction_count' => $accountMovements->count(),
            ];
        })->values();

        $filtered = $movements
            ->when($selectedAccount, fn (Collection $rows) => $rows->where('account.id', $selectedAccount->id))
            ->when($month, fn (Collection $rows) => $rows->filter(fn ($row) => $row['occurred_at']->isSameMonth($month)))
            ->when($direction, fn (Collection $rows) => $rows->where('direction', $direction))
            ->sortByDesc('occurred_at')
            ->values();
        $moneyIn = round((float) $filtered->where('direction', 'in')->sum('amount'), 2);
        $moneyOut = round((float) $filtered->where('direction', 'out')->sum('amount'), 2);
        $unassigned = $movements->whereNull('account')->values();

        return [
            'accounts' => $accounts,
            'account_summaries' => $accountSummaries,
            'movements' => $filtered,
            'summary' => [
                'money_in' => $moneyIn,
                'money_out' => $moneyOut,
                'net_cash_flow' => round($moneyIn - $moneyOut, 2),
                'account_balance' => round((float) $accountSummaries->sum('balance'), 2),
                'transaction_count' => $filtered->count(),
                'unassigned_count' => $unassigned->count(),
                'unassigned_amount' => round((float) $unassigned->sum('amount'), 2),
            ],
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function movements(User $owner): Collection
    {
        $bookingEntries = BookingFinancialEntry::query()
            ->with(['financialAccount', 'booking.unit'])
            ->whereIn('kind', ['payment', 'deposit', 'deposit_refund'])
            ->whereHas('booking.unit', fn ($units) => $units->where('host_id', $owner->id))
            ->get()
            ->map(fn (BookingFinancialEntry $entry) => [
                'source_type' => 'booking_financial_entry',
                'source_id' => $entry->id,
                'sort_key' => 'booking-financial-entry-'.$entry->id,
                'account' => $entry->financialAccount,
                'direction' => $entry->cashDirection(),
                'category' => $entry->category,
                'title' => $entry->label(),
                'description' => 'Booking #'.$entry->booking_id.' · '.$entry->booking->customerDisplayName().' · '.$entry->booking->unit->name,
                'amount' => round((float) $entry->amount, 2),
                'occurred_at' => $entry->occurred_at,
                'notes' => $entry->notes,
                'url' => route('bookings.show', $entry->booking),
            ]);

        $bookingExpenses = BookingExpense::query()
            ->with(['financialAccount', 'booking.unit', 'provider'])
            ->whereIn('status', ['paid', 'payment_received'])
            ->whereNotNull('paid_at')
            ->whereHas('booking.unit', fn ($units) => $units->where('host_id', $owner->id))
            ->get()
            ->map(fn (BookingExpense $expense) => [
                'source_type' => 'booking_expense',
                'source_id' => $expense->id,
                'sort_key' => 'booking-expense-'.$expense->id,
                'account' => $expense->financialAccount,
                'direction' => 'out',
                'category' => $expense->category,
                'title' => $expense->categoryLabel(),
                'description' => 'Booking #'.$expense->booking_id.' · '.($expense->provider?->name ?: $expense->vendor_name ?: 'Service expense').' · '.$expense->booking->unit->name,
                'amount' => round((float) $expense->amount, 2),
                'occurred_at' => $expense->paid_at,
                'notes' => $expense->notes,
                'url' => route('bookings.show', $expense->booking),
            ]);

        $unitCosts = UnitCost::query()
            ->with(['financialAccount', 'unit'])
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->whereHas('unit', fn ($units) => $units->where('host_id', $owner->id))
            ->get()
            ->map(fn (UnitCost $cost) => [
                'source_type' => 'unit_cost',
                'source_id' => $cost->id,
                'sort_key' => 'unit-cost-'.$cost->id,
                'account' => $cost->financialAccount,
                'direction' => 'out',
                'category' => $cost->category,
                'title' => $cost->categoryLabel(),
                'description' => $cost->unit->name.($cost->vendor_name ? ' · '.$cost->vendor_name : ''),
                'amount' => round((float) $cost->amount, 2),
                'occurred_at' => $cost->paid_at,
                'notes' => $cost->notes,
                'url' => route('sales.index', ['unit' => $cost->unit_id]),
            ]);

        $obligationPayments = UnitObligationPayment::query()
            ->with(['financialAccount', 'obligation.unit'])
            ->whereHas('obligation.unit', fn ($units) => $units->where('host_id', $owner->id))
            ->get()
            ->map(fn (UnitObligationPayment $payment) => [
                'source_type' => 'unit_obligation_payment',
                'source_id' => $payment->id,
                'sort_key' => 'unit-obligation-payment-'.$payment->id,
                'account' => $payment->financialAccount,
                'direction' => 'out',
                'category' => $payment->obligation->category,
                'title' => $payment->obligation->name,
                'description' => $payment->obligation->unit->name.' · '.$payment->installment_month->format('F Y').' installment',
                'amount' => round((float) $payment->amount, 2),
                'occurred_at' => $payment->paid_at,
                'notes' => $payment->notes,
                'url' => route('sales.index', ['unit' => $payment->obligation->unit_id]),
            ]);

        return collect()
            ->concat($bookingEntries)
            ->concat($bookingExpenses)
            ->concat($unitCosts)
            ->concat($obligationPayments)
            ->filter(fn ($movement) => $movement['direction'] !== null)
            ->values();
    }
}
