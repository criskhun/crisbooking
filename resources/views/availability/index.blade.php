@extends('layouts.app')

@section('title', 'Available listings — Davao Rent Zone')
@section('body-class', 'availability-page-body')

@section('content')
    @php
        $categoryLabels = [
            'all' => 'All categories',
            'car' => 'Car rental',
            'condo' => 'Condo rental',
            'driving' => 'Driving',
            'other' => 'Other services',
        ];
        $categoryIcons = ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'other' => '◇'];
        $homeFilters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category' => $selectedCategory,
        ];
    @endphp

    <header class="availability-page-nav">
        <a class="brand" href="{{ route('home') }}" aria-label="Davao Rent Zone home">
            <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
            <span class="brand-name">Davao Rent Zone</span>
        </a>
        <a class="availability-back-link" href="{{ route('home', $homeFilters).'#availability-results' }}">← Back to calendar</a>
    </header>

    <main class="availability-page">
        <form class="availability-sticky-filter" method="GET" action="{{ route('availability.index') }}" aria-label="Change availability filters" data-results-filter>
            <div class="availability-filter-title">
                <span aria-hidden="true">⌕</span>
                <div><small>Search filters</small><strong>Change your dates or category</strong></div>
            </div>
            <div class="availability-field">
                <label for="results-start-date">Start date</label>
                <input id="results-start-date" name="start_date" type="date" min="{{ now()->toDateString() }}" value="{{ $startDate->toDateString() }}" required data-results-start>
            </div>
            <div class="availability-field">
                <label for="results-end-date">End date</label>
                <input id="results-end-date" name="end_date" type="date" min="{{ $startDate->toDateString() }}" value="{{ $endDate->toDateString() }}" required data-results-end>
            </div>
            <div class="availability-field availability-category-field">
                <label for="results-category">Category</label>
                <select id="results-category" name="category">
                    @foreach($categoryLabels as $value => $label)
                        <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="availability-field availability-location-field">
                <label for="results-location">Country or city</label>
                <input id="results-location" name="location" type="search" maxlength="100" value="{{ $selectedLocation }}" placeholder="e.g. Davao City">
            </div>
            <button class="button button-primary button-small" type="submit">Update results</button>
        </form>

        <section class="availability-page-heading" aria-labelledby="available-listings-heading">
            <div>
                <span class="eyebrow">Available for your schedule</span>
                <h1 id="available-listings-heading">{{ $availableListings->count() }} {{ Str::plural('listing', $availableListings->count()) }} found</h1>
                <p>
                    {{ $categoryLabels[$selectedCategory] }} ·
                    {{ $startDate->isSameDay($endDate) ? $startDate->format('M j, Y') : $startDate->format('M j').' – '.$endDate->format('M j, Y') }}
                    @if($selectedLocation !== '') · {{ $selectedLocation }} @endif
                </p>
            </div>
            <span class="availability-sort-note">Highest rated first</span>
        </section>

        <section class="available-unit-grid" aria-label="Available units">
            @forelse($availableListings as $listing)
                @php
                    $group = in_array($listing->category, ['car', 'condo', 'driving'], true) ? $listing->category : 'other';
                    $latestReview = $listing->listingReviews->first();
                    $startingPrice = $listing->isPackageRental() && $listing->rates->isNotEmpty()
                        ? $listing->rates->min('price')
                        : $listing->price;
                @endphp
                <article class="available-unit-card">
                    <a class="available-unit-photo" href="{{ route('listings.show', $listing) }}" aria-label="View {{ $listing->name }}">
                        @if($listing->primaryImagePath())
                            <img src="{{ Storage::disk('public')->url($listing->primaryImagePath()) }}" alt="{{ $listing->name }}">
                        @else
                            <span aria-hidden="true">{{ $categoryIcons[$group] }}</span>
                        @endif
                        <small>Available</small>
                    </a>
                    <div class="available-unit-copy">
                        <div class="available-unit-meta">
                            <span>{{ $categoryLabels[$group] }}</span>
                            @if($listing->listing_reviews_count)
                                <strong>★ {{ number_format((float) $listing->listing_reviews_avg_rating, 1) }} <small>({{ $listing->listing_reviews_count }})</small></strong>
                            @else
                                <strong>New listing</strong>
                            @endif
                        </div>
                        <h2><a href="{{ route('listings.show', $listing) }}">{{ $listing->name }}</a></h2>
                        <p class="available-unit-location">⌖ {{ $listing->location ?: 'Location arranged with host' }}</p>
                        @if($latestReview)
                            <p class="available-unit-review">“{{ Str::limit($latestReview->comment, 115) }}”</p>
                        @else
                            <p class="available-unit-review">No reviews yet. Be among the first to book this listing.</p>
                        @endif
                        <div class="available-unit-footer">
                            <span><small>From</small><strong>₱{{ number_format((float) $startingPrice, 2) }}</strong></span>
                            <a href="{{ route('listings.show', $listing) }}">View listing <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="availability-page-empty">
                    <span aria-hidden="true">⌕</span>
                    <h2>No available listings found</h2>
                    <p>Change the dates or category in the filter above and try again.</p>
                    <a href="{{ route('home', $homeFilters) }}" class="button button-ghost button-small">Return to calendar</a>
                </div>
            @endforelse
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const start = document.querySelector('[data-results-start]');
            const end = document.querySelector('[data-results-end]');

            start?.addEventListener('change', () => {
                if (!end.value || end.value < start.value) end.value = start.value;
                end.min = start.value;
            });
        });
    </script>
@endsection
