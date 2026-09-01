<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitCost;
use App\Models\UnitObligation;
use App\Services\UnitFinanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request, UnitFinanceReportService $financeReports): View
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'max:30'],
            'unit' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $accessibleUnits = Unit::query()
            ->with([
                'host:id,name', 'financialProfile', 'costs.recordedBy:id,name',
                'obligations.payments', 'bookings.financialEntries', 'bookings.expenses',
            ])
            ->when(! $request->user()->is_admin, fn ($query) => $query->where('host_id', $request->user()->id))
            ->orderBy('name')
            ->get();
        $categories = $accessibleUnits->pluck('category')->filter()->unique()->sort()->values();
        $selectedCategory = $validated['category'] ?? '';
        if ($selectedCategory !== '' && ! $categories->contains($selectedCategory)) {
            abort(404);
        }
        $selectedUnitId = (int) ($validated['unit'] ?? 0);
        $selectedUnit = $selectedUnitId ? $accessibleUnits->firstWhere('id', $selectedUnitId) : null;
        if ($selectedUnitId && ! $selectedUnit) {
            abort(404);
        }
        $reportMonth = ! empty($validated['month'])
            ? Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth()
            : null;
        $asOfMonth = ($reportMonth ?: now())->copy()->startOfMonth();
        $reportingUnits = $accessibleUnits
            ->when($selectedCategory, fn ($units) => $units->where('category', $selectedCategory))
            ->when($selectedUnit, fn ($units) => $units->where('id', $selectedUnit->id))
            ->values();
        $unitReports = $reportingUnits
            ->map(fn (Unit $unit) => $financeReports->report($unit, $reportMonth, $asOfMonth))
            ->sortByDesc('operating_profit')
            ->values();

        $bookings = Booking::query()
            ->with(['unit:id,host_id,name,category', 'client:id,name', 'financialEntries', 'expenses'])
            ->whereIn('unit_id', $reportingUnits->pluck('id'))
            ->when($reportMonth, fn ($query) => $query
                ->where('start_at', '>=', $reportMonth->copy()->startOfMonth())
                ->where('start_at', '<', $reportMonth->copy()->addMonth()))
            ->latest('start_at')
            ->get();
        $confirmed = $bookings->where('status', 'confirmed');
        $pending = $bookings->whereIn('status', ['pending', 'pre_approved', 'payment_submitted']);
        $cancelled = $bookings->whereIn('status', ['cancelled', 'declined', 'unavailable']);
        $salesTotal = round((float) $unitReports->sum('gross_sales'), 2);
        $metrics = [
            'sales_total' => $salesTotal,
            'operating_expenses' => round((float) $unitReports->sum('total_operating_expenses'), 2),
            'operating_profit' => round((float) $unitReports->sum('operating_profit'), 2),
            'cash_after_obligations' => round((float) $unitReports->sum('cash_after_obligations'), 2),
            'due_payables' => round((float) $unitReports->sum('due_payables'), 2),
            'available_after_due' => round((float) $unitReports->sum('available_after_due'), 2),
            'receivables' => round((float) $unitReports->sum('receivables'), 2),
            'confirmed_count' => $confirmed->count(),
            'pending_total' => round((float) $pending->sum('total_amount'), 2),
            'pending_count' => $pending->count(),
            'average_sale' => $confirmed->count() ? $salesTotal / $confirmed->count() : 0,
            'unique_clients' => $confirmed->map(fn (Booking $booking) => $booking->customerDisplayName())->unique()->count(),
            'cancelled_count' => $cancelled->count(),
        ];
        $metrics['profit_margin'] = $salesTotal > 0
            ? round($metrics['operating_profit'] / $salesTotal * 100, 1)
            : 0;

        $monthlySales = collect(range(11, 0))->map(function ($monthsAgo) use ($reportingUnits, $financeReports) {
            $month = now()->startOfMonth()->subMonthsNoOverflow($monthsAgo);
            $reports = $reportingUnits->map(fn (Unit $unit) => $financeReports->report($unit, $month, $month));

            return [
                'label' => $month->format('M'),
                'month' => $month->format('Y-m'),
                'value' => round((float) $reports->sum('gross_sales'), 2),
                'expenses' => round((float) $reports->sum('total_operating_expenses'), 2),
                'profit' => round((float) $reports->sum('operating_profit'), 2),
                'cash' => round((float) $reports->sum('cash_after_obligations'), 2),
            ];
        });
        $categorySales = $confirmed->groupBy(fn ($booking) => $booking->unit->category)->map(fn ($items, $category) => [
            'category' => $category,
            'value' => $items->sum(fn ($booking) => $booking->revenueAmount()),
            'count' => $items->count(),
        ])->sortByDesc('value')->values();
        $sourceSales = $confirmed
            ->groupBy(fn (Booking $booking) => $booking->acquisitionSourceKey())
            ->map(fn ($items, $source) => [
                'source' => $source,
                'label' => $items->first()->acquisitionSourceLabel(),
                'value' => round((float) $items->sum(fn (Booking $booking) => $booking->revenueAmount()), 2),
                'count' => $items->count(),
            ])
            ->sortByDesc('value')
            ->values();
        $maxMonthlySale = max(1, (float) $monthlySales->max('value'));
        $maxCategorySale = max(1, (float) $categorySales->max('value'));
        $maxSourceSale = max(1, (float) $sourceSales->max('value'));
        $recentBookings = $bookings->take(20);
        $summaryConfirmedBookings = $confirmed->sortByDesc('start_at')->values();
        $collectibleBookings = $summaryConfirmedBookings
            ->filter(fn (Booking $booking) => $booking->isManualBooking() && $booking->outstandingBalance() > 0)
            ->values();
        $bookingExpenseRows = $summaryConfirmedBookings->flatMap(fn (Booking $booking) => $booking->expenses
            ->where('status', '!=', 'cancelled')
            ->map(fn ($expense) => ['booking' => $booking, 'expense' => $expense]))
            ->sortByDesc(fn ($row) => $row['expense']->created_at)
            ->values();
        $unitOperatingCostRows = $unitReports->flatMap(fn ($report) => $report['costs']
            ->where('classification', 'operating')
            ->map(fn ($cost) => ['unit' => $report['unit'], 'cost' => $cost]))
            ->sortByDesc(fn ($row) => $row['cost']->incurred_on)
            ->values();
        $payableInstallmentRows = $unitReports->flatMap(fn ($report) => $report['outstanding_installments']
            ->map(fn ($due) => [...$due, 'unit' => $report['unit']]))
            ->sortBy('due_date')
            ->values();
        $payableCostRows = $unitReports->flatMap(fn ($report) => $report['cost_payables']
            ->map(fn ($cost) => ['unit' => $report['unit'], 'cost' => $cost]))
            ->sortBy(fn ($row) => $row['cost']->due_on ?: $row['cost']->incurred_on)
            ->values();
        $selectedUnitReport = $selectedUnit
            ? $financeReports->report($selectedUnit, $reportMonth, $asOfMonth)
            : null;
        $selectedUnitBookings = $selectedUnit
            ? $bookings->where('unit_id', $selectedUnit->id)->values()
            : collect();
        $trendStart = now()->startOfMonth()->subMonthsNoOverflow(11);
        $trendBookings = Booking::query()
            ->with(['unit:id,host_id,name,category', 'client:id,name', 'financialEntries', 'expenses'])
            ->whereIn('unit_id', $reportingUnits->pluck('id'))
            ->where('start_at', '>=', $trendStart)
            ->where('start_at', '<', now()->startOfMonth()->addMonth())
            ->latest('start_at')
            ->get();
        $chartDrilldowns = collect();
        foreach ($monthlySales as $monthSale) {
            $monthBookings = $trendBookings->filter(fn (Booking $booking) => $booking->status === 'confirmed' && $booking->start_at->format('Y-m') === $monthSale['month']
            );
            $chartDrilldowns->put('month:'.$monthSale['month'], $this->chartDetail(
                Carbon::createFromFormat('!Y-m', $monthSale['month'])->format('F Y').' sales',
                'Confirmed bookings behind this month’s gross-sales bar.',
                $monthBookings,
                'Gross sales'
            ));
        }
        foreach ($categorySales as $categorySale) {
            $chartDrilldowns->put('category:'.$categorySale['category'], $this->chartDetail(
                str($categorySale['category'])->replace('_', ' ')->title().' sales',
                'Confirmed bookings in this category for the active report filters.',
                $confirmed->where('unit.category', $categorySale['category']),
                'Gross sales'
            ));
        }
        foreach ($sourceSales as $sourceSale) {
            $chartDrilldowns->put('source:'.$sourceSale['source'], $this->chartDetail(
                $sourceSale['label'].' sales',
                'Confirmed bookings acquired from this marketing source.',
                $confirmed->filter(fn (Booking $booking) => $booking->acquisitionSourceKey() === $sourceSale['source']),
                'Gross sales'
            ));
        }
        $statusGroups = [
            'all' => ['All booking statuses', $bookings],
            'confirmed' => ['Confirmed bookings', $confirmed],
            'pending' => ['Pending pipeline', $pending],
            'cancelled' => ['Cancelled and declined bookings', $cancelled],
        ];
        foreach ($statusGroups as $statusKey => [$title, $statusBookings]) {
            $chartDrilldowns->put('status:'.$statusKey, $this->chartDetail(
                $title,
                'Booking records behind this status segment for the active report filters.',
                $statusBookings,
                $statusKey === 'confirmed' ? 'Gross sales' : 'Recorded value'
            ));
        }

        return view('sales.index', compact(
            'categories', 'accessibleUnits', 'selectedCategory', 'selectedUnit', 'selectedUnitId',
            'reportMonth', 'asOfMonth', 'metrics', 'monthlySales', 'categorySales', 'sourceSales', 'unitReports',
            'selectedUnitReport', 'selectedUnitBookings', 'maxMonthlySale', 'maxCategorySale', 'maxSourceSale',
            'recentBookings', 'chartDrilldowns', 'summaryConfirmedBookings', 'collectibleBookings',
            'bookingExpenseRows', 'unitOperatingCostRows', 'payableInstallmentRows', 'payableCostRows'
        ) + [
            'costCategoryOptions' => UnitCost::CATEGORY_LABELS,
            'obligationCategoryOptions' => UnitObligation::CATEGORY_LABELS,
        ]);
    }

    /** @return array<string, mixed> */
    private function chartDetail(string $title, string $subtitle, Collection $bookings, string $valueLabel): array
    {
        $rows = $bookings->sortByDesc('start_at')->values()->map(function (Booking $booking) {
            $isConfirmed = $booking->status === 'confirmed';
            $value = $isConfirmed ? $booking->revenueAmount() : (float) $booking->total_amount;

            return [
                'id' => $booking->id,
                'url' => route('bookings.show', $booking),
                'unit' => $booking->unit->name,
                'customer' => $booking->customerDisplayName(),
                'source' => $booking->isManualBooking() ? $booking->sourceDisplayLabel() : $booking->acquisitionSourceLabel(),
                'schedule' => $booking->start_at->format('M j, Y · g:i A').' → '.$booking->end_at->format('M j, Y · g:i A'),
                'status' => $booking->statusLabel(),
                'status_key' => $booking->status,
                'value' => '₱'.number_format($value, 2),
                'recognized' => $isConfirmed,
            ];
        });
        $totalValue = $bookings->sum(fn (Booking $booking) => $booking->status === 'confirmed' ? $booking->revenueAmount() : (float) $booking->total_amount
        );

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'count' => $rows->count(),
            'count_label' => $rows->count().' '.str('booking')->plural($rows->count()),
            'value_label' => $valueLabel,
            'value' => '₱'.number_format($totalValue, 2),
            'rows' => $rows,
        ];
    }
}
