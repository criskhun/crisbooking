<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('hostApplication');
        $bookings = Booking::query();

        if ($user->isHost() || $user->is_admin) {
            $bookings->whereHas('unit', fn ($query) => $query->when(! $user->is_admin, fn ($units) => $units->where('host_id', $user->id)));
        } else {
            $bookings->where('client_id', $user->id);
        }

        $todayCount = (clone $bookings)->blocking()
            ->where('start_at', '<', today()->endOfDay())
            ->where('end_at', '>', today()->startOfDay())
            ->count();
        $upcomingCount = (clone $bookings)->blocking()->where('start_at', '>', now())->count();
        $monthSales = (clone $bookings)->where('status', 'confirmed')
            ->whereBetween('start_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['total_amount', 'additional_charges'])
            ->sum(fn (Booking $booking) => $booking->revenueAmount());
        $pendingBalance = (clone $bookings)->where('status', 'pending')->sum('total_amount');
        $todayBookings = (clone $bookings)->with(['unit.host', 'client'])
            ->blocking()
            ->where('start_at', '<', today()->endOfDay())
            ->where('end_at', '>', today()->startOfDay())
            ->orderBy('start_at')
            ->limit(6)
            ->get();
        $upcomingBookings = (clone $bookings)->with(['unit.host', 'client'])
            ->blocking()
            ->where('start_at', '>', now())
            ->orderBy('start_at')
            ->limit(6)
            ->get();

        $marketingUnits = $user->isClient()
            ? Unit::query()
                ->with(['host:id,name', 'rates', 'images'])
                ->where('is_active', true)
                ->whereHas('host', fn ($hosts) => $hosts->whereNotNull('profile_completed_at'))
                ->where(function ($bookable) {
                    $bookable->whereNotIn('category', ['car', 'condo'])->orWhereHas('rates');
                })
                ->orderByRaw('CASE WHEN photo_path IS NULL THEN 1 ELSE 0 END')
                ->latest('id')
                ->limit(8)
                ->get()
            : collect();

        $overviewMapUnits = $marketingUnits
            ->filter(fn (Unit $unit) => $unit->latitude !== null && $unit->longitude !== null)
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'latitude' => (float) $unit->latitude,
                'longitude' => (float) $unit->longitude,
                'location' => $unit->location,
                'category' => $unit->category,
                'url' => route('calendar.index', ['category' => $unit->category]),
            ])
            ->values();

        $hostUnits = $user->isHost() || $user->is_admin
            ? Unit::query()
                ->with([
                    'rates',
                    'images',
                    'bookings' => fn ($query) => $query->blocking()->where('end_at', '>', now())->orderBy('start_at'),
                ])
                ->when(! $user->is_admin, fn ($query) => $query->where('host_id', $user->id))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
            : collect();

        return view('dashboard', [
            'todayCount' => $todayCount,
            'upcomingCount' => $upcomingCount,
            'monthSales' => $monthSales,
            'pendingBalance' => $pendingBalance,
            'unitCount' => $user->isHost() || $user->is_admin
                ? Unit::query()->when(! $user->is_admin, fn ($query) => $query->where('host_id', $user->id))->count()
                : Unit::query()->where('is_active', true)->count(),
            'todayBookings' => $todayBookings,
            'upcomingBookings' => $upcomingBookings,
            'marketingUnits' => $marketingUnits,
            'overviewMapUnits' => $overviewMapUnits,
            'hostUnits' => $hostUnits,
            'hostApplication' => $user->hostApplication,
        ]);
    }
}
