@extends('layouts.app')

@section('title', 'Review admin report — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ route('admin.support-reports.index') }}">← All admin reports</a><h1>{{ $supportReport->subject }}</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif
            <div class="admin-report-review-layout">
                <section class="support-report-detail-card">
                    <header><span class="support-status status-{{ $supportReport->status }}">{{ $supportReport->statusLabel() }}</span><span>{{ $supportReport->categoryLabel() }}</span></header>
                    <div class="account-identity">@include('partials.avatar', ['avatarUser' => $supportReport->reporter, 'avatarClass' => 'account-avatar'])<div><strong>{{ $supportReport->reporter->name }}</strong><small>{{ ucfirst($supportReport->reporter->role) }} · {{ $supportReport->reporter->email }}</small></div></div>
                    <dl class="support-report-meta"><div><dt>Submitted</dt><dd>{{ $supportReport->created_at->format('M j, Y · g:i A') }}</dd></div><div><dt>Related listing</dt><dd>{{ $supportReport->unit?->name ?? 'None selected' }}</dd></div><div><dt>Host</dt><dd>{{ $supportReport->unit?->host?->name ?? 'Not applicable' }}</dd></div><div><dt>Booking</dt><dd>@if($supportReport->booking)<a href="{{ route('bookings.show', $supportReport->booking) }}">#{{ $supportReport->booking->id }} · Open booking</a>@elseif($supportReport->booking_id)Booking no longer exists @else None selected @endif</dd></div></dl>
                    <div class="support-report-message"><span class="eyebrow">Reporter’s message</span><p>{{ $supportReport->message }}</p></div>
                    @if($supportReport->booking)<a class="button button-ghost" href="{{ route('admin.bookings.index', ['search' => $supportReport->booking->id]) }}">Open in booking records</a>@endif
                </section>
                <aside class="support-response-card">
                    <span class="eyebrow">Administrator action</span><h2>Update and reply</h2><form method="POST" action="{{ route('admin.support-reports.update', $supportReport) }}">@csrf @method('PATCH')
                        <div class="field-group"><label for="report_status">Status</label><select id="report_status" name="status" required>@foreach(['open'=>'Open','in_progress'=>'In progress','resolved'=>'Resolved','closed'=>'Closed'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $supportReport->status) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="field-group"><label for="admin_response">Response to reporter</label><textarea id="admin_response" name="admin_response" rows="8" maxlength="5000" placeholder="Explain the action taken or the information needed.">{{ old('admin_response', $supportReport->admin_response) }}</textarea></div>
                        <p>The reporter receives an in-app notification and can read this response in Contact admin.</p><button class="button button-primary button-full" type="submit">Save response</button>
                    </form>
                    @if($supportReport->reviewer)<small class="support-reviewed-by">Last reviewed by {{ $supportReport->reviewer->name }} · {{ $supportReport->reviewed_at?->diffForHumans() }}</small>@endif
                </aside>
            </div>
        </main>
    </div>
@endsection
