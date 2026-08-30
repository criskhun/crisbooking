@extends('layouts.app')

@section('title', 'Service providers — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="eyebrow">Direct host applications</span><h1>Service providers & earnings</h1><p>Apply directly to property and vehicle hosts. You do not need to create a public service listing.</p></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif

            @if($receivedApplications->isNotEmpty())
                <section class="service-work-panel service-application-panel">
                    <div class="overview-section-heading"><div><span class="eyebrow">Host review</span><h2>Service-provider applications</h2><p>Approve workers you want to assign to cleaning, laundry, delivery, carwash, or maintenance expenses.</p></div></div>
                    <div class="service-application-list">
                        @foreach($receivedApplications as $application)
                            <article class="service-application-card status-{{ $application->status }}"><div><small>{{ $application->statusLabel() }}</small><h3>{{ $application->applicant->name }}</h3><strong>{{ $application->serviceLabels() }}</strong><p>{{ $application->application_message }}</p>@if($application->review_note)<p>Review note: {{ $application->review_note }}</p>@endif</div>@if($application->status === 'pending')<div class="service-application-actions"><form method="POST" action="{{ route('service-provider-applications.review', $application) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><input name="review_note" maxlength="1000" placeholder="Optional approval note"><button class="button button-primary button-small" type="submit">Approve provider</button></form><form method="POST" action="{{ route('service-provider-applications.review', $application) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><input name="review_note" maxlength="1000" placeholder="Reason for declining"><button class="button button-ghost button-small" type="submit">Decline</button></form></div>@endif</article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="service-work-panel service-application-panel">
                <div class="overview-section-heading"><div><span class="eyebrow">Work with a host</span><h2>Apply as a service provider</h2><p>Use your existing account. Select the services you provide and send an application directly to a host.</p></div></div>
                @if($myApplications->isNotEmpty())
                    <div class="my-provider-applications">@foreach($myApplications as $application)<article><span class="booking-status status-{{ $application->status }}">{{ $application->statusLabel() }}</span><strong>{{ $application->host->name }}</strong><small>{{ $application->serviceLabels() }}</small>@if($application->review_note)<p>{{ $application->review_note }}</p>@endif</article>@endforeach</div>
                @endif
                <div class="service-host-list">
                    @forelse($availableHosts as $host)
                        @php
                            $existingApplication = $myApplications->firstWhere('host_id', $host->id);
                        @endphp
                        <details class="service-host-card"><summary><span>♙</span><div><strong>{{ $host->name }}</strong><small>{{ $host->units_count }} active {{ str('rental')->plural($host->units_count) }}</small></div><b>{{ $existingApplication?->status === 'accepted' ? 'Approved' : ($existingApplication ? 'Update application' : 'Apply') }}</b></summary><form method="POST" action="{{ route('service-provider-applications.store') }}">@csrf<input type="hidden" name="host_id" value="{{ $host->id }}"><fieldset><legend>Services you can provide</legend><div class="option-check-grid">@foreach(\App\Models\ServiceProviderApplication::SERVICE_OPTIONS as $value => $label)<label><input type="checkbox" name="services[]" value="{{ $value }}" @checked(in_array($value, old('services', $existingApplication?->services ?? [])))><span>{{ $label }}</span></label>@endforeach</div></fieldset><div class="field-group"><label for="provider_message_{{ $host->id }}">Introduce yourself and your experience</label><textarea id="provider_message_{{ $host->id }}" name="application_message" minlength="10" maxlength="2000" rows="4" required>{{ old('application_message', $existingApplication?->application_message) }}</textarea></div><button class="button button-primary" type="submit">Send application to {{ $host->name }}</button></form></details>
                    @empty
                        <div class="accounts-empty">No hosts with active condo or car listings are accepting direct applications yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="service-work-metrics">
                <article><small>Active assignments</small><strong>{{ number_format($metrics['active_count']) }}</strong></article>
                <article><small>Total assigned value</small><strong>₱{{ number_format($metrics['assigned_total'], 2) }}</strong></article>
                <article><small>Completed, awaiting payment</small><strong>₱{{ number_format($metrics['payment_pending'], 2) }}</strong></article>
                <article><small>Paid earnings</small><strong>₱{{ number_format($metrics['paid_total'], 2) }}</strong></article>
            </section>

            <section class="service-work-panel">
                <div class="overview-section-heading"><div><span class="eyebrow">Assignment history</span><h2>Your approved service jobs</h2><p>Mark a job completed when the work is finished. The booking host records the final payment.</p></div></div>
                <div class="service-work-list">
                    @forelse($assignments as $assignment)
                        <article class="service-work-card status-{{ $assignment->status }}">
                            <div><small>Booking #{{ $assignment->booking_id }} · {{ $assignment->categoryLabel() }}</small><h3>{{ $assignment->booking->unit->name }}</h3><p>{{ $assignment->booking->unit->location ?: 'Location arranged with host' }} · {{ $assignment->booking->start_at->format('M j, Y · g:i A') }}</p>@if($assignment->notes)<p>{{ $assignment->notes }}</p>@endif</div>
                            <div class="service-work-value"><span class="booking-status status-{{ $assignment->status }}">{{ $assignment->statusLabel() }}</span><strong>₱{{ number_format($assignment->amount, 2) }}</strong>@if($assignment->scheduled_at)<small>Scheduled {{ $assignment->scheduled_at->format('M j, Y · g:i A') }}</small>@endif</div>
                            @if($assignment->status === 'assigned')<form method="POST" action="{{ route('service-work.complete', $assignment) }}">@csrf @method('PATCH')<button class="button button-primary button-small" type="submit">Mark completed</button></form>@endif
                        </article>
                    @empty
                        <div class="accounts-empty"><strong>No service work has been assigned yet.</strong><p>Send a direct application above. After a host approves it, they can assign you without requiring a service listing.</p></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
