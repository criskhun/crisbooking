@extends('layouts.app')

@section('title', 'Clients — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Workspace</span><h1>Successfully booked clients</h1></div>@include('partials.user-badge')</header>
            <section class="workspace-page">
                <div class="workspace-hero"><div><span class="eyebrow">Confirmed relationships only</span><h2>Your client directory</h2><p>Clients appear here after at least one booking on your listing has been confirmed.</p></div><strong>{{ $clients->count() }}<small>{{ Str::plural('client', $clients->count()) }}</small></strong></div>
                <div class="workspace-client-grid">
                    @forelse ($clients as $client)
                        @php($lastBooking = $client->successfulBookings->first())
                        <article class="workspace-client-card">
                            <div class="workspace-client-person"><span>{{ strtoupper(substr($client->name, 0, 1)) }}</span><div><small>Verified booking client</small><h2>{{ $client->name }}</h2><p>{{ collect([$client->city, $client->nationality])->filter()->join(' · ') }}</p></div></div>
                            <dl><div><dt>Confirmed bookings</dt><dd>{{ $client->successful_bookings_count }}</dd></div><div><dt>Completed</dt><dd>{{ $client->completed_bookings_count }}</dd></div><div><dt>Sales value</dt><dd>₱{{ number_format($client->confirmed_sales_total, 2) }}</dd></div></dl>
                            <div class="workspace-client-latest"><small>Most recent booking</small><strong>{{ $lastBooking->unit->name }}</strong><span>{{ $lastBooking->start_at->format('M j, Y') }} · {{ str($lastBooking->unit->category)->replace('_', ' ')->title() }}</span></div>
                            <div class="workspace-client-actions"><a class="button button-ghost" href="{{ route('profiles.show', $client) }}">View client profile</a><a href="{{ route('bookings.show', $lastBooking) }}">Latest booking →</a></div>
                        </article>
                    @empty
                        <div class="overview-empty workspace-empty"><strong>No confirmed clients yet.</strong><p>Once you approve a booking request, that client will appear in this private workspace.</p><a class="button button-primary" href="{{ route('inquiries.index') }}">Review inquiries</a></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
