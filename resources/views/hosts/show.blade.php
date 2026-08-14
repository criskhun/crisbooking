@extends('layouts.app')

@section('title', $businessName.' — Host listings')

@section('content')
    <header class="public-listing-header host-storefront-header">
        <a class="brand" href="{{ route('home') }}"><span>DRZ</span><strong>Davao Rent Zone</strong></a>
        <nav><a href="{{ route('home') }}">Browse rentals</a><details class="public-info-guide"><summary aria-label="How to use this host page">i</summary><div><strong>How to use this page</strong><p>Review the host or business, compare their active listings, then choose <b>View & book</b> for rates, rules, photos, and inquiry options.</p></div></details>@auth<a class="button button-primary button-small" href="{{ route('calendar.index', ['mode' => 'book']) }}">Book now</a>@else<a class="button button-primary button-small" href="{{ route('login') }}">Log in</a>@endauth</nav>
    </header>

    <main class="host-storefront-shell">
        <section class="host-storefront-hero" data-guide-feature="host-storefront">
            @include('partials.avatar', ['avatarUser' => $hostUser, 'avatarClass' => 'host-storefront-avatar', 'avatarAlt' => $businessName])
            <div><span class="eyebrow">Verified Davao Rent Zone host</span><h1>{{ $businessName }}</h1>@if($businessName !== $hostUser->name)<p>Hosted by {{ $hostUser->name }}</p>@endif<p>{{ $hostUser->bio ?: 'Browse this host’s active rentals and services.' }}</p></div>
            <dl><div><dt>Active listings</dt><dd>{{ $hostUser->units->count() }}</dd></div><div><dt>Host rating</dt><dd>{{ $hostRating ? '★ '.number_format($hostRating, 1) : 'New host' }}</dd></div><div><dt>Location</dt><dd>{{ $hostUser->city ?: 'Davao Region' }}</dd></div></dl>
        </section>

        <section class="host-storefront-listings">
            <div class="host-storefront-heading"><div><span class="eyebrow">Available from this host</span><h2>Choose a listing to view and book</h2></div><a href="{{ route('calendar.index', ['mode' => 'book']) }}">View all hosts →</a></div>
            <div class="host-listing-grid">
                @forelse($hostUser->units as $unit)
                    @php($startingPrice = $unit->isPackageRental() ? $unit->rates->min('price') : $unit->price)
                    <article>
                        <a class="host-listing-photo" href="{{ route('listings.show', $unit) }}">@if($unit->primaryImagePath())<img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="{{ $unit->name }}">@else<span>{{ ['car' => '🚗', 'condo' => '🏢', 'cleaning' => '🧹', 'driving' => '🛞', 'massage' => '💆', 'consultancy' => '💬'][$unit->category] ?? '◇' }}</span>@endif</a>
                        <div><small>{{ str($unit->category)->replace('_', ' ')->title() }} · {{ $unit->location ?: 'Location arranged' }}</small><h3>{{ $unit->name }}</h3><p>{{ Str::limit($unit->description ?: 'Open the listing for full details, rates, and rules.', 110) }}</p><footer><span>From <strong>₱{{ number_format($startingPrice, 2) }}</strong></span><a class="button button-primary" href="{{ route('listings.show', $unit) }}">View & book</a></footer></div>
                    </article>
                @empty
                    <div class="overview-empty"><strong>No active listings right now.</strong><p>Please check again later.</p></div>
                @endforelse
            </div>
        </section>
    </main>
@endsection
