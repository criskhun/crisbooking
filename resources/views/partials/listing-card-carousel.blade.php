@php
    $carouselUnit = $carouselUnit ?? $unit ?? $listing;
    $carouselLinkUrl = $carouselLinkUrl ?? route('listings.show', $carouselUnit);
    $carouselLinkClass = trim($carouselLinkClass ?? 'listing-card-carousel-link');
    $carouselPlaceholder = $carouselPlaceholder ?? '◇';
    $carouselImages = $carouselUnit->images
        ->pluck('path')
        ->push($carouselUnit->photo_path)
        ->filter()
        ->unique()
        ->values();
    $carouselCount = $carouselImages->count();
@endphp

<div class="listing-card-carousel" data-listing-carousel data-carousel-index="0">
    <a class="{{ $carouselLinkClass }}" href="{{ $carouselLinkUrl }}" aria-label="View {{ $carouselUnit->name }}" data-listing-carousel-link>
        @forelse($carouselImages as $index => $imagePath)
            <img
                src="{{ Storage::disk('public')->url($imagePath) }}"
                alt="{{ $carouselUnit->name }} photo {{ $index + 1 }}"
                @class(['listing-card-carousel-slide', 'is-active' => $index === 0])
                data-listing-carousel-slide
                aria-hidden="{{ $index === 0 ? 'false' : 'true' }}"
                @if($index > 0) loading="lazy" @endif
            >
        @empty
            <span class="listing-card-carousel-placeholder" aria-hidden="true">{{ $carouselPlaceholder }}</span>
        @endforelse
    </a>

    @if($carouselCount > 1)
        <button class="listing-card-carousel-nav previous" type="button" data-listing-carousel-previous aria-label="Previous photo of {{ $carouselUnit->name }}">‹</button>
        <button class="listing-card-carousel-nav next" type="button" data-listing-carousel-next aria-label="Next photo of {{ $carouselUnit->name }}">›</button>
        <div class="listing-card-carousel-dots" aria-hidden="true">
            @foreach($carouselImages as $index => $imagePath)<span @class(['is-active' => $index === 0]) data-listing-carousel-dot></span>@endforeach
        </div>
        <span class="sr-only" role="status" aria-live="polite" data-listing-carousel-status>Photo 1 of {{ $carouselCount }}</span>
    @endif
</div>
