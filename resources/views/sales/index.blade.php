@extends('layouts.app')

@section('title', 'Sales dashboard — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Revenue workspace</span><h1>Sales dashboard</h1></div>@include('partials.user-badge')</header>
            <section class="sales-dashboard">
                <nav class="sales-category-tabs" aria-label="Sales category filters">
                    <a @class(['active' => $selectedCategory === '']) href="{{ route('sales.index') }}">All sales</a>
                    @foreach ($categories as $tabCategory)<a @class(['active' => $selectedCategory === $tabCategory]) href="{{ route('sales.index', ['category' => $tabCategory]) }}">{{ str($tabCategory)->replace('_', ' ')->title() }}</a>@endforeach
                </nav>

                <div class="sales-metric-grid">
                    <article class="primary"><span>Confirmed sales</span><strong>₱{{ number_format($metrics['sales_total'], 2) }}</strong><small>{{ $metrics['confirmed_count'] }} confirmed {{ Str::plural('booking', $metrics['confirmed_count']) }}</small></article>
                    <article><span>Pending pipeline</span><strong>₱{{ number_format($metrics['pending_total'], 2) }}</strong><small>{{ $metrics['pending_count'] }} awaiting approval</small></article>
                    <article><span>Average sale</span><strong>₱{{ number_format($metrics['average_sale'], 2) }}</strong><small>Per confirmed booking</small></article>
                    <article><span>Unique clients</span><strong>{{ $metrics['unique_clients'] }}</strong><small>Successfully booked</small></article>
                    <article><span>Cancelled</span><strong>{{ $metrics['cancelled_count'] }}</strong><small>Not included in sales</small></article>
                </div>

                <div class="sales-chart-grid">
                    <section class="sales-chart-card monthly-sales-chart">
                        <div class="sales-chart-heading"><div><span class="eyebrow">Trend</span><h2>Sales by month</h2></div><small>Last 12 months</small></div>
                        <div class="sales-bars" role="img" aria-label="Monthly confirmed sales chart">
                            @foreach ($monthlySales as $monthSale)
                                <div><span class="sales-bar-value">{{ $monthSale['value'] > 0 ? '₱'.number_format($monthSale['value'] / 1000, 1).'k' : '₱0' }}</span><i style="height: {{ max(3, round($monthSale['value'] / $maxMonthlySale * 100)) }}%"></i><small>{{ $monthSale['label'] }}</small></div>
                            @endforeach
                        </div>
                    </section>

                    <section class="sales-chart-card">
                        <div class="sales-chart-heading"><div><span class="eyebrow">Mix</span><h2>Sales by category</h2></div><small>Confirmed</small></div>
                        <div class="category-sales-bars">
                            @forelse ($categorySales as $categorySale)
                                <div><span><strong>{{ str($categorySale['category'])->replace('_', ' ')->title() }}</strong><small>{{ $categorySale['count'] }} bookings</small></span><i><b style="width: {{ max(3, round($categorySale['value'] / $maxCategorySale * 100)) }}%"></b></i><em>₱{{ number_format($categorySale['value'], 2) }}</em></div>
                            @empty
                                <div class="overview-empty compact"><strong>No confirmed sales yet.</strong><p>Category performance will appear after a booking is confirmed.</p></div>
                            @endforelse
                        </div>
                    </section>

                    <section class="sales-chart-card sales-status-card">
                        @php
                            $statusTotal = max(1, $metrics['confirmed_count'] + $metrics['pending_count'] + $metrics['cancelled_count']);
                            $confirmedDegrees = round($metrics['confirmed_count'] / $statusTotal * 360);
                            $pendingDegrees = round($metrics['pending_count'] / $statusTotal * 360);
                        @endphp
                        <div class="sales-chart-heading"><div><span class="eyebrow">Pipeline health</span><h2>Booking status mix</h2></div></div>
                        <div class="sales-status-visual"><div class="sales-donut" style="--confirmed-angle: {{ $confirmedDegrees }}deg; --pending-angle: {{ $confirmedDegrees + $pendingDegrees }}deg"><strong>{{ $statusTotal }}</strong><small>Total</small></div><ul><li><i class="confirmed"></i><span>Confirmed</span><strong>{{ $metrics['confirmed_count'] }}</strong></li><li><i class="pending"></i><span>Pending</span><strong>{{ $metrics['pending_count'] }}</strong></li><li><i class="cancelled"></i><span>Cancelled</span><strong>{{ $metrics['cancelled_count'] }}</strong></li></ul></div>
                    </section>
                </div>

                <section class="sales-ledger-card">
                    <div class="sales-chart-heading"><div><span class="eyebrow">Ledger</span><h2>Recent booking sales</h2></div><small>{{ $selectedCategory ? str($selectedCategory)->replace('_', ' ')->title() : 'All categories' }}</small></div>
                    <div class="sales-ledger-table"><div class="sales-ledger-head"><span>Booking</span><span>Client</span><span>Category</span><span>Status</span><span>Sale value</span></div>@forelse($recentBookings as $sale)<a href="{{ route('bookings.show', $sale) }}"><span><strong>#{{ $sale->id }} · {{ $sale->unit->name }}</strong><small>{{ $sale->start_at->format('M j, Y · g:i A') }}</small></span><span>{{ $sale->client->name }}</span><span>{{ str($sale->unit->category)->replace('_', ' ')->title() }}</span><span><em class="booking-status status-{{ $sale->status }}">{{ $sale->statusLabel() }}</em></span><strong>{{ $sale->status === 'confirmed' ? '₱'.number_format($sale->revenueAmount(), 2) : '—' }}</strong></a>@empty<div class="overview-empty"><strong>No booking records in this category.</strong></div>@endforelse</div>
                </section>
            </section>
        </main>
    </div>
@endsection
