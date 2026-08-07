@extends('layouts.app')

@section('title', 'Inquiries & messages — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Conversations</span><h1>Inquiries & messages</h1></div>@include('partials.user-badge')</header>
            <section class="inquiry-list-section">
                @if (session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif
                <div class="inquiry-list-heading"><div><span class="eyebrow">Before booking</span><h2>Talk through the details first</h2><p>Every booking starts with an inquiry so clients and hosts can validate each other and agree on the plan.</p></div><span>{{ $inquiries->count() }}</span></div>
                <div class="inquiry-list">
                    @forelse ($inquiries as $inquiry)
                        @php $partner = auth()->id() === $inquiry->client_id ? $inquiry->host : $inquiry->client; $latest = $inquiry->messages->first(); @endphp
                        <a href="{{ route('inquiries.show', $inquiry) }}" class="inquiry-row">
                            <span class="inquiry-avatar">{{ strtoupper(substr($partner->name, 0, 1)) }}</span>
                            <div class="inquiry-row-main"><div><strong>{{ $unitName = $inquiry->unit->name }}</strong><span class="inquiry-status status-{{ $inquiry->status }}">{{ str($inquiry->status)->replace('_', ' ')->title() }}</span></div><small>{{ $partner->name }} · {{ $inquiry->desired_start_at->format('M j, Y g:i A') }}</small><p>{{ Str::limit($latest?->body ?: 'No messages yet.', 100) }}</p></div>
                            <div class="inquiry-row-meta">@if ($inquiry->unread_messages_count)<span>{{ $inquiry->unread_messages_count }}</span>@endif<small>{{ $inquiry->updated_at->diffForHumans() }}</small><b>→</b></div>
                        </a>
                    @empty
                        <div class="inquiry-empty"><span>✦</span><h2>No inquiries yet</h2><p>{{ auth()->user()->isClient() ? 'Choose an available listing and send the host a question.' : 'Client inquiries will appear here.' }}</p>@if (auth()->user()->isClient())<a class="button button-primary" href="{{ route('calendar.index') }}">Find a listing</a>@endif</div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
