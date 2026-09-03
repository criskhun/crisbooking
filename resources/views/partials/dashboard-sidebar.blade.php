@php
    $pendingHostReviewCount = auth()->user()->is_admin
        ? \App\Models\HostApplication::query()->whereIn('status', ['submitted', 'under_review'])->count()
        : 0;
    $openSupportReportCount = auth()->user()->is_admin
        ? \App\Models\SupportReport::query()->whereIn('status', ['open', 'in_progress'])->count()
        : 0;
    $hostApplicationAttention = auth()->user()->isClient()
        ? auth()->user()->hostApplication()->whereIn('status', ['needs_changes', 'rejected'])->exists()
        : false;
    $inquiryAttentionCount = auth()->user()->inquiryAttentionCount();
    $serviceWorkActionCount = auth()->user()->serviceWorkActionCount();
    $favoriteCount = auth()->user()->favoriteUnits()
        ->where('units.is_active', true)
        ->whereHas('host', fn ($host) => $host->whereNotNull('profile_completed_at'))
        ->count();
    $hasAffiliateCalendar = ! auth()->user()->isHost()
        && ! auth()->user()->is_admin
        && auth()->user()->marketerAffiliatePartnerships()
            ->where('status', 'accepted')
            ->whereHas('units')
            ->exists();
