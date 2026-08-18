<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'category' => ['nullable', Rule::in(['all', 'car', 'condo', 'driving', 'other'])],
        ]);

        $startDateValue = $validated['start_date'] ?? $validated['date'] ?? now()->toDateString();
        $endDateValue = $validated['end_date'] ?? $startDateValue;
        $startDate = CarbonImmutable::createFromFormat('Y-m-d', $startDateValue)->startOfDay();
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', $endDateValue)->startOfDay();
        $availabilityEndExclusive = $endDate->addDay();
        $selectedCategory = $validated['category'] ?? 'all';

        $month = isset($validated['month'])
            ? CarbonImmutable::createFromFormat('Y-m-d', $validated['month'].'-01')->startOfMonth()
            : $startDate->startOfMonth();

        $gridStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);
        $gridEndExclusive = $gridEnd->addDay()->startOfDay();

        $bookingCounts = [];
        $availableListings = collect();

        if (Schema::hasTable('users') && Schema::hasTable('units') && Schema::hasTable('bookings') && Schema::hasTable('reviews')) {
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

            $availableListingsQuery = Unit::query()
                ->with([
                    'images',
                    'listingReviews' => fn ($reviews) => $reviews->with('reviewer')->latest(),
                ])
                ->withAvg('listingReviews', 'rating')
                ->withCount('listingReviews')
                ->where('is_active', true)
                ->whereHas('host', fn ($host) => $host->whereNotNull('profile_completed_at'))
                ->availableBetween($startDate, $availabilityEndExclusive);

            match ($selectedCategory) {
                'car', 'condo', 'driving' => $availableListingsQuery->where('category', $selectedCategory),
                'other' => $availableListingsQuery->whereNotIn('category', ['car', 'condo', 'driving']),
                default => null,
            };

            $availableListings = $availableListingsQuery
                ->orderByDesc('listing_reviews_avg_rating')
                ->orderByDesc('listing_reviews_count')
                ->orderBy('name')
                ->get();
        }

        $visibleListings = $this->topListingPerCategory($availableListings, $selectedCategory);

        $calendarDays = collect();
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $calendarDays->push($day);
        }

        return view('welcome', compact(
            'month',
            'startDate',
            'endDate',
            'calendarDays',
            'bookingCounts',
            'availableListings',
            'visibleListings',
            'selectedCategory',
        ));
    }

    private function topListingPerCategory(Collection $listings, string $selectedCategory): Collection
    {
        if ($selectedCategory !== 'all') {
            return $listings->take(1);
        }

        return collect(['car', 'condo', 'driving', 'other'])
            ->map(fn (string $group) => $listings->first(
                fn (Unit $unit) => $this->categoryGroup($unit->category) === $group
            ))
            ->filter()
            ->values();
    }

    private function categoryGroup(string $category): string
    {
        return in_array($category, ['car', 'condo', 'driving'], true) ? $category : 'other';
    }
}
