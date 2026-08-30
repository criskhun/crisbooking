@extends('layouts.app')

@section('title', $branding->site_name.' — '.($branding->tagline ?: 'Rentals and services in one place'))

@section('content')
    @php
        $categoryLabels = [
            'all' => 'All listings',
            'car' => 'Car rental',
            'condo' => 'Condo rental',
            'driving' => 'Driving',
            'other' => 'Other services',
        ];
        $categoryIcons = ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'other' => '◇'];
        $baseFilters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'category' => $selectedCategory,
        ];
    @endphp

    <main class="landing-shell">
        <nav class="landing-nav" aria-label="Main navigation">
            <a class="brand" href="{{ route('home') }}" aria-label="{{ $branding->site_name }} home">
                <span class="brand-mark" aria-hidden="true">
                    <img src="{{ $branding->logo_url }}" alt="">
                </span>
                <span class="brand-name">{{ $branding->site_name }}</span>
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
                <span class="eyebrow">Find what is available</span>
                <h1>One calendar.<br><em>Every service.</em></h1>
                <p>Choose a category and your preferred dates to see the highest-rated car rentals, condo rentals, driving services, and more.</p>

                <div class="hero-actions">
                    @auth
                        <a class="button button-primary" href="{{ route('dashboard') }}">Go to dashboard <span aria-hidden="true">→</span></a>
                    @else
                        <a class="button button-primary" href="{{ route('register') }}">Start with {{ $branding->site_name }} <span aria-hidden="true">→</span></a>
                        <a class="text-link" href="{{ route('login') }}">I already have an account</a>
                    @endauth
                </div>

                <nav class="service-list" aria-label="Filter listings by category">
                    @foreach($categoryLabels as $categoryKey => $categoryLabel)
                        <a
                            href="{{ route('home', array_merge($baseFilters, ['category' => $categoryKey])).'#availability-results' }}"
                            class="{{ $selectedCategory === $categoryKey ? 'active' : '' }}"
                            @if($selectedCategory === $categoryKey) aria-current="true" @endif
                        >{{ $categoryLabel }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="hero-visual">
                <div class="calendar-card">
                    <form class="availability-filter" method="GET" action="{{ route('home') }}" data-availability-form>
                        <input type="hidden" name="category" value="{{ $selectedCategory }}">
                        <div class="availability-field">
                            <label for="home-start-date">Start date</label>
                            <input id="home-start-date" name="start_date" type="date" min="{{ now()->toDateString() }}" value="{{ $startDate->toDateString() }}" required data-range-start>
                        </div>
                        <span aria-hidden="true">→</span>
                        <div class="availability-field">
                            <label for="home-end-date">End date</label>
                            <input id="home-end-date" name="end_date" type="date" min="{{ $startDate->toDateString() }}" value="{{ $endDate->toDateString() }}" required data-range-end>
                        </div>
                        <button class="button button-primary button-small" type="submit">Show availability</button>
                    </form>
                    <p class="calendar-range-help" data-range-help aria-live="polite">Select a start and end date in the calendar or use the date fields.</p>

                    <div class="calendar-card-head">
                        <div><small>Availability calendar</small><strong>{{ $month->format('F Y') }}</strong></div>
                        <nav class="calendar-arrows" aria-label="Calendar month navigation">
                            <a href="{{ route('home', array_merge($baseFilters, ['month' => $month->subMonth()->format('Y-m')])) }}" aria-label="Previous month">‹</a>
                            <a href="{{ route('home', array_merge($baseFilters, ['month' => $month->addMonth()->format('Y-m')])) }}" aria-label="Next month">›</a>
                        </nav>
                    </div>
                    <div class="week-row"><span>MON</span><span>TUE</span><span>WED</span><span>THU</span><span>FRI</span><span>SAT</span><span>SUN</span></div>
                    <div class="calendar-grid" data-availability-calendar>
                        @foreach ($calendarDays as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $isInRange = $day->betweenIncluded($startDate, $endDate);
                                $isRangeEdge = $day->isSameDay($startDate) || $day->isSameDay($endDate);
                            @endphp
                            <a
                                href="{{ route('home', ['month' => $day->format('Y-m'), 'start_date' => $dateKey, 'end_date' => $dateKey, 'category' => $selectedCategory]) }}"
                                data-calendar-date="{{ $dateKey }}"
                                class="{{ $day->isSameMonth($month) ? '' : 'outside-month' }} {{ $isInRange ? 'in-range' : '' }} {{ $isRangeEdge ? 'range-edge' : '' }} {{ isset($bookingCounts[$dateKey]) ? 'has-bookings' : '' }}"
                                aria-label="{{ $day->format('F j, Y') }}{{ isset($bookingCounts[$dateKey]) ? ', '.$bookingCounts[$dateKey].' unavailable '.Str::plural('listing', $bookingCounts[$dateKey]) : '' }}"
                                @if($isRangeEdge) aria-current="date" @endif
                            >
                                <span>{{ $day->day }}</span>
                                @if(isset($bookingCounts[$dateKey]))<small>{{ $bookingCounts[$dateKey] }}</small>@endif
                            </a>
                        @endforeach
                    </div>

                    <section class="availability-results" id="availability-results" aria-labelledby="availability-heading">
                        <div class="calendar-list-heading">
                            <small>{{ $startDate->isSameDay($endDate) ? $startDate->format('D, M j') : $startDate->format('M j').' – '.$endDate->format('M j') }}</small>
                            <strong id="availability-heading">{{ $selectedCategory === 'all' ? 'Top available by category' : 'Top available '.strtolower($categoryLabels[$selectedCategory]) }}</strong>
                        </div>

                        @forelse($visibleListings as $listing)
                            @php
                                $group = in_array($listing->category, ['car', 'condo', 'driving'], true) ? $listing->category : 'other';
                                $chipClass = $group === 'car' ? 'chip-car' : ($group === 'condo' ? 'chip-condo' : 'chip-service');
                                $latestReview = $listing->listingReviews->first();
                            @endphp
                            <a class="booking-chip availability-listing {{ $chipClass }}" href="{{ route('listings.show', $listing) }}">
                                <span class="listing-icon" aria-hidden="true">{{ $categoryIcons[$group] }}</span>
                                <div>
                                    <small>{{ $categoryLabels[$group] }} · @if($listing->listing_reviews_count) <b>★ {{ number_format((float) $listing->listing_reviews_avg_rating, 1) }}</b> ({{ $listing->listing_reviews_count }}) @else New listing @endif</small>
                                    <strong>{{ $listing->name }}</strong>
                                    @if($latestReview)
                                        <span class="listing-review">“{{ Str::limit($latestReview->comment, 78) }}”</span>
                                    @else
                                        <span class="listing-review">Available for your selected {{ Str::plural('date', $startDate->isSameDay($endDate) ? 1 : 2) }}</span>
                                    @endif
                                </div>
                                <span aria-hidden="true">→</span>
                            </a>
                        @empty
                            <div class="calendar-list-empty">No approved listings in this category are available for these dates. Try another date or category.</div>
                        @endforelse

                        @if($availableListings->isNotEmpty())
                            <a class="see-all-available" href="{{ route('availability.index', $baseFilters) }}">
                                See all {{ $availableListings->count() }} available <span aria-hidden="true">→</span>
                            </a>
                        @endif
                    </section>
                </div>
                <div class="floating-stat"><span>✓</span><div><small>{{ $availableListings->count() }} available for your dates</small><strong>{{ $categoryLabels[$selectedCategory] }}</strong></div></div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-availability-form]');
            const startInput = form?.querySelector('[data-range-start]');
            const endInput = form?.querySelector('[data-range-end]');
            const help = document.querySelector('[data-range-help]');
            const calendarDates = Array.from(document.querySelectorAll('[data-calendar-date]'));
            let choosingEnd = false;

            const paintRange = () => {
                calendarDates.forEach((dateLink) => {
                    const value = dateLink.dataset.calendarDate;
                    const inRange = value >= startInput.value && value <= endInput.value;
                    const isEdge = value === startInput.value || value === endInput.value;
                    dateLink.classList.toggle('in-range', inRange);
                    dateLink.classList.toggle('range-edge', isEdge);
                    if (isEdge) dateLink.setAttribute('aria-current', 'date');
                    else dateLink.removeAttribute('aria-current');
                });
            };

            startInput?.addEventListener('change', () => {
                if (!endInput.value || endInput.value < startInput.value) endInput.value = startInput.value;
                endInput.min = startInput.value;
                paintRange();
            });
            endInput?.addEventListener('change', paintRange);

            calendarDates.forEach((dateLink) => dateLink.addEventListener('click', (event) => {
                if (!form || !startInput || !endInput) return;
                event.preventDefault();
                const selected = dateLink.dataset.calendarDate;

                if (!choosingEnd) {
                    startInput.value = selected;
                    endInput.value = selected;
                    endInput.min = selected;
                    choosingEnd = true;
                    help.textContent = 'Start date selected. Choose an end date, or press Show availability for one day.';
                    paintRange();
                    return;
                }

                if (selected < startInput.value) {
                    endInput.value = startInput.value;
                    startInput.value = selected;
                } else {
                    endInput.value = selected;
                }
                endInput.min = startInput.value;
                choosingEnd = false;
                paintRange();
                form.requestSubmit();
            }));
        });
    </script>
@endsection
