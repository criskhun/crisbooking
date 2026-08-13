<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth()
            : now()->toImmutable()->startOfMonth();

        $selectedDate = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['date'])->startOfDay()
            : ($month->isSameMonth(now()) ? now()->toImmutable()->startOfDay() : $month);

        if (! $selectedDate->isSameMonth($month)) {
            $month = $selectedDate->startOfMonth();
        }

        $gridStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $gridEndExclusive = $gridEnd->addDay()->startOfDay();

        $bookingCounts = [];
        $topBookedListings = collect();

        if (Schema::hasTable('users') && Schema::hasTable('units') && Schema::hasTable('bookings')) {
            Booking::query()
                ->blocking()
                ->where('start_at', '<', $gridEndExclusive)
                ->where('end_at', '>', $gridStart)
                ->whereHas('unit', fn ($query) => $query
                    ->where('is_active', true)
                    ->whereHas('host', fn ($host) => $host->whereNotNull('profile_completed_at')))
                ->get(['start_at', 'end_at'])
                ->each(function (Booking $booking) use (&$bookingCounts, $gridStart, $gridEnd): void {
                    $firstDay = $booking->start_at->toImmutable()->startOfDay()->max($gridStart);
                    $lastDay = $booking->end_at->toImmutable()->subSecond()->startOfDay()->min($gridEnd);

                    for ($day = $firstDay; $day->lte($lastDay); $day = $day->addDay()) {
                        $key = $day->toDateString();
                        $bookingCounts[$key] = ($bookingCounts[$key] ?? 0) + 1;
                    }
                });

            $selectedEnd = $selectedDate->addDay();
            $topBookedListings = Unit::query()
                ->with('images')
                ->where('is_active', true)
                ->whereHas('host', fn ($host) => $host->whereNotNull('profile_completed_at'))
                ->withCount([
                    'bookings as selected_date_bookings_count' => fn ($bookings) => $bookings
                        ->blocking()
                        ->where('start_at', '<', $selectedEnd)
                        ->where('end_at', '>', $selectedDate),
                    'bookings as confirmed_bookings_count' => fn ($bookings) => $bookings->where('status', 'confirmed'),
                ])
                ->orderByDesc('selected_date_bookings_count')
                ->orderByDesc('confirmed_bookings_count')
                ->orderBy('name')
                ->limit(3)
                ->get();
        }

        $calendarDays = collect();
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $calendarDays->push($day);
        }

        return view('welcome', compact(
            'month',
            'selectedDate',
            'calendarDays',
            'bookingCounts',
            'topBookedListings',
        ));
    }
}
