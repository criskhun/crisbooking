@extends('layouts.app')

@section('title', $booking->unit->name.' booking — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php
        $unit = $booking->unit;
        $isClient = auth()->id() === $booking->client_id;
        $galleryImages = $unit->images->values();
        $galleryCount = $galleryImages->count();
        $rateLabels = ['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'];
        $currentPackages = collect($booking->package_breakdown ?: ($booking->rate_period && $booking->rate_period !== 'mixed' ? [
            $booking->rate_period => ['quantity' => $booking->rate_quantity, 'unit_price' => $booking->packageUnitPrice(), 'subtotal' => (float) $booking->total_amount],
        ] : []));
        $requestedPackages = collect($booking->change_package_breakdown ?: ($booking->rate_period && $booking->rate_period !== 'mixed' && $booking->change_start_at && $booking->change_end_at ? [
            $booking->rate_period => ['quantity' => $booking->packageQuantityFor($booking->change_start_at, $booking->change_end_at), 'unit_price' => $booking->packageUnitPrice(), 'subtotal' => $booking->packageTotalFor($booking->change_start_at, $booking->change_end_at)],
        ] : []));
        $currentPackageSummary = $currentPackages->map(fn ($package, $period) => $package['quantity'].' × '.($rateLabels[$period] ?? str($period)->replace('_', ' ')->title()))->implode(' + ');
        $requestedPackageSummary = $requestedPackages->map(fn ($package, $period) => $package['quantity'].' × '.($rateLabels[$period] ?? str($period)->replace('_', ' ')->title()))->implode(' + ');
        $requestedPackageTotal = $requestedPackages->sum('subtotal');
        $myBookingReview = $booking->reviews->firstWhere('reviewer_id', auth()->id());
        $reviewPartner = $isClient ? $unit->host : $booking->client;
        $statusCopy = [
            'pending' => ['Waiting for host', 'Your request and inquiry are ready for the host to review.'],
            'confirmed' => ['Booking approved', 'Your booking is confirmed. Keep coordinating in chat.'],
            'cancelled' => ['Booking cancelled', 'This booking is no longer active.'],
        ];
        [$statusTitle, $statusDescription] = $statusCopy[$booking->status] ?? [ucfirst($booking->status), 'Review the booking information below.'];
    @endphp
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ route('calendar.index', $isClient ? ['mode' => 'book'] : ['mode' => 'manage']) }}">← Back to {{ $isClient ? 'my bookings' : 'calendar' }}</a><h1>Booking details</h1></div>@include('partials.user-badge')</header>

            <section class="booking-detail-shell">
                @if (session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif
                <div class="booking-detail-status status-{{ $booking->status }}"><span>{{ $booking->status === 'confirmed' ? '✓' : ($booking->status === 'pending' ? '◷' : '×') }}</span><div><small>{{ ucfirst($booking->status) }} booking #{{ $booking->id }}</small><h2>{{ $statusTitle }}</h2><p>{{ $statusDescription }}</p></div>@if ($booking->inquiry)<a class="button button-primary" href="{{ route('inquiries.show', $booking->inquiry) }}">Open booking chat</a>@endif</div>

                <div class="booking-detail-layout">
                    <div class="booking-detail-main">
                        <section class="booking-unit-card">
                            <div class="booking-unit-gallery">
                                @if ($galleryImages->isNotEmpty())
                                    @foreach ($galleryImages->take(3) as $index => $image)
                                        <button class="booking-gallery-trigger" type="button" data-booking-gallery-open data-image-index="{{ $index }}" aria-label="Open photo {{ $index + 1 }} of {{ $galleryCount }} for {{ $unit->name }}">
                                            <img src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $unit->name }} photo {{ $index + 1 }}">
                                            <span class="booking-gallery-label">{{ $index === min(2, $galleryCount - 1) && $galleryCount > 1 ? 'View all '.$galleryCount.' photos' : 'View photo '.($index + 1) }}</span>
                                        </button>
                                    @endforeach
                                @elseif ($unit->primaryImagePath())
                                    <button class="booking-gallery-trigger" type="button" data-booking-gallery-open data-image-index="0" aria-label="Open photo for {{ $unit->name }}">
                                        <img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="{{ $unit->name }} photo">
                                        <span class="booking-gallery-label">View photo</span>
                                    </button>
                                @else
                                    <span>{{ ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'][$unit->category] ?? '◇' }}</span>
                                @endif
                            </div>
                            <div class="booking-unit-copy"><span class="eyebrow">{{ str($unit->category)->replace('_', ' ')->title() }}</span><h2>{{ $unit->name }}</h2><p class="booking-unit-location">⌖ {{ $unit->location ?: 'Location arranged with host' }}</p><p>{{ $unit->description ?: 'No additional listing description was provided.' }}</p></div>
                            <div class="booking-unit-facts">
                                <span><small>Host</small><strong>{{ $unit->host->name }}</strong></span>
                                <span><small>Capacity</small><strong>{{ $unit->capacity ? 'Up to '.$unit->capacity : 'Ask host' }}</strong></span>
                                <span><small>Type</small><strong>{{ ucfirst($unit->kind) }}</strong></span>
                            </div>
                            @if ($unit->category === 'car' && $unit->car_details)
                                <div class="booking-feature-list"><strong>{{ $unit->car_details['year'] ?? '' }} {{ $unit->car_details['make'] ?? '' }} {{ $unit->car_details['model'] ?? '' }}</strong><span>{{ $unit->car_details['color'] ?? 'Color not specified' }} · {{ ucfirst($unit->car_details['transmission'] ?? '') }} · {{ ucfirst($unit->car_details['fuel_type'] ?? '') }}</span>@foreach ($unit->car_details['accessories'] ?? [] as $accessory)<em>{{ str($accessory)->replace('_', ' ')->title() }}</em>@endforeach @foreach($unit->car_details['custom_accessories'] ?? [] as $accessory)<em>{{ $accessory }}</em>@endforeach</div>
                            @elseif ($unit->category === 'condo' && $unit->property_details)
                                <div class="booking-feature-list"><strong>{{ ucfirst($unit->property_details['type'] ?? 'Property') }}</strong><span>{{ $unit->property_details['bedrooms'] ?? 0 }} rooms · {{ $unit->property_details['bathrooms'] ?? 0 }} comfort rooms · {{ $unit->property_details['beds'] ?? 0 }} beds</span>@foreach ($unit->property_details['amenities'] ?? [] as $amenity)<em>{{ str($amenity)->replace('_', ' ')->title() }}</em>@endforeach</div>
                            @endif
                            <details class="client-unit-rules booking-detail-rules"><summary>{{ $unit->category === 'car' ? 'Car rules' : ($unit->category === 'condo' ? 'House rules' : 'Service rules') }} <span>Expand</span></summary><p>{{ $unit->rules ?: 'No additional rules were provided.' }}</p></details>
                        </section>

                        @if ($booking->notes)<section class="booking-notes-card"><span class="eyebrow">Booking notes</span><p>{{ $booking->notes }}</p></section>@endif
                    </div>

                    <aside class="booking-summary-card">
                        <span class="eyebrow">Your reservation</span><h2>{{ $booking->start_at->format('M j, Y') }}</h2>
                        <dl>
                            <div><dt>Starts</dt><dd>{{ $booking->start_at->format('M j, Y · g:i A') }}</dd></div>
                            <div><dt>Ends</dt><dd>{{ $booking->end_at->format('M j, Y · g:i A') }}</dd></div>
                            <div><dt>Guests / pax</dt><dd>{{ $booking->party_size }} {{ Str::plural('person', $booking->party_size) }}</dd></div>
                            @if ($booking->rental_coverage)<div><dt>Rental coverage</dt><dd>{{ ['within_city' => 'Within-city use', 'out_of_town' => 'Out-of-town use'][$booking->rental_coverage] ?? str($booking->rental_coverage)->replace('_', ' ')->title() }}</dd></div>@endif
                            @if ($currentPackages->isNotEmpty())
                                <div><dt>Packages</dt><dd class="package-breakdown-list">@foreach($currentPackages as $period => $package)<span>{{ $package['quantity'] }} × {{ $rateLabels[$period] ?? str($period)->replace('_', ' ')->title() }} <small>₱{{ number_format($package['unit_price'], 2) }} each</small></span>@endforeach</dd></div>
                            @endif
                            @if (! empty($booking->additional_charges))
                                <div><dt>Required charges</dt><dd class="package-breakdown-list">@foreach($booking->additional_charges as $charge)<span>{{ $charge['label'] }} <small>₱{{ number_format($charge['amount'], 2) }}{{ !empty($charge['refundable']) ? ' · refundable' : '' }}</small></span>@endforeach</dd></div>
                            @endif
                            <div><dt>Status</dt><dd><span class="booking-status status-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span></dd></div>
                        </dl>
                        <div class="booking-total"><small>Total booking value</small><strong>₱{{ number_format($booking->total_amount, 2) }}</strong></div>
                        <div class="booking-calendar-actions"><a href="{{ $googleCalendarUrl }}" target="_blank" rel="noopener">Google Calendar</a><a href="{{ route('bookings.calendar', $booking) }}">iPhone / Apple (.ics)</a></div>
                        @if ($booking->inquiry)
                            <a class="button button-primary button-full" href="{{ route('inquiries.show', $booking->inquiry) }}">Go to inquiry chat</a>
                            <small class="booking-chat-note">This opens the conversation for this exact booking.</small>
                        @endif
                        @if ($isClient)<a class="button button-ghost button-full" href="{{ route('profiles.show', $unit->host) }}">View host profile</a>@else<a class="button button-ghost button-full" href="{{ route('profiles.show', $booking->client) }}">View client profile</a>@endif
                        @if ($isClient && $booking->status !== 'cancelled' && $booking->end_at->isFuture())
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">@csrf @method('PATCH')<button class="booking-cancel-button" type="submit">Cancel booking</button></form>
                        @elseif (! $isClient && $booking->status === 'pending')
                            <div class="booking-host-actions"><form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="button button-primary" type="submit">Approve</button></form><form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="cancelled"><button class="booking-cancel-button" type="submit">Decline</button></form></div>
                        @endif
                    </aside>
                </div>

                @if ($booking->status === 'confirmed' && $booking->end_at->isPast())
                    <section class="booking-review-card">
                        <div class="booking-change-heading"><span>★</span><div><small>Completed booking</small><h2>Review {{ $reviewPartner->name }}</h2><p>Your rating and comment will appear on their {{ $isClient ? 'host' : 'client' }} profile.</p></div></div>
                        @if ($myBookingReview)
                            <div class="review-submitted"><strong>{{ str_repeat('★', $myBookingReview->rating) }}{{ str_repeat('☆', 5 - $myBookingReview->rating) }}</strong><p>{{ $myBookingReview->comment }}</p><small>Review published {{ $myBookingReview->created_at->format('M j, Y') }}</small></div>
                        @else
                            <form method="POST" action="{{ route('bookings.reviews.store', $booking) }}" class="review-form">@csrf<fieldset><legend>Your rating</legend><div class="review-star-options">@foreach(range(5, 1) as $star)<input id="booking-rating-{{ $star }}" name="rating" type="radio" value="{{ $star }}" required><label for="booking-rating-{{ $star }}" title="{{ $star }} stars">★</label>@endforeach</div></fieldset><div class="field-group"><label for="review_comment">Public comment</label><textarea id="review_comment" name="comment" rows="4" minlength="10" maxlength="1500" required placeholder="Describe your experience with this booking partner…">{{ old('comment') }}</textarea>@error('comment')<p class="error-text">{{ $message }}</p>@enderror</div><button class="button button-primary" type="submit">Publish review</button></form>
                        @endif
                    </section>
                @endif

                @if ($booking->change_request_status)
                    <section class="booking-change-state status-{{ $booking->change_request_status }}">
                        <span>{{ $booking->change_request_status === 'pending' ? '◷' : ($booking->change_request_status === 'approved' ? '✓' : '×') }}</span>
                        <div>
                            <small>Schedule change request</small>
                            <h2>{{ match ($booking->change_request_status) { 'pending' => 'Waiting for host approval', 'approved' => 'Change approved', default => 'Change declined' } }}</h2>
                            @if ($booking->change_request_status === 'pending')
                                <p>The existing booking stays active until the host approves this request.</p>
                            @elseif ($booking->change_request_status === 'approved')
                                <p>The booking now uses the approved dates and number of guests shown above.</p>
                            @else
                                <p>The existing dates and number of guests were kept. You may submit another request.</p>
                            @endif
                        </div>
                        @if ($booking->change_start_at && $booking->change_end_at)
                            <dl>
                                <div><dt>Requested dates</dt><dd>{{ $booking->change_start_at->format('M j, Y · g:i A') }} – {{ $booking->change_end_at->format('M j, Y · g:i A') }}</dd></div>
                                <div><dt>Requested pax</dt><dd>{{ $booking->change_party_size }} {{ Str::plural('person', $booking->change_party_size) }}</dd></div>
                                @if ($requestedPackages->isNotEmpty())<div><dt>New packages</dt><dd>{{ $requestedPackageSummary }} = ₱{{ number_format($requestedPackageTotal, 2) }}</dd></div>@endif
                                @if ($booking->change_request_note)<div><dt>Client note</dt><dd>{{ $booking->change_request_note }}</dd></div>@endif
                            </dl>
                        @endif
                    </section>
                @endif

                @if ($isClient && in_array($booking->status, ['pending', 'confirmed'], true) && $booking->end_at->isFuture() && ! $booking->hasPendingChangeRequest())
                    <section class="booking-change-card">
                        <div class="booking-change-heading"><span>↻</span><div><small>Changes require host approval</small><h2>Request new dates or pax</h2><p>Your current reservation remains unchanged while the host reviews availability.</p></div></div>
                        <form method="POST" action="{{ route('bookings.change-request', $booking) }}" class="booking-change-form">
                            @csrf @method('PATCH')
                            @if ($unit->isPackageRental())<input type="hidden" name="change_duration_pricing" value="1">@endif
                            <div class="field-group"><label for="change_start_at">New start</label><input id="change_start_at" name="change_start_at" type="datetime-local" min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}" value="{{ old('change_start_at', $booking->start_at->format('Y-m-d\TH:i')) }}" required>@error('change_start_at')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="change_end_at">New end or return</label><input id="change_end_at" name="change_end_at" type="datetime-local" min="{{ old('change_start_at', $booking->start_at->format('Y-m-d\TH:i')) }}" value="{{ old('change_end_at', $booking->end_at->format('Y-m-d\TH:i')) }}" required>@error('change_end_at')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="change_party_size">New number of pax</label><input id="change_party_size" name="change_party_size" type="number" min="1" @if($unit->capacity) max="{{ $unit->capacity }}" @endif value="{{ old('change_party_size', $booking->party_size) }}" required>@error('change_party_size')<p class="error-text">{{ $message }}</p>@enderror</div>
                            @if ($unit->isPackageRental())
                                <div class="booking-package-builder booking-change-package-builder" data-package-builder data-duration-driven="1" data-start-id="change_start_at" data-end-id="change_end_at">
                                    <div class="package-builder-heading"><div><span class="eyebrow">Rates for the new dates</span><h3>Matching rental package</h3></div><small>Dates determine the available rate combination.</small></div>
                                    <div class="package-quantity-grid">@foreach($unit->rates->where('coverage', $unit->category === 'car' ? ($booking->rental_coverage ?: $booking->rate?->coverage) : 'standard') as $rate)<label class="package-quantity-card" data-package-card><span><strong>{{ $rateLabels[$rate->period] }}</strong><small>₱{{ number_format($rate->price, 2) }} each</small></span><input type="number" name="change_package_quantities[{{ $rate->period }}]" min="0" max="365" value="0" data-package-quantity data-period="{{ $rate->period }}" data-price="{{ $rate->price }}" aria-label="Number of {{ $rateLabels[$rate->period] }} packages" readonly></label>@endforeach</div>
                                    <div class="package-calculation-summary"><span><small>Selected return</small><strong data-package-end-note></strong></span><span><small>Estimated package total</small><strong data-package-total></strong></span></div>
                                    @error('change_package_quantities')<p class="error-text">{{ $message }}</p>@enderror
                                </div>
                            @endif
                            <div class="field-group booking-change-note"><label for="change_request_note">Reason or note <span class="optional-label">Optional</span></label><textarea id="change_request_note" name="change_request_note" rows="3" maxlength="1000" placeholder="Tell the host why you need this change…">{{ old('change_request_note') }}</textarea>@error('change_request_note')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <button class="button button-primary" type="submit">Send change request</button>
                        </form>
                    </section>
                @elseif (! $isClient && $booking->hasPendingChangeRequest())
                    <section class="booking-change-card host-review-card">
                        <div class="booking-change-heading"><span>!</span><div><small>Host approval needed</small><h2>Review requested booking changes</h2><p>Availability was checked when the client submitted this request and will be checked again when you approve it.</p></div></div>
                        <div class="booking-change-comparison">
                            <div><small>Current booking</small><strong>{{ $booking->start_at->format('M j, g:i A') }} – {{ $booking->end_at->format('M j, g:i A') }}</strong><span>{{ $booking->party_size }} {{ Str::plural('person', $booking->party_size) }}</span>@if($currentPackages->isNotEmpty())<span>{{ $currentPackageSummary }} · ₱{{ number_format($booking->total_amount, 2) }}</span>@endif</div>
                            <span>→</span>
                            <div><small>Requested booking</small><strong>{{ $booking->change_start_at->format('M j, g:i A') }} – {{ $booking->change_end_at->format('M j, g:i A') }}</strong><span>{{ $booking->change_party_size }} {{ Str::plural('person', $booking->change_party_size) }}</span>@if($requestedPackages->isNotEmpty())<span>{{ $requestedPackageSummary }} · ₱{{ number_format($requestedPackageTotal, 2) }}</span>@endif</div>
                        </div>
                        <div class="booking-change-actions">
                            <form method="POST" action="{{ route('bookings.change-request.review', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button class="button button-primary" type="submit">Approve changes</button></form>
                            <form method="POST" action="{{ route('bookings.change-request.review', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="decline"><button class="booking-decline-change" type="submit">Decline changes</button></form>
                        </div>
                    </section>
                @endif
            </section>

            @if ($galleryImages->isNotEmpty() || $unit->primaryImagePath())
                @php
                    $viewerImages = $galleryImages->isNotEmpty()
                        ? $galleryImages->map(fn ($image) => ['src' => Storage::disk('public')->url($image->path), 'alt' => $unit->name.' photo'])
                        : collect([['src' => Storage::disk('public')->url($unit->primaryImagePath()), 'alt' => $unit->name.' photo']]);
                @endphp
                <dialog class="booking-photo-viewer" data-booking-gallery-dialog aria-labelledby="booking-gallery-title">
                    <div class="booking-photo-viewer-panel">
                        <header>
                            <div><span class="eyebrow">Booking photos</span><h2 id="booking-gallery-title">{{ $unit->name }}</h2></div>
                            <div class="booking-photo-viewer-actions"><span data-booking-gallery-count>1 / {{ $viewerImages->count() }}</span><button type="button" data-booking-gallery-close aria-label="Close photo viewer">×</button></div>
                        </header>
                        <div class="booking-photo-stage">
                            @if ($viewerImages->count() > 1)<button type="button" class="booking-photo-nav previous" data-booking-gallery-previous aria-label="Previous photo">‹</button>@endif
                            <img src="{{ $viewerImages->first()['src'] }}" alt="{{ $viewerImages->first()['alt'] }} 1" data-booking-gallery-image>
                            @if ($viewerImages->count() > 1)<button type="button" class="booking-photo-nav next" data-booking-gallery-next aria-label="Next photo">›</button>@endif
                        </div>
                        @if ($viewerImages->count() > 1)
                            <div class="booking-photo-thumbnails" aria-label="Choose a photo">
                                @foreach ($viewerImages as $index => $image)
                                    <button type="button" data-booking-gallery-thumbnail data-image-index="{{ $index }}" data-image-src="{{ $image['src'] }}" data-image-alt="{{ $image['alt'] }} {{ $index + 1 }}" aria-label="Show photo {{ $index + 1 }}" @class(['active' => $index === 0])><img src="{{ $image['src'] }}" alt=""></button>
                                @endforeach
                            </div>
                        @else
                            <button type="button" data-booking-gallery-thumbnail data-image-index="0" data-image-src="{{ $viewerImages->first()['src'] }}" data-image-alt="{{ $viewerImages->first()['alt'] }} 1" hidden></button>
                        @endif
                    </div>
                </dialog>
            @endif
        </main>
    </div>
@endsection
