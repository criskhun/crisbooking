@php
    $guideTitle = $branding->site_name.' workspace guide';
    $guideIntroduction = 'Follow the highlighted controls to learn this page. You can stop at any time and reopen the guide whenever you need it.';
    $guideSteps = [];

    if (request()->routeIs('dashboard')) {
        $guideTitle = auth()->user()->isClient() ? 'Client dashboard guide' : 'Rental dashboard guide';
        $guideSteps = auth()->user()->isClient() ? [
            ['selector' => '.client-market-hero', 'title' => 'Start a new booking', 'copy' => 'Open the booking workspace to compare verified rentals, dates, prices, and category-specific options.'],
            ['selector' => '[data-overview-nearby-map]', 'title' => 'Explore what is nearby', 'copy' => 'Use your location to see nearby map-pinned listings, then open a listing for its full details.'],
            ['selector' => '.client-trip-section', 'title' => 'Track your upcoming plans', 'copy' => 'Open any upcoming booking to review its schedule, payment details, host conversation, and available actions.'],
        ] : [
            ['selector' => '.host-control-hero', 'title' => 'Use your rental control center', 'copy' => 'Add a listing, open live availability, or book another host directly from these primary actions.'],
            ['selector' => '.host-stat-grid', 'title' => 'Read today’s business status', 'copy' => 'Review current rentals, upcoming work, confirmed income, and requests that still need approval.'],
            ['selector' => '.host-unit-grid', 'title' => 'Check every listing quickly', 'copy' => 'See which units are available, occupied, or unpublished and jump directly to their availability.'],
            ['selector' => '.host-booking-list', 'title' => 'Open the upcoming schedule', 'copy' => 'Select a booking to manage its customer, dates, status, charges, and communication.'],
        ];
    } elseif (request()->routeIs('profile.edit')) {
        $guideTitle = 'Profile and photo guide';
        $guideSteps = [
            ['selector' => '[data-guide-feature="profile-photos"]', 'title' => 'Manage profile photos', 'copy' => 'Upload a new photo, reuse any saved photo, or delete old ones. The active photo appears throughout the system.'],
            ['selector' => '[data-verification-form]', 'title' => 'Complete and save verification', 'copy' => 'Keep identity, contact, address, emergency, and ID information accurate, then save the form at the bottom.'],
        ];
    } elseif (request()->routeIs('calendar.*') && request('mode') !== 'manage') {
        $guideTitle = 'Book Now guide';
        $guideSteps = [
            ['selector' => '.booking-category-grid', 'title' => 'Choose a category', 'copy' => 'Start with a car, stay, or service category.'],
            ['selector' => '[data-guide-feature="booking-map"]', 'title' => 'Explore the map', 'copy' => 'Click a map pin to see the host, business, photo, price, and listing links.'],
            ['selector' => '#booking-details', 'title' => 'Add dates and needs', 'copy' => 'Choose dates, people, location, radius, and category-specific filters.'],
            ['selector' => '#booking-results', 'title' => 'Select and book', 'copy' => 'Open a result, review its rates and rules, then begin an inquiry or request the booking.'],
        ];
    } elseif (request()->routeIs('calendar.*')) {
        $guideTitle = 'Availability calendar guide';
        $guideSteps = [
            ['selector' => '#manual-booking', 'title' => 'Record an outside booking', 'copy' => 'Add reservations received by phone, walk-in, social media, or another source so every schedule stays accurate.'],
            ['selector' => '.calendar-filter-bar', 'title' => 'Focus the calendar', 'copy' => 'Filter the schedule by category or listing and switch between the month and listing timeline views.'],
            ['selector' => '.booking-calendar-grid, .listing-timeline', 'title' => 'Review live availability', 'copy' => 'Choose a date or booking to inspect availability, customer details, duration, source, and status.'],
            ['selector' => '.booking-workspace', 'title' => 'Manage booking requests', 'copy' => 'Review the selected day and open requests to approve, update, or continue managing each booking.'],
        ];
    } elseif (request()->routeIs('inquiries.index')) {
        $guideTitle = 'Inquiry inbox guide';
        $guideSteps = [
            ['selector' => '[data-live-inquiry-list]', 'title' => 'Open active conversations', 'copy' => 'Unread activity and status changes update automatically. Select any row to continue the conversation.'],
            ['selector' => '.inquiry-list-heading', 'title' => 'Understand your inbox', 'copy' => 'Use the totals and statuses to identify conversations that need your reply or another action.'],
        ];
    } elseif (request()->routeIs('inquiries.show')) {
        $guideTitle = 'Inquiry conversation guide';
        $guideSteps = [
            ['selector' => '[data-realtime-chat]', 'title' => 'Chat in real time', 'copy' => 'Send messages, emojis, or allowed attachments. New messages and typing activity appear while this page is open.'],
            ['selector' => '.standard-inquiry-pricing', 'title' => 'Review standard rates', 'copy' => 'The listing price appears first. Open negotiation only when either party wants another price.'],
            ['selector' => '.price-negotiation-panel', 'title' => 'Manage price proposals', 'copy' => 'Send, accept, reject, or review negotiated pricing before the booking is finalized.'],
            ['selector' => '.inquiry-booking-state', 'title' => 'Continue to the booking', 'copy' => 'When the inquiry is ready, request or open the booking and continue its status and payment workflow.'],
        ];
    } elseif (request()->routeIs('units.create', 'units.edit')) {
        $guideTitle = 'Listing setup guide';
        $guideSteps = [
            ['selector' => '.listing-gallery-field', 'title' => 'Add listing photos', 'copy' => 'Upload multiple photos and select the primary photo shown first.'],
            ['selector' => '[data-listing-location-map]', 'title' => 'Pin the location', 'copy' => 'Search an address, use your position, or click the map.'],
            ['selector' => '[data-category-section]', 'title' => 'Complete category details', 'copy' => 'Rates, rules, accessories, rooms, amenities, and private access details change with the selected category.'],
        ];
    } elseif (request()->routeIs('units.index')) {
        $guideTitle = 'Listings and services guide';
        $guideSteps = [
            ['selector' => '.listing-summary-bar', 'title' => 'Review your listing status', 'copy' => 'See published, unavailable, and currently booked totals before managing individual listings.'],
            ['selector' => '.listing-grid', 'title' => 'Manage each listing', 'copy' => 'Review photos, details, rates, private access information, availability, and management actions in one card.'],
            ['selector' => '.listing-card-actions', 'title' => 'Edit or change availability', 'copy' => 'Use the actions on a listing to update its setup, publish status, schedule, or removal.'],
        ];
    } elseif (request()->routeIs('favorites.index')) {
        $guideTitle = 'Favorites guide';
        $guideSteps = [
            ['selector' => '[data-favorites-page]', 'title' => 'Compare saved listings', 'copy' => 'Review every saved rental together, open its details, or continue directly to booking.'],
            ['selector' => '.favorite-listing-grid', 'title' => 'Keep favorites organized', 'copy' => 'Use the heart on a saved card to remove it; the page count updates immediately.'],
        ];
    } elseif (request()->routeIs('bookings.show')) {
        $guideTitle = 'Booking management guide';
        $guideSteps = [
            ['selector' => '.booking-unit-card', 'title' => 'Review the booked listing', 'copy' => 'Confirm the photos, location, package details, included features, and rules attached to this reservation.'],
            ['selector' => '.booking-summary-card', 'title' => 'Check schedule and totals', 'copy' => 'Review dates, status, pricing breakdown, calendar options, and links to the inquiry conversation.'],
            ['selector' => '.booking-host-actions, .booking-correction-card, .booking-extension-card', 'title' => 'Use the available booking actions', 'copy' => 'Actions change with your role and booking status, including approval, correction, extension, payment, expense, and cancellation tools.'],
            ['selector' => '.booking-finance-card, .booking-expense-card, .booking-payment-card', 'title' => 'Keep financial records complete', 'copy' => 'Record and verify charges, payments, proofs, expenses, and balances so the booking history remains accurate.'],
        ];
    } elseif (request()->routeIs('affiliates.index')) {
        $guideTitle = 'Affiliate workspace guide';
        $guideSteps = [
            ['selector' => '.affiliate-hero', 'title' => 'Understand the partnership process', 'copy' => 'See how hosts and marketers work together before reviewing invitations or available hosts.'],
            ['selector' => '.affiliate-partnership-grid', 'title' => 'Manage current partnerships', 'copy' => 'Open a partnership to review its status, assigned listings, commission, links, sales, and messages.'],
            ['selector' => '.affiliate-host-grid', 'title' => 'Start a partnership', 'copy' => 'Choose an eligible host and send an application with the requested details.'],
        ];
    } elseif (request()->routeIs('affiliates.show')) {
        $guideTitle = 'Affiliate partnership guide';
        $guideSteps = [
            ['selector' => '.affiliate-application-card', 'title' => 'Review and configure the partnership', 'copy' => 'Check its status, commission, assigned listings, application message, and available approval actions.'],
            ['selector' => '.affiliate-link-list', 'title' => 'Share tracked listing links', 'copy' => 'Copy the referral link for each assigned listing so resulting bookings can be attributed correctly.'],
            ['selector' => '.affiliate-sales-list', 'title' => 'Track affiliate sales', 'copy' => 'Review referred bookings, their status, and commission results.'],
            ['selector' => '.affiliate-chat-card', 'title' => 'Coordinate with your partner', 'copy' => 'Keep partnership decisions and updates together in the built-in conversation.'],
        ];
    } elseif (request()->routeIs('service-work.index')) {
        $guideTitle = 'Service provider workspace guide';
        $guideSteps = [
            ['selector' => '.host-service-overview, .service-work-metrics', 'title' => 'Review service activity', 'copy' => 'See requests, assigned work, completion status, and earnings that need attention.'],
            ['selector' => '.host-service-requests-panel, .service-work-list', 'title' => 'Manage service work', 'copy' => 'Open requests or assignments to review scope, evidence, payment, status, and next actions.'],
            ['selector' => '.service-application-panel, .service-host-list', 'title' => 'Build provider relationships', 'copy' => 'Review applications or apply directly to eligible hosts for the service categories you offer.'],
        ];
    } elseif (request()->routeIs('workspace.clients')) {
        $guideTitle = 'Client workspace guide';
        $guideSteps = [
            ['selector' => '.workspace-hero', 'title' => 'Understand your client list', 'copy' => 'This workspace contains customers with successful booking records, not unconfirmed inquiries.'],
            ['selector' => '.workspace-client-grid', 'title' => 'Review each client relationship', 'copy' => 'Open a client profile or their latest booking to review history and continue service.'],
        ];
    } elseif (request()->routeIs('accounting.index')) {
        $guideTitle = 'Accounting ledger guide';
        $guideSteps = [
            ['selector' => '.accounting-summary-grid', 'title' => 'Read your cash position', 'copy' => 'Compare tracked balances, money received, money paid, and net cash flow for the active filters.'],
            ['selector' => '.financial-accounts-panel', 'title' => 'Build your chart of accounts', 'copy' => 'Register accounts under Assets, Revenue, Expenses, Liabilities, or Equity. Only active accounts can be selected for new transactions.'],
            ['selector' => '.accounting-filter-form', 'title' => 'Focus the ledger', 'copy' => 'Choose an account category first, then narrow by account, month, or money direction.'],
            ['selector' => '.accounting-ledger-table-wrap', 'title' => 'Audit and assign every transaction', 'copy' => 'Open the source record for each movement and assign historical unassigned entries to the correct account.'],
        ];
    } elseif (request()->routeIs('sales.index')) {
        $guideTitle = 'Sales and profitability guide';
        $guideSteps = [
            ['selector' => '.sales-report-filter', 'title' => 'Choose the reporting period', 'copy' => 'Filter dates to compare revenue, operating profit, collected payments, and upcoming obligations.'],
            ['selector' => '.sales-profit-metrics', 'title' => 'Read your key financial totals', 'copy' => 'Use these headline values to understand performance and money that still needs action.'],
            ['selector' => '.sales-chart-grid', 'title' => 'Explore business trends', 'copy' => 'Select interactive chart values to inspect the bookings behind each month, category, source, or status.'],
            ['selector' => '.unit-finance-workspace', 'title' => 'Maintain listing finances', 'copy' => 'Configure ownership and commission, then record costs, obligations, and payments for each unit.'],
        ];
    } elseif (request()->routeIs('support.index')) {
        $guideTitle = 'Support and reporting guide';
        $guideSteps = [
            ['selector' => '.support-report-form', 'title' => 'Send a clear report', 'copy' => 'Choose a category and priority, add the related page or booking, and explain what happened before submitting.'],
            ['selector' => '.support-history-card', 'title' => 'Follow admin responses', 'copy' => 'Review the status and administrator response for every report you have submitted.'],
        ];
    } elseif (request()->routeIs('host-applications.show')) {
        $guideTitle = 'Host application guide';
        $guideSteps = [
            ['selector' => '.application-status-card, .application-intro-card', 'title' => 'Check what your application needs', 'copy' => 'Read the current status and any administrator note before completing or resubmitting information.'],
            ['selector' => '.identity-selfie-grid', 'title' => 'Complete identity photos', 'copy' => 'Use the camera guides for a clear face photo and a photo of you holding your ID.'],
            ['selector' => '[data-host-application-form]', 'title' => 'Complete and submit the application', 'copy' => 'Choose the account type, provide business details when required, confirm the checklist, and submit for review.'],
            ['selector' => '.application-timeline-card', 'title' => 'Follow application history', 'copy' => 'Use the timeline to understand submissions, reviews, requested changes, and the final decision.'],
        ];
    } elseif (request()->routeIs('profiles.show')) {
        $guideTitle = 'Booking partner profile guide';
        $guideSteps = [
            ['selector' => '.public-profile-hero', 'title' => 'Verify the booking partner', 'copy' => 'Review the person’s role, identity status, contact details, and relationship to your booking.'],
            ['selector' => '.profile-reviews-section', 'title' => 'Review booking history feedback', 'copy' => 'Use ratings and completed-booking reviews to understand prior experiences.'],
        ];
    } elseif (request()->routeIs('accounts.index')) {
        $guideTitle = 'User administration guide';
        $guideSteps = [
            ['selector' => '.accounts-summary', 'title' => 'Review account totals', 'copy' => 'See active, suspended, and administrator totals before working with individual accounts.'],
            ['selector' => '.accounts-table-wrap', 'title' => 'Manage user access', 'copy' => 'Review identity and sign-in details, then open an account to change its role or access settings.'],
        ];
    } elseif (request()->routeIs('accounts.edit')) {
        $guideTitle = 'Account access guide';
        $guideSteps = [
            ['selector' => '.edit-account-heading', 'title' => 'Confirm the selected account', 'copy' => 'Verify the person and current role before changing permissions.'],
            ['selector' => '.role-selector', 'title' => 'Choose the correct role', 'copy' => 'The selected role controls the workspace and actions available to this account.'],
            ['selector' => '.management-options', 'title' => 'Control account access', 'copy' => 'Grant administrator access or suspend the account, then save the changes.'],
        ];
    } elseif (request()->routeIs('admin.settings.edit')) {
        $guideTitle = 'System branding guide';
        $guideSteps = [
            ['selector' => '.settings-form-stack', 'title' => 'Update public branding', 'copy' => 'Manage the site name, tagline, contact details, logo, favicon, colors, and footer information.'],
            ['selector' => '.settings-preview-panel', 'title' => 'Review changes live', 'copy' => 'Use the preview to check text, logo, and colors before saving them for the whole system.'],
            ['selector' => '.settings-save', 'title' => 'Publish system settings', 'copy' => 'Save only after the preview looks correct; the changes apply throughout the site.'],
        ];
    } elseif (request()->routeIs('admin.bookings.index')) {
        $guideTitle = 'Booking records guide';
        $guideSteps = [
            ['selector' => '.admin-booking-filters', 'title' => 'Find booking records', 'copy' => 'Filter by dates, status, or search details to narrow the administrator record set.'],
            ['selector' => '.admin-bookings-table', 'title' => 'Audit active records', 'copy' => 'Review the parties, listing, dates, source, amount, status, and available record actions.'],
            ['selector' => '.deletion-ledger-section', 'title' => 'Review the deletion ledger', 'copy' => 'Use the retained audit history to understand records removed from the active booking table.'],
        ];
    } elseif (request()->routeIs('admin.host-applications.index')) {
        $guideTitle = 'Host application review queue guide';
        $guideSteps = [
            ['selector' => '.application-filter-bar', 'title' => 'Filter the review queue', 'copy' => 'Find applications by applicant, account type, identity checks, and review status.'],
            ['selector' => '.host-applications-table', 'title' => 'Open an application for review', 'copy' => 'Use the verification indicators and status to prioritize applications that need administrator action.'],
        ];
    } elseif (request()->routeIs('admin.host-applications.show')) {
        $guideTitle = 'Host application decision guide';
        $guideSteps = [
            ['selector' => '.admin-application-content', 'title' => 'Verify submitted evidence', 'copy' => 'Review profile details, identity images, business information, documents, and the application timeline.'],
            ['selector' => '.application-review-panel', 'title' => 'Record a clear decision', 'copy' => 'Add a useful review note and approve, request changes, keep under review, or reject the application.'],
        ];
    } elseif (request()->routeIs('admin.support-reports.index')) {
        $guideTitle = 'Admin report queue guide';
        $guideSteps = [
            ['selector' => '.application-filter-bar', 'title' => 'Prioritize submitted reports', 'copy' => 'Filter by search details, category, priority, and status to find reports requiring action.'],
            ['selector' => '.admin-reports-section .accounts-table-wrap', 'title' => 'Open a report for review', 'copy' => 'Review the reporter, subject, related record, priority, status, and last update.'],
        ];
    } elseif (request()->routeIs('admin.support-reports.show')) {
        $guideTitle = 'Admin report response guide';
        $guideSteps = [
            ['selector' => '.support-report-detail-card', 'title' => 'Investigate the report', 'copy' => 'Review the reporter, context, attachment, related page or booking, and full description.'],
            ['selector' => '.support-response-card', 'title' => 'Update and respond', 'copy' => 'Set the correct status and write a response that tells the user what was found and what happens next.'],
        ];
    }

    $guideSteps = array_merge($guideSteps, [
        ['selector' => '[data-global-listing-search]', 'title' => 'Search across the system', 'copy' => 'Find listings by details such as “5 seater car,” “2 BR condo,” a host name, or a business name.'],
        ['selector' => '[data-notification-center]', 'title' => 'See what needs attention', 'copy' => 'Open notifications for inquiries, chats, booking requests, service work, and other updates.'],
        ['selector' => '[data-mobile-sidebar-toggle], [data-mobile-sidebar]', 'title' => 'Move around your workspace', 'copy' => 'Use the main workspace menu to open bookings, inquiries, listings, clients, sales, support, and profile settings.'],
    ]);
