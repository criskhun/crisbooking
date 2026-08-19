@extends('layouts.app')

@section('title', 'Admin reports — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Administrator inbox</span><h1>Admin reports</h1></div>@include('partials.user-badge')</header>
            <section class="admin-booking-summary support-summary" aria-label="Report summary">
                @foreach(['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $value => $label)<div><small>{{ $label }}</small><strong>{{ $counts[$value] ?? 0 }}</strong></div>@endforeach
            </section>
            <section class="accounts-section admin-reports-section">
                <div class="overview-section-heading"><div><span class="eyebrow">Host and affiliate support</span><h2>Reports requiring administrator action</h2><p>Review cleanup requests and respond to the person who submitted each report.</p></div></div>
                <form class="application-filter-bar" method="GET" action="{{ route('admin.support-reports.index') }}">
                    <label><span class="sr-only">Search reports</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Search reporter, listing, or message"></label>
                    <label><span class="sr-only">Filter category</span><select name="category"><option value="">All report types</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label><span class="sr-only">Filter status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
                    <button class="button button-primary" type="submit">Filter</button>@if(request()->hasAny(['search','category','status']))<a href="{{ route('admin.support-reports.index') }}">Clear</a>@endif
                </form>
                <div class="accounts-table-wrap"><table class="accounts-table"><thead><tr><th>Report</th><th>Reporter</th><th>Related record</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody>
                    @forelse($reports as $report)<tr><td><strong>{{ $report->subject }}</strong><small class="table-subcopy">{{ $report->categoryLabel() }}</small></td><td><strong>{{ $report->reporter->name }}</strong><small class="table-subcopy">{{ ucfirst($report->reporter->role) }} · {{ $report->reporter->email }}</small></td><td>@if($report->unit)<strong>{{ $report->unit->name }}</strong><small class="table-subcopy">{{ $report->unit->host->name }}@if($report->booking) · Booking #{{ $report->booking->id }}@endif</small>@else<span>General support</span>@endif</td><td><strong>{{ $report->created_at->format('M j, Y') }}</strong><small class="table-subcopy">{{ $report->created_at->diffForHumans() }}</small></td><td><span class="support-status status-{{ $report->status }}">{{ $report->statusLabel() }}</span></td><td><a class="table-review-link" href="{{ route('admin.support-reports.show', $report) }}">Review →</a></td></tr>
                    @empty<tr><td colspan="6" class="accounts-empty">No reports match this view.</td></tr>@endforelse
                </tbody></table></div>
                @if($reports->hasPages())<nav class="simple-pagination" aria-label="Report pages">@if($reports->onFirstPage())<span>← Previous</span>@else<a href="{{ $reports->previousPageUrl() }}">← Previous</a>@endif<small>Page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}</small>@if($reports->hasMorePages())<a href="{{ $reports->nextPageUrl() }}">Next →</a>@else<span>Next →</span>@endif</nav>@endif
            </section>
        </main>
    </div>
@endsection
