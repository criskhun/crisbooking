@extends('layouts.app')

@section('title', 'Favorites — Davao Rent Zone')
@section('body-class', 'dashboard-body favorites-page-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">Your saved listings</span><h1>Favorites</h1></div>
                <div class="header-actions"><a class="button button-primary button-small" href="{{ route('calendar.index', ['mode' => 'book']) }}">Browse listings</a>@include('partials.user-badge')</div>
            </header>

            @if(session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif

            <section class="favorites-section" aria-labelledby="favorites-heading" data-favorites-page>
                <div class="favorites-heading">
                    <div><span class="eyebrow">Saved for later</span><h2 id="favorites-heading" data-favorites-heading>{{ $favorites->count() }} favorite {{ Str::plural('listing', $favorites->count()) }}</h2><p>Compare your saved rentals and services, then open a listing to inquire or book.</p></div>
                    @if($favorites->isNotEmpty())<span data-favorites-count>{{ $favorites->count() }} saved</span>@endif
                </div>

                <div class="favorite-listing-grid">
                    @forelse($favorites as $unit)
                        @php
                            $startingPrice = $unit->startingPrice();
                        @endphp
                        <article class="favorite-listing-card" data-favorite-card>
                            <div class="favorite-listing-media">
                                @include('partials.listing-card-carousel', ['carouselUnit' => $unit])
                                @if($unit->hasSale())<span class="listing-sale-badge">{{ number_format((float) $unit->sale_percentage, 0) }}% off</span>@endif
                                @include('partials.listing-favorite', ['favoriteUnit' => $unit])
                            </div>
                            <div class="favorite-listing-copy">
                                <div class="favorite-listing-meta"><span>{{ str($unit->category)->replace('_', ' ')->title() }}</span>@if($unit->listing_reviews_count)<strong><x-fa-icon name="star" class="fa-rating" /> {{ number_format((float) $unit->listing_reviews_avg_rating, 1) }} <small>({{ $unit->listing_reviews_count }})</small></strong>@else<small>New listing</small>@endif</div>
                                <h3><a href="{{ route('listings.show', $unit) }}">{{ $unit->name }}</a></h3>
                                <p><x-fa-icon name="location-dot" /> {{ $unit->location ?: 'Location arranged with host' }}</p>
                                <small>Hosted by {{ $unit->host->publicHostName() }}{{ $unit->capacity ? ' · Up to '.$unit->capacity.' '.Str::plural('person', $unit->capacity) : '' }}</small>
                                <footer>
                                    <span><small>From</small>@if($unit->hasSale())<del>₱{{ number_format($startingPrice, 2) }}</del>@endif<strong>₱{{ number_format($unit->discountedPrice($startingPrice), 2) }}</strong>@if($unit->hasSale())<em>Save {{ number_format((float) $unit->sale_percentage, 0) }}%</em>@endif</span>
                                    <div><a class="button button-ghost" href="{{ route('listings.show', $unit) }}">View details</a><a class="button button-primary" href="{{ route('listings.inquire', $unit) }}">Inquire</a></div>
                                </footer>
                            </div>
                        </article>
                    @empty
                        <div class="favorites-empty">
                            <span><x-fa-icon name="heart" /></span><h2>No favorites yet</h2><p>Tap the heart on any listing to save it here for easy comparison later.</p><a class="button button-primary" href="{{ route('calendar.index', ['mode' => 'book']) }}">Find a listing</a>
                        </div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
