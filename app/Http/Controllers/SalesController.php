<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\UnitCost;
use App\Models\UnitObligation;
use App\Services\UnitFinanceReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        $maxMonthlySale = max(1, (float) $monthlySales->max('value'));
        $maxCategorySale = max(1, (float) $categorySales->max('value'));
        $recentBookings = $bookings->take(20);
        $selectedUnitReport = $selectedUnit
            ? $financeReports->report($selectedUnit, $reportMonth, $asOfMonth)
            : null;

        return view('sales.index', compact(
            'categories', 'accessibleUnits', 'selectedCategory', 'selectedUnit', 'selectedUnitId',
            'reportMonth', 'asOfMonth', 'metrics', 'monthlySales', 'categorySales', 'unitReports',
            'selectedUnitReport', 'maxMonthlySale', 'maxCategorySale', 'recentBookings'
        ) + [
            'costCategoryOptions' => UnitCost::CATEGORY_LABELS,
            'obligationCategoryOptions' => UnitObligation::CATEGORY_LABELS,
        ]);
    }
}
