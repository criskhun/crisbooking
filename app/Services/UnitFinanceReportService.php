<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitFinancialProfile;
use App\Models\UnitObligation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UnitFinanceReportService
{
    /** @return array<string, mixed> */
    public function report(Unit $unit, ?Carbon $month = null, ?Carbon $asOfMonth = null): array
    {
        $unit->loadMissing([
            'host:id,name', 'financialProfile', 'costs', 'obligations.payments',
            'bookings.financialEntries', 'bookings.expenses',
        ]);
        $asOfMonth ??= ($month ?: now())->copy()->startOfMonth();
        $profile = $unit->financialProfile ?: new UnitFinancialProfile([
            'management_type' => 'owner_managed',
            'owner_name' => $unit->host?->name,
            'owner_share_percentage' => 100,
            'manager_share_percentage' => 0,
            'share_basis' => 'operating_profit',
            'initial_asset_value' => 0,
        ]);
        $bookings = $this->inPeriod(
            $unit->bookings->where('status', 'confirmed'),
            $month,
            fn (Booking $booking) => $booking->start_at,
        );
        $costs = $this->inPeriod($unit->costs, $month, fn ($cost) => $cost->incurred_on);
        $grossSales = round((float) $bookings->sum(fn (Booking $booking) => $booking->revenueAmount()), 2);
        $bookingExpenses = round((float) $bookings->sum(fn (Booking $booking) => $booking->expenseTotal()), 2);
        $operatingCosts = round((float) $costs->where('classification', 'operating')->sum('amount'), 2);
        $capitalImprovements = round((float) $costs->where('classification', 'capital')->sum('amount'), 2);
        $operatingProfit = round($grossSales - $bookingExpenses - $operatingCosts, 2);
        $sharePool = $profile->share_basis === 'gross_sales' ? $grossSales : max(0, $operatingProfit);
        $ownerShare = round($sharePool * (float) $profile->owner_share_percentage / 100, 2);
        $managerShare = round($sharePool * (float) $profile->manager_share_percentage / 100, 2);
        $obligationPayments = $unit->obligations->flatMap->payments;
        $periodObligationPayments = $this->inPeriod($obligationPayments, $month, fn ($payment) => $payment->installment_month);
        $financingPayments = round((float) $periodObligationPayments->sum('amount'), 2);
        $outstandingInstallments = $unit->obligations
            ->where('status', 'active')
            ->flatMap(fn (UnitObligation $obligation) => $this->outstandingInstallments($obligation, $asOfMonth));
        $costPayables = $unit->costs
            ->where('status', 'payable')
            ->filter(fn ($cost) => $cost->due_on ? $cost->due_on->lte($asOfMonth->copy()->endOfMonth()) : $cost->incurred_on->lte($asOfMonth->copy()->endOfMonth()));
        $duePayables = round((float) $outstandingInstallments->sum('amount') + (float) $costPayables->sum('amount'), 2);
        $currentAssetValue = round((float) $profile->initial_asset_value + (float) $unit->costs->where('classification', 'capital')->sum('amount'), 2);
        $cashAfterObligations = round($operatingProfit - $capitalImprovements - $financingPayments, 2);
        $receivables = round((float) $bookings
            ->where('booking_origin', 'manual')
            ->sum(fn (Booking $booking) => $booking->outstandingBalance()), 2);
        $installmentsDue = round((float) $outstandingInstallments->sum('amount'), 2);

        return [
            'unit' => $unit,
            'profile' => $profile,
            'bookings' => $bookings,
            'costs' => $costs,
            'gross_sales' => $grossSales,
            'booking_expenses' => $bookingExpenses,
            'operating_costs' => $operatingCosts,
            'total_operating_expenses' => round($bookingExpenses + $operatingCosts, 2),
            'operating_profit' => $operatingProfit,
            'profit_margin' => $grossSales > 0 ? round($operatingProfit / $grossSales * 100, 1) : 0,
            'owner_share' => $ownerShare,
            'manager_share' => $managerShare,
            'capital_improvements' => $capitalImprovements,
            'financing_payments' => $financingPayments,
            'cash_after_obligations' => $cashAfterObligations,
            'due_payables' => $duePayables,
            // Unit costs already reduce operating profit; subtracting them again here would double-count them.
            'available_after_due' => round($cashAfterObligations - $installmentsDue, 2),
            'receivables' => $receivables,
            'current_asset_value' => $currentAssetValue,
            'outstanding_installments' => $outstandingInstallments,
            'cost_payables' => $costPayables,
            'confirmed_count' => $bookings->count(),
            'average_booking' => $bookings->count() ? round($grossSales / $bookings->count(), 2) : 0,
        ];
    }

    /** @return Collection<int, array{obligation:UnitObligation, month:Carbon, amount:float, due_date:Carbon}> */
    public function outstandingInstallments(UnitObligation $obligation, Carbon $asOfMonth): Collection
    {
        $start = $obligation->start_month->copy()->startOfMonth();
        $end = $obligation->endMonth()->min($asOfMonth->copy()->startOfMonth());
        if ($start->gt($end)) {
            return collect();
        }

        $paidMonths = $obligation->payments->mapWithKeys(
            fn ($payment) => [$payment->installment_month->format('Y-m') => (float) $payment->amount]
        );
        $rows = collect();
        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $remaining = max(0, (float) $obligation->monthly_amount - (float) $paidMonths->get($month->format('Y-m'), 0));
            if ($remaining > 0) {
                $rows->push([
                    'obligation' => $obligation,
                    'month' => $month->copy(),
                    'amount' => $remaining,
                    'due_date' => $month->copy()->day(min($obligation->due_day, $month->daysInMonth)),
                ]);
            }
        }

        return $rows;
    }

    private function inPeriod(Collection $items, ?Carbon $month, callable $dateResolver): Collection
    {
        if (! $month) {
            return $items->values();
        }

        return $items->filter(fn ($item) => $dateResolver($item)?->isSameMonth($month))->values();
    }
}
