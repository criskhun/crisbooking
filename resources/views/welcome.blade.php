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

            <div class="hero-visual" aria-hidden="true">
                <div class="calendar-card">
                    <div class="calendar-card-head">
                        <div><small>Your schedule</small><strong>August 2026</strong></div>
                        <span class="calendar-arrows">‹ &nbsp; ›</span>
                    </div>
                    <div class="week-row"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span></div>
                    <div class="calendar-grid">
                        @foreach ([27,28,29,30,31,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30] as $day)
                            <span class="{{ $day === 5 ? 'today' : '' }}">{{ $day }}</span>
                        @endforeach
                    </div>
                    <div class="booking-chip chip-car"><i></i><div><small>Car rental</small><strong>Toyota Vios · 9:00 AM</strong></div></div>
                    <div class="booking-chip chip-condo"><i></i><div><small>Condo check-in</small><strong>Unit 18B · 2:00 PM</strong></div></div>
                </div>
                <div class="floating-stat"><span>₱</span><div><small>This month</small><strong>Sales at a glance</strong></div></div>
            </div>
        </section>
    </main>
@endsection
