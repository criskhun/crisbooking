@extends('layouts.app')

@section('title', 'Affiliates & sales — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Grow together</span><h1>Affiliates & sales</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif

            <section class="affiliate-hero"><div><span class="eyebrow">Performance partnerships</span><h2>Market trusted rentals and earn from confirmed sales.</h2><p>Apply directly to an established host. The host decides whether to accept and sets your commission percentage.</p></div><div><strong>{{ $partnerships->where('status', 'accepted')->count() }}</strong><small>Active partnerships</small></div></section>

            <section class="overview-section">
                <div class="overview-section-heading"><div><span class="eyebrow">Your network</span><h2>Applications and partnerships</h2></div></div>
                <div class="affiliate-partnership-grid">
                    @forelse($partnerships as $partnership)
                        @php($isHostSide = $partnership->host_id === auth()->id())
                        <a href="{{ route('affiliates.show', $partnership) }}">
                            <span class="affiliate-status status-{{ $partnership->status }}">{{ str($partnership->status)->title() }}</span>
                            <small>{{ $isHostSide ? 'Sales applicant' : 'Host partner' }}</small>
                            <h3>{{ $isHostSide ? $partnership->marketer->name : $partnership->host->name }}</h3>
                            @if($partnership->isAccepted())<p><strong>{{ number_format($partnership->commission_percentage, 2) }}%</strong> commission · {{ $partnership->confirmed_referrals_count }} confirmed {{ Str::plural('sale', $partnership->confirmed_referrals_count) }}</p><b>₱{{ number_format($partnership->commission_earned ?? 0, 2) }} earned</b>@else<p>{{ Str::limit($partnership->review_note ?: $partnership->application_message, 100) }}</p>@endif
                            <span>Open partnership →</span>
                        </a>
                    @empty
                        <div class="overview-empty"><span>％</span><strong>No affiliate partnership yet.</strong><p>Choose an established host below and introduce how you plan to market their listings.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="overview-section">
                <div class="overview-section-heading"><div><span class="eyebrow">Apply to market</span><h2>Established hosts with active listings</h2><p>Each application is reviewed by the host, not platform staff.</p></div></div>
                <div class="affiliate-host-grid">
                    @forelse($availableHosts as $host)
                        <article><span>♙</span><div><small>{{ $host->city ?: 'Verified local host' }}</small><h3>{{ $host->name }}</h3><p>{{ Str::limit($host->bio ?: 'An established Davao Rent Zone host.', 120) }}</p><strong>{{ $host->units_count }} active {{ Str::plural('listing', $host->units_count) }}</strong></div><details><summary class="button button-primary">Apply to this host</summary><form method="POST" action="{{ route('affiliates.store') }}">@csrf<input type="hidden" name="host_id" value="{{ $host->id }}"><div class="field-group"><label for="application_message_{{ $host->id }}">How will you market these listings?</label><textarea id="application_message_{{ $host->id }}" name="application_message" minlength="20" maxlength="2000" rows="5" required placeholder="Introduce your audience, channels, and sales approach."></textarea></div><button class="button button-primary" type="submit">Send application</button></form></details></article>
                    @empty
                        <div class="overview-empty"><strong>No additional established hosts are available right now.</strong><p>Hosts you already applied to appear in your partnerships above.</p></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
