<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $categories = Unit::query()
            ->when(! $request->user()->is_admin, fn ($query) => $query->where('host_id', $request->user()->id))
            ->distinct()->orderBy('category')->pluck('category');
        $selectedCategory = $request->string('category')->toString();
        if ($selectedCategory !== '' && ! $categories->contains($selectedCategory)) {
            abort(404);
        }

        $bookings = Booking::query()
            ->with(['unit:id,host_id,name,category', 'client:id,name'])
            ->when(! $request->user()->is_admin, fn ($query) => $query->whereHas('unit', fn ($units) => $units->where('host_id', $request->user()->id)))
            ->when($selectedCategory, fn ($query) => $query->whereHas('unit', fn ($units) => $units->where('category', $selectedCategory)))
            ->latest('start_at')->get();
        $confirmed = $bookings->where('status', 'confirmed');
        $pending = $bookings->whereIn('status', ['pending', 'pre_approved', 'payment_submitted']);
        $cancelled = $bookings->whereIn('status', ['cancelled', 'declined', 'unavailable']);
        $salesTotal = $confirmed->sum(fn (Booking $booking) => $booking->revenueAmount());
        $metrics = [
            'sales_total' => $salesTotal,
            'confirmed_count' => $confirmed->count(),
            'pending_total' => $pending->sum('total_amount'),
            'pending_count' => $pending->count(),
            'average_sale' => $confirmed->count() ? $salesTotal / $confirmed->count() : 0,
            'unique_clients' => $confirmed->map(fn (Booking $booking) => $booking->customerDisplayName())->unique()->count(),
            'cancelled_count' => $cancelled->count(),
        ];

        $monthlySales = collect(range(11, 0))->map(function ($monthsAgo) use ($confirmed) {
            $month = now()->subMonths($monthsAgo)->startOfMonth();
            $value = $confirmed->filter(fn ($booking) => $booking->start_at->isSameMonth($month))->sum(fn ($booking) => $booking->revenueAmount());

            return ['label' => $month->format('M'), 'month' => $month->format('Y-m'), 'value' => $value];
        });
        $categorySales = $confirmed->groupBy(fn ($booking) => $booking->unit->category)->map(fn ($items, $category) => [
            'category' => $category,
            'value' => $items->sum(fn ($booking) => $booking->revenueAmount()),
            'count' => $items->count(),
        ])->sortByDesc('value')->values();
        $maxMonthlySale = max(1, (float) $monthlySales->max('value'));
        $maxCategorySale = max(1, (float) $categorySales->max('value'));
        $recentBookings = $bookings->take(20);

        return view('sales.index', compact(
            'categories', 'selectedCategory', 'metrics', 'monthlySales', 'categorySales',
            'maxMonthlySale', 'maxCategorySale', 'recentBookings'
        ));
    }
}
