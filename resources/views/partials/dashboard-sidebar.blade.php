<aside class="sidebar">
    <a class="brand brand-light" href="{{ route('dashboard') }}">
        <span class="brand-mark" aria-hidden="true"><img src="{{ asset('images/davao-rent-zone-logo-mark.svg') }}" alt=""></span>
        <span class="brand-name">Davao Rent Zone</span>
    </a>
    <nav class="sidebar-nav">
        <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span>⌂</span> Overview</a>
        <a @class(['active' => request()->routeIs('calendar.*')]) href="{{ route('calendar.index') }}"><span>□</span> {{ auth()->user()->isClient() ? 'Book now' : 'Calendar' }}</a>
        <a @class(['active' => request()->routeIs('inquiries.*')]) href="{{ route('inquiries.index') }}"><span>✦</span> Inquiries</a>
        <a @class(['active' => request()->routeIs('profile.*') || request()->routeIs('profiles.*')]) href="{{ route('profile.edit') }}"><span>♙</span> Verification profile</a>
        @if (auth()->user()->isHost() || auth()->user()->is_admin)
            <a @class(['active' => request()->routeIs('units.*')]) href="{{ route('units.index') }}"><span>＋</span> Units & services</a>
        @endif
        @if (auth()->user()->is_admin)
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
