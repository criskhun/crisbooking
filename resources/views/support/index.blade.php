@extends('layouts.app')

@section('title', 'Contact admin — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Help & reporting</span><h1>Contact admin</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif

            <div class="support-layout">
                <section class="support-compose-card">
                    <div class="overview-section-heading"><div><span class="eyebrow">Send a report</span><h2>How can the administrators help?</h2><p>Use this for test-booking removal, incorrect dates, listing concerns, affiliate issues, or account support.</p></div></div>
                    <form class="support-report-form" method="POST" action="{{ route('support.store') }}">@csrf
                        <div class="field-group"><label for="support_category">Report type</label><select id="support_category" name="category" required>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category', request('category')) === $value)>{{ $label }}</option>@endforeach</select></div>
                        <div class="field-group"><label for="support_subject">Subject</label><input id="support_subject" name="subject" maxlength="160" value="{{ old('subject') }}" required placeholder="Briefly describe what you need"></div>
                        <div class="field-group"><label for="support_unit_id">Related listing, vehicle, or service <span class="optional-label">Optional</span></label><select id="support_unit_id" name="unit_id"><option value="">No specific listing</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected((int) old('unit_id', request('unit_id')) === $unit->id)>{{ $unit->name }} · {{ $unit->host->name }}</option>@endforeach</select></div>
                        <div class="field-group"><label for="support_booking_id">Related booking <span class="optional-label">Optional</span></label><select id="support_booking_id" name="booking_id"><option value="">No specific booking</option>@foreach($bookings as $booking)<option value="{{ $booking->id }}" @selected((int) old('booking_id', request('booking_id')) === $booking->id)>#{{ $booking->id }} · {{ $booking->unit->name }} · {{ $booking->start_at->format('M j, Y') }}</option>@endforeach</select></div>
                        <div class="field-group support-message-field"><label for="support_message">Details</label><textarea id="support_message" name="message" rows="7" minlength="10" maxlength="5000" required placeholder="Include what happened, the expected result, and whether you want a booking record removed.">{{ old('message') }}</textarea></div>
                        <div class="support-submit-row"><p>Administrators will receive an in-app notification and reply here.</p><button class="button button-primary" type="submit">Send report</button></div>
                    </form>
                </section>

                <aside class="support-history-card">
                    <div class="overview-section-heading"><div><span class="eyebrow">Your requests</span><h2>Report history</h2></div></div>
                    <div class="support-report-list">
                        @forelse($reports as $report)
                            <article><header><span class="support-status status-{{ $report->status }}">{{ $report->statusLabel() }}</span><time>{{ $report->created_at->format('M j, Y') }}</time></header><h3>{{ $report->subject }}</h3><p>{{ $report->message }}</p>@if($report->unit)<small>Related to {{ $report->unit->name }}@if($report->booking) · Booking #{{ $report->booking->id }}@endif</small>@endif @if($report->admin_response)<div class="support-admin-response"><strong>Administrator response</strong><p>{{ $report->admin_response }}</p><small>{{ $report->reviewer?->name }} · {{ $report->reviewed_at?->diffForHumans() }}</small></div>@endif</article>
                        @empty
                            <div class="empty-panel"><span>✓</span><p>You have not sent an administrator report.</p></div>
                        @endforelse
                    </div>
                </aside>
            </div>
        </main>
    </div>
@endsection
