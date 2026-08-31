@extends('layouts.app')

@section('title', 'Booking records — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Administrator controls</span><h1>Booking records</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif

            <section class="admin-booking-summary" aria-label="Booking record summary">
                <div><small>All records</small><strong>{{ $counts['all'] }}</strong></div>
                <div><small>Blocking dates</small><strong>{{ $counts['blocking'] }}</strong></div>
                <div><small>Outside bookings</small><strong>{{ $counts['manual'] }}</strong></div>
                <div><small>Removed records</small><strong>{{ $counts['removed'] }}</strong></div>
            </section>

            <section class="accounts-section admin-records-section">
                <div class="overview-section-heading"><div><span class="eyebrow">Current and historical activity</span><h2>Bookings by host and unit</h2><p>Filter platform and outside bookings. Removing a record releases its calendar dates and saves an administrator audit entry.</p></div></div>
                <form class="admin-booking-filters" method="GET" action="{{ route('admin.bookings.index') }}">
                    <label><span>Search</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Booking #, customer, host, unit, reference"></label>
                    <label><span>Host</span><select name="host_id"><option value="">All hosts</option>@foreach($hosts as $host)<option value="{{ $host->id }}" @selected((int) request('host_id') === $host->id)>{{ $host->name }}</option>@endforeach</select></label>
                    <label><span>Unit or service</span><select name="unit_id"><option value="">All units</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((int) request('unit_id') === $unit->id)>{{ $unit->name }} · {{ $unit->host->name }}</option>@endforeach</select></label>
                    <label><span>Schedule</span><select name="period"><option value="">Any date</option>@foreach($periods as $period)<option value="{{ $period }}" @selected(request('period') === $period)>{{ str($period)->title() }}</option>@endforeach</select></label>
                    <label><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                    <label><span>Origin</span><select name="origin"><option value="">Any origin</option><option value="platform" @selected(request('origin') === 'platform')>Platform</option><option value="manual" @selected(request('origin') === 'manual')>Outside / manual</option></select></label>
                    <div class="admin-filter-actions"><button class="button button-primary" type="submit">Apply filters</button>@if(request()->hasAny(['search','host_id','unit_id','period','status','origin']))<a href="{{ route('admin.bookings.index') }}">Clear</a>@endif</div>
                </form>

                <div class="accounts-table-wrap">
                    <table class="accounts-table admin-bookings-table">
                        <thead><tr><th>Booking</th><th>Host / unit</th><th>Customer / source</th><th>Schedule</th><th>Status</th><th>Total</th><th>Admin action</th></tr></thead>
                        <tbody>
                            @forelse($bookings as $booking)
                                <tr>
                                    <td><strong>#{{ $booking->id }}</strong><small class="table-subcopy">{{ $booking->isManualBooking() ? 'Outside booking' : 'Platform booking' }}</small></td>
                                    <td><strong>{{ $booking->unit->name }}</strong><small class="table-subcopy">{{ $booking->unit->host->name }} · {{ str($booking->unit->category)->replace('_',' ')->title() }}</small></td>
                                    <td><strong>{{ $booking->customerDisplayName() }}</strong><small class="table-subcopy">{{ $booking->isManualBooking() ? $booking->sourceDisplayLabel() : $booking->client->email }}</small></td>
                                    <td><strong>{{ $booking->start_at->format('M j, Y') }} → {{ $booking->end_at->format('M j, Y') }}</strong><small class="table-subcopy">{{ $booking->durationDisplayLabel() }}</small></td>
                                    <td><span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span></td>
                                    <td><strong>₱{{ number_format((float) $booking->total_amount, 2) }}</strong></td>
                                    <td>
                                        <div class="admin-booking-actions"><a href="{{ route('bookings.show', $booking) }}">Open</a>
                                            <details><summary>Remove</summary><form method="POST" action="{{ route('admin.bookings.destroy', $booking) }}" onsubmit="return confirm('Remove booking #{{ $booking->id }}? Its calendar dates will become available and this cannot be restored automatically.')">@csrf @method('DELETE')<input type="hidden" name="confirmation" value="remove"><label><span>Reason</span><textarea name="removal_reason" rows="3" required minlength="5" maxlength="1000" placeholder="Example: Test record requested for removal by host"></textarea></label><button class="button button-danger button-small" type="submit">Delete record</button></form></details>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="accounts-empty">No booking records match these filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($bookings->hasPages())<nav class="simple-pagination" aria-label="Booking record pages">@if($bookings->onFirstPage())<span>← Previous</span>@else<a href="{{ $bookings->previousPageUrl() }}">← Previous</a>@endif<small>Page {{ $bookings->currentPage() }} of {{ $bookings->lastPage() }}</small>@if($bookings->hasMorePages())<a href="{{ $bookings->nextPageUrl() }}">Next →</a>@else<span>Next →</span>@endif</nav>@endif
            </section>

            <section class="accounts-section deletion-ledger-section">
                <div class="overview-section-heading"><div><span class="eyebrow">Audit trail</span><h2>Recently removed records</h2><p>The original booking is deleted, but this ledger preserves who removed it, when, and why.</p></div></div>
                <div class="accounts-table-wrap"><table class="accounts-table"><thead><tr><th>Original booking</th><th>Host / unit</th><th>Schedule</th><th>Removed</th><th>Reason</th></tr></thead><tbody>@forelse($deletions as $deletion)<tr><td><strong>#{{ $deletion->original_booking_id }}</strong><small class="table-subcopy">{{ str($deletion->booking_origin)->title() }} · {{ str($deletion->booking_status)->replace('_',' ')->title() }}</small></td><td><strong>{{ $deletion->unit_name }}</strong><small class="table-subcopy">{{ $deletion->host_name }} · {{ $deletion->customer_name }}</small></td><td>{{ $deletion->start_at->format('M j, Y') }} → {{ $deletion->end_at->format('M j, Y') }}</td><td><strong>{{ $deletion->removed_at->format('M j, Y') }}</strong><small class="table-subcopy">{{ $deletion->remover?->name ?? 'Former administrator' }}</small></td><td>{{ $deletion->removal_reason }}</td></tr>@empty<tr><td colspan="5" class="accounts-empty">No booking records have been removed.</td></tr>@endforelse</tbody></table></div>
            </section>
        </main>
    </div>
@endsection
