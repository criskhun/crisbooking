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
                    <span aria-hidden="true" data-favorite-icon>{{ $isFavorited ? '♥' : '♡' }}</span>
                </button>
            </form>
        @else
            <a class="listing-favorite-control {{ $favoriteClass ?? '' }}" href="{{ route('verification.notice') }}" aria-label="Verify your email to save this listing" title="Verify your email to save this listing"><span aria-hidden="true">♡</span></a>
        @endif
    @else
        <a class="listing-favorite-control {{ $favoriteClass ?? '' }}" href="{{ route('login') }}" aria-label="Log in to save this listing" title="Log in to save this listing"><span aria-hidden="true">♡</span></a>
    @endauth
@endif
