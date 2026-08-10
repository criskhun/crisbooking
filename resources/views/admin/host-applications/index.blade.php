@extends('layouts.app')

@section('title', 'Host applications — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Trust & safety</span><h1>Host applications</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif

            <section class="accounts-section application-admin-section">
                <div class="application-admin-summary">
                    @foreach(['submitted' => 'Waiting', 'under_review' => 'In review', 'needs_changes' => 'Needs changes', 'approved' => 'Approved'] as $status => $label)
                        <div><small>{{ $label }}</small><strong>{{ $counts[$status] ?? 0 }}</strong></div>
                    @endforeach
                </div>
                <form class="application-filter-bar" method="GET" action="{{ route('admin.host-applications.index') }}">
                    <label><span class="sr-only">Search applicants</span><input name="search" type="search" value="{{ request('search') }}" placeholder="Search name or email"></label>
                    <label><span class="sr-only">Filter status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></label>
                    <button class="button button-primary" type="submit">Filter</button>
                    @if(request()->hasAny(['search', 'status']))<a href="{{ route('admin.host-applications.index') }}">Clear</a>@endif
                </form>
                <div class="accounts-table-wrap">
                    <table class="accounts-table host-applications-table">
                        <thead><tr><th>Applicant</th><th>Submitted</th><th>Setup</th><th>Profile</th><th>Status</th><th>Reviewer</th><th></th></tr></thead>
                        <tbody>
                            @forelse($applications as $application)
                                <tr>
                                    <td><div class="account-identity"><span>{{ strtoupper(substr($application->user->name, 0, 1)) }}</span><div><strong>{{ $application->user->name }}</strong><small>{{ $application->user->email }}</small></div></div></td>
                                    <td><strong>{{ $application->submitted_at?->format('M j, Y') }}</strong><small class="table-subcopy">{{ $application->submitted_at?->diffForHumans() }}</small></td>
                                    <td><strong>{{ str($application->account_type)->title() }}</strong><small class="table-subcopy">{{ str($application->payout_method)->replace('_', ' ')->title() }}</small></td>
                                    <td><span class="verification-badge">{{ $application->user->hasCompleteProfile() ? 'Complete' : 'Incomplete' }}</span><small class="table-subcopy">{{ $application->needsIdentityImages() ? 'Selfies missing' : 'Selfies ready' }}</small></td>
                                    <td><span class="application-status-badge status-{{ $application->status }}">{{ $application->statusLabel() }}</span></td>
                                    <td>{{ $application->reviewer?->name ?? 'Unassigned' }}</td>
                                    <td><a class="table-review-link" href="{{ route('admin.host-applications.show', $application) }}">Review →</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="accounts-empty">No host applications match this view.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($applications->hasPages())<nav class="simple-pagination" aria-label="Application pages">@if($applications->onFirstPage())<span>← Previous</span>@else<a href="{{ $applications->previousPageUrl() }}">← Previous</a>@endif<small>Page {{ $applications->currentPage() }} of {{ $applications->lastPage() }}</small>@if($applications->hasMorePages())<a href="{{ $applications->nextPageUrl() }}">Next →</a>@else<span>Next →</span>@endif</nav>@endif
            </section>
        </main>
    </div>
@endsection
