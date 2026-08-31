@extends('layouts.app')

@section('title', $booking->unit->name.' booking — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php
        $unit = $booking->unit;
        $isClient = ! $booking->isManualBooking() && auth()->id() === $booking->client_id;
        $galleryImages = $unit->images->values();
        $galleryCount = $galleryImages->count();
        $rateLabels = ['hour' => '1 hour', '12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'];
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
        $reviewPartner = $booking->isManualBooking() ? null : ($isClient ? $unit->host : $booking->client);
        $canManageFinances = auth()->user()->is_admin || $unit->host_id === auth()->id();
        $statusCopy = [
            'pending' => [$isClient ? 'Waiting for host' : 'Action required', $isClient ? 'The host must pre-approve or decline your request.' : 'Review this request and either pre-approve or decline it.'],
            'pre_approved' => ['Pre-approved — payment required', $isClient ? 'Upload your proof of payment so the host can complete confirmation.' : 'The client can now submit proof of payment.'],
            'payment_submitted' => ['Payment proof needs review', $isClient ? 'Your proof was sent. The host must review it before confirmation.' : 'Review the client’s payment proof, then confirm or decline the booking.'],
            'confirmed' => $booking->isManualBooking()
                ? ['Outside booking recorded', $booking->durationDisplayLabel().' are blocked on every availability calendar.']
                : ['Booking confirmed', 'Payment was reviewed and this booking is confirmed. Keep coordinating in chat.'],
            'declined' => ['Booking declined', 'The host declined this request.'],
            'unavailable' => ['Schedule no longer available', 'Another request was confirmed for this schedule, so this pending request was disabled.'],
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
                <div class="booking-detail-status status-{{ $booking->status }}"><span>{{ $booking->status === 'confirmed' ? '✓' : (in_array($booking->status, ['pending', 'pre_approved', 'payment_submitted'], true) ? '◷' : '×') }}</span><div><small>{{ $booking->statusLabel() }} booking #{{ $booking->id }}</small><h2>{{ $statusTitle }}</h2><p>{{ $statusDescription }}</p></div>@if ($booking->inquiry)<a class="button button-primary" href="{{ route('inquiries.show', $booking->inquiry) }}">Open booking chat</a>@endif</div>

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
                            @if($booking->isManualBooking())
                                <div><dt>Time blocked</dt><dd>{{ $booking->durationDisplayLabel() }}</dd></div>
                                <div><dt>Sales source</dt><dd>{{ $booking->sourceDisplayLabel() }}</dd></div>
                                <div><dt>External customer</dt><dd>{{ $booking->customerDisplayName() }}</dd></div>
                                @if($booking->affiliatePartnership)<div><dt>Affiliate</dt><dd>{{ $booking->affiliatePartnership->marketer->name }} · {{ number_format((float) $booking->affiliate_commission_percentage, 2) }}%</dd></div>@endif
                            @endif
                            @if ($booking->rental_coverage)<div><dt>Rental coverage</dt><dd>{{ ['within_city' => 'Within-city use', 'out_of_town' => 'Out-of-town use'][$booking->rental_coverage] ?? str($booking->rental_coverage)->replace('_', ' ')->title() }}</dd></div>@endif
                            @if ($booking->fulfillment_method)<div><dt>Vehicle handover</dt><dd>{{ $booking->fulfillment_method === 'delivery' ? 'Delivery to customer' : 'Customer pickup' }}@if($booking->delivery_address)<small>{{ $booking->delivery_address }}</small>@endif</dd></div>@endif
                            @if ($currentPackages->isNotEmpty())
                                <div><dt>Packages</dt><dd class="package-breakdown-list">@foreach($currentPackages as $period => $package)<span>{{ $package['quantity'] }} × {{ $rateLabels[$period] ?? str($period)->replace('_', ' ')->title() }} <small>₱{{ number_format($package['unit_price'], 2) }} each</small></span>@endforeach</dd></div>
                            @endif
                            @if (! empty($booking->additional_charges))
                                <div><dt>Required charges</dt><dd class="package-breakdown-list">@foreach($booking->additional_charges as $charge)<span>{{ $charge['label'] }} <small>₱{{ number_format($charge['amount'], 2) }}{{ !empty($charge['refundable']) ? ' · refundable' : '' }}</small></span>@endforeach</dd></div>
                            @endif
                            <div><dt>Status</dt><dd><span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></dd></div>
                        </dl>
                        <div class="booking-total"><small>Total booking value</small><strong>₱{{ number_format($booking->total_amount, 2) }}</strong></div>
                        <div class="booking-calendar-actions"><a href="{{ $googleCalendarUrl }}" target="_blank" rel="noopener">Google Calendar</a><a href="{{ route('bookings.calendar', $booking) }}">iPhone / Apple (.ics)</a></div>
                        @if ($booking->inquiry)
                            <a class="button button-primary button-full" href="{{ route('inquiries.show', $booking->inquiry) }}">Go to inquiry chat</a>
                            <small class="booking-chat-note">This opens the conversation for this exact booking.</small>
                        @endif
                        @if ($isClient)<a class="button button-ghost button-full" href="{{ route('profiles.show', $unit->host) }}">View host profile</a>@elseif(! $booking->isManualBooking())<a class="button button-ghost button-full" href="{{ route('profiles.show', $booking->client) }}">View client profile</a>@endif
                        @if(auth()->user()->is_admin)
                            <a class="button button-ghost button-full" href="{{ route('admin.bookings.index', ['search' => $booking->id]) }}">Manage this record</a>
                        @else
                            <a class="button button-ghost button-full" href="{{ route('support.index', ['category' => 'booking_issue', 'unit_id' => $unit->id, 'booking_id' => $booking->id]) }}">Report this booking to admin</a>
                        @endif
                        @if ($booking->isManualBooking() && $booking->status === 'confirmed')
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this outside booking and release its dates?')">@csrf @method('PATCH')<button class="booking-cancel-button" type="submit">Cancel outside booking & release dates</button></form>
                        @elseif ($isClient && in_array($booking->status, ['pending', 'pre_approved', 'payment_submitted', 'confirmed'], true) && $booking->end_at->isFuture())
                            <form method="POST" action="{{ route('bookings.cancel', $booking) }}" onsubmit="return confirm('Cancel this booking?')">@csrf @method('PATCH')<button class="booking-cancel-button" type="submit">Cancel booking</button></form>
                        @elseif (! $isClient && $booking->status === 'pending')
                            <div class="booking-host-actions"><form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="pre_approved"><button class="button button-primary" type="submit">Pre-approve</button></form><form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><button class="booking-cancel-button" type="submit">Decline</button></form></div>
                        @elseif (! $isClient && in_array($booking->status, ['pre_approved', 'payment_submitted'], true))
                            <div class="booking-host-actions">
                                @if ($booking->status === 'payment_submitted')<form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="confirmed"><button class="button button-primary" type="submit">Accept payment & confirm</button></form>@endif
                                <form method="POST" action="{{ route('bookings.status', $booking) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><button class="booking-cancel-button" type="submit">Decline</button></form>
                            </div>
                        @endif
                    </aside>
                </div>

                @if ($canCorrectManualBooking)
                    @php
                        $correctionFields = ['start_at', 'end_at', 'party_size', 'source_channel', 'source_details', 'external_customer_name', 'affiliate_partnership_id', 'package_period', 'package_quantity', 'correction_reason'];
                        $correctionHasErrors = $errors->hasAny($correctionFields);
                        $correctionPackagePeriod = old('package_period', $booking->rate_period && $booking->rate_period !== 'mixed' ? $booking->rate_period : 'day');
                    @endphp
                    <section class="booking-correction-card">
                        <details class="booking-correction-editor" @if($correctionHasErrors) open @endif>
                            <summary>
                                <span><small>Host correction</small><strong>Edit reservation details</strong><em>Dates, pax, source, customer, affiliate, and package</em></span>
                                <b>Open editor</b>
                            </summary>
                            <div class="booking-correction-body">
                                <div class="booking-correction-notice"><span>!</span><p><strong>This changes the reservation record.</strong> Active dates are checked again for conflicts. The booking sale amount stays unchanged, and every edit requires a reason that remains in the audit trail.</p></div>
                                <form method="POST" action="{{ route('bookings.manual-details.update', $booking) }}" class="booking-correction-form" data-manual-correction-form>
                                    @csrf @method('PATCH')
                                    <div class="field-group"><label for="manual_start_at">{{ $unit->category === 'condo' ? 'Check-in date and time' : 'Start date and time' }}</label><input id="manual_start_at" name="start_at" type="datetime-local" value="{{ old('start_at', $booking->start_at->format('Y-m-d\TH:i')) }}" @if($unit->category === 'condo') data-manual-correction-date data-condo-fixed-time="{{ $unit->condoCheckInTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help" data-manual-correction-time-help>Daily packages use the listing check-in time; hourly packages use the exact entered time.</small>@endif @error('start_at')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_end_at">{{ $unit->category === 'condo' ? 'Check-out date and time' : 'End date and time' }}</label><input id="manual_end_at" name="end_at" type="datetime-local" value="{{ old('end_at', $booking->end_at->format('Y-m-d\TH:i')) }}" @if($unit->category === 'condo') data-manual-correction-date data-condo-fixed-time="{{ $unit->condoCheckOutTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help" data-manual-correction-time-help>Daily packages use the listing check-out time; hourly packages use the exact entered time.</small>@endif @error('end_at')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_party_size">Guests / pax</label><input id="manual_party_size" name="party_size" type="number" min="1" @if($unit->capacity) max="{{ $unit->capacity }}" @endif value="{{ old('party_size', $booking->party_size) }}" required>@error('party_size')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_source_channel">Sales source</label><select id="manual_source_channel" name="source_channel" required>@foreach(\App\Models\Booking::MANUAL_SOURCE_OPTIONS as $value => $label)<option value="{{ $value }}" @selected(old('source_channel', $booking->source_channel) === $value)>{{ $label }}</option>@endforeach</select>@error('source_channel')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_source_details">Source reference <span class="optional-label">Optional</span></label><input id="manual_source_details" name="source_details" maxlength="160" value="{{ old('source_details', $booking->source_details) }}" placeholder="Confirmation number or source note">@error('source_details')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_external_customer">External customer or company <span class="optional-label">Optional</span></label><input id="manual_external_customer" name="external_customer_name" maxlength="120" list="booking_customer_suggestions" value="{{ old('external_customer_name', $booking->external_customer_name) }}" autocomplete="off" placeholder="Start typing a repeat customer"><datalist id="booking_customer_suggestions">@foreach($manualBookingCustomerSuggestions as $suggestion)<option value="{{ $suggestion->customer_name }}" label="{{ $suggestion->booking_count }} previous {{ Str::plural('booking', $suggestion->booking_count) }}"></option>@endforeach</datalist>@error('external_customer_name')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="manual_affiliate">Affiliate <span class="optional-label">Optional</span></label><select id="manual_affiliate" name="affiliate_partnership_id"><option value="">No affiliate</option>@foreach($manualBookingPartnerships as $partnership)<option value="{{ $partnership->id }}" @selected((string) old('affiliate_partnership_id', $booking->affiliate_partnership_id) === (string) $partnership->id)>{{ $partnership->marketer->name }} · {{ number_format((float) $partnership->commission_percentage, 2) }}%{{ $partnership->status !== 'accepted' ? ' · current assignment' : '' }}</option>@endforeach</select><small class="field-help">Only accepted affiliates assigned to this listing can be newly selected.</small>@error('affiliate_partnership_id')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    @if($unit->isPackageRental())
                                        <div class="field-group"><label for="manual_package_period">Package</label><select id="manual_package_period" name="package_period" required data-manual-correction-package>@foreach($rateLabels as $value => $label)<option value="{{ $value }}" @selected($correctionPackagePeriod === $value)>{{ $label }}</option>@endforeach</select>@error('package_period')<p class="error-text">{{ $message }}</p>@enderror</div>
                                        <div class="field-group"><label for="manual_package_quantity">Package quantity</label><input id="manual_package_quantity" name="package_quantity" type="number" min="1" max="8760" value="{{ old('package_quantity', max(1, $booking->rate_quantity)) }}" required><small class="field-help">This corrects the package description only; ₱{{ number_format($booking->total_amount, 2) }} remains the recorded sale.</small>@error('package_quantity')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    @endif
                                    <div class="field-group booking-correction-reason"><label for="manual_correction_reason">Reason for correction</label><textarea id="manual_correction_reason" name="correction_reason" rows="3" minlength="5" maxlength="500" required placeholder="Example: Corrected a typing mistake from the original outside booking.">{{ old('correction_reason') }}</textarea><small class="field-help">Required. This reason cannot be removed from the audit trail.</small>@error('correction_reason')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <button class="button button-primary" type="submit">Save correction & audit entry</button>
                                </form>
                            </div>
                        </details>

                        <div class="booking-correction-history">
                            <div><span class="eyebrow">Immutable history</span><h2>Reservation audit trail</h2><p>Who changed the booking, when it changed, what changed, and why.</p></div>
                            @forelse($booking->detailRevisions as $revision)
                                <article class="booking-correction-revision">
                                    <header><strong>{{ $revision->editedBy?->name ?? 'Deleted account' }}</strong><time datetime="{{ $revision->created_at->toIso8601String() }}">{{ $revision->created_at->format('M j, Y · g:i A') }}</time></header>
                                    <p><b>Reason:</b> {{ $revision->reason }}</p>
                                    <dl>@foreach($revision->changedDetails() as $change)<div><dt>{{ $change['label'] }}</dt><dd><span>{{ $change['before'] }}</span><b>→</b><strong>{{ $change['after'] }}</strong></dd></div>@endforeach</dl>
                                </article>
                            @empty
                                <div class="booking-correction-empty"><span>✓</span><p><strong>No reservation corrections yet.</strong> The original outside-booking details are still in use.</p></div>
                            @endforelse
                        </div>
                    </section>
                @endif

                @if ($booking->isManualBooking())
                    @php
                        $extensionHasErrors = $errors->hasAny(['duration_unit', 'duration_quantity', 'additional_amount', 'payment_status']);
                    @endphp
                    <section class="booking-extension-card">
                        <div class="booking-finance-heading"><div><span class="eyebrow">Stay or service continuation</span><h2>Booking extensions</h2><p>Extend the occupied time and record the extra earning as paid now or still collectible.</p></div>@if($booking->extensions->isNotEmpty())<span class="booking-status status-confirmed">{{ $booking->extensions->count() }} {{ Str::plural('extension', $booking->extensions->count()) }}</span>@endif</div>

                        @if($canManageFinances && $booking->status === 'confirmed')
                            <details class="booking-extension-editor" @if($extensionHasErrors) open @endif>
                                <summary><span><strong>Add an extension</strong><small>Current end: {{ $booking->end_at->format('M j, Y · g:i A') }}</small></span><b>Extend booking</b></summary>
                                <form method="POST" action="{{ route('bookings.extensions.store', $booking) }}" class="booking-extension-form" data-booking-extension-form data-current-end="{{ $booking->end_at->format('Y-m-d\TH:i:s') }}">
                                    @csrf
                                    <div class="field-group"><label for="extension_duration_unit">Extend by</label><select id="extension_duration_unit" name="duration_unit" required data-extension-unit><option value="day" @selected(old('duration_unit', 'day') === 'day')>Day</option><option value="hour" @selected(old('duration_unit') === 'hour')>Hour</option></select>@error('duration_unit')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="extension_duration_quantity" data-extension-quantity-label>Number of days</label><input id="extension_duration_quantity" name="duration_quantity" type="number" min="1" max="365" value="{{ old('duration_quantity', 1) }}" required data-extension-quantity>@error('duration_quantity')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="extension_additional_amount">Additional earning</label><div class="money-input"><span>₱</span><input id="extension_additional_amount" name="additional_amount" type="text" inputmode="decimal" value="{{ filled(old('additional_amount')) ? number_format((float) old('additional_amount'), 2, '.', ',') : '' }}" required data-accounting-input></div>@error('additional_amount')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="field-group"><label for="extension_payment_status">Payment status</label><select id="extension_payment_status" name="payment_status" required><option value="paid" @selected(old('payment_status') === 'paid')>Paid now</option><option value="collectible" @selected(old('payment_status', 'collectible') === 'collectible')>Add to collectibles</option></select>@error('payment_status')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <div class="booking-extension-preview" data-extension-preview aria-live="polite"></div>
                                    <div class="field-group booking-extension-notes"><label for="extension_notes">Reference or note <span class="optional-label">Optional</span></label><input id="extension_notes" name="notes" maxlength="500" value="{{ old('notes') }}" placeholder="Customer request, payment reference, or agreement">@error('notes')<p class="error-text">{{ $message }}</p>@enderror</div>
                                    <button class="button button-primary" type="submit">Save extension</button>
                                </form>
                            </details>
                        @endif

                        <div class="booking-extension-history">
                            @forelse($booking->extensions as $extension)
                                <article><span class="booking-extension-icon" aria-hidden="true">→</span><div><strong>{{ $extension->durationLabel() }} extension</strong><small>{{ $extension->previous_end_at->format('M j, g:i A') }} → {{ $extension->new_end_at->format('M j, Y · g:i A') }}</small>@if($extension->notes)<p>{{ $extension->notes }}</p>@endif<span>Added {{ $extension->created_at->format('M j, Y · g:i A') }}{{ $extension->createdBy ? ' by '.$extension->createdBy->name : '' }}</span></div><div class="booking-extension-value"><strong>₱{{ number_format((float) $extension->additional_amount, 2) }}</strong><span class="booking-status {{ $extension->payment_status === 'paid' ? 'status-confirmed' : 'status-payment_submitted' }}">{{ $extension->paymentStatusLabel() }}</span></div></article>
                            @empty
                                <p>No extensions have been added to this booking.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="booking-finance-card">
                        <div class="booking-finance-heading"><div><span class="eyebrow">Payments & collections</span><h2>Booking financial ledger</h2><p>Track rental payments separately from refundable deposits, damage fees, and residence penalties.</p></div><span class="booking-status {{ $booking->outstandingBalance() > 0 ? 'status-payment_submitted' : 'status-confirmed' }}">{{ $booking->paymentStatusLabel() }}</span></div>
                        <div class="booking-finance-metrics">
                            <div><small>Booking and charges</small><strong>₱{{ number_format($booking->revenueAmount(), 2) }}</strong></div>
                            <div><small>Payments collected</small><strong>₱{{ number_format($booking->paymentTotal(), 2) }}</strong></div>
                            <div class="{{ $booking->outstandingBalance() > 0 ? 'attention' : '' }}"><small>Outstanding balance</small><strong>₱{{ number_format($booking->outstandingBalance(), 2) }}</strong></div>
                            <div><small>Security deposit held</small><strong>₱{{ number_format($booking->securityDepositHeld(), 2) }}</strong><span>Required: ₱{{ number_format($booking->securityDepositRequired(), 2) }}</span></div>
                        </div>
                        <div class="booking-finance-ledger">
                            @forelse($booking->financialEntries as $entry)
                                @include('bookings._financial-entry')
                            @empty
                                <p>No payments, deposits, or charges have been recorded.</p>
                            @endforelse
                        </div>
                        @if($canManageFinances)
                            <div class="booking-finance-forms">
                                <form method="POST" action="{{ route('bookings.financial-entries.store', $booking) }}">@csrf<input type="hidden" name="kind" value="payment"><input type="hidden" name="category" value="balance_payment"><strong>Record a collection</strong><label><span>Amount</span><div class="money-input"><span>₱</span><input name="amount" type="text" inputmode="decimal" required data-accounting-input></div></label><label><span>Reference or note</span><input name="notes" maxlength="500" placeholder="Cash, transfer reference…"></label><button class="button button-primary button-small" type="submit">Add payment</button></form>
                                <form method="POST" action="{{ route('bookings.financial-entries.store', $booking) }}">@csrf<input type="hidden" name="kind" value="charge"><strong>Add damage or penalty</strong><label><span>Type</span><select name="category" required><option value="damage">Damage fee</option>@if($unit->category === 'condo')<option value="late_checkout">Late check-out</option><option value="smoking">Smoking penalty</option><option value="excessive_cleaning">Garbage / excessive cleaning</option>@endif<option value="other_penalty">Other charge</option></select></label><label><span>Amount</span><div class="money-input"><span>₱</span><input name="amount" type="text" inputmode="decimal" required data-accounting-input></div></label><label><span>Details</span><input name="notes" maxlength="500" required placeholder="Describe the damage or violation"></label><button class="button button-primary button-small" type="submit">Add charge</button></form>
                                <form method="POST" action="{{ route('bookings.financial-entries.store', $booking) }}">@csrf<strong>Security deposit</strong><label><span>Action</span><select name="kind" required><option value="deposit">Record deposit collected</option><option value="deposit_refund">Return deposit</option><option value="deposit_application">Apply deposit to charges</option></select></label><label><span>Amount</span><div class="money-input"><span>₱</span><input name="amount" type="text" inputmode="decimal" required data-accounting-input></div></label><label><span>Note</span><input name="notes" maxlength="500" placeholder="Return method or damage reference"></label><button class="button button-primary button-small" type="submit">Record deposit action</button></form>
                            </div>
                        @endif
                    </section>
                @endif

                @if ($canManageExpenses)
                    <section class="booking-finance-card booking-expense-card">
                        <div class="booking-finance-heading"><div><span class="eyebrow">Private operating costs</span><h2>Booking expenses & assigned services</h2><p>Record the costs used to fulfill this booking. Assigned platform providers receive the job in their Service work account.</p></div><span class="booking-status status-confirmed">Net ₱{{ number_format($booking->netRevenueAmount(), 2) }}</span></div>
                        <div class="booking-finance-metrics booking-expense-metrics">
                            <div><small>Booking revenue</small><strong>₱{{ number_format($booking->revenueAmount(), 2) }}</strong></div>
                            <div><small>Total operating expenses</small><strong>₱{{ number_format($booking->expenseTotal(), 2) }}</strong></div>
                            <div><small>Provider payments pending</small><strong>₱{{ number_format($booking->expenses->where('status', 'completed')->sum('amount'), 2) }}</strong></div>
                            <div><small>Net after expenses</small><strong>₱{{ number_format($booking->netRevenueAmount(), 2) }}</strong></div>
                        </div>
                        <div class="booking-expense-list">
                            @forelse($booking->expenses as $expense)
                                <article class="booking-expense-entry status-{{ $expense->status }}">
                                    <div><small>{{ $expense->categoryLabel() }}</small><strong>{{ $expense->serviceUnit?->name ?? $expense->provider?->name ?? $expense->vendor_name ?? 'Direct expense' }}</strong><span>{{ $expense->provider?->name ? 'Provider: '.$expense->provider->name : 'Recorded by '.$expense->recordedBy?->name }}@if($expense->scheduled_at) · {{ $expense->scheduled_at->format('M j, Y · g:i A') }}@endif</span>@if($expense->notes)<p>{{ $expense->notes }}</p>@endif
                                        @if($expense->completion_images)<div class="private-file-links"><small>Completion evidence:</small>@foreach($expense->completion_images as $imageIndex => $image)<a href="{{ route('service-work.completion-images.show', [$expense, $imageIndex]) }}" target="_blank">Image {{ $imageIndex + 1 }}</a>@endforeach</div>@endif
                                        @if($expense->payment_proof_path)<div class="private-file-links"><small>Payment:</small><a href="{{ route('bookings.expenses.payment-proof', [$booking, $expense]) }}" target="_blank">Open {{ $expense->payment_proof_name ?: 'proof of payment' }}</a></div>@endif
                                    </div>
                                    <div class="booking-expense-amount"><b>₱{{ number_format($expense->amount, 2) }}</b><span class="booking-status status-{{ $expense->status }}">{{ $expense->statusLabel() }}</span></div>
                                    @if($expense->provider_user_id && $expense->status === 'completed')
                                        <form method="POST" action="{{ route('bookings.expenses.status', [$booking, $expense]) }}" enctype="multipart/form-data" class="expense-payment-form">@csrf @method('PATCH')<input type="hidden" name="status" value="paid"><label><span>Proof of payment</span><input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>@error('payment_proof')<p class="error-text">{{ $message }}</p>@enderror<button class="button button-primary button-small" type="submit">Mark paid</button></form>
                                    @elseif(! in_array($expense->status, ['paid', 'payment_received'], true))
                                        <form method="POST" action="{{ route('bookings.expenses.status', [$booking, $expense]) }}">@csrf @method('PATCH')<select name="status" aria-label="Update {{ $expense->categoryLabel() }} status">@if($expense->provider_user_id)<option value="assigned" @selected($expense->status === 'assigned')>Assigned</option>@else<option value="recorded" @selected($expense->status === 'recorded')>Recorded</option><option value="completed" @selected($expense->status === 'completed')>Completed</option>@endif<option value="cancelled" @selected($expense->status === 'cancelled')>Cancelled</option></select><button class="button button-ghost button-small" type="submit">Update</button></form>
                                    @endif
                                </article>
                            @empty
                                <p>No operating expenses have been recorded for this booking.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('bookings.expenses.store', $booking) }}" class="booking-expense-form booking-expense-batch-form" data-expense-batch-form>
                            @csrf
                            <div class="booking-expense-form-heading"><strong>Select every expense that applies</strong><small>You can record Cleaning, Laundry, Water, and other services together in one submission.</small></div>
                            @error('expenses')<p class="error-text booking-expense-form-error">{{ $message }}</p>@enderror
                            <div class="booking-expense-choice-grid">
                                @foreach($expenseCategories as $value => $label)
                                    @php
                                        $expenseEnabled = (bool) old('expenses.'.$value.'.enabled', false);
                                        $matchingProviders = $providerApplications->filter(
                                            fn ($application) => collect(\App\Models\BookingExpense::compatibleProviderServices($value))
                                                ->intersect($application->services)
                                                ->isNotEmpty()
                                        );
                                    @endphp
                                    <article class="booking-expense-choice" data-expense-choice>
                                        <label class="booking-expense-choice-toggle"><input type="hidden" name="expenses[{{ $value }}][enabled]" value="0"><input type="checkbox" name="expenses[{{ $value }}][enabled]" value="1" @checked($expenseEnabled) data-expense-choice-toggle><span><strong>{{ $label }}</strong><small>Include this expense</small></span></label>
                                        <div class="booking-expense-choice-fields" data-expense-choice-fields @unless($expenseEnabled) hidden @endunless>
                                            <div class="field-group"><label for="expense_amount_{{ $value }}">Amount</label><div class="money-input"><span>₱</span><input id="expense_amount_{{ $value }}" name="expenses[{{ $value }}][amount]" type="text" inputmode="decimal" value="{{ old('expenses.'.$value.'.amount') }}" data-expense-required data-accounting-input></div>@error('expenses.'.$value.'.amount')<p class="error-text">{{ $message }}</p>@enderror</div>
                                            <div class="field-group"><label for="expense_provider_{{ $value }}">Approved provider <span class="optional-label">Optional</span></label><select id="expense_provider_{{ $value }}" name="expenses[{{ $value }}][provider_application_id]" data-expense-provider-select><option value="">No assigned account</option>@foreach($matchingProviders as $providerApplication)<option value="{{ $providerApplication->id }}" @selected((int) old('expenses.'.$value.'.provider_application_id') === $providerApplication->id)>{{ $providerApplication->applicant->name }}</option>@endforeach</select><small class="field-help">Selecting an approved provider removes the outside-vendor field.</small></div>
                                            <div class="field-group" data-expense-vendor-field><label for="expense_vendor_{{ $value }}">Outside vendor <span class="optional-label">Optional</span></label><input id="expense_vendor_{{ $value }}" name="expenses[{{ $value }}][vendor_name]" maxlength="120" value="{{ old('expenses.'.$value.'.vendor_name') }}" placeholder="Name if not a platform provider"></div>
                                            <div class="field-group"><label for="expense_schedule_{{ $value }}">Schedule <span class="optional-label">Optional</span></label><input id="expense_schedule_{{ $value }}" name="expenses[{{ $value }}][scheduled_at]" type="datetime-local" value="{{ old('expenses.'.$value.'.scheduled_at') }}"></div>
                                            <div class="field-group booking-expense-choice-note"><label for="expense_notes_{{ $value }}">Details <span class="optional-label">Optional</span></label><input id="expense_notes_{{ $value }}" name="expenses[{{ $value }}][notes]" maxlength="500" value="{{ old('expenses.'.$value.'.notes') }}" placeholder="Quantity, instructions, receipt reference…"></div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                            <button class="button button-primary" type="submit">Record all selected expenses</button>
                        </form>
                    </section>
                @endif

                @if ($isClient && in_array($booking->status, ['pre_approved', 'payment_submitted'], true))
                    <section class="booking-change-card payment-proof-card">
                        <div class="booking-change-heading"><span>₱</span><div><small>Pre-approval payment step</small><h2>{{ $booking->payment_proof_path ? 'Payment proof submitted' : 'Send proof of payment' }}</h2><p>The booking remains unconfirmed until the host reviews your proof.</p></div></div>
                        @if ($booking->payment_proof_path)<p><a class="button button-ghost" href="{{ route('bookings.payment-proof.show', $booking) }}" target="_blank">View submitted proof</a></p>@endif
                        <form method="POST" action="{{ route('bookings.payment-proof.store', $booking) }}" enctype="multipart/form-data" class="booking-change-form">
                            @csrf
                            <div class="field-group"><label for="payment_proof">{{ $booking->payment_proof_path ? 'Replace payment proof' : 'Payment receipt or transfer proof' }}</label><input id="payment_proof" name="payment_proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required><small class="field-help">JPG, PNG, WebP, or PDF up to 5 MB.</small>@error('payment_proof')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <button class="button button-primary" type="submit">{{ $booking->payment_proof_path ? 'Replace proof' : 'Submit proof' }}</button>
                        </form>
                    </section>
                @elseif ($booking->payment_proof_path)
                    <section class="booking-change-card payment-proof-card">
                        <div class="booking-change-heading"><span>₱</span><div><small>Private payment document</small><h2>{{ $isClient ? 'Submitted payment proof' : 'Review payment proof' }}</h2><p>{{ $isClient ? 'This is the proof attached to your booking.' : 'Open the client’s proof before making the final confirmation decision.' }}</p></div></div>
                        <a class="button button-primary" href="{{ route('bookings.payment-proof.show', $booking) }}" target="_blank">Open {{ $booking->payment_proof_name ?: 'payment proof' }}</a>
                    </section>
                @endif

                @if (! $booking->isManualBooking() && $booking->status === 'confirmed' && $booking->end_at->isPast())
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

                @if ($isClient && in_array($booking->status, ['pending', 'pre_approved', 'payment_submitted', 'confirmed'], true) && $booking->end_at->isFuture() && ! $booking->hasPendingChangeRequest())
                    <section class="booking-change-card">
                        <div class="booking-change-heading"><span>↻</span><div><small>Changes require host approval</small><h2>Request new dates or pax</h2><p>Your current reservation remains unchanged while the host reviews availability.</p></div></div>
                        <form method="POST" action="{{ route('bookings.change-request', $booking) }}" class="booking-change-form">
                            @csrf @method('PATCH')
                            @if ($unit->isPackageRental())<input type="hidden" name="change_duration_pricing" value="1">@endif
                            <div class="field-group"><label for="change_start_at">New {{ $unit->category === 'condo' ? 'check-in date' : 'start' }}</label><input id="change_start_at" name="change_start_at" type="datetime-local" min="{{ now()->addMinute()->startOfMinute()->format('Y-m-d\TH:i') }}" value="{{ old('change_start_at', $booking->start_at->format('Y-m-d\TH:i')) }}" @if($unit->category === 'condo') data-fixed-booking-time="{{ $unit->condoCheckInTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help">Host-set check-in: {{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckInTime())->format('g:i A') }}</small>@endif @error('change_start_at')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="change_end_at">New {{ $unit->category === 'condo' ? 'check-out date' : 'end or return' }}</label><input id="change_end_at" name="change_end_at" type="datetime-local" min="{{ old('change_start_at', $booking->start_at->format('Y-m-d\TH:i')) }}" value="{{ old('change_end_at', $booking->end_at->format('Y-m-d\TH:i')) }}" @if($unit->category === 'condo') data-fixed-booking-time="{{ $unit->condoCheckOutTime() }}" @endif required>@if($unit->category === 'condo')<small class="field-help">Host-set check-out: {{ \Carbon\Carbon::createFromFormat('H:i', $unit->condoCheckOutTime())->format('g:i A') }}</small>@endif @error('change_end_at')<p class="error-text">{{ $message }}</p>@enderror</div>
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
