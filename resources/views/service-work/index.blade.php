@extends('layouts.app')

@section('title', 'Service work — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="eyebrow">Provider workspace</span><h1>Service work & earnings</h1><p>Assignments from condo and vehicle booking operations appear here.</p></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif

            <section class="service-work-metrics">
                <article><small>Active assignments</small><strong>{{ number_format($metrics['active_count']) }}</strong></article>
                <article><small>Total assigned value</small><strong>₱{{ number_format($metrics['assigned_total'], 2) }}</strong></article>
                <article><small>Completed, awaiting payment</small><strong>₱{{ number_format($metrics['payment_pending'], 2) }}</strong></article>
                <article><small>Paid earnings</small><strong>₱{{ number_format($metrics['paid_total'], 2) }}</strong></article>
            </section>

            <section class="service-work-panel">
                <div class="overview-section-heading"><div><span class="eyebrow">Assignment history</span><h2>Your service jobs</h2><p>Mark a job completed when the work is finished. The booking host records the final payment.</p></div></div>
                <div class="service-work-list">
                    @forelse($assignments as $assignment)
                        <article class="service-work-card status-{{ $assignment->status }}">
                            <div><small>Booking #{{ $assignment->booking_id }} · {{ $assignment->categoryLabel() }}</small><h3>{{ $assignment->booking->unit->name }}</h3><p>{{ $assignment->booking->unit->location ?: 'Location arranged with host' }} · {{ $assignment->booking->start_at->format('M j, Y · g:i A') }}</p>@if($assignment->notes)<p>{{ $assignment->notes }}</p>@endif</div>
                            <div class="service-work-value"><span class="booking-status status-{{ $assignment->status }}">{{ $assignment->statusLabel() }}</span><strong>₱{{ number_format($assignment->amount, 2) }}</strong>@if($assignment->scheduled_at)<small>Scheduled {{ $assignment->scheduled_at->format('M j, Y · g:i A') }}</small>@endif</div>
                            @if($assignment->status === 'assigned')<form method="POST" action="{{ route('service-work.complete', $assignment) }}">@csrf @method('PATCH')<button class="button button-primary button-small" type="submit">Mark completed</button></form>@endif
                        </article>
                    @empty
                        <div class="accounts-empty"><strong>No service work has been assigned yet.</strong><p>After your host application is approved, publish a service listing for Cleaning, Laundry, Delivery, Carwash, or Vehicle Maintenance so booking hosts can assign you.</p></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
