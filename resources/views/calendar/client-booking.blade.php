@php
    $categories = [
        'car' => ['icon' => '🚗', 'label' => 'Car rental', 'copy' => 'Drive on your own schedule'],
        'condo' => ['icon' => '🏢', 'label' => 'Stay', 'copy' => 'Condos, rooms, and properties'],
        'driving' => ['icon' => '🛞', 'label' => 'Driving service', 'copy' => 'Book a driver for your trip'],
        'pet_transport' => ['icon' => '🐾', 'label' => 'Pet transport', 'copy' => 'Safe travel for your pet'],
        'other' => ['icon' => '◇', 'label' => 'Other service', 'copy' => 'Browse other bookable services'],
    ];
    $rateLabels = ['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'];
    $minimumStart = now()->addMinute()->startOfMinute();
    $defaultStart = now()->addHours(2)->startOfHour();
    $defaultEnd = $defaultStart->copy()->addDay();
@endphp

<div class="booking-journey">
    <div class="journey-intro">
        <span class="form-kicker">Find your next booking</span>
        <h2>What do you need?</h2>
        <p>Tell us what you are booking first, then add your trip details to see only the options that fit.</p>
    </div>

    <ol class="booking-steps" aria-label="Booking steps">
        <li class="active"><span>1</span><strong>Choose what</strong></li>
        <li @class(['active' => $category])><span>2</span><strong>Add details</strong></li>
        <li @class(['active' => $searchSubmitted])><span>3</span><strong>Select a match</strong></li>
    </ol>

    <section class="booking-step-card">
        <div class="step-heading"><span>Step 1</span><div><h3>Choose what you want to book</h3><p>You can change this anytime.</p></div></div>
        <div class="booking-category-grid">
            @foreach ($categories as $key => $item)
                <a @class(['booking-category-card', 'selected' => $category === $key]) href="{{ route('calendar.index', ['category' => $key]) }}">
                    <span>{{ $item['icon'] }}</span><strong>{{ $item['label'] }}</strong><small>{{ $item['copy'] }}</small>
                </a>
            @endforeach
        </div>
    </section>

    @if ($category)
        <section class="booking-step-card" id="booking-details">
            <div class="step-heading"><span>Step 2</span><div><h3>Add your booking details</h3><p>We’ll check capacity and availability for your dates.</p></div></div>
            <form method="GET" action="{{ route('calendar.index') }}" class="discovery-form">
                <input type="hidden" name="category" value="{{ $category }}">
                <input type="hidden" name="search" value="1">
                <div class="field-group"><label for="search_start">Start date & time</label><input id="search_start" name="search_start" type="datetime-local" min="{{ $minimumStart->format('Y-m-d\TH:i') }}" value="{{ old('search_start', $searchStart?->format('Y-m-d\TH:i') ?? $defaultStart->format('Y-m-d\TH:i')) }}" required><small class="field-help">Today is available when the selected time is still ahead.</small></div>
                <div class="field-group"><label for="search_end">End or return date & time</label><input id="search_end" name="search_end" type="datetime-local" min="{{ old('search_start', $searchStart?->format('Y-m-d\TH:i') ?? $defaultStart->format('Y-m-d\TH:i')) }}" value="{{ old('search_end', $searchEnd?->format('Y-m-d\TH:i') ?? $defaultEnd->format('Y-m-d\TH:i')) }}" required></div>
                <div class="field-group"><label for="party_size">Number of people</label><input id="party_size" name="party_size" type="number" min="1" max="10000" value="{{ old('party_size', $partySize) }}" required></div>
                <div class="field-group"><label for="location">Location</label><input id="location" name="location" list="booking-locations" value="{{ old('location', request('location')) }}" placeholder="City, landmark, or area" data-map-address></div>
                <datalist id="booking-locations">@foreach ($locations as $location)<option value="{{ $location }}">@endforeach</datalist>
                <div class="field-group radius-field"><label for="radius_km">Search radius</label><div class="radius-control"><input id="radius_km" name="radius_km" type="range" min="10" max="1000" step="10" value="{{ old('radius_km', $radiusKm) }}" data-radius-input><output for="radius_km" data-radius-output>{{ number_format($radiusKm, 0) }} km</output></div></div>
                <input name="search_latitude" type="hidden" value="{{ old('search_latitude', $searchLatitude) }}" data-map-latitude>
                <input name="search_longitude" type="hidden" value="{{ old('search_longitude', $searchLongitude) }}" data-map-longitude>
                @if ($category === 'condo')
                    <div class="field-group"><label for="amenity">Must-have amenity</label><select id="amenity" name="amenity"><option value="">Any amenity</option>@foreach (['wifi' => 'Wi-Fi', 'air_conditioning' => 'Air conditioning', 'kitchen' => 'Kitchen', 'parking' => 'Parking', 'pool' => 'Swimming pool', 'balcony' => 'Balcony', 'pet_friendly' => 'Pet friendly', 'furnished' => 'Furnished'] as $value => $label)<option value="{{ $value }}" @selected(request('amenity') === $value)>{{ $label }}</option>@endforeach</select></div>
                @endif
                <div class="field-group"><label for="sort">Sort results</label><select id="sort" name="sort"><option value="recommended" @selected(request('sort', 'recommended') === 'recommended')>Recommended</option><option value="price_low" @selected(request('sort') === 'price_low')>Lowest price</option><option value="capacity_high" @selected(request('sort') === 'capacity_high')>Highest capacity</option></select></div>
                <button class="button button-primary discovery-submit" type="submit">Show available options <span>→</span></button>
                <div class="search-map-panel" data-search-location-map data-map-id="{{ config('services.google.maps_map_id') }}">
                    <div class="map-field-heading"><div><strong>Choose your search area</strong><small>Click the map or use your current location. The circle follows your selected radius.</small></div><span data-map-coordinate-label>{{ $searchLatitude !== null ? number_format($searchLatitude, 5).', '.number_format($searchLongitude, 5) : 'No center selected' }}</span></div>
                    <div class="map-toolbar"><button class="map-action-button" type="button" data-map-find-address>Find typed location</button><button class="map-action-button" type="button" data-map-use-location>Use my location</button><button class="map-clear-button" type="button" data-map-clear>Clear map radius</button></div>
                    <div class="google-map-canvas search-map-canvas" data-map-canvas aria-label="Google map radius search"></div>
                    @unless(config('services.google.maps_api_key'))<div class="map-setup-note"><strong>Google Map preview is not configured yet</strong><span>Add <code>GOOGLE_MAPS_API_KEY</code>. Text location search remains available.</span></div>@endunless
                    <small class="map-status" data-map-status aria-live="polite"></small>
                    <script type="application/json" data-map-units>@json($matchingMapUnits)</script>
                </div>
            </form>
        </section>
    @endif

    @if ($searchSubmitted)
        <section class="booking-step-card booking-results" id="booking-results">
            <div class="results-heading">
                <div><span class="eyebrow">Step 3 · Available options</span><h3>{{ $matchingUnits->count() }} {{ Str::plural('match', $matchingUnits->count()) }} for your trip</h3><p>{{ $searchStart->format('M j, g:i A') }} – {{ $searchEnd->format('M j, g:i A') }} · {{ $partySize }} {{ Str::plural('person', $partySize) }}{{ request('location') ? ' · '.request('location') : '' }}</p></div>
            </div>
            <div class="client-result-grid">
                @forelse ($matchingUnits as $unit)
                    @php
                        $isSelected = $selectedUnit?->id === $unit->id;
                        $selectUrl = route('calendar.index', array_merge(request()->query(), ['selected_unit' => $unit->id])).'#booking-selection';
                    @endphp
                    <article @class(['client-result-card', 'selected' => $isSelected])>
                        <div class="result-photo">@if ($unit->primaryImagePath())<img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="{{ $unit->name }}">@else<span>{{ $categories[$unit->category]['icon'] ?? '◇' }}</span>@endif</div>
                        <div class="result-card-body">
                            <div class="result-card-kicker"><span>{{ $categories[$unit->category]['label'] ?? ucfirst($unit->kind) }}</span><small>Available</small></div>
                            <h4>{{ $unit->name }}</h4>
                            <p class="result-location">⌖ {{ $unit->location ?: 'Location arranged with host' }}</p>
                            <p>{{ Str::limit($unit->description ?: 'Contact the host for more details about this listing.', 115) }}</p>
                            <div class="result-facts"><span><small>Capacity</small><strong>{{ $unit->capacity ? 'Up to '.$unit->capacity : 'Ask host' }}</strong></span><span><small>{{ isset($unit->distance_km) ? 'Distance' : 'Verified host' }}</small><strong>{{ isset($unit->distance_km) ? number_format($unit->distance_km, 1).' km away' : '✓ '.$unit->host->name }}</strong></span></div>
                            <div class="result-card-footer"><div><small>From</small><strong>₱{{ number_format($unit->isPackageRental() ? $unit->rates->min('price') : $unit->price, 2) }}</strong></div><a class="button {{ $isSelected ? 'button-selected' : 'button-primary' }}" href="{{ $selectUrl }}">{{ $isSelected ? 'Selected ✓' : 'View & select' }}</a></div>
                        </div>
                    </article>
                @empty
                    <div class="no-results"><span>⌕</span><h4>No exact matches yet</h4><p>Try a nearby location, fewer people, or different dates.</p><a href="#booking-details">Change search details</a></div>
                @endforelse
            </div>
        </section>
    @endif

    @if ($selectedUnit)
        @php
            $coverageLabels = ['within_city' => 'Within-city use', 'out_of_town' => 'Out-of-town use'];
            $carCoverageRates = $selectedUnit->category === 'car'
                ? $selectedUnit->rates->groupBy('coverage')->filter(fn ($rates, $coverage) => isset($coverageLabels[$coverage]))
                : collect();
            $selectedCoverage = old('rental_coverage', $carCoverageRates->keys()->first());
            $additionalFees = $selectedUnit->category === 'car'
                ? collect($selectedUnit->car_details['charges'] ?? [])->map(fn ($charge) => $charge['label'].': ₱'.number_format($charge['amount'] ?? 0, 2).(!empty($charge['refundable']) ? ' (refundable)' : ''))->implode("\n")
                : collect(['parking' => 'Parking', 'pool' => 'Swimming pool'])->map(function ($label, $key) use ($selectedUnit) {
                    $details = $selectedUnit->property_details[$key] ?? null;
                    if (! $details) return null;
                    return ($details['payment_type'] ?? 'included') === 'separate' ? $label.': ₱'.number_format($details['rate'] ?? 0, 2).' / '.($details['rate_unit'] ?? 'booking') : $label.': Included';
                })->filter()->implode("\n");
        @endphp
        <section class="booking-selection-card" id="booking-selection">
            <div class="selection-summary">
                <span class="eyebrow">Your selection</span><h3>{{ $selectedUnit->name }}</h3><p>{{ $selectedUnit->description ?: 'Review the booking details and send your request to the host.' }}</p>
                <div class="selection-facts"><span>⌖ {{ $selectedUnit->location ?: 'Location arranged with host' }}</span><span>♙ {{ $partySize }} {{ Str::plural('person', $partySize) }}</span><span>◷ {{ $searchStart->format('M j, g:i A') }}</span></div>
                <details class="client-unit-rules"><summary>{{ $selectedUnit->category === 'car' ? 'Car rules' : ($selectedUnit->category === 'condo' ? 'House rules' : 'Service rules') }} <span>Expand</span></summary><p>{{ $selectedUnit->rules ?: 'No additional rules were provided for this listing.' }}</p><small>By submitting a request, you agree to follow the host’s rules.</small></details>
                @if ($additionalFees)<details class="client-unit-rules"><summary>{{ $selectedUnit->category === 'car' ? 'Required car charges' : 'Amenity access & fees' }} <span>Expand</span></summary><p>{{ $additionalFees }}</p></details>@endif
            </div>
            @if (! auth()->user()->hasCompleteProfile())
                <div class="selection-gate-card"><span>01</span><h3>Complete your verification profile</h3><p>Your contact information and government ID are required before you can contact this host.</p><a class="button button-primary button-full" href="{{ route('profile.edit') }}">Complete profile</a></div>
            @elseif (! $selectedInquiry)
                <form method="POST" action="{{ route('inquiries.store') }}" class="booking-form selection-booking-form inquiry-start-form">
                    @csrf
                    <input type="hidden" name="unit_id" value="{{ $selectedUnit->id }}">
                    <input type="hidden" name="desired_start_at" value="{{ $searchStart->format('Y-m-d\TH:i') }}">
                    <input type="hidden" name="desired_end_at" value="{{ $searchEnd->format('Y-m-d\TH:i') }}">
                    <input type="hidden" name="party_size" value="{{ $partySize }}">
                    <div class="inquiry-required-heading"><span>Required first</span><h3>Inquire with the host</h3><p>Introduce yourself, confirm availability, and ask any questions before requesting a booking.</p></div>
                    <div class="field-group"><label for="initial_message">Message to {{ $selectedUnit->host->name }}</label><textarea id="initial_message" name="initial_message" rows="5" minlength="10" maxlength="2000" placeholder="Hi! I’m interested in this listing for the selected dates. Is it available, and is there anything I should know?" required>{{ old('initial_message') }}</textarea>@error('initial_message')<p class="error-text">{{ $message }}</p>@enderror</div>
                    <button class="button button-primary" type="submit">Send inquiry & open chat</button>
                    <small class="request-note">Booking becomes available after this inquiry is sent.</small>
                </form>
            @else
            <form method="POST" action="{{ route('bookings.store') }}" class="booking-form selection-booking-form">
                @csrf
                <input type="hidden" name="inquiry_id" value="{{ $selectedInquiry->id }}">
                <input type="hidden" name="duration_pricing" value="1">
                <select id="unit_id" name="unit_id" hidden required><option value="{{ $selectedUnit->id }}" data-package-rental="{{ $selectedUnit->isPackageRental() ? '1' : '0' }}" selected>{{ $selectedUnit->name }}</option></select>
                <input id="start_at" name="start_at" type="hidden" value="{{ old('start_at', $searchStart->format('Y-m-d\TH:i')) }}">
                <div data-booking-end-field hidden><input id="end_at" name="end_at" type="hidden" value="{{ old('end_at', $searchEnd->format('Y-m-d\TH:i')) }}"></div>
                <input name="party_size" type="hidden" value="{{ $partySize }}">
                @if ($selectedUnit->isPackageRental())
                    @if ($selectedUnit->category === 'car')
                        <div class="field-group rental-coverage-choice">
                            <label for="rental_coverage">Where will you drive?</label>
                            <select id="rental_coverage" name="rental_coverage" required data-rental-coverage-select>
                                @foreach ($carCoverageRates as $coverage => $coverageRates)
                                    <option value="{{ $coverage }}" @selected($selectedCoverage === $coverage)>{{ $coverageLabels[$coverage] }}</option>
                                @endforeach
                            </select>
                            <small class="field-help">Choose the travel area so the correct rental price is applied.</small>
                            @error('rental_coverage')<p class="error-text">{{ $message }}</p>@enderror
                        </div>
                        @foreach ($carCoverageRates as $coverage => $coverageRates)
                            <div data-rental-coverage-panel="{{ $coverage }}" @if($selectedCoverage !== $coverage) hidden @endif>
                                <div class="booking-package-builder" data-package-builder data-duration-driven="1" data-start-id="start_at" data-end-id="end_at">
                                    <div class="package-builder-heading"><div><span class="eyebrow">{{ $coverageLabels[$coverage] }}</span><h3>Your matching rental package</h3></div><small>Calculated using the selected travel coverage.</small></div>
                                    <div class="package-quantity-grid">
                                        @foreach ($coverageRates as $rate)
                                            <label class="package-quantity-card" data-package-card><span><strong>{{ $rateLabels[$rate->period] }}</strong><small>₱{{ number_format($rate->price, 2) }} each</small></span><input type="number" name="package_quantities[{{ $rate->period }}]" min="0" max="365" value="0" data-package-quantity data-period="{{ $rate->period }}" data-price="{{ $rate->price }}" aria-label="Number of {{ $rateLabels[$rate->period] }} packages" readonly @disabled($selectedCoverage !== $coverage)></label>
                                        @endforeach
                                    </div>
                                    <div class="package-calculation-summary"><span><small>Selected return</small><strong data-package-end-note></strong></span><span><small>Estimated package total</small><strong data-package-total></strong></span></div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="booking-package-builder" data-package-builder data-duration-driven="1" data-start-id="start_at" data-end-id="end_at">
                            <div class="package-builder-heading"><div><span class="eyebrow">Rates for your selected dates</span><h3>Your matching rental package</h3></div><small>Change the dates above to see a different rate combination.</small></div>
                            <div class="package-quantity-grid">
                                @foreach ($selectedUnit->rates->where('coverage', 'standard') as $rate)
                                    <label class="package-quantity-card" data-package-card><span><strong>{{ $rateLabels[$rate->period] }}</strong><small>₱{{ number_format($rate->price, 2) }} each</small></span><input type="number" name="package_quantities[{{ $rate->period }}]" min="0" max="365" value="0" data-package-quantity data-period="{{ $rate->period }}" data-price="{{ $rate->price }}" aria-label="Number of {{ $rateLabels[$rate->period] }} packages" readonly></label>
                                @endforeach
                            </div>
                            <div class="package-calculation-summary"><span><small>Selected return</small><strong data-package-end-note></strong></span><span><small>Estimated package total</small><strong data-package-total></strong></span></div>
                        </div>
                    @endif
                    @error('package_quantities')<p class="error-text">{{ $message }}</p>@enderror
                @else
                    <input id="unit_rate_id" name="unit_rate_id" type="hidden" value="">
                @endif
                <div class="field-group"><label for="notes">Notes for the host <span class="optional-label">Optional</span></label><textarea id="notes" name="notes" rows="4" maxlength="1000" placeholder="Pickup details, arrival notes, or special requests…">{{ old('notes') }}</textarea></div>
                <div class="inquiry-complete-note"><span>✓</span><div><strong>Inquiry completed</strong><small>You can continue chatting before the host confirms.</small></div><a href="{{ route('inquiries.show', $selectedInquiry) }}">Open chat</a></div>
                <button class="button button-primary" type="submit">Request this booking</button>
                <small class="request-note">The host will review your profile, conversation, and request.</small>
            </form>
            @endif
        </section>
    @endif

    @unless ($searchSubmitted)
    <details class="client-catalog">
        <summary>Browse all listings <span>{{ $units->count() }}</span></summary>
        <div class="catalog-list">
            @foreach ($units as $unit)
                @php
                    $fees = $unit->category === 'car'
                        ? collect($unit->car_details['charges'] ?? [])->map(fn ($charge) => $charge['label'].': ₱'.number_format($charge['amount'] ?? 0, 2).(!empty($charge['refundable']) ? ' refundable' : ''))
                        : collect(['parking' => 'Parking', 'pool' => 'Swimming pool'])->map(function ($label, $key) use ($unit) {
                            $details = $unit->property_details[$key] ?? null;
                            if (! $details) return null;
                            return ($details['payment_type'] ?? 'included') === 'separate' ? $label.': ₱'.number_format($details['rate'] ?? 0, 2).' / '.($details['rate_unit'] ?? 'booking') : $label.': Included';
                        })->filter();
                @endphp
                <article>
                    <strong>{{ $unit->name }}</strong>
                    <small>{{ $unit->location ?: 'Location arranged with host' }} · Hosted by {{ $unit->host->name }}</small>
                    @foreach ($fees as $fee)
                        <small>{{ $fee }}</small>
                    @endforeach
                    @if ($unit->rules)
                        <details class="client-unit-rules">
                            <summary>{{ $unit->category === 'car' ? 'Car rules' : ($unit->category === 'condo' ? 'House rules' : 'Service rules') }} <span>Expand</span></summary>
                            <p>{{ $unit->rules }}</p>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>
    </details>
    @endunless

    <section class="client-bookings-card">
        <div class="side-panel-title"><div><span class="eyebrow">Your schedule</span><h2>Upcoming bookings</h2></div><span>{{ $clientBookings->count() }}</span></div>
        <div class="day-booking-list">
            @forelse ($clientBookings as $booking)
                <article class="day-booking-item">
                    <a class="booking-card-main-link" href="{{ route('bookings.show', $booking) }}" aria-label="View booking for {{ $booking->unit->name }}">
                        <span class="booking-time">{{ $booking->start_at->format('M j') }}<small>{{ $booking->start_at->format('g:i A') }} to {{ $booking->end_at->format($booking->end_at->isSameDay($booking->start_at) ? 'g:i A' : 'M j, g:i A') }}</small></span>
                        <div class="booking-details">
                            <strong>{{ $booking->unit->name }}</strong>
                            <small>Hosted by {{ $booking->unit->host->name }} · {{ $booking->party_size }} {{ Str::plural('person', $booking->party_size) }} · ₱{{ number_format($booking->total_amount, 2) }}</small>
                            @if ($booking->notes)<p>{{ $booking->notes }}</p>@endif
                        @if ($booking->unit->category === 'condo' && in_array('wifi', $booking->unit->property_details['amenities'] ?? [], true))
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
                        <span class="booking-card-open">View details →</span>
                    </a>
                    <div class="booking-item-actions">
                        <span class="booking-status status-{{ $booking->status }}">{{ $booking->status === 'confirmed' ? 'Booked' : ucfirst($booking->status) }}</span>
                        @if ($booking->status !== 'cancelled')
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">@csrf @method('PATCH')<button class="danger-action" type="submit">Cancel</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="day-bookings-empty"><span>◇</span><p>Your confirmed and pending trips will appear here.</p></div>
            @endforelse
        </div>
    </section>
</div>
