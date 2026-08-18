<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'category' => ['nullable', Rule::in(['all', 'car', 'condo', 'driving', 'other'])],
        ]);

        $startDateValue = $validated['start_date'] ?? now()->toDateString();
        $endDateValue = $validated['end_date'] ?? $startDateValue;
        $startDate = CarbonImmutable::createFromFormat('Y-m-d', $startDateValue)->startOfDay();
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', $endDateValue)->startOfDay();
        $selectedCategory = $validated['category'] ?? 'all';

        $listingsQuery = Unit::query()
            ->with([
                'host.hostApplication',
                'images',
                'rates',
                'listingReviews' => fn ($reviews) => $reviews->with('reviewer')->latest(),
            ])
            ->withAvg('listingReviews', 'rating')
            ->withCount('listingReviews')
            ->where('is_active', true)
            ->whereHas('host', fn ($host) => $host
                ->whereNotNull('profile_completed_at'))
            ->availableBetween($startDate, $endDate->addDay());

        match ($selectedCategory) {
            'car', 'condo', 'driving' => $listingsQuery->where('category', $selectedCategory),
            'other' => $listingsQuery->whereNotIn('category', ['car', 'condo', 'driving']),
            default => null,
        };

        $availableListings = $listingsQuery
            ->orderByDesc('listing_reviews_avg_rating')
            ->orderByDesc('listing_reviews_count')
            ->orderBy('name')
            ->get();

        return view('availability.index', compact(
            'availableListings',
            'startDate',
            'endDate',
            'selectedCategory',
        ));
    }
}
