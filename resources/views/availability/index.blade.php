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
        $homeFilters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category' => $selectedCategory,
        ];
    @endphp

    <header class="availability-page-nav">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $branding->site_name }} home">
            <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
            <span class="brand-name">{{ $branding->site_name }}</span>
        </a>
        <a class="availability-back-link" href="{{ route('home', $homeFilters).'#availability-results' }}"><x-fa-icon name="arrow-left" /> Back to calendar</a>
    </header>

    <main class="availability-page">
        <form class="availability-sticky-filter" method="GET" action="{{ route('availability.index') }}" aria-label="Change availability filters" data-results-filter>
            <div class="availability-filter-title">
                <x-fa-icon name="magnifying-glass" />
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

        <section class="listing-view-shell" data-listing-view-switch data-default-view="grid" aria-labelledby="available-listings-heading">
            <div class="availability-page-heading">
                <div>
                    <span class="eyebrow">Available for your schedule</span>
                    <h1 id="available-listings-heading">{{ $availableListings->count() }} {{ Str::plural('listing', $availableListings->count()) }} found</h1>
                    <p>
                        {{ $categoryLabels[$selectedCategory] }} ·
                        {{ $startDate->isSameDay($endDate) ? $startDate->format('M j, Y') : $startDate->format('M j').' – '.$endDate->format('M j, Y') }}
                        @if($selectedLocation !== '') · {{ $selectedLocation }} @endif
                    </p>
                </div>
                <div class="listing-view-heading-actions">
                    <span class="availability-sort-note">Highest rated first</span>
                    <div class="listing-view-toggle" role="group" aria-label="Choose listing view">
                        <button type="button" data-listing-view-select="grid" aria-pressed="true"><x-fa-icon name="table-cells-large" /> Grid</button>
                        <button type="button" data-listing-view-select="map" aria-pressed="false"><x-fa-icon name="map-location-dot" /> Map</button>
                    </div>
                </div>
            </div>

            <section class="available-unit-grid" aria-label="Available units" data-listing-grid-panel>
            @forelse($availableListings as $listing)
                @php
                    $group = in_array($listing->category, ['car', 'condo', 'driving'], true) ? $listing->category : 'other';
                    $latestReview = $listing->listingReviews->first();
                    $startingPrice = $listing->startingPrice();
                    $salePrice = $listing->discountedPrice($startingPrice);
                    $property = $listing->property_details ?? [];
                    $car = $listing->car_details ?? [];
                @endphp
                <article class="available-unit-card">
                    <div class="available-unit-media">
                        @include('partials.listing-card-carousel', ['carouselUnit' => $listing, 'carouselLinkClass' => 'available-unit-photo'])
                        @if($listing->hasSale())<span class="listing-sale-badge">{{ number_format((float) $listing->sale_percentage, $listing->sale_percentage == (int) $listing->sale_percentage ? 0 : 1) }}% off</span>@endif
                        @include('partials.listing-favorite', ['favoriteUnit' => $listing])
                    </div>
                    <div class="available-unit-copy">
                        <div class="available-unit-meta">
                            <span>{{ $listing->location ?: $categoryLabels[$group] }}</span>
                            @if($listing->listing_reviews_count)
                                <strong><x-fa-icon name="star" class="fa-rating" /> {{ number_format((float) $listing->listing_reviews_avg_rating, 1) }} <small>({{ $listing->listing_reviews_count }})</small></strong>
                            @else
                                <strong>New listing</strong>
                            @endif
                        </div>
                        <h2><a href="{{ route('listings.show', $listing) }}">{{ $listing->name }}</a></h2>
                        <p class="available-unit-location">{{ $categoryLabels[$group] }} · {{ $listing->capacity ? $listing->capacity.' '.Str::plural('guest', $listing->capacity) : 'Ask host for capacity' }}</p>
                        <div class="available-unit-amenities" aria-label="Listing highlights">
                            @if(in_array('wifi', $property['amenities'] ?? [], true))<span title="Wi-Fi"><x-fa-icon name="wifi" /> Wi-Fi</span>@endif
                            @if(in_array('pool', $property['amenities'] ?? [], true))<span title="Swimming pool"><x-fa-icon name="person-swimming" /> Pool</span>@endif
                            @if(in_array('parking', $property['amenities'] ?? [], true))<span title="Parking"><x-fa-icon name="square-parking" /> Parking</span>@endif
                            @if($listing->category === 'condo' && isset($property['bedrooms']))<span>{{ $property['bedrooms'] }} BR</span>@endif
                            @if($listing->category === 'car' && isset($car['transmission']))<span>{{ str($car['transmission'])->title() }}</span>@endif
                            @if($listing->category === 'car' && isset($car['year']))<span>{{ $car['year'] }}</span>@endif
                        </div>
                        @if($latestReview)
                            <p class="available-unit-review">“{{ Str::limit($latestReview->comment, 115) }}”</p>
                        @else
                            <p class="available-unit-review">No reviews yet. Be among the first to book this listing.</p>
                        @endif
                        <div class="available-unit-footer">
                            <span><small>From</small>@if($listing->hasSale())<del>₱{{ number_format($startingPrice, 2) }}</del>@endif<strong>₱{{ number_format($salePrice, 2) }}</strong><em>{{ $listing->isPackageRental() ? 'per package' : 'per '.$listing->pricing_unit }}</em>@if($listing->hasSale())<b><x-fa-icon name="check" /> Save {{ number_format((float) $listing->sale_percentage, 0) }}% direct</b>@endif</span>
                            <a href="{{ route('listings.show', $listing) }}">View listing <x-fa-icon name="arrow-right" /></a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="availability-page-empty">
                    <x-fa-icon name="magnifying-glass" />
                    <h2>No available listings found</h2>
                    <p>Change the dates or category in the filter above and try again.</p>
                    <a href="{{ route('home', $homeFilters) }}" class="button button-ghost button-small">Return to calendar</a>
                </div>
            @endforelse
            </section>

            <section class="listing-results-map-panel booking-map-explorer" aria-label="Available listings map" data-listing-map-panel data-overview-nearby-map data-default-radius-km="100" data-nearby-radius-km="50" data-map-id="{{ config('services.google.maps_map_id') }}" hidden>
                <div class="listing-map-heading">
                    <div><span class="eyebrow">Map view</span><h2>Compare hosts by lowest price</h2><p>Each marker shows the host and the lowest available price for that listing.</p></div>
                    <button class="map-action-button" type="button" data-map-use-location>Show near me</button>
                </div>
                <div class="google-map-canvas booking-discovery-map" data-map-canvas aria-label="Map of available listings and their lowest prices"></div>
                @unless(config('services.google.maps_api_key'))<div class="map-setup-note"><strong>Google Map preview is not configured yet</strong><span>Add <code>GOOGLE_MAPS_API_KEY</code>. Grid view and listing cards remain available.</span></div>@endunless
                <small class="map-status" data-map-status aria-live="polite"></small>
                <script type="application/json" data-map-units>@json($availableMapListings)</script>
            </section>
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
