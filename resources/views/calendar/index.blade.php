@extends('layouts.app')

@section('title', $bookingMode ? 'Book now — Davao Rent Zone' : ($isAffiliateCalendar ? 'Affiliate calendar — Davao Rent Zone' : 'Host calendar — Davao Rent Zone'))
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">{{ $bookingMode ? 'Discover' : 'Schedule' }}</span><h1>{{ $bookingMode ? 'Book what you need' : ($isAffiliateCalendar ? 'Affiliate calendar' : 'Host calendar') }}</h1></div>
                @include('partials.user-badge')
            </header>

            <section class="booking-calendar-section">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @if ($errors->any())
                    <div class="oauth-error account-alert" role="alert"><strong>The booking could not be saved.</strong><br>{{ $errors->first() }}</div>
                @endif

                @include('calendar._integration')

                @if ($bookingMode)
                    @if ($canManageListings)
                        <div class="calendar-mode-switch"><span>You are booking as {{ auth()->user()->name }}. {{ $isAffiliateCalendar ? 'Your assigned affiliate listings are available in your calendar.' : 'Your own listings are excluded.' }}</span><a class="button button-ghost button-small" href="{{ route('calendar.index', ['mode' => 'manage']) }}">Open {{ $isAffiliateCalendar ? 'affiliate' : 'host' }} calendar</a></div>
                    @endif
                    @include('calendar.client-booking')
                @else
                @include('calendar.manual-booking')
                @php
                    $calendarCategoryMeta = [
                        'condo' => ['theme' => 'condo', 'icon' => '🏠', 'label' => 'Condo / residence'],
                        'car' => ['theme' => 'car', 'icon' => '🚗', 'label' => 'Car rental'],
                        'cleaning' => ['theme' => 'cleaning', 'icon' => '🧹', 'label' => 'Cleaning'],
                        'driving' => ['theme' => 'driving', 'icon' => '🛞', 'label' => 'Driving'],
                        'massage' => ['theme' => 'massage', 'icon' => '💆', 'label' => 'Massage'],
                        'consultancy' => ['theme' => 'consultancy', 'icon' => '💼', 'label' => 'Consultancy'],
                        'pet_transport' => ['theme' => 'pet-transport', 'icon' => '🐾', 'label' => 'Pet transport'],
                    ];
                    $calendarFilterQuery = array_filter([
                        'mode' => 'manage',
                        'calendar_view' => $calendarView,
                        'schedule_category' => $scheduleCategory,
                        'schedule_unit' => $scheduleUnitId ?: null,
                    ]);
                @endphp
                <div class="calendar-toolbar">
                    <div>
                        <span class="eyebrow">{{ $month->format('Y') }}</span>
                        <h2>{{ $month->format('F') }}</h2>
                    </div>
                    <div class="calendar-toolbar-actions">
                        <nav class="calendar-view-switch" aria-label="Calendar view">
                            <a @class(['active' => $calendarView === 'month']) href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['calendar_view' => 'month', 'month' => $month->format('Y-m'), 'date' => $selectedDate->format('Y-m-d')])) }}"><span aria-hidden="true">▦</span> Month</a>
                            <a @class(['active' => $calendarView === 'listings']) href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['calendar_view' => 'listings', 'month' => $month->format('Y-m'), 'date' => $selectedDate->format('Y-m-d')])) }}"><span aria-hidden="true">☷</span> Listings</a>
                        </nav>
                        <div class="calendar-navigation">
                            <a aria-label="Previous month" href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => $month->copy()->subMonth()->format('Y-m')])) }}">←</a>
                            <a class="calendar-today-link" href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => now()->format('Y-m'), 'date' => now()->format('Y-m-d')])) }}">Today</a>
                            <a aria-label="Next month" href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => $month->copy()->addMonth()->format('Y-m')])) }}">→</a>
                        </div>
                    </div>
                </div>

                <form class="calendar-filter-bar" method="GET" action="{{ route('calendar.index') }}">
                    <input type="hidden" name="mode" value="manage">
                    <input type="hidden" name="calendar_view" value="{{ $calendarView }}">
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
                    <label><span>Category</span><select name="schedule_category">
                        <option value="">All categories</option>
                        @foreach ($scheduleCategories as $filterCategory)
                            @php $filterMeta = $calendarCategoryMeta[$filterCategory] ?? ['icon' => '✦', 'label' => str($filterCategory)->replace('_', ' ')->title()]; @endphp
                            <option value="{{ $filterCategory }}" @selected($scheduleCategory === $filterCategory)>{{ $filterMeta['icon'] }} {{ $filterMeta['label'] }}</option>
                        @endforeach
                    </select></label>
                    <label><span>Listing / unit</span><select name="schedule_unit">
                        <option value="">All listings</option>
                        @foreach ($scheduleUnits as $filterUnit)
                            <option value="{{ $filterUnit->id }}" @selected($scheduleUnitId === $filterUnit->id)>{{ $filterUnit->name }} · {{ str($filterUnit->category)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select></label>
                    <button class="button button-primary button-small" type="submit">Apply filters</button>
                    <a class="calendar-filter-reset" href="{{ route('calendar.index', ['mode' => 'manage', 'calendar_view' => $calendarView, 'month' => $month->format('Y-m'), 'date' => $selectedDate->format('Y-m-d')]) }}">Clear</a>
                </form>

                <div class="calendar-category-legend" aria-label="Calendar category colors">
                    @foreach ($scheduleCategories as $legendCategory)
                        @php $legendMeta = $calendarCategoryMeta[$legendCategory] ?? ['theme' => 'other', 'icon' => '✦', 'label' => str($legendCategory)->replace('_', ' ')->title()]; @endphp
                        <span class="category-{{ $legendMeta['theme'] }}"><b aria-hidden="true">{{ $legendMeta['icon'] }}</b>{{ $legendMeta['label'] }}</span>
                    @endforeach
                </div>
                <div class="calendar-unit-legend" aria-label="Individual listing colors">
                    @foreach($units as $profileUnit)
                        @php
                            $profileStyle = $calendarUnitStyles[$profileUnit->id] ?? null;
                        @endphp
                        <a class="category-{{ $calendarCategoryMeta[$profileUnit->category]['theme'] ?? 'other' }}" href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['schedule_unit' => $profileUnit->id])) }}" style="--unit-accent: {{ $profileStyle['accent'] ?? '#64748b' }}; --unit-soft: {{ $profileStyle['soft'] ?? '#f1f5f9' }}; --unit-fill: {{ $profileStyle['fill'] ?? '#f1f5f9' }}; --unit-ink: {{ $profileStyle['ink'] ?? '#334155' }};">
                            @if($profileUnit->primaryImagePath())<img src="{{ Storage::disk('public')->url($profileUnit->primaryImagePath()) }}" alt="">@else<span>{{ $calendarCategoryMeta[$profileUnit->category]['icon'] ?? '✦' }}</span>@endif
                            <span><strong>{{ $profileUnit->name }}</strong><small>{{ str($profileUnit->category)->replace('_', ' ')->title() }} · unique listing shade</small></span>
                        </a>
                    @endforeach
                </div>

                @if ($calendarView === 'listings')
                    @include('calendar.listing-timeline')
                @else
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
                                <a @class(['calendar-day', 'outside-month' => ! $day->isSameMonth($month), 'is-today' => $day->isToday(), 'selected' => $day->isSameDay($selectedDate)]) style="grid-column: {{ $calendarColumn }}; grid-row: {{ $calendarRow }};" data-calendar-date="{{ $day->format('Y-m-d') }}" href="{{ route('calendar.index', array_merge($calendarFilterQuery, ['month' => $month->format('Y-m'), 'date' => $day->format('Y-m-d')])) }}">
                                    <span class="calendar-day-number">{{ $day->day }}</span>
                                </a>
                            @endforeach
                            @foreach ($calendarSegments as $segment)
                                @php
                                    $calendarBooking = $segment['booking'];
                                    $eventMeta = $calendarCategoryMeta[$calendarBooking->unit->category] ?? ['theme' => 'other', 'icon' => '✦', 'label' => str($calendarBooking->unit->category)->replace('_', ' ')->title()];
                                    $calendarCanOpenBooking = $viewerCanManageBookings || ($calendarBooking->isManualBooking() && $calendarBooking->affiliatePartnership?->marketer_id === auth()->id());
                                    $unitStyle = $calendarUnitStyles[$calendarBooking->unit_id] ?? null;
                                    $calendarProfileUnit = $units->firstWhere('id', $calendarBooking->unit_id);
                                    $calendarBookingLabel = $calendarCanOpenBooking
                                        ? $calendarBooking->customerDisplayName().' · '.$calendarBooking->unit->name.($calendarBooking->isManualBooking() ? ' · '.$calendarBooking->sourceLabel() : '')
                                        : 'Reserved';
                                @endphp
                                <a @class(['calendar-booking-span', 'category-'.$eventMeta['theme'], 'status-'.$calendarBooking->status, 'starts-booking' => $segment['starts_booking'], 'ends-booking' => $segment['ends_booking'], 'continues-before' => $segment['continues_before'], 'continues-after' => $segment['continues_after']])
                                   style="grid-column: {{ $segment['start_column'] }} / {{ $segment['end_column'] }}; grid-row: {{ $segment['week'] }}; --calendar-lane: {{ $segment['lane'] }}; --unit-accent: {{ $unitStyle['accent'] ?? '#64748b' }}; --unit-soft: {{ $unitStyle['soft'] ?? '#f1f5f9' }}; --unit-fill: {{ $unitStyle['fill'] ?? '#f1f5f9' }}; --unit-ink: {{ $unitStyle['ink'] ?? '#334155' }};"
                                   data-booking-id="{{ $calendarBooking->id }}" data-segment-start="{{ $segment['start_date'] }}" data-segment-end="{{ $segment['end_date'] }}"
                                   data-unit="{{ $calendarBooking->unit->name }}" data-category="{{ $eventMeta['label'] }}" data-category-icon="{{ $eventMeta['icon'] }}"
                                   @if($calendarCanOpenBooking)
                                       data-calendar-booking-open data-client="{{ $calendarBooking->customerDisplayName() }}" data-status="{{ $calendarBooking->statusLabel() }}" data-status-key="{{ $calendarBooking->status }}"
                                       data-start="{{ $calendarBooking->start_at->format('M j, Y · g:i A') }}" data-end="{{ $calendarBooking->end_at->format('M j, Y · g:i A') }}"
                                       data-party-size="{{ number_format($calendarBooking->party_size) }}" data-total="₱{{ number_format($calendarBooking->total_amount, 2) }}"
                                       data-source="{{ $calendarBooking->isManualBooking() ? $calendarBooking->sourceDisplayLabel() : '' }}"
                                       data-notes="{{ $calendarBooking->notes }}" data-booking-url="{{ route('bookings.show', $calendarBooking) }}"
                                       href="{{ route('bookings.show', $calendarBooking) }}"
                                   @else
                                       aria-label="Reserved: {{ $calendarBooking->unit->name }}, {{ $calendarBooking->start_at->format('M j, g:i A') }} to {{ $calendarBooking->end_at->format('M j, g:i A') }}"
                                   @endif
                                   title="{{ $calendarBooking->unit->name }}: {{ $calendarBooking->start_at->format('M j, g:i A') }} to {{ $calendarBooking->end_at->format('M j, g:i A') }}">
                                    @if ($segment['continues_before'])<span class="calendar-span-continuation">‹</span>@endif
                                    @if ($segment['starts_booking'])<time>{{ $calendarBooking->start_at->format('g:i A') }}</time>@endif
                                    @if($calendarProfileUnit?->primaryImagePath())<img class="calendar-listing-avatar" src="{{ Storage::disk('public')->url($calendarProfileUnit->primaryImagePath()) }}" alt="">@endif
                                    <span class="calendar-event-icon" aria-hidden="true">{{ $eventMeta['icon'] }}</span>
                                    <strong>{{ $calendarBookingLabel }}</strong>
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
                @endif

                @if ($viewerCanManageBookings)
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
                                        <small>{{ $booking->isManualBooking() ? 'External: '.$booking->customerDisplayName().' · '.$booking->sourceDisplayLabel().' · '.$booking->durationDays().' '.Str::plural('day', $booking->durationDays()) : 'Client: '.$booking->customerDisplayName().($booking->rate_period ? ' · '.(['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'][$booking->rate_period] ?? $booking->rate_period) : '') }} · ₱{{ number_format($booking->total_amount, 2) }}</small>
                                        @if ((auth()->user()->isHost() || auth()->user()->is_admin) && ! $booking->isManualBooking())
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
                                            @elseif (in_array($booking->status, ['pending', 'pre_approved', 'payment_submitted'], true))
                                                <p class="locked-access-note">🔒 Wi-Fi access will appear here after the host confirms this booking.</p>
                                            @endif
                                        @endif
                                    </div>
                                    <div class="booking-item-actions">
                                        <span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span>
                                        @if ((auth()->user()->isHost() || auth()->user()->is_admin) && $booking->status === 'pending')
                                            <form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="pre_approved"><button type="submit">Pre-approve</button></form>
                                            <form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><button class="danger-action" type="submit">Decline</button></form>
                                        @elseif ((auth()->user()->isHost() || auth()->user()->is_admin) && $booking->status === 'payment_submitted')
                                            <a href="{{ route('bookings.show', $booking) }}">Review payment</a>
                                        @elseif ($booking->client_id === auth()->id() && in_array($booking->status, ['pending', 'pre_approved', 'payment_submitted', 'confirmed'], true) && $booking->end_at->isFuture())
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

                <dialog class="calendar-booking-dialog" data-calendar-booking-dialog aria-labelledby="calendar-booking-dialog-title">
                    <div class="calendar-booking-dialog-panel">
                        <header>
                            <span class="calendar-dialog-icon" data-calendar-dialog-icon aria-hidden="true">✦</span>
                            <div><span class="eyebrow" data-calendar-dialog-category>Booking</span><h2 id="calendar-booking-dialog-title" data-calendar-dialog-unit>Booking details</h2></div>
                            <button type="button" data-calendar-dialog-close aria-label="Close booking details">×</button>
                        </header>
                        <div class="calendar-dialog-status-row"><span class="booking-status" data-calendar-dialog-status>Pending</span></div>
                        <dl>
                            <div><dt>Customer / booking name</dt><dd data-calendar-dialog-client></dd></div>
                            <div><dt>Starts</dt><dd data-calendar-dialog-start></dd></div>
                            <div><dt>Ends</dt><dd data-calendar-dialog-end></dd></div>
                            <div><dt>Guests / quantity</dt><dd data-calendar-dialog-party></dd></div>
                            <div><dt>Total</dt><dd data-calendar-dialog-total></dd></div>
                            <div data-calendar-dialog-source-wrap hidden><dt>Sales source</dt><dd data-calendar-dialog-source></dd></div>
                        </dl>
                        <div class="calendar-dialog-notes" data-calendar-dialog-notes-wrap><small>Booking notes</small><p data-calendar-dialog-notes></p></div>
                        <footer><button class="button button-ghost" type="button" data-calendar-dialog-close>Close</button><a class="button button-primary" href="#" data-calendar-dialog-link>Open full booking</a></footer>
                    </div>
                </dialog>
                @endif
                @endif
            </section>
        </main>
    </div>
@endsection
