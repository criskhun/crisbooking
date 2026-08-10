@extends('layouts.app')

@section('title', 'Review host application — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php
        $applicant = $hostApplication->user;
        $maskedPayout = str_repeat('•', max(4, strlen($hostApplication->payout_account_number) - 4)).substr($hostApplication->payout_account_number, -4);
        $reviewable = in_array($hostApplication->status, ['submitted', 'under_review'], true);
    @endphp
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ route('admin.host-applications.index') }}">← All applications</a><h1>{{ $applicant->name }}</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif

            <section class="admin-application-layout">
                <div class="admin-application-content">
                    <section class="application-profile-card">
                        <div class="application-card-heading"><div><span class="eyebrow">Live verification profile</span><h2>Applicant identity</h2><p>No personal details are duplicated in the application.</p></div><a href="{{ route('profiles.show', $applicant) }}">Open public profile →</a></div>
                        <div class="application-profile-grid">
                            <div><small>Legal name</small><strong>{{ $applicant->name }}</strong></div>
                            <div><small>Email</small><strong>{{ $applicant->email }}</strong></div>
                            <div><small>Mobile</small><strong>{{ $applicant->phone }}</strong></div>
                            <div><small>Date of birth</small><strong>{{ $applicant->date_of_birth?->format('F j, Y') }}</strong></div>
                            <div class="wide"><small>Address</small><strong>{{ collect([$applicant->address, $applicant->barangay, $applicant->city, $applicant->province, $applicant->country])->filter()->join(', ') }}</strong></div>
                            <div><small>Government ID</small><strong>{{ str($applicant->government_id_type)->replace('_', ' ')->title() }}</strong></div>
                            <div><small>Verification</small><strong>{{ $applicant->hasCompleteProfile() ? 'Profile complete' : 'Profile incomplete' }}</strong></div>
                        </div>
                        @if($applicant->government_id_path)<a class="private-document-link" href="{{ route('profiles.document.preview', $applicant) }}">View private government ID →</a>@endif
                    </section>

                    <section class="application-summary-card">
                        <div class="application-card-heading"><div><span class="eyebrow">Application #{{ $hostApplication->id }}</span><h2>Host and payout setup</h2></div><span class="application-status-badge status-{{ $hostApplication->status }}">{{ $hostApplication->statusLabel() }}</span></div>
                        <dl class="application-detail-list">
                            <div><dt>Applying as</dt><dd>{{ str($hostApplication->account_type)->title() }}</dd></div>
                            <div><dt>Experience</dt><dd>{{ str($hostApplication->hosting_experience)->replace('_', ' ')->title() }}</dd></div>
                            @if($hostApplication->business_name)<div><dt>Business</dt><dd>{{ $hostApplication->business_name }}</dd></div><div><dt>Registration</dt><dd>••••{{ substr($hostApplication->business_registration_number, -4) }}</dd></div>@endif
                            <div><dt>Payout method</dt><dd>{{ str($hostApplication->payout_method)->replace('_', ' ')->title() }}</dd></div>
                            <div><dt>Provider</dt><dd>{{ $hostApplication->payout_provider }}</dd></div>
                            <div><dt>Account holder</dt><dd>{{ $hostApplication->payout_account_name }}</dd></div>
                            <div><dt>Account</dt><dd>{{ $maskedPayout }}</dd></div>
                            <div class="wide"><dt>Hosting plan</dt><dd>{{ $hostApplication->motivation }}</dd></div>
                        </dl>
                        @if($hostApplication->business_document_path)<a class="private-document-link" href="{{ route('host-applications.business-document', $hostApplication) }}">Download private business document →</a>@endif
                    </section>

                    <section class="application-timeline-card"><div class="application-card-heading"><div><span class="eyebrow">Audit trail</span><h2>Status history</h2></div></div><ol>@foreach($hostApplication->histories as $history)<li><span></span><div><strong>{{ str($history->to_status)->replace('_', ' ')->title() }}</strong><small>{{ $history->created_at->format('M j, Y · g:i A') }} · {{ $history->actor?->name ?? 'System' }}</small>@if($history->note)<p>{{ $history->note }}</p>@endif</div></li>@endforeach</ol></section>
                </div>

                <aside class="application-review-panel">
                    <span class="eyebrow">Review decision</span><h2>{{ $hostApplication->statusLabel() }}</h2><p>Approval changes this client’s role to host and unlocks listing creation. Each listing remains responsible for its category-specific information.</p>
                    @if($reviewable)
                        <form method="POST" action="{{ route('admin.host-applications.review', $hostApplication) }}">@csrf @method('PATCH')
                            <div class="field-group"><label for="review_note">Review note</label><textarea id="review_note" name="review_note" rows="6" maxlength="3000" placeholder="Required when requesting changes or rejecting.">{{ old('review_note', $hostApplication->review_note) }}</textarea>@error('review_note')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="review-actions">
                                @if($hostApplication->status === 'submitted')<button class="button button-ghost" name="status" value="under_review" type="submit">Mark in review</button>@endif
                                <button class="button button-ghost warning" name="status" value="needs_changes" type="submit">Request changes</button>
                                <button class="button button-primary" name="status" value="approved" type="submit" onclick="return confirm('Approve this applicant and grant host access?')">Approve host</button>
                                <button class="button button-danger" name="status" value="rejected" type="submit" onclick="return confirm('Reject this host application?')">Reject</button>
                            </div>
                        </form>
                    @else
                        <div class="review-complete"><strong>Review completed</strong><span>{{ $hostApplication->reviewer?->name ?? 'Administrator' }} · {{ $hostApplication->reviewed_at?->format('M j, Y g:i A') }}</span>@if($hostApplication->review_note)<p>{{ $hostApplication->review_note }}</p>@endif</div>
                    @endif
                </aside>
            </section>
        </main>
    </div>
@endsection