@endphp
<div class="page-guide" data-page-guide data-guide-page="{{ request()->route()?->getName() }}">
    <button class="page-guide-button" type="button" data-page-guide-open aria-label="Open page guide"><x-fa-icon name="info" /></button>
    <dialog class="page-guide-dialog" data-page-guide-dialog aria-labelledby="page-guide-title">
        <div class="page-guide-dialog-heading"><span><x-fa-icon name="info" /></span><div><small>Information & demo</small><h2 id="page-guide-title">{{ $guideTitle }}</h2></div><button class="icon-only-button" type="button" data-page-guide-close aria-label="Close guide"><x-fa-icon name="xmark" /></button></div>
        <p>{{ $guideIntroduction }}</p>
        <ol>@foreach($guideSteps as $step)<li><button type="button" data-guide-focus="{{ $step['selector'] }}"><span>{{ $loop->iteration }}</span><strong>{{ $step['title'] }}</strong><small>{{ $step['copy'] }}</small></button></li>@endforeach</ol>
        <footer><button class="button button-primary" type="button" data-guide-start>Start guided demo</button><button class="button button-ghost" type="button" data-page-guide-close>Close</button></footer>
        <script type="application/json" data-guide-steps>@json($guideSteps)</script>
    </dialog>
    <aside class="guide-demo-hint" data-guide-demo-hint role="status" aria-live="polite" aria-atomic="true" hidden><small data-guide-demo-count></small><strong data-guide-demo-title></strong><p data-guide-demo-copy></p><div><button type="button" data-guide-demo-stop>Stop guide</button><button type="button" data-guide-demo-next>Next <x-fa-icon name="arrow-right" /></button></div></aside>
</div>
