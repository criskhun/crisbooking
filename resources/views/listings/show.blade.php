@extends('layouts.app')

@section('title', $unit->name.' — Davao Rent Zone')
@section('body-class', 'public-listing-body')

@section('content')
    @php
        $startingPrice = $unit->startingPrice();
        $salePrice = $unit->discountedPrice($startingPrice);
        $referralCode = $affiliate?->referral_code;
        $shareUrl = $unit->publicUrl($referralCode);
        $icons = ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'];
    @endphp
    <header class="public-listing-nav">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span><span class="brand-name">Davao Rent Zone</span></a>
        <nav>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a class="button button-primary button-small" href="{{ route('calendar.index', ['mode' => 'book']) }}">Book now</a>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a class="button button-primary button-small" href="{{ route('register') }}">Create account</a>
            @endauth
        </nav>
    </header>

    <main class="public-listing-shell">
        @if (session('status'))<div class="flash-message" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif

        <section class="public-listing-hero">
            <div class="public-listing-gallery">
                @forelse($unit->images as $image)
                    <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $unit->name }} photo {{ $loop->iteration }}">
                @empty
                    @if($unit->photo_path)<img src="{{ Storage::disk('public')->url($unit->photo_path) }}" alt="{{ $unit->name }}">@else<div class="public-listing-placeholder">{{ $icons[$unit->category] ?? '◇' }}</div>@endif
                @endforelse
            </div>
            <div class="public-listing-summary">
                @include('partials.listing-favorite', ['favoriteUnit' => $unit, 'favoriteClass' => 'public-summary-favorite'])
                <span class="eyebrow">{{ str($unit->category)->replace('_', ' ')->title() }} · Verified host</span>
                <h1>{{ $unit->name }}</h1>
                <p class="public-listing-location">⌖ {{ $unit->location ?: 'Location arranged with the host' }} @if($unit->listing_reviews_count)<span>★ {{ number_format((float) $unit->listing_reviews_avg_rating, 1) }} ({{ $unit->listing_reviews_count }} {{ Str::plural('review', $unit->listing_reviews_count) }})</span>@else<span>New listing</span>@endif</p>
                <p>{{ $unit->description ?: 'Contact the host to learn more about this rental or service.' }}</p>
                <div class="public-listing-price"><small>Starting from</small>@if($unit->hasSale())<del>₱{{ number_format($startingPrice, 2) }}</del>@endif<strong>₱{{ number_format($salePrice, 2) }}</strong><span>{{ $unit->isPackageRental() ? 'per package' : '/ '.$unit->pricing_unit }}</span>@if($unit->hasSale())<em>✓ {{ number_format((float) $unit->sale_percentage, 0) }}% host sale applied</em>@endif</div>
                <div class="public-share-row">
                    <input type="text" value="{{ $shareUrl }}" readonly aria-label="Shareable listing link" data-share-url>
                    <button class="button button-ghost" type="button" data-copy-share-link>Copy link</button>
                </div>
                @if($affiliate)<small class="referral-disclosure">Shared by an approved sales affiliate. Your price is unchanged.</small>@endif
            </div>
        </section>

        <div class="public-listing-layout">
            <div class="public-listing-details">
                <section>
                    <span class="eyebrow">Listing details</span><h2>What this listing offers</h2>
                    <dl class="public-listing-facts">
                        <div><dt>Hosted by</dt><dd><a class="public-host-link" href="{{ route('hosts.show', $unit->host) }}">{{ $unit->host->publicHostName() }}</a></dd></div>
                        <div><dt>Type</dt><dd>{{ str($unit->kind)->title() }}</dd></div>
                        <div><dt>Capacity</dt><dd>{{ $unit->capacity ? $unit->capacity.' '.Str::plural('person', $unit->capacity) : 'Ask the host' }}</dd></div>
                        <div><dt>Status</dt><dd>Accepting inquiries</dd></div>
                        @if($unit->category === 'condo')<div><dt>Check-in</dt><dd>{{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckInTime())->format('g:i A') }}</dd></div><div><dt>Check-out</dt><dd>{{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckOutTime())->format('g:i A') }}</dd></div>@endif
                    </dl>
                </section>

                @if($unit->rates->isNotEmpty())
                    <section><span class="eyebrow">Transparent pricing</span><h2>Rental packages</h2><div class="public-rate-grid">@foreach($unit->rates as $rate)<span><small>{{ $unit->category === 'car' ? str($rate->coverage)->replace('_', ' ')->title().' · ' : '' }}{{ str($rate->period)->replace('_', ' ')->title() }}</small>@if($unit->hasSale())<del>₱{{ number_format($rate->price, 2) }}</del>@endif<strong>₱{{ number_format($unit->discountedPrice($rate->price), 2) }}</strong></span>@endforeach</div></section>
                @endif

                @if($unit->rules)<section><span class="eyebrow">Before you book</span><h2>{{ $unit->category === 'condo' ? 'House rules' : ($unit->category === 'car' ? 'Rental rules' : 'Service rules') }}</h2><p>{!! nl2br(e($unit->rules)) !!}</p></section>@endif
                <section class="public-listing-reviews">
                    <span class="eyebrow">Guest feedback</span><h2>@if($unit->listing_reviews_count)★ {{ number_format((float) $unit->listing_reviews_avg_rating, 1) }} from {{ $unit->listing_reviews_count }} {{ Str::plural('review', $unit->listing_reviews_count) }}@else No unit ratings yet @endif</h2>
                    <div class="public-review-grid">
                        @forelse($unit->listingReviews->take(6) as $review)
                            <article><header><strong>{{ $review->reviewer->name }}</strong><span>★ {{ number_format((float) $review->rating, 1) }}</span></header>@if($review->comment)<p>“{{ $review->comment }}”</p>@endif<small>{{ $review->created_at->format('M Y') }}</small></article>
                        @empty
                            <p class="public-review-empty">Completed guests can rate this unit after their stay or service.</p>
                        @endforelse
                    </div>
                </section>
                <section class="public-host-preview"><div>@include('partials.avatar', ['avatarUser' => $unit->host, 'avatarClass' => 'public-host-avatar'])<div><span class="eyebrow">Your host</span><h2>{{ $unit->host->publicHostName() }}</h2>@if($unit->host->publicHostName() !== $unit->host->name)<small>Managed by {{ $unit->host->name }}</small>@endif</div></div><p>{{ $unit->host->bio ?: 'View this host’s storefront to see all available rentals and services.' }}</p><a class="button button-ghost" href="{{ route('hosts.show', $unit->host) }}">View all host listings →</a></section>
            </div>

            <aside class="public-inquiry-card" id="listing-inquiry">
                <span class="eyebrow">Interested?</span><h2>Inquire before booking</h2><p>Tell the host your preferred schedule. An account is required so your inquiry and booking stay secure.</p>
                @guest
                    <a class="button button-primary public-inquiry-action" href="{{ route('listings.inquire', array_filter(['unit' => $unit, 'ref' => $referralCode])) }}">Log in to inquire or book</a>
                    <small>New here? You can create an account from the login page.</small>
                @elseif(auth()->id() !== $unit->host_id)
                    <form method="POST" action="{{ route('inquiries.store') }}" class="public-inquiry-form">
                        @csrf
                        <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                        @if($referralCode)<input type="hidden" name="referral_code" value="{{ $referralCode }}">@endif
                        <div class="field-group"><label for="desired_start_at">{{ $unit->category === 'condo' ? 'Check-in date' : 'Start' }}</label><input id="desired_start_at" name="desired_start_at" type="datetime-local" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" value="{{ old('desired_start_at') }}" @if($unit->category === 'condo') data-fixed-booking-time="{{ $unit->condoCheckInTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help">Fixed check-in time: {{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckInTime())->format('g:i A') }}</small>@endif</div>
                        <div class="field-group"><label for="desired_end_at">{{ $unit->category === 'condo' ? 'Check-out date' : 'End or return' }}</label><input id="desired_end_at" name="desired_end_at" type="datetime-local" min="{{ now()->addMinutes(2)->format('Y-m-d\TH:i') }}" value="{{ old('desired_end_at') }}" @if($unit->category === 'condo') data-fixed-booking-time="{{ $unit->condoCheckOutTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help">Fixed check-out time: {{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckOutTime())->format('g:i A') }}</small>@endif</div>
                        <div class="field-group"><label for="party_size">People</label><input id="party_size" name="party_size" type="number" min="1" max="{{ $unit->capacity ?: 10000 }}" value="{{ old('party_size', 1) }}" required></div>
                        <div class="field-group"><label for="initial_message">Message to {{ $unit->host->name }}</label><textarea id="initial_message" name="initial_message" rows="4" minlength="10" maxlength="2000" required>{{ old('initial_message', 'Hi! I am interested in this listing. Is it available for my selected schedule?') }}</textarea></div>
                        <button class="button button-primary public-inquiry-action" type="submit">Start inquiry</button>
                    </form>
                @else
                    <p class="account-alert">You own this listing, so you cannot inquire about or book it. Other active accounts can book it.</p>
                @endguest
            </aside>
        </div>
    </main>

    <script>
        document.querySelector('[data-copy-share-link]')?.addEventListener('click', async (event) => {
            const input = document.querySelector('[data-share-url]');
            try { await navigator.clipboard.writeText(input.value); event.currentTarget.textContent = 'Copied!'; }
            catch (error) { input.select(); document.execCommand('copy'); event.currentTarget.textContent = 'Copied!'; }
        });
    </script>
@endsection
