<div class="global-listing-search" data-global-listing-search data-search-url="{{ route('listing-search.index') }}">
    <button class="global-search-toggle" type="button" data-global-search-toggle aria-expanded="false" aria-label="Search listings, hosts, and businesses"><x-fa-icon name="magnifying-glass" /><span>Search</span></button>
    <section class="global-search-panel" data-global-search-panel hidden>
        <label for="global_listing_search"><span class="sr-only">Search listings, hosts, or businesses</span><input id="global_listing_search" type="search" autocomplete="off" placeholder="Try “5 seater car” or “2 BR condo”" data-global-search-input></label>
        <p class="global-search-help">Search by listing, vehicle capacity, bedrooms, location, host, or business name.</p>
        <div class="global-search-results" data-global-search-results></div>
        <p class="global-search-empty" data-global-search-empty>Type at least 2 characters to begin.</p>
    </section>
</div>
