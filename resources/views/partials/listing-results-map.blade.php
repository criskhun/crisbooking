<section class="listing-results-map-panel booking-map-explorer" aria-label="{{ $mapAriaLabel ?? 'Available listings map' }}" data-listing-map-panel data-overview-nearby-map data-default-radius-km="500" data-map-id="{{ config('services.google.maps_map_id') }}" hidden>
    <div class="listing-map-heading">
        <div>
            <span class="eyebrow">Map view</span>
            <h3>{{ $mapTitle ?? 'Compare hosts by lowest price' }}</h3>
            <p>Each marker shows the host and the lowest available price for that listing.</p>
        </div>
        <button class="map-action-button" type="button" data-map-use-location>Show near me</button>
    </div>
    <div class="google-map-canvas booking-discovery-map" data-map-canvas aria-label="{{ $mapAriaLabel ?? 'Map of bookable listings and their lowest prices' }}"></div>
    @unless(config('services.google.maps_api_key'))<div class="map-setup-note"><strong>Google Map preview is not configured yet</strong><span>Add <code>GOOGLE_MAPS_API_KEY</code>. Grid view and listing cards remain available.</span></div>@endunless
    <small class="map-status" data-map-status aria-live="polite"></small>
    <script type="application/json" data-map-units>@json($mapUnits)</script>
</section>
