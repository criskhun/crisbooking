<aside class="sidebar">
    <a class="brand brand-light" href="{{ route('dashboard') }}">
        <span class="brand-mark"><svg viewBox="0 0 32 32"><path d="M8 5v4M24 5v4M6 11h20M8 7h16a3 3 0 0 1 3 3v15a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3V10a3 3 0 0 1 3-3Z"/><path d="m11 19 3 3 7-8"/></svg></span>
        <span>MyBooking</span>
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
