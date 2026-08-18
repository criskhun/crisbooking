@extends('layouts.app')

@section('title', 'Dashboard — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div>
                    <span class="form-kicker">{{ now()->format('l, F j') }}</span>
                    <h1>Good day, {{ explode(' ', auth()->user()->name)[0] }}!</h1>
                </div>
                @include('partials.user-badge')
            </header>

            @if (session('status'))
                <div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>
            @endif

            @unless (auth()->user()->hasCompleteProfile())
                <div class="profile-required-banner"><span>!</span><div><strong>Complete your verification profile</strong><p>Contact details and a government-issued ID are required before you can {{ auth()->user()->isHost() ? 'publish listings and answer booking inquiries' : 'inquire or request a booking' }}.</p></div><a class="button button-primary" href="{{ route('profile.edit') }}">Complete profile</a></div>
            @endunless

            @if (auth()->user()->isClient())
                <section class="client-overview">
                    <div class="host-application-prompt">
                        <div><span class="eyebrow">Have something to offer?</span><h2>{{ $hostApplication ? 'Host application: '.str($hostApplication->status)->replace('_', ' ')->title() : 'Turn your vehicle, property, or service into a listing.' }}</h2><p>{{ $hostApplication?->review_note ?: 'Apply using your existing verification profile. After approval, create category-specific listings without entering your personal information again.' }}</p></div>
                        <a class="button button-primary" href="{{ route('host-applications.show') }}">{{ $hostApplication ? 'View application' : 'Apply as a host' }} →</a>
                    </div>
                    <div class="client-market-hero">
                        <div><span class="eyebrow">Book trusted local rentals</span><h2>Find the right ride, stay, or service for your next plan.</h2><p>Compare verified hosts, choose your exact schedule, and see the rental package that matches your dates.</p><a class="button button-primary" href="{{ route('calendar.index') }}">Explore available rentals <span>→</span></a></div>
                        <div class="market-hero-points"><span><strong>{{ $marketingUnits->count() }}</strong><small>Featured options</small></span><span><strong>Verified</strong><small>Host profiles</small></span><span><strong>Realtime</strong><small>Inquiry chat</small></span></div>
                    </div>

                    <div class="market-category-row">
                        @foreach (['car' => ['🚗', 'Cars'], 'condo' => ['🏢', 'Stays'], 'driving' => ['🛞', 'Drivers'], 'pet_transport' => ['🐾', 'Pet transport']] as $category => [$icon, $label])
                            <a href="{{ route('calendar.index', ['category' => $category]) }}"><span>{{ $icon }}</span><strong>{{ $label }}</strong><small>Browse availability →</small></a>
                        @endforeach
                    </div>

                    <section class="overview-section nearby-map-section" data-overview-nearby-map data-default-radius-km="500" data-map-id="{{ config('services.google.maps_map_id') }}">
                        <div class="overview-section-heading"><div><span class="eyebrow">Explore nearby</span><h2>What can I book near me?</h2><p>See map-pinned listings from verified hosts and center the map on your current position.</p></div><div class="map-toolbar"><button class="map-action-button" type="button" data-map-use-location>Use my location</button><a href="{{ route('calendar.index') }}">Search with a radius →</a></div></div>
                        <div class="google-map-canvas overview-map-canvas" data-map-canvas aria-label="Map of nearby bookable listings"></div>
                        @unless(config('services.google.maps_api_key'))<div class="map-setup-note"><strong>Google Map preview is ready for an API key</strong><span>Add <code>GOOGLE_MAPS_API_KEY</code> to show nearby listings here.</span></div>@endunless
                        <small class="map-status" data-map-status aria-live="polite"></small>
                        <script type="application/json" data-map-units>@json($overviewMapUnits)</script>
                    </section>

                    <section class="overview-section">
                        <div class="overview-section-heading"><div><span class="eyebrow">Available to explore</span><h2>Featured rentals and services</h2></div><a href="{{ route('calendar.index') }}">View all options →</a></div>
                        <div class="marketing-unit-grid">
                            @forelse ($marketingUnits as $unit)
                                @php($startingPrice = $unit->isPackageRental() ? $unit->rates->min('price') : $unit->price)
                                <article class="marketing-unit-card">
                                    <div class="marketing-unit-photo">@if($unit->primaryImagePath())<img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="{{ $unit->name }}">@else<span>{{ ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'][$unit->category] ?? '◇' }}</span>@endif<small>Verified host</small></div>
                                    <div class="marketing-unit-copy"><span>{{ str($unit->category)->replace('_', ' ')->title() }} · {{ $unit->location ?: 'Location arranged' }}</span><h3>{{ $unit->name }}</h3><p>{{ Str::limit($unit->description ?: 'Ask the host for more details about this option.', 88) }}</p><div><span><small>From</small><strong>₱{{ number_format($startingPrice, 2) }}</strong></span><a href="{{ route('listings.show', $unit) }}">View listing</a></div></div>
                                </article>
                            @empty
                                <div class="overview-empty"><span>◇</span><strong>New listings are coming soon.</strong><p>Check the booking page for all currently available services.</p></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="overview-section client-trip-section">
                        <div class="overview-section-heading"><div><span class="eyebrow">Your plans</span><h2>Upcoming bookings</h2></div><a href="{{ route('calendar.index') }}">Open my bookings →</a></div>
                        <div class="overview-booking-list">@forelse($upcomingBookings as $booking)<a href="{{ route('bookings.show', $booking) }}"><time>{{ $booking->start_at->format('M j') }}<small>{{ $booking->start_at->format('g:i A') }}</small></time><span><strong>{{ $booking->unit->name }}</strong><small>{{ $booking->unit->location ?: 'Location arranged with host' }} · {{ ucfirst($booking->status) }}</small></span><b>₱{{ number_format($booking->total_amount, 2) }}</b></a>@empty<div class="overview-empty compact"><strong>No upcoming booking yet.</strong><p>Explore a listing and begin with an inquiry.</p></div>@endforelse</div>
                    </section>
                </section>
            @else
                <section class="host-overview">
                    <div class="host-control-hero"><div><span class="eyebrow">Rental control center</span><h2>Manage rentals, requests, and live availability.</h2><p>Your operational overview shows what is occupied now, what starts next, and which requests need attention. You can also book listings offered by other hosts.</p></div><div><a class="button button-primary" href="{{ route('units.create') }}">＋ Add listing</a><a class="button button-ghost" href="{{ route('calendar.index', ['mode' => 'manage']) }}">Open availability calendar</a><a class="button button-ghost" href="{{ route('calendar.index', ['mode' => 'book']) }}">Book another host</a></div></div>

                    <div class="stat-grid host-stat-grid">
                        <article><span class="stat-icon car">●</span><small>Today's rentals</small><strong>{{ $todayCount }}</strong><p>{{ $todayCount ? 'Active on today’s schedule' : 'No rental scheduled today' }}</p></article>
                        <article><span class="stat-icon condo">●</span><small>Upcoming rentals</small><strong>{{ $upcomingCount }}</strong><p>Across {{ $unitCount }} {{ Str::plural('listing', $unitCount) }}</p></article>
                        <article><span class="stat-icon driving">●</span><small>Confirmed this month</small><strong>₱{{ number_format($monthSales, 0) }}</strong><p>Approved booking value</p></article>
                        <article><span class="stat-icon pet">●</span><small>Pending approval value</small><strong>₱{{ number_format($pendingBalance, 0) }}</strong><p>Review requests and inquiries</p></article>
                    </div>

                    <section class="overview-section">
                        <div class="overview-section-heading"><div><span class="eyebrow">Live availability</span><h2>Your rental units</h2></div><a href="{{ route('units.index') }}">Manage listings →</a></div>
                        <div class="host-unit-grid">
                            @forelse($hostUnits as $unit)
                                @php($activeBooking = $unit->bookings->first(fn ($booking) => $booking->start_at->lte(now()) && $booking->end_at->gt(now())))
                                @php($nextBooking = $unit->bookings->first(fn ($booking) => $booking->start_at->gt(now())))
                                <article><div class="host-unit-status"><span>{{ ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'][$unit->category] ?? '◇' }}</span><em class="{{ $activeBooking ? 'occupied' : ($unit->is_active ? 'available' : 'inactive') }}">{{ $activeBooking ? 'Occupied now' : ($unit->is_active ? 'Available now' : 'Not published') }}</em></div><h3>{{ $unit->name }}</h3><p>{{ $unit->location ?: 'Location arranged with client' }}</p><div class="host-unit-next"><small>{{ $activeBooking ? 'Returns' : ($nextBooking ? 'Next booking' : 'Schedule') }}</small><strong>{{ $activeBooking ? $activeBooking->end_at->format('M j, g:i A') : ($nextBooking ? $nextBooking->start_at->format('M j, g:i A') : 'Open') }}</strong></div><a href="{{ route('calendar.index', ['month' => ($activeBooking?->start_at ?? $nextBooking?->start_at ?? now())->format('Y-m')]) }}">View availability →</a></article>
                            @empty
                                <div class="overview-empty"><span>＋</span><strong>No rental units registered yet.</strong><p>Add your first listing to begin accepting inquiries.</p></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="overview-section">
                        <div class="overview-section-heading"><div><span class="eyebrow">Rental schedule</span><h2>Upcoming bookings</h2></div><a href="{{ route('calendar.index') }}">Full calendar →</a></div>
                        <div class="overview-booking-list host-booking-list">@forelse($upcomingBookings as $booking)<a href="{{ route('bookings.show', $booking) }}"><time>{{ $booking->start_at->format('M j') }}<small>{{ $booking->start_at->format('g:i A') }}</small></time><span><strong>{{ $booking->unit->name }}</strong><small>Client: {{ $booking->client->name }} · {{ $booking->party_size }} {{ Str::plural('person', $booking->party_size) }}</small></span><span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span><b>₱{{ number_format($booking->total_amount, 2) }}</b></a>@empty<div class="overview-empty compact"><strong>No upcoming rentals.</strong><p>Your approved and pending requests will appear here.</p></div>@endforelse</div>
                    </section>
                </section>
            @endif
        </main>
    </div>
@endsection
