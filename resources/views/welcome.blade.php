@extends('layouts.app')

@section('title', 'Davao Rent Zone — Rentals and services in one place')

@section('content')
    <main class="landing-shell">
        <nav class="landing-nav" aria-label="Main navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="Davao Rent Zone home">
                <span class="brand-mark" aria-hidden="true">
                    <img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt="">
                </span>
                <span class="brand-name">Davao Rent Zone</span>
            </a>

            <div class="nav-actions">
                @auth
                    <a class="button button-primary button-small" href="{{ route('dashboard') }}">Open dashboard</a>
                @else
                    <a class="button button-ghost button-small" href="{{ route('login') }}">Log in</a>
                    <a class="button button-primary button-small" href="{{ route('register') }}">Create account</a>
                @endauth
            </div>
        </nav>

        @if (session('status'))
            <div class="flash-message" role="status">{{ session('status') }}</div>
        @endif

        <section class="hero">
            <div class="hero-copy">
                <span class="eyebrow">Booking management, made simple</span>
                <h1>One calendar.<br><em>Every service.</em></h1>
                <p>Manage reservations, blocked dates, and sales for your car rentals, condo rentals, driving services, and pet transportation.</p>

                <div class="hero-actions">
                    @auth
                        <a class="button button-primary" href="{{ route('dashboard') }}">Go to dashboard <span aria-hidden="true">→</span></a>
                    @else
                        <a class="button button-primary" href="{{ route('register') }}">Start with Davao Rent Zone <span aria-hidden="true">→</span></a>
                        <a class="text-link" href="{{ route('login') }}">I already have an account</a>
                    @endauth
                </div>

                <div class="service-list" aria-label="Supported services">
                    <span>Car rental</span><span>Condo rental</span><span>Driving</span><span>Pet transport</span>
                </div>
            </div>

            <div class="hero-visual">
                <div class="calendar-card">
                    <div class="calendar-card-head">
                        <div><small>Explore booked dates</small><strong>{{ $month->format('F Y') }}</strong></div>
                        <nav class="calendar-arrows" aria-label="Calendar month navigation">
                            <a href="{{ route('home', ['month' => $month->subMonth()->format('Y-m'), 'date' => $month->subMonth()->startOfMonth()->toDateString()]) }}" aria-label="Previous month">‹</a>
                            <a href="{{ route('home', ['month' => $month->addMonth()->format('Y-m'), 'date' => $month->addMonth()->startOfMonth()->toDateString()]) }}" aria-label="Next month">›</a>
                        </nav>
                    </div>
                    <div class="week-row"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span></div>
                    <div class="calendar-grid">
                        @foreach ($calendarDays as $day)
                            @php($dateKey = $day->toDateString())
                            <a
                                href="{{ route('home', ['month' => $month->format('Y-m'), 'date' => $dateKey]) }}"
                                class="{{ $day->isSameMonth($month) ? '' : 'outside-month' }} {{ $day->isSameDay($selectedDate) ? 'selected' : '' }} {{ isset($bookingCounts[$dateKey]) ? 'has-bookings' : '' }}"
                                aria-label="{{ $day->format('F j, Y') }}{{ isset($bookingCounts[$dateKey]) ? ', '.$bookingCounts[$dateKey].' active bookings' : '' }}"
                                @if($day->isSameDay($selectedDate)) aria-current="date" @endif
                            >
                                <span>{{ $day->day }}</span>
                                @if(isset($bookingCounts[$dateKey]))<small>{{ $bookingCounts[$dateKey] }}</small>@endif
                            </a>
                        @endforeach
                    </div>
                    <div class="calendar-list-heading">
                        <small>{{ $selectedDate->format('D, M j') }}</small>
                        <strong>Top booked listings</strong>
                    </div>
                    @forelse($topBookedListings as $listing)
                        @php($chipClass = $listing->category === 'car' ? 'chip-car' : ($listing->category === 'condo' ? 'chip-condo' : 'chip-service'))
                        <a class="booking-chip {{ $chipClass }}" href="{{ route('listings.show', $listing) }}">
                            <i></i>
                            <div>
                                <small>{{ Str::headline($listing->category) }}{{ $listing->selected_date_bookings_count ? ' · booked this date' : '' }}</small>
                                <strong>{{ $listing->name }} · {{ $listing->confirmed_bookings_count }} confirmed</strong>
                            </div>
                            <span aria-hidden="true">→</span>
                        </a>
                    @empty
                        <div class="calendar-list-empty">No approved listings are available yet.</div>
                    @endforelse
                </div>
                <div class="floating-stat"><span>✓</span><div><small>{{ count($bookingCounts) }} booked days shown</small><strong>Select a date to explore</strong></div></div>
            </div>
        </section>
    </main>
@endsection
