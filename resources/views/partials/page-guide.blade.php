@php
    $guideTitle = 'Davao Rent Zone quick guide';
    $guideSteps = [
        ['selector' => '[data-global-listing-search]', 'title' => 'Search anything', 'copy' => 'Find listings by details such as “5 seater car,” “2 BR condo,” a host name, or a business name.'],
        ['selector' => '[data-notification-center]', 'title' => 'See what needs attention', 'copy' => 'Open notifications for inquiries, chats, booking requests, and other updates.'],
        ['selector' => '[data-mobile-sidebar]', 'title' => 'Move around your workspace', 'copy' => 'Use the workspace menu to open bookings, inquiries, listings, clients, sales, and profile settings.'],
    ];

    if (request()->routeIs('profile.edit')) {
        $guideTitle = 'Profile and photo guide';
        $guideSteps = [
            ['selector' => '[data-guide-feature="profile-photos"]', 'title' => 'Manage profile photos', 'copy' => 'Upload a new photo, reuse any saved photo, or delete old ones. The active photo appears throughout the system.'],
            ['selector' => '[data-verification-form]', 'title' => 'Complete verification', 'copy' => 'Keep your identity and contact information accurate, then save at the bottom of the form.'],
        ];
    } elseif (request()->routeIs('calendar.*') && request('mode') !== 'manage') {
        $guideTitle = 'Book Now guide';
        $guideSteps = [
            ['selector' => '.booking-category-grid', 'title' => 'Choose a category', 'copy' => 'Start with a car, stay, or service category.'],
            ['selector' => '[data-guide-feature="booking-map"]', 'title' => 'Explore the map', 'copy' => 'Click a map pin to see the host, business, photo, price, and listing links.'],
            ['selector' => '#booking-details', 'title' => 'Add dates and needs', 'copy' => 'Choose dates, people, location, radius, and category-specific filters.'],
            ['selector' => '#booking-results', 'title' => 'Select and book', 'copy' => 'Open a result, review its rates and rules, then begin an inquiry or request the booking.'],
        ];
    } elseif (request()->routeIs('inquiries.*')) {
        $guideTitle = 'Inquiry guide';
        $guideSteps = [
            ['selector' => '[data-realtime-chat], [data-live-inquiry-list]', 'title' => 'Live inquiry updates', 'copy' => 'Unread counts and conversation details update automatically while this page is open.'],
            ['selector' => '.standard-inquiry-pricing', 'title' => 'Review standard rates', 'copy' => 'The listing price appears first. Open negotiation only when either party wants another price.'],
        ];
    } elseif (request()->routeIs('units.create', 'units.edit')) {
        $guideTitle = 'Listing setup guide';
        $guideSteps = [
            ['selector' => '.listing-gallery-field', 'title' => 'Add listing photos', 'copy' => 'Upload multiple photos and select the primary photo shown first.'],
            ['selector' => '[data-listing-location-map]', 'title' => 'Pin the location', 'copy' => 'Search an address, use your position, or click the map.'],
            ['selector' => '[data-category-section]', 'title' => 'Complete category details', 'copy' => 'Rates, rules, accessories, rooms, amenities, and private access details change with the selected category.'],
        ];
    }
@endphp
<div class="page-guide" data-page-guide>
    <button class="page-guide-button" type="button" data-page-guide-open aria-label="Open page guide">i</button>
    <dialog class="page-guide-dialog" data-page-guide-dialog aria-labelledby="page-guide-title">
        <div class="page-guide-dialog-heading"><span>i</span><div><small>Information & demo</small><h2 id="page-guide-title">{{ $guideTitle }}</h2></div><button type="button" data-page-guide-close aria-label="Close guide">×</button></div>
        <p>Use the short walkthrough below whenever you are unsure what to do.</p>
        <ol>@foreach($guideSteps as $step)<li><button type="button" data-guide-focus="{{ $step['selector'] }}"><span>{{ $loop->iteration }}</span><strong>{{ $step['title'] }}</strong><small>{{ $step['copy'] }}</small></button></li>@endforeach</ol>
        <footer><button class="button button-primary" type="button" data-guide-start>Start guided demo</button><button class="button button-ghost" type="button" data-page-guide-close>Close</button></footer>
        <script type="application/json" data-guide-steps>@json($guideSteps)</script>
    </dialog>
    <aside class="guide-demo-hint" data-guide-demo-hint hidden><small data-guide-demo-count></small><strong data-guide-demo-title></strong><p data-guide-demo-copy></p><div><button type="button" data-guide-demo-stop>Stop</button><button type="button" data-guide-demo-next>Next →</button></div></aside>
</div>
