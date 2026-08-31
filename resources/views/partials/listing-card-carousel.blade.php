@php
    $carouselUnit = $carouselUnit ?? $unit ?? $listing;
    $carouselLinkUrl = $carouselLinkUrl ?? route('listings.show', $carouselUnit);
    $carouselLinkClass = trim($carouselLinkClass ?? 'listing-card-carousel-link');
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
            <span class="listing-card-carousel-placeholder" aria-hidden="true"><x-category-icon :category="$carouselUnit->category" /></span>
        @endforelse
    </a>

    @if($carouselCount > 1)
        <button class="listing-card-carousel-nav previous icon-only-button" type="button" data-listing-carousel-previous aria-label="Previous photo of {{ $carouselUnit->name }}"><x-fa-icon name="chevron-left" /></button>
        <button class="listing-card-carousel-nav next icon-only-button" type="button" data-listing-carousel-next aria-label="Next photo of {{ $carouselUnit->name }}"><x-fa-icon name="chevron-right" /></button>
        <div class="listing-card-carousel-dots" role="group" aria-label="Choose a photo of {{ $carouselUnit->name }}">
            @foreach($carouselImages as $index => $imagePath)
                <button
                    type="button"
                    @class(['is-active' => $index === 0])
                    data-listing-carousel-dot
                    data-carousel-index="{{ $index }}"
                    aria-label="Show photo {{ $index + 1 }} of {{ $carouselCount }} for {{ $carouselUnit->name }}"
                    aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"
                ></button>
            @endforeach
        </div>
        <span class="sr-only" role="status" aria-live="polite" data-listing-carousel-status>Photo 1 of {{ $carouselCount }}</span>
    @endif
</div>
