@php
    $favoriteUnit = $favoriteUnit ?? $unit ?? $listing;
    $isFavorited = (bool) ($favoriteUnit->is_favorited ?? false);
    $favoriteLabel = $isFavorited ? 'Remove from favorites' : 'Save to favorites';
@endphp

@if(! auth()->check() || auth()->id() !== $favoriteUnit->host_id)
    @auth
        @if(auth()->user()->hasVerifiedEmail())
            <form class="listing-favorite-control {{ $favoriteClass ?? '' }}" method="POST" action="{{ route('listings.favorite', $favoriteUnit) }}" data-favorite-form>
                @csrf
                <button type="submit" @class(['is-favorited' => $isFavorited]) aria-label="{{ $favoriteLabel }}" aria-pressed="{{ $isFavorited ? 'true' : 'false' }}" title="{{ $favoriteLabel }}">
                    <x-fa-icon name="heart" data-favorite-icon @class(['is-favorited' => $isFavorited]) />
                </button>
            </form>
        @else
            <a class="listing-favorite-control {{ $favoriteClass ?? '' }}" href="{{ route('listings.favorite.after-login', $favoriteUnit) }}" aria-label="Verify your email to save this listing" title="Verify your email to save this listing"><x-fa-icon name="heart" /></a>
        @endif
    @else
        <a class="listing-favorite-control {{ $favoriteClass ?? '' }}" href="{{ route('listings.favorite.after-login', $favoriteUnit) }}" aria-label="Log in to save this listing" title="Log in to save this listing"><x-fa-icon name="heart" /></a>
    @endauth
@endif
