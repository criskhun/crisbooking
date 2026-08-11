@php
    $pendingHostReviewCount = auth()->user()->is_admin
        ? \App\Models\HostApplication::query()->whereIn('status', ['submitted', 'under_review'])->count()
        : 0;
    $hostApplicationAttention = auth()->user()->isClient()
        ? auth()->user()->hostApplication()->whereIn('status', ['needs_changes', 'rejected'])->exists()
        : false;
@endphp
<button class="sidebar-scrim" type="button" data-mobile-sidebar-close hidden aria-label="Close navigation menu"></button>
<aside class="sidebar" data-mobile-sidebar>
    <button class="sidebar-toggle" type="button" data-mobile-sidebar-toggle aria-controls="dashboard-navigation" aria-expanded="false">
        <span class="sr-only" data-mobile-sidebar-label>Open navigation menu</span>
        <span class="sidebar-toggle-arrow" aria-hidden="true">›</span>
    </button>
    <a class="brand brand-light" href="{{ route('dashboard') }}">
        <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
        <span class="brand-name">Davao Rent Zone</span>
    </a>
    <nav class="sidebar-nav" id="dashboard-navigation">
        <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span>⌂</span> Overview</a>
        <a @class(['active' => request()->routeIs('calendar.*')]) href="{{ route('calendar.index') }}"><span>□</span> {{ auth()->user()->isClient() ? 'Book now' : 'Calendar' }}</a>
        <a @class(['active' => request()->routeIs('inquiries.*')]) href="{{ route('inquiries.index') }}"><span>✦</span> Inquiries</a>
        <a @class(['active' => request()->routeIs('affiliates.*')]) href="{{ route('affiliates.index') }}"><span>％</span> Affiliates & sales</a>
        <a @class(['active' => request()->routeIs('profile.*') || request()->routeIs('profiles.*')]) href="{{ route('profile.edit') }}"><span>♙</span> Verification profile @unless(auth()->user()->hasCompleteProfile())<b class="sidebar-notification-badge attention" title="Your verification profile needs attention">!</b>@endunless</a>
        @if (auth()->user()->isClient())
            <a @class(['active' => request()->routeIs('host-applications.*')]) href="{{ route('host-applications.show') }}"><span>◇</span> Become a host @if($hostApplicationAttention)<b class="sidebar-notification-badge attention" title="Your host application needs attention">!</b>@endif</a>
        @endif
        @if (auth()->user()->isHost() || auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('units.*')]) href="{{ route('units.index') }}"><span>＋</span> Units & services</a>
        @endif
        @if (auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('admin.host-applications.*')]) href="{{ route('admin.host-applications.index') }}"><span>✓</span> Host applications @if($pendingHostReviewCount)<b class="sidebar-notification-badge" title="{{ $pendingHostReviewCount }} applications need review">{{ $pendingHostReviewCount > 99 ? '99+' : $pendingHostReviewCount }}</b>@endif</a>
            <a @class(['active' => request()->routeIs('accounts.*')]) href="{{ route('accounts.index') }}"><span>♙</span> Users</a>
        @endif
        <span class="sidebar-label">Workspace</span>
        <a class="disabled" href="#"><span>♙</span> {{ auth()->user()->isHost() ? 'Clients' : 'Hosts' }}</a>
        <a class="disabled" href="#"><span>₱</span> Sales</a>
    </nav>
    <form method="POST" action="{{ route('logout') }}" class="sidebar-logout">
        @csrf
        <button type="submit"><span>↗</span> Log out</button>
    </form>
</aside>
