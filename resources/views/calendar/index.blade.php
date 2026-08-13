@extends('layouts.app')

@section('title', $bookingMode ? 'Book now — Davao Rent Zone' : 'Booking calendar — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">{{ $bookingMode ? 'Discover' : 'Schedule' }}</span><h1>{{ $bookingMode ? 'Book what you need' : 'Booking calendar' }}</h1></div>
                @include('partials.user-badge')
            </header>

            <section class="booking-calendar-section">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @if ($errors->any())
                    <div class="oauth-error account-alert" role="alert"><strong>The booking could not be saved.</strong><br>{{ $errors->first() }}</div>
                @endif

                @if ($bookingMode)
                    @if ($canManageListings)
                        <div class="calendar-mode-switch"><span>You are booking as {{ auth()->user()->name }}. Your own listings are excluded.</span><a class="button button-ghost button-small" href="{{ route('calendar.index', ['mode' => 'manage']) }}">Open host calendar</a></div>
                    @endif
                    @include('calendar.client-booking')
                @else
                <div class="calendar-toolbar">
                    <div>
                        <span class="eyebrow">{{ $month->format('Y') }}</span>
                        <h2>{{ $month->format('F') }}</h2>
                    </div>
                    <div class="calendar-navigation">
                        <a aria-label="Previous month" href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}">←</a>
                        <a class="calendar-today-link" href="{{ route('calendar.index', ['month' => now()->format('Y-m'), 'date' => now()->format('Y-m-d')]) }}">Today</a>
                        <a aria-label="Next month" href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">→</a>
                    </div>
                </div>

                <div class="calendar-layout">
                    <div class="calendar-month-panel">
                        <div class="calendar-weekdays">
                            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)<span>{{ $weekday }}</span>@endforeach
                        </div>
                        <div class="booking-calendar-grid" style="--calendar-weeks: {{ $calendarWeekCount }}; --calendar-lanes: {{ $calendarLaneCount }};">
                            @foreach ($days as $day)
                                @php
                                    $calendarCell = $loop->index;
                                    $calendarRow = intdiv($calendarCell, 7) + 1;
                                    $calendarColumn = ($calendarCell % 7) + 1;
                                @endphp
                                <a @class(['calendar-day', 'outside-month' => ! $day->isSameMonth($month), 'is-today' => $day->isToday(), 'selected' => $day->isSameDay($selectedDate)]) style="grid-column: {{ $calendarColumn }}; grid-row: {{ $calendarRow }};" data-calendar-date="{{ $day->format('Y-m-d') }}" href="{{ route('calendar.index', ['month' => $month->format('Y-m'), 'date' => $day->format('Y-m-d')]) }}">
                                    <span class="calendar-day-number">{{ $day->day }}</span>
                                </a>
                            @endforeach
                            @foreach ($calendarSegments as $segment)
                                @php $calendarBooking = $segment['booking']; @endphp
                                <a @class(['calendar-booking-span', 'status-'.$calendarBooking->status, 'starts-booking' => $segment['starts_booking'], 'ends-booking' => $segment['ends_booking'], 'continues-before' => $segment['continues_before'], 'continues-after' => $segment['continues_after']])
                                   style="grid-column: {{ $segment['start_column'] }} / {{ $segment['end_column'] }}; grid-row: {{ $segment['week'] }}; --calendar-lane: {{ $segment['lane'] }};"
                                   data-booking-id="{{ $calendarBooking->id }}" data-segment-start="{{ $segment['start_date'] }}" data-segment-end="{{ $segment['end_date'] }}"
                                   href="{{ route('bookings.show', $calendarBooking) }}"
                                   title="{{ $calendarBooking->unit->name }}: {{ $calendarBooking->start_at->format('M j, g:i A') }} to {{ $calendarBooking->end_at->format('M j, g:i A') }}">
                                    @if ($segment['continues_before'])<span class="calendar-span-continuation">‹</span>@endif
                                    @if ($segment['starts_booking'])<time>{{ $calendarBooking->start_at->format('g:i A') }}</time>@endif
                                    <strong>{{ $calendarBooking->unit->name }}</strong>
                                    @if ($segment['ends_booking'])<time>→ {{ $calendarBooking->end_at->format('g:i A') }}</time>@elseif ($segment['continues_after'])<span class="calendar-span-continuation">›</span>@endif
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <aside class="calendar-side-panel">
                        <div class="selected-date-heading">
                            <span class="selected-date-number">{{ $selectedDate->format('d') }}</span>
                            <div><span class="eyebrow">Selected date</span><h2>{{ $selectedDate->format('l, M j') }}</h2></div>
                        </div>

                        <div class="availability-list">
                            <div class="side-panel-title"><h3>Your availability</h3><span>{{ $units->count() }}</span></div>
                            @forelse ($units as $unit)
                                @php
                                    $booked = $bookedUnitIds->contains($unit->id);
                                @endphp
                                <div class="availability-row">
                                    @if ($unit->primaryImagePath())<img class="availability-photo" src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="">@endif
                                    <div><strong>{{ $unit->name }}</strong><small>{{ ucfirst($unit->kind) }} · {{ $unit->isPackageRental() ? 'From ₱'.number_format($unit->rates->min('price'), 2) : '₱'.number_format($unit->price, 2).'/'.$unit->pricing_unit }}</small></div>
                                    @if (! $unit->is_active)<span class="availability-badge unavailable">Unavailable</span>
                                    @elseif ($booked)<span class="availability-badge booked" title="This date has booked times; other hours may still be available.">Check times</span>
                                    @else<span class="availability-badge available">Available</span>@endif
                                </div>
                            @empty
                                <p class="side-empty">No listings are available yet.</p>
                            @endforelse
                        </div>
                    </aside>
                </div>

                <div class="booking-workspace">
                    <section class="day-bookings-card">
                        <div class="side-panel-title"><div><span class="eyebrow">Schedule</span><h2>Booking requests</h2></div></div>
                        @php
                            $selectedStart = $selectedDate->copy()->startOfDay();
                            $selectedEnd = $selectedDate->copy()->addDay()->startOfDay();
                            $selectedBookings = $bookings->filter(fn ($booking) => $booking->start_at->lt($selectedEnd) && $booking->end_at->gt($selectedStart));
                        @endphp
                        <div class="day-booking-list">
                            @forelse ($selectedBookings as $booking)
                                <article class="day-booking-item">
                                    <span class="booking-time">{{ $booking->start_at->format('g:i A') }}<small>to {{ $booking->end_at->format($booking->end_at->isSameDay($booking->start_at) ? 'g:i A' : 'M j, g:i A') }}</small></span>
                                    <div class="booking-details">
                                        <strong>{{ $booking->unit->name }}</strong>
                                        <small>Client: {{ $booking->client->name }}{{ $booking->rate_period ? ' · '.(['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'][$booking->rate_period] ?? $booking->rate_period) : '' }} · ₱{{ number_format($booking->total_amount, 2) }}</small>
                                        @if (auth()->user()->isHost() || auth()->user()->is_admin)
                                            <div class="booking-trust-links"><a href="{{ route('profiles.show', $booking->client) }}">View client profile</a>@if ($booking->inquiry)<a href="{{ route('inquiries.show', $booking->inquiry) }}">Open inquiry chat</a>@endif</div>
                                        @endif
                                        @if ($booking->notes)<p>{{ $booking->notes }}</p>@endif
                                        @if ($booking->client_id === auth()->id() && $booking->unit->category === 'condo' && in_array('wifi', $booking->unit->property_details['amenities'] ?? [], true))
                                            @if ($booking->status === 'confirmed' && $booking->unit->wifi_details)
                                                <div class="confirmed-wifi-access">
                                                    <strong>Wi-Fi access</strong>
                                                    <span><small>SSID</small>{{ $booking->unit->wifi_details['ssid'] ?? '' }}</span>
                                                    <span><small>Password</small>{{ $booking->unit->wifi_details['password'] ?? '' }}</span>
                                                    @if (! empty($booking->unit->wifi_details['notes']))<p>{{ $booking->unit->wifi_details['notes'] }}</p>@endif
                                                    @if ($booking->unit->wifi_qr_path)<img src="{{ route('units.wifi-qr', $booking->unit) }}" alt="Wi-Fi QR code for {{ $booking->unit->name }}">@endif
                                                </div>
                                            @elseif ($booking->status === 'pending')
                                                <p class="locked-access-note">🔒 Wi-Fi access will appear here after the host confirms this booking.</p>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="booking-item-actions">
                                        <span class="booking-status status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                                        @if ((auth()->user()->isHost() || auth()->user()->is_admin) && $booking->status === 'pending')
                                            <form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button type="submit">Confirm</button></form>
                                            <form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="danger-action" type="submit">Decline</button></form>
                                        @elseif ($booking->client_id === auth()->id() && $booking->status !== 'cancelled' && $booking->end_at->isFuture())
                                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">@csrf @method('PATCH')<button class="danger-action" type="submit">Cancel</button></form>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="day-bookings-empty"><span>✓</span><p>No bookings on this date.</p></div>
                            @endforelse
                        </div>
                    </section>
                </div>
                @endif
            </section>
        </main>
    </div>
@endsection
