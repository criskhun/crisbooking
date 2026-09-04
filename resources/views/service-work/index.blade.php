@extends('layouts.app')

@section('title', 'Service providers — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="eyebrow">Direct host applications</span><h1>Service providers & earnings</h1><p>Apply directly to property and vehicle hosts. You do not need to create a public service listing.</p></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="flash-message error-alert" role="alert">{{ $errors->first() }}</div>@endif

            @if($hostDashboard)
                <section class="host-service-overview">
                    <div class="overview-section-heading"><div><span class="eyebrow">Host operations report</span><h2>Requested service overview</h2><p>See what needs your attention, what providers are working on, and what has already been paid and closed.</p></div><a class="button button-ghost button-small" href="{{ route('service-work.index', ['host_filter_submitted' => 1, 'host_statuses' => array_keys($hostStatusOptions)]) }}">View complete history</a></div>
                    <div class="host-service-summary-grid">
                        <article class="attention"><small>Needs your action</small><strong>{{ number_format($hostDashboard['action_count']) }}</strong><span>{{ $hostDashboard['pending_application_count'] }} applications · {{ $hostDashboard['status_counts']['completed'] }} payments</span></article>
                        <article><small>Provider working</small><strong>{{ number_format($hostDashboard['active_count']) }}</strong><span>Assigned requests</span></article>
                        <article><small>Awaiting receipt confirmation</small><strong>{{ number_format($hostDashboard['awaiting_confirmation_count']) }}</strong><span>Payments sent</span></article>
                        <article><small>Closed requests</small><strong>{{ number_format($hostDashboard['closed_count']) }}</strong><span>Provider confirmed payment</span></article>
                        <article><small>Total requested-service cost</small><strong>₱{{ number_format($hostDashboard['total_cost'], 2) }}</strong><span>Excludes cancelled requests</span></article>
                    </div>
                    <div class="host-service-chart-grid">
                        <article class="host-service-chart-card">
                            <div><span class="eyebrow">Status graph</span><h3>Request distribution</h3></div>
                            <div class="service-status-chart">
                                @foreach($hostStatusOptions as $status => $label)
                                    @php
                                        $statusCount = $hostDashboard['status_counts'][$status];
                                        $statusPercent = $hostDashboard['total_count'] ? round(($statusCount / $hostDashboard['total_count']) * 100, 1) : 0;
                                    @endphp
                                    <div class="status-{{ $status }}"><span>{{ $label }}</span><div><i style="--status-width: {{ $statusPercent }}%"></i></div><b>{{ $statusCount }}</b></div>
                                @endforeach
                            </div>
                        </article>
                        <article class="host-service-chart-card">
                            <div><span class="eyebrow">Six-month graph</span><h3>Requested-service costs</h3></div>
                            <div class="service-month-chart" aria-label="Service costs for the last six months">
                                @foreach($hostDashboard['monthly_costs'] as $month)
                                    <div><b>₱{{ number_format($month['amount'], 0) }}</b><i style="--month-height: {{ round(($month['amount'] / $hostDashboard['monthly_max']) * 100, 1) }}%"></i><span>{{ $month['label'] }}</span></div>
                                @endforeach
                            </div>
                        </article>
                    </div>
                </section>

                <section class="service-work-panel host-service-requests-panel">
                    <div class="overview-section-heading"><div><span class="eyebrow">Host request tracker</span><h2>Services you requested</h2><p>The default view shows completed work that needs payment. Select other statuses to review every request until it closes.</p></div></div>
                    <form method="GET" action="{{ route('service-work.index') }}" class="service-status-filter">
                        <input type="hidden" name="host_filter_submitted" value="1">
                        <fieldset><legend>Show request statuses</legend><div>
                            @foreach($hostStatusOptions as $status => $label)
                                <label class="status-{{ $status }}"><input type="checkbox" name="host_statuses[]" value="{{ $status }}" @checked(in_array($status, $selectedHostStatuses, true))><span>{{ $label }}</span><b>{{ $hostDashboard['status_counts'][$status] }}</b></label>
                            @endforeach
                        </div></fieldset>
                        <button class="button button-primary button-small" type="submit">Apply status filter</button>
                        <a href="{{ route('service-work.index') }}">Reset to action needed</a>
                    </form>
                    <div class="host-service-request-list">
                        @forelse($hostRequests as $requestExpense)
                            @php
                                $requestSteps = ['assigned' => 'Assigned', 'completed' => 'Work completed', 'paid' => 'Payment sent', 'payment_received' => 'Closed'];
                                $requestStepIndex = array_search($requestExpense->status, array_keys($requestSteps), true);
                            @endphp
                            <article class="host-service-request status-{{ $requestExpense->status }}">
                                <div class="host-service-request-heading"><div><small>Booking #{{ $requestExpense->booking_id }} · {{ $requestExpense->categoryLabel() }}</small><h3>{{ $requestExpense->booking->unit->name }}</h3><p>{{ $requestExpense->provider?->name ?: 'Provider account unavailable' }}@if($requestExpense->scheduled_at) · Scheduled {{ $requestExpense->scheduled_at->format('M j, Y · g:i A') }}@endif</p></div><div><span class="booking-status status-{{ $requestExpense->status }}">{{ $requestExpense->statusLabel() }}</span><strong>₱{{ number_format($requestExpense->amount, 2) }}</strong></div></div>
                                @if($requestExpense->status === 'cancelled')
                                    <div class="service-request-cancelled">This request was cancelled.</div>
                                @else
                                    <ol class="service-request-timeline">
                                        @foreach($requestSteps as $stepStatus => $stepLabel)
                                            @php $stepIndex = array_search($stepStatus, array_keys($requestSteps), true); @endphp
                                            <li @class(['complete' => $requestStepIndex !== false && $stepIndex < $requestStepIndex, 'current' => $stepStatus === $requestExpense->status])><i></i><span>{{ $stepLabel }}</span></li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if($requestExpense->notes)<p class="service-request-notes">{{ $requestExpense->notes }}</p>@endif
                                <div class="host-service-request-footer">
                                    <div class="private-file-links">
                                        @foreach($requestExpense->completion_images ?? [] as $imageIndex => $image)<a href="{{ route('service-work.completion-images.show', [$requestExpense, $imageIndex]) }}" target="_blank">Completion image {{ $imageIndex + 1 }}</a>@endforeach
                                        @if($requestExpense->payment_proof_path)<a href="{{ route('bookings.expenses.payment-proof', [$requestExpense->booking, $requestExpense]) }}" target="_blank">Payment proof</a>@endif
                                        <a href="{{ route('bookings.show', $requestExpense->booking) }}">Open booking</a>
                                    </div>
                                    @if($requestExpense->status === 'completed')
                                        @php
                                            $servicePaymentAccounts = $financialAccountsByHost->get(
                                                $requestExpense->booking->unit->host_id,
                                                collect()
                                            );
                                        @endphp
                                        <form method="POST" action="{{ route('bookings.expenses.status', [$requestExpense->booking, $requestExpense]) }}" enctype="multipart/form-data" class="host-service-pay-form">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="paid">
                                            <label><span>Proof of payment</span><input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
                                            @if($servicePaymentAccounts->isNotEmpty())
                                                <label><span>Paid from account</span><select name="financial_account_id" required><option value="">Choose an account</option>@foreach($servicePaymentAccounts as $financialAccount)<option value="{{ $financialAccount->id }}">{{ $financialAccount->selectionLabel() }}</option>@endforeach</select></label>
                                                <button class="button button-primary button-small" type="submit">Attach proof & mark paid</button>
                                            @else
                                                <a class="financial-account-required" href="{{ route('accounting.index').'#financial-accounts' }}"><x-fa-icon name="plus" /> Add an account before paying</a>
                                            @endif
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="accounts-empty"><strong>No requests match this status filter.</strong><p>Choose additional statuses above to see assigned, paid, closed, or cancelled requests.</p></div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if($receivedApplications->isNotEmpty())
                <section class="service-work-panel service-application-panel">
                    <div class="overview-section-heading"><div><span class="eyebrow">Host review</span><h2>Service-provider applications</h2><p>Approve workers you want to assign to cleaning, laundry, delivery, carwash, or maintenance expenses.</p></div></div>
                    <div class="service-application-list">
                        @foreach($receivedApplications as $application)
                            <article class="service-application-card status-{{ $application->status }}"><div><small>{{ $application->statusLabel() }}</small><h3>{{ $application->applicant->name }}</h3><strong>{{ $application->serviceLabels() }}</strong><p>{{ $application->application_message }}</p>@if($application->application_images)<div class="private-file-links"><small>Application images:</small>@foreach($application->application_images as $imageIndex => $image)<a href="{{ route('service-provider-applications.images.show', [$application, $imageIndex]) }}" target="_blank">Image {{ $imageIndex + 1 }}</a>@endforeach</div>@endif @if($application->review_note)<p>Review note: {{ $application->review_note }}</p>@endif</div>@if($application->status === 'pending')<div class="service-application-actions"><form method="POST" action="{{ route('service-provider-applications.review', $application) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><input name="review_note" maxlength="1000" placeholder="Optional approval note"><button class="button button-primary button-small" type="submit">Approve provider</button></form><form method="POST" action="{{ route('service-provider-applications.review', $application) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="declined"><input name="review_note" maxlength="1000" placeholder="Reason for declining"><button class="button button-ghost button-small" type="submit">Decline</button></form></div>@endif</article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="service-work-panel service-application-panel">
                <div class="overview-section-heading"><div><span class="eyebrow">Work with a host</span><h2>Apply as a service provider</h2><p>Use your existing account. Select the services you provide and send an application directly to a host.</p></div></div>
                @if($myApplications->isNotEmpty())
                    <div class="my-provider-applications">@foreach($myApplications as $application)<article><span class="booking-status status-{{ $application->status }}">{{ $application->statusLabel() }}</span><strong>{{ $application->host->name }}</strong><small>{{ $application->serviceLabels() }}</small>@if($application->application_images)<div class="private-file-links">@foreach($application->application_images as $imageIndex => $image)<a href="{{ route('service-provider-applications.images.show', [$application, $imageIndex]) }}" target="_blank">Application image {{ $imageIndex + 1 }}</a>@endforeach</div>@endif @if($application->review_note)<p>{{ $application->review_note }}</p>@endif</article>@endforeach</div>
                @endif
                <div class="service-host-list">
                    @forelse($availableHosts as $host)
                        @php
                            $existingApplication = $myApplications->firstWhere('host_id', $host->id);
                        @endphp
                        <details class="service-host-card"><summary><span><x-fa-icon name="user-tie" /></span><div><strong>{{ $host->name }}</strong><small>{{ $host->units_count }} active {{ str('rental')->plural($host->units_count) }}</small></div><b>{{ $existingApplication?->status === 'accepted' ? 'Approved' : ($existingApplication ? 'Update application' : 'Apply') }}</b></summary><form method="POST" action="{{ route('service-provider-applications.store') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="host_id" value="{{ $host->id }}"><fieldset><legend>Services you can provide</legend><div class="option-check-grid">@foreach(\App\Models\ServiceProviderApplication::SERVICE_OPTIONS as $value => $label)<label><input type="checkbox" name="services[]" value="{{ $value }}" @checked(in_array($value, old('services', $existingApplication?->services ?? [])))><span>{{ $label }}</span></label>@endforeach</div></fieldset><div class="field-group"><label for="provider_message_{{ $host->id }}">Introduce yourself and your experience</label><textarea id="provider_message_{{ $host->id }}" name="application_message" minlength="10" maxlength="2000" rows="4" required>{{ old('application_message', $existingApplication?->application_message) }}</textarea></div><div class="field-group"><label for="provider_images_{{ $host->id }}">Work or supplier images <span class="optional-label">Optional</span></label><input id="provider_images_{{ $host->id }}" name="application_images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple><small class="field-help">Attach up to 6 JPG, PNG, or WebP images, maximum 5 MB each.</small>@error('application_images')<p class="error-text">{{ $message }}</p>@enderror @error('application_images.*')<p class="error-text">{{ $message }}</p>@enderror</div><button class="button button-primary" type="submit">Send application to {{ $host->name }}</button></form></details>
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
                            <div><small>Booking #{{ $assignment->booking_id }} · {{ $assignment->categoryLabel() }}</small><h3>{{ $assignment->booking->unit->name }}</h3><p>{{ $assignment->booking->unit->location ?: 'Location arranged with host' }} · {{ $assignment->booking->start_at->format('M j, Y · g:i A') }}</p>@if($assignment->notes)<p>{{ $assignment->notes }}</p>@endif @if($assignment->completion_images)<div class="private-file-links"><small>Completion evidence:</small>@foreach($assignment->completion_images as $imageIndex => $image)<a href="{{ route('service-work.completion-images.show', [$assignment, $imageIndex]) }}" target="_blank">Image {{ $imageIndex + 1 }}</a>@endforeach</div>@endif @if($assignment->payment_proof_path)<div class="private-file-links"><small>Host payment proof:</small><a href="{{ route('bookings.expenses.payment-proof', [$assignment->booking, $assignment]) }}" target="_blank">Open {{ $assignment->payment_proof_name ?: 'proof' }}</a></div>@endif</div>
                            <div class="service-work-value"><span class="booking-status status-{{ $assignment->status }}">{{ $assignment->statusLabel() }}</span><strong>₱{{ number_format($assignment->amount, 2) }}</strong>@if($assignment->scheduled_at)<small>Scheduled {{ $assignment->scheduled_at->format('M j, Y · g:i A') }}</small>@endif</div>
                            @if($assignment->status === 'assigned')<form method="POST" action="{{ route('service-work.complete', $assignment) }}" enctype="multipart/form-data" class="service-completion-form">@csrf @method('PATCH')<label><span>Completion images <small>Optional, up to 6</small></span><input type="file" name="completion_images[]" accept="image/jpeg,image/png,image/webp" multiple></label>@error('completion_images')<p class="error-text">{{ $message }}</p>@enderror @error('completion_images.*')<p class="error-text">{{ $message }}</p>@enderror<button class="button button-primary button-small" type="submit">Mark completed</button></form>@elseif($assignment->status === 'paid')<form method="POST" action="{{ route('service-work.payment-received', $assignment) }}">@csrf @method('PATCH')<button class="button button-primary button-small" type="submit">Confirm payment received</button></form>@endif
                        </article>
                    @empty
                        <div class="accounts-empty"><strong>No service work has been assigned yet.</strong><p>Send a direct application above. After a host approves it, they can assign you without requiring a service listing.</p></div>
                    @endforelse
                </div>
            </section>
        </main>
    </div>
@endsection
