<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $startDateValue = $validated['start_date'] ?? now()->toDateString();
        $endDateValue = $validated['end_date'] ?? $startDateValue;
        $startDate = CarbonImmutable::createFromFormat('Y-m-d', $startDateValue)->startOfDay();
        $endDate = CarbonImmutable::createFromFormat('Y-m-d', $endDateValue)->startOfDay();
        $selectedCategory = $validated['category'] ?? 'all';
        $selectedLocation = trim($validated['location'] ?? '');

        $listingsQuery = Unit::query()
            ->with([
                'host.hostApplication',
                'images',
                'rates',
                'listingReviews' => fn ($reviews) => $reviews->with('reviewer')->latest(),
            ])
            ->withAvg('listingReviews', 'rating')
            ->withCount('listingReviews')
            ->when($request->user(), fn ($query, $user) => $query->withExists([
                'favoritedBy as is_favorited' => fn ($favorites) => $favorites->where('users.id', $user->id),
            ]))
            ->where('is_active', true)
            ->whereHas('host', fn ($host) => $host
                ->whereNotNull('profile_completed_at'))
            ->availableBetween($startDate, $endDate->addDay());

        match ($selectedCategory) {
            'car', 'condo', 'driving' => $listingsQuery->where('category', $selectedCategory),
            'other' => $listingsQuery->whereNotIn('category', ['car', 'condo', 'driving']),
            default => null,
        };

        if ($selectedLocation !== '') {
            $listingsQuery->where(fn ($locations) => $locations
                ->whereLike('location', '%'.$selectedLocation.'%')
                ->orWhereHas('host', fn ($host) => $host
                    ->whereLike('country', '%'.$selectedLocation.'%')
                    ->orWhereLike('city', '%'.$selectedLocation.'%')));
        }

        $availableListings = $listingsQuery
            ->orderByDesc('listing_reviews_avg_rating')
            ->orderByDesc('listing_reviews_count')
            ->orderBy('name')
            ->get();

        $availableMapListings = $availableListings
            ->filter(fn (Unit $listing) => $listing->latitude !== null && $listing->longitude !== null)
            ->map(function (Unit $listing) {
                $startingPrice = $listing->startingPrice();

                return [
                    'id' => $listing->id,
                    'name' => $listing->name,
                    'latitude' => (float) $listing->latitude,
                    'longitude' => (float) $listing->longitude,
                    'location' => $listing->location,
                    'category' => $listing->category,
                    'capacity' => $listing->capacity,
                    'bedrooms' => $listing->property_details['bedrooms'] ?? null,
                    'starting_price' => (float) $listing->discountedPrice($startingPrice),
                    'original_price' => (float) $startingPrice,
                    'sale_percentage' => (float) ($listing->sale_percentage ?? 0),
                    'host_name' => $listing->host->name,
                    'business_name' => $listing->host->publicHostName(),
                    'host_avatar_url' => $listing->host->avatarUrl(),
                    'marker_image_url' => $listing->host->avatarUrl() ?: ($listing->primaryImagePath() ? Storage::disk('public')->url($listing->primaryImagePath()) : null),
                    'image_url' => $listing->primaryImagePath() ? Storage::disk('public')->url($listing->primaryImagePath()) : null,
                    'url' => route('listings.show', $listing),
                    'inquiry_url' => route('listings.inquire', $listing),
                    'host_url' => route('hosts.show', $listing->host),
                    'navigation_url' => 'https://www.google.com/maps/dir/?api=1&destination='.$listing->latitude.','.$listing->longitude,
                ];
            })
            ->values();

        return view('availability.index', compact(
            'availableListings',
            'availableMapListings',
            'startDate',
            'endDate',
            'selectedCategory',
            'selectedLocation',
        ));
    }
}
