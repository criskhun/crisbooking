@extends('layouts.app')

@section('title', 'Units & services — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">Host inventory</span><h1>Units & services</h1></div>
                <div class="header-actions"><a class="button button-primary button-small" href="{{ route('units.create') }}">＋ Register listing</a>@include('partials.user-badge')</div>
            </header>

            <section class="listing-section">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @error('unit')<div class="oauth-error account-alert" role="alert">{{ $message }}</div>@enderror

                <div class="listing-summary-bar">
                    <div><span class="eyebrow">All listings</span><strong>{{ $units->count() }}</strong></div>
                    <div><span class="eyebrow">Available now</span><strong>{{ $units->where('is_active', true)->where('active_bookings_count', 0)->count() }}</strong></div>
                    <div><span class="eyebrow">Booked now</span><strong>{{ $units->where('active_bookings_count', '>', 0)->count() }}</strong></div>
                    <p>A listing is shown as booked whenever a pending or confirmed reservation overlaps the current time.</p>
                </div>

                <div class="listing-grid">
                    @forelse ($units as $unit)
                        @php
                            $icons = ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾', 'other' => '◇'];
                            $isBooked = $unit->active_bookings_count > 0;
                        @endphp
                        <article class="listing-card">
                            <div class="listing-photo">
                                @if ($unit->primaryImagePath())
                                    <img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="{{ $unit->name }}">
                                    @if ($unit->images->count() > 1)<small class="photo-count">▧ {{ $unit->images->count() }} photos</small>@endif
                                @else
                                    <span>{{ $icons[$unit->category] ?? '◇' }}</span>
                                @endif
                            </div>
                            <div class="listing-card-top">
                                <span class="listing-icon">{{ $icons[$unit->category] ?? '◇' }}</span>
                                @if (! $unit->is_active)
                                    <span class="availability-badge unavailable">Unavailable</span>
                                @elseif ($isBooked)
                                    <span class="availability-badge booked">Booked now</span>
                                @else
                                    <span class="availability-badge available">Available</span>
                                @endif
                            </div>
                            <span class="listing-kind">{{ ucfirst($unit->kind) }} · {{ str($unit->category)->replace('_', ' ')->title() }}</span>
                            <h2>{{ $unit->name }}</h2>
                            <p>{{ $unit->description ?: 'No description added yet.' }}</p>
                            @if ($unit->category === 'car' && $unit->car_details)
                                <div class="listing-detail-summary"><strong>{{ $unit->car_details['year'] ?? '' }} {{ $unit->car_details['make'] ?? '' }} {{ $unit->car_details['model'] ?? '' }}</strong><small>{{ $unit->car_details['color'] ?? 'Color not specified' }} · {{ ucfirst($unit->car_details['transmission'] ?? '') }} · {{ ucfirst($unit->car_details['fuel_type'] ?? '') }}</small></div>
                                @if (! empty($unit->car_details['accessories']))<div class="detail-chip-list">@foreach ($unit->car_details['accessories'] as $accessory)<span>{{ str($accessory)->replace('_', ' ')->title() }}</span>@endforeach</div>@endif
                                @if (! empty($unit->car_details['custom_accessories']))<div class="detail-chip-list custom">@foreach ($unit->car_details['custom_accessories'] as $accessory)<span>{{ $accessory }}</span>@endforeach</div>@endif
                                @if (! empty($unit->car_details['charges']))<div class="amenity-access-list car-charge-summary">@foreach($unit->car_details['charges'] as $charge)<span><small>{{ $charge['label'] }}</small><strong>₱{{ number_format($charge['amount'], 2) }}{{ !empty($charge['refundable']) ? ' refundable' : '' }}</strong></span>@endforeach</div>@endif
                            @elseif ($unit->category === 'condo' && $unit->property_details)
                                <div class="listing-detail-summary"><strong>{{ ucfirst($unit->property_details['type'] ?? 'Property') }}</strong><small>{{ $unit->property_details['bedrooms'] ?? 0 }} rooms · {{ $unit->property_details['bathrooms'] ?? 0 }} comfort rooms · {{ $unit->property_details['beds'] ?? 0 }} beds</small></div>
                                @if (! empty($unit->property_details['amenities']))<div class="detail-chip-list">@foreach ($unit->property_details['amenities'] as $amenity)<span>{{ str($amenity)->replace('_', ' ')->title() }}</span>@endforeach</div>@endif
                                <div class="amenity-access-list">
                                    @foreach (['parking' => 'Parking', 'pool' => 'Swimming pool'] as $key => $label)
                                        @if ($details = ($unit->property_details[$key] ?? null))
                                            <span><small>{{ $label }}</small><strong>{{ ($details['payment_type'] ?? 'included') === 'separate' ? '₱'.number_format($details['rate'] ?? 0, 2).' / '.($details['rate_unit'] ?? 'booking') : 'Included' }}</strong></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            @if ($unit->category === 'car' && in_array('gps', $unit->car_details['accessories'] ?? [], true) && $unit->gps_details)
                                <details class="private-gps-preview">
                                    <summary>🔒 Host-only GPS access</summary>
                                    <dl>
                                        <div><dt>GPS</dt><dd>{{ $unit->gps_details['device_name'] ?? 'Not specified' }}</dd></div>
                                        <div><dt>Username</dt><dd>{{ $unit->gps_details['username'] ?? 'Not specified' }}</dd></div>
                                        <div><dt>Password</dt><dd>{{ $unit->gps_details['password'] ?? 'Not specified' }}</dd></div>
                                        @if (! empty($unit->gps_details['login_url']))<div><dt>Login</dt><dd><a href="{{ $unit->gps_details['login_url'] }}" target="_blank" rel="noopener noreferrer">Open GPS website</a></dd></div>@endif
                                        @if (! empty($unit->gps_details['notes']))<div><dt>Notes</dt><dd>{{ $unit->gps_details['notes'] }}</dd></div>@endif
                                    </dl>
                                </details>
                            @endif
                            @if ($unit->category === 'condo' && in_array('wifi', $unit->property_details['amenities'] ?? [], true) && $unit->wifi_details)
                                <details class="private-gps-preview private-wifi-preview">
                                    <summary>🔒 Private Wi-Fi access</summary>
                                    <dl>
                                        <div><dt>SSID</dt><dd>{{ $unit->wifi_details['ssid'] ?? 'Not specified' }}</dd></div>
                                        <div><dt>Password</dt><dd>{{ $unit->wifi_details['password'] ?? 'Not specified' }}</dd></div>
                                        @if (! empty($unit->wifi_details['notes']))<div><dt>Notes</dt><dd>{{ $unit->wifi_details['notes'] }}</dd></div>@endif
                                    </dl>
                                    @if ($unit->wifi_qr_path)<img class="host-wifi-qr" src="{{ route('units.wifi-qr', $unit) }}" alt="Wi-Fi QR code for {{ $unit->name }}">@endif
                                </details>
                            @endif
                            @if ($unit->rules)
                                <details class="listing-rules-preview"><summary>{{ $unit->category === 'car' ? 'Car rules' : ($unit->category === 'condo' ? 'House rules' : 'Service rules') }}</summary><p>{{ $unit->rules }}</p></details>
                            @endif
                            <div class="listing-meta">
                                <span><small>Rate</small><strong>{{ $unit->isPackageRental() ? ($unit->hasRentalRates() ? $unit->rates->count().' '.Str::plural('rental package', $unit->rates->count()) : 'Rates need setup') : '₱'.number_format($unit->price, 2).' / '.$unit->pricing_unit }}</strong></span>
                                <span><small>Location</small><strong>{{ $unit->location ?: 'Not specified' }}</strong></span>
                            </div>
                            @if ($unit->isPackageRental())
                                @php
                                    $rateLabels = ['12_hours' => '12 hrs', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'];
                                @endphp
                                @if ($unit->rates->isNotEmpty())
                                    <div class="rental-rate-list">
                                        @foreach ($unit->rates as $rate)<span><small>{{ $rateLabels[$rate->period] }}</small><strong>₱{{ number_format($rate->price, 2) }}</strong></span>@endforeach
                                    </div>
                                @else
                                    <p class="rate-setup-note">Edit this listing to add its four rental prices and photo.</p>
                                @endif
                            @endif
                            <div class="listing-card-actions">
                                <a href="{{ route('calendar.index', ['date' => now()->format('Y-m-d')]) }}">View calendar</a>
                                <a href="{{ route('units.edit', $unit) }}">Edit</a>
                                <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Remove this listing?')">
                                    @csrf @method('DELETE')
                                    <button type="submit">Remove</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="empty-listing-state"><span>＋</span><h2>Register your first listing</h2><p>Add a unit or service so clients can see its availability and book it.</p><a class="button button-primary" href="{{ route('units.create') }}">Register listing</a></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