@endphp
<button class="sidebar-scrim" type="button" data-mobile-sidebar-close hidden aria-label="Close navigation menu"></button>
<aside class="sidebar" data-mobile-sidebar>
    <button class="sidebar-toggle" type="button" data-mobile-sidebar-toggle aria-controls="dashboard-navigation" aria-expanded="false">
        <span class="sr-only" data-mobile-sidebar-label>Open navigation menu</span>
        <span class="sidebar-toggle-icon" aria-hidden="true">
            <i class="fa-solid fa-bars sidebar-toggle-glyph sidebar-toggle-glyph-menu"></i>
            <i class="fa-solid fa-xmark sidebar-toggle-glyph sidebar-toggle-glyph-close"></i>
        </span>
    </button>
    <div class="sidebar-panel">
        <a class="brand brand-light" href="{{ route('dashboard') }}">
            <span class="brand-mark" aria-hidden="true"><img src="{{ $branding->logo_url }}" alt=""></span>
            <span class="brand-name">{{ $branding->site_name }}</span>
        </a>
        <nav class="sidebar-nav" id="dashboard-navigation" aria-label="Workspace navigation">
        <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><i class="fa-solid fa-gauge-high sidebar-nav-icon" aria-hidden="true"></i> Overview</a>
        <a @class(['active' => request()->routeIs('calendar.*') && (request('mode') === 'book' || (auth()->user()->isClient() && request('mode') !== 'manage'))]) href="{{ route('calendar.index', ['mode' => 'book']) }}"><i class="fa-solid fa-calendar-check sidebar-nav-icon" aria-hidden="true"></i> Book now</a>
        <a @class(['active' => request()->routeIs('favorites.*')]) href="{{ route('favorites.index') }}"><i class="fa-solid fa-heart sidebar-nav-icon" aria-hidden="true"></i> Favorites @if($favoriteCount)<b class="sidebar-notification-badge favorite-count" data-sidebar-favorite-count title="{{ $favoriteCount }} saved {{ Str::plural('listing', $favoriteCount) }}">{{ $favoriteCount > 99 ? '99+' : $favoriteCount }}</b>@endif</a>
        @if (auth()->user()->isHost() || auth()->user()->is_admin || $hasAffiliateCalendar)
            <a @class(['active' => request()->routeIs('calendar.*') && ($hasAffiliateCalendar ? request('mode') === 'manage' : request('mode') !== 'book')]) href="{{ route('calendar.index', ['mode' => 'manage']) }}"><i class="fa-solid fa-calendar-days sidebar-nav-icon" aria-hidden="true"></i> {{ $hasAffiliateCalendar ? 'Affiliate calendar' : 'Host calendar' }}</a>
        @endif
        <a @class(['active' => request()->routeIs('inquiries.*')]) href="{{ route('inquiries.index') }}"><i class="fa-solid fa-comments sidebar-nav-icon" aria-hidden="true"></i> Inquiries <b class="sidebar-notification-badge" data-inquiry-attention-count @if(!$inquiryAttentionCount) hidden @endif title="Inquiries need your attention">{{ $inquiryAttentionCount > 99 ? '99+' : $inquiryAttentionCount }}</b></a>
        <a @class(['active' => request()->routeIs('affiliates.*')]) href="{{ route('affiliates.index') }}"><i class="fa-solid fa-handshake sidebar-nav-icon" aria-hidden="true"></i> {{ auth()->user()->isHost() || auth()->user()->is_admin ? 'Affiliate management' : 'Affiliates & sales' }}</a>
        <a @class(['active' => request()->routeIs('profile.*') || request()->routeIs('profiles.*')]) href="{{ route('profile.edit') }}"><i class="fa-solid fa-id-card sidebar-nav-icon" aria-hidden="true"></i> Verification profile @unless(auth()->user()->hasCompleteProfile())<b class="sidebar-notification-badge attention" title="Your verification profile needs attention">!</b>@endunless</a>
        @if (auth()->user()->isClient())
            <a @class(['active' => request()->routeIs('host-applications.*')]) href="{{ route('host-applications.show') }}"><i class="fa-solid fa-house-user sidebar-nav-icon" aria-hidden="true"></i> Become a host @if($hostApplicationAttention)<b class="sidebar-notification-badge attention" title="Your host application needs attention">!</b>@endif</a>
        @endif
        @if (auth()->user()->isHost() || auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('units.*')]) href="{{ route('units.index') }}"><i class="fa-solid fa-building sidebar-nav-icon" aria-hidden="true"></i> Units & services</a>
        @endif
        <a @class(['active' => request()->routeIs('service-work.*') || request()->routeIs('service-provider-applications.*')]) href="{{ route('service-work.index') }}"><i class="fa-solid fa-screwdriver-wrench sidebar-nav-icon" aria-hidden="true"></i> Service providers @if($serviceWorkActionCount)<b class="sidebar-notification-badge attention" data-service-work-action-count="{{ $serviceWorkActionCount }}" title="{{ $serviceWorkActionCount }} service {{ Str::plural('item', $serviceWorkActionCount) }} need your action">{{ $serviceWorkActionCount > 99 ? '99+' : $serviceWorkActionCount }}</b>@endif</a>
        @if (auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('admin.host-applications.*')]) href="{{ route('admin.host-applications.index') }}"><i class="fa-solid fa-clipboard-check sidebar-nav-icon" aria-hidden="true"></i> Host applications @if($pendingHostReviewCount)<b class="sidebar-notification-badge" title="{{ $pendingHostReviewCount }} applications need review">{{ $pendingHostReviewCount > 99 ? '99+' : $pendingHostReviewCount }}</b>@endif</a>
            <a @class(['active' => request()->routeIs('admin.bookings.*')]) href="{{ route('admin.bookings.index') }}"><i class="fa-solid fa-book-open sidebar-nav-icon" aria-hidden="true"></i> Booking records</a>
            <a @class(['active' => request()->routeIs('admin.support-reports.*')]) href="{{ route('admin.support-reports.index') }}"><i class="fa-solid fa-triangle-exclamation sidebar-nav-icon" aria-hidden="true"></i> Admin reports @if($openSupportReportCount)<b class="sidebar-notification-badge attention" title="{{ $openSupportReportCount }} reports need attention">{{ $openSupportReportCount > 99 ? '99+' : $openSupportReportCount }}</b>@endif</a>
            <a @class(['active' => request()->routeIs('accounts.*')]) href="{{ route('accounts.index') }}"><i class="fa-solid fa-users sidebar-nav-icon" aria-hidden="true"></i> Users</a>
            <a @class(['active' => request()->routeIs('admin.settings.*')]) href="{{ route('admin.settings.edit') }}"><i class="fa-solid fa-gear sidebar-nav-icon" aria-hidden="true"></i> System settings</a>
        @else
            <a @class(['active' => request()->routeIs('support.*')]) href="{{ route('support.index') }}"><i class="fa-solid fa-headset sidebar-nav-icon" aria-hidden="true"></i> Contact admin</a>
        @endif
        <span class="sidebar-label">Workspace</span>
        @if (auth()->user()->isHost() || auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('workspace.clients')]) href="{{ route('workspace.clients') }}"><i class="fa-solid fa-address-book sidebar-nav-icon" aria-hidden="true"></i> Clients</a>
            <a @class(['active' => request()->routeIs('sales.*')]) href="{{ route('sales.index') }}"><i class="fa-solid fa-chart-column sidebar-nav-icon" aria-hidden="true"></i> Sales</a>
            <a @class(['active' => request()->routeIs('accounting.*')]) href="{{ route('accounting.index') }}"><i class="fa-solid fa-book-open sidebar-nav-icon" aria-hidden="true"></i> Accounting ledger</a>
        @endif
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
            @csrf
            <button type="submit"><i class="fa-solid fa-right-from-bracket sidebar-nav-icon" aria-hidden="true"></i> Log out</button>
        </form>
    </div>
</aside>
