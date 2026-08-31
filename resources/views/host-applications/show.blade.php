@extends('layouts.app')

@section('title', 'Become a host — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php
        $profileUser = auth()->user();
        $canEdit = ! $profileUser->isHost() && (! $application || $application->canBeEditedByApplicant());
        $maskedPayout = $application?->payout_account_number
            ? str_repeat('•', max(4, strlen($application->payout_account_number) - 4)).substr($application->payout_account_number, -4)
            : null;
    @endphp
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">Host onboarding</span><h1>Become a host</h1></div>
                @include('partials.user-badge')
            </header>

            @if (session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @error('application')<div class="oauth-error account-alert" role="alert">{{ $message }}</div>@enderror

            <section class="host-application-shell">
                @if ($application)
                    <div class="application-status-card status-{{ $application->status }}">
                        <span><x-fa-icon :name="$application->status === 'approved' ? 'check' : ($application->status === 'rejected' ? 'xmark' : 'triangle-exclamation')" /></span>
                        <div>
                            <small>Application #{{ $application->id }} · {{ $application->statusLabel() }}</small>
                            <h2>{{ $application->status === 'submitted' && $application->needsIdentityImages() ? 'Add your identity selfies to continue.' : match($application->status) {
                                'submitted' => 'Your application is in the review queue.',
                                'under_review' => 'An administrator is reviewing your application.',
                                'needs_changes' => 'Changes are required before review can continue.',
                                'approved' => 'You are approved to host.',
                                'rejected' => 'Your application was not approved.',
                                default => 'Finish your host application.',
                            } }}</h2>
                            @if ($application->review_note)<p><strong>Admin feedback:</strong> {{ $application->review_note }}</p>@else<p>Submitted {{ $application->submitted_at?->diffForHumans() }}. We will keep the status here up to date.</p>@endif
                        </div>
                        @if ($application->status === 'approved')<a class="button button-primary" href="{{ route('units.create') }}">Create your first listing</a>@endif
                    </div>
                @else
                    <div class="application-intro-card">
                        <div><span class="eyebrow">One application, every listing type</span><h2>Apply here only when you want to publish your own listings.</h2><p>Workers who only want Cleaning, Laundry, Vehicle Delivery, Carwash, or Maintenance assignments can use a normal account and apply directly to hosts from Service providers—no host application or service listing is required.</p></div>
                        <strong>Usually completed in a few minutes</strong>
                    </div>
                @endif

                <section class="application-profile-card">
                    <div class="application-card-heading"><div><span class="eyebrow">From your profile</span><h2>Personal information</h2><p>This information is not copied into the application. Updates to your profile appear here automatically.</p></div><a href="{{ route('profile.edit') }}">Edit profile →</a></div>
                    <div class="application-profile-grid">
                        <div><small>Legal name</small><strong>{{ $profileUser->name }}</strong></div>
                        <div><small>Verified email</small><strong>{{ $profileUser->email }}</strong></div>
                        <div><small>Mobile</small><strong>{{ $profileUser->phone ?: 'Missing' }}</strong></div>
                        <div><small>Date of birth</small><strong>{{ $profileUser->date_of_birth?->format('F j, Y') ?: 'Missing' }}</strong></div>
                        <div class="wide"><small>Home address</small><strong>{{ collect([$profileUser->address, $profileUser->barangay, $profileUser->city, $profileUser->province, $profileUser->country])->filter()->join(', ') ?: 'Missing' }}</strong></div>
                        <div><small>Government ID</small><strong>{{ $profileUser->government_id_type ? str($profileUser->government_id_type)->replace('_', ' ')->title() : 'Missing' }}</strong></div>
                        <div><small>Profile status</small><strong>{{ $profileUser->hasCompleteProfile() ? 'Complete and ready' : 'Incomplete' }}</strong></div>
                    </div>
                    @unless ($profileUser->hasCompleteProfile())
                        <div class="profile-required-inline"><strong>Complete your profile first.</strong><span>A legal name, contact details, address, emergency contact, and government ID are required.</span><a class="button button-primary" href="{{ route('profile.edit') }}">Complete profile</a></div>
                    @endunless
                </section>

                @if ($canEdit && $profileUser->hasCompleteProfile())
                    <form method="POST" action="{{ route('host-applications.store') }}" enctype="multipart/form-data" class="verification-form host-application-form" data-host-application-form>
                        @csrf
                        <section>
                            <div class="verification-section-heading"><span>01</span><div><h2>Identity selfies</h2><p>These private images help the administrator compare your face with the valid ID already saved in your profile.</p></div></div>
                            <div class="identity-selfie-grid">
                                <div class="selfie-upload-card">
                                    <span class="selfie-preview face-preview" data-selfie-preview="face">
                                        @if($application?->face_selfie_path)<img src="{{ route('host-applications.identity-image', [$application, 'type' => 'face']) }}" alt="Current face selfie">@else<span class="selfie-placeholder">Position your face here</span>@endif
                                        <i aria-hidden="true"></i>
                                    </span>
                                    <strong>Clear face selfie</strong>
                                    <small>Center your face inside the oval. Remove sunglasses, masks, and hats. Use even lighting and include only yourself.</small>
                                    <button class="selfie-file-action" type="button" data-camera-open="face">{{ $application?->face_selfie_path ? 'Retake face selfie' : 'Open camera for selfie' }}</button>
                                    <input id="face_selfie" name="face_selfie" type="file" accept="image/jpeg" data-selfie-input="face" {{ $application?->face_selfie_path ? '' : 'data-camera-required=true' }} tabindex="-1" aria-hidden="true">
                                    @error('face_selfie')<span class="error-text">{{ $message }}</span>@enderror
                                </div>
                                <div class="selfie-upload-card">
                                    <span class="selfie-preview id-hold-preview" data-selfie-preview="id">
                                        @if($application?->id_selfie_path)<img src="{{ route('host-applications.identity-image', [$application, 'type' => 'id']) }}" alt="Current selfie holding a valid ID">@else<span class="selfie-placeholder">Your face and ID must both be visible</span>@endif
                                    </span>
                                    <strong>Selfie holding your valid ID</strong>
                                    <small>Hold the same ID from your profile beside your face. Keep your full face and the ID visible, sharp, and readable.</small>
                                    <button class="selfie-file-action" type="button" data-camera-open="id">{{ $application?->id_selfie_path ? 'Retake selfie with ID' : 'Open camera with ID' }}</button>
                                    <input id="id_selfie" name="id_selfie" type="file" accept="image/jpeg" data-selfie-input="id" {{ $application?->id_selfie_path ? '' : 'data-camera-required=true' }} tabindex="-1" aria-hidden="true">
                                    @error('id_selfie')<span class="error-text">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <p class="error-text camera-requirement-error" data-camera-requirement-error hidden>Take both required photos before submitting your host application.</p>
                            <p class="identity-privacy-note">Images are stored privately, are unavailable to clients or hosts, and can only be opened by you and administrators.</p>
                        </section>
                        <section>
                            <div class="verification-section-heading"><span>02</span><div><h2>Hosting setup</h2><p>Tell the review team how you intend to operate on the marketplace.</p></div></div>
                            <div class="verification-grid">
                                <div class="field-group"><label for="account_type">Applying as</label><select id="account_type" name="account_type" required data-host-account-type><option value="individual" @selected(old('account_type', $application?->account_type ?? 'individual') === 'individual')>Individual</option><option value="business" @selected(old('account_type', $application?->account_type) === 'business')>Registered business</option></select>@error('account_type')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group"><label for="hosting_experience">Hosting experience</label><select id="hosting_experience" name="hosting_experience" required>@foreach(['none' => 'No previous experience', 'less_than_one_year' => 'Less than 1 year', 'one_to_three_years' => '1–3 years', 'more_than_three_years' => 'More than 3 years'] as $value => $label)<option value="{{ $value }}" @selected(old('hosting_experience', $application?->hosting_experience) === $value)>{{ $label }}</option>@endforeach</select>@error('hosting_experience')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group verification-wide"><label for="motivation">Why do you want to host?</label><textarea id="motivation" name="motivation" rows="5" minlength="30" maxlength="2000" required placeholder="Describe what you plan to offer and how you will provide a reliable experience.">{{ old('motivation', $application?->motivation) }}</textarea>@error('motivation')<p class="error-text">{{ $message }}</p>@enderror</div>
                            </div>
                            <div class="business-fields" data-host-business-fields>
                                <div class="field-group"><label for="business_name">Registered business name</label><input id="business_name" name="business_name" value="{{ old('business_name', $application?->business_name) }}">@error('business_name')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group"><label for="business_registration_number">Registration number</label><input id="business_registration_number" name="business_registration_number" value="{{ old('business_registration_number', $application?->business_registration_number) }}">@error('business_registration_number')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group verification-wide"><label for="business_document">Business registration document {{ $application?->business_document_path ? '(upload only to replace)' : '' }}</label><input id="business_document" name="business_document" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf">@error('business_document')<p class="error-text">{{ $message }}</p>@enderror
                                    @if($application?->business_document_path)<small class="field-help"><a href="{{ route('host-applications.business-document', $application) }}">Download current private document</a></small>@endif
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="verification-section-heading"><span>03</span><div><h2>Payout destination</h2><p>Stored privately and used only to prepare host payouts. You can leave the account number blank when resubmitting to keep the current one.</p></div></div>
                            <div class="verification-grid">
                                <div class="field-group"><label for="payout_method">Payout method</label><select id="payout_method" name="payout_method" required><option value="bank_transfer" @selected(old('payout_method', $application?->payout_method) === 'bank_transfer')>Bank transfer</option><option value="e_wallet" @selected(old('payout_method', $application?->payout_method) === 'e_wallet')>E-wallet</option></select>@error('payout_method')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group"><label for="payout_provider">Bank or wallet provider</label><input id="payout_provider" name="payout_provider" value="{{ old('payout_provider', $application?->payout_provider) }}" placeholder="Example: BPI or GCash" required>@error('payout_provider')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group"><label for="payout_account_name">Account holder name</label><input id="payout_account_name" name="payout_account_name" value="{{ old('payout_account_name', $application?->payout_account_name ?? $profileUser->name) }}" required>@error('payout_account_name')<p class="error-text">{{ $message }}</p>@enderror</div>
                                <div class="field-group"><label for="payout_account_number">Account number {{ $maskedPayout ? '(current '.$maskedPayout.')' : '' }}</label><input id="payout_account_number" name="payout_account_number" inputmode="numeric" autocomplete="off" placeholder="{{ $maskedPayout ? 'Leave blank to keep current account' : 'Enter account or wallet number' }}" {{ $maskedPayout ? '' : 'required' }}>@error('payout_account_number')<p class="error-text">{{ $message }}</p>@enderror</div>
                            </div>
                        </section>

                        <section>
                            <div class="verification-section-heading"><span>04</span><div><h2>Declarations</h2><p>Listing-specific permits, ownership evidence, insurance, photos, and safety information will be requested in the applicable listing form.</p></div></div>
                            <div class="application-checklist">
                                <label><input type="checkbox" name="authority_confirmed" value="1" required @checked(old('authority_confirmed'))><span><strong>I have authority to offer my listings.</strong><small>I own them or can provide permission from the owner when a listing requires it.</small></span></label>
                                <label><input type="checkbox" name="safety_confirmed" value="1" required @checked(old('safety_confirmed'))><span><strong>I will meet safety and legal requirements.</strong><small>I will keep listing documents, registrations, insurance, permits, and maintenance information accurate.</small></span></label>
                                <label><input type="checkbox" name="terms_accepted" value="1" required @checked(old('terms_accepted'))><span><strong>I accept the host terms, fees, cancellation, damage, and prohibited-use policies.</strong></span></label>
                                <label><input type="checkbox" name="privacy_consented" value="1" required @checked(old('privacy_consented'))><span><strong>I consent to verification and processing of this application.</strong><small>I confirm the information provided is accurate.</small></span></label>
                            </div>
                            @foreach(['authority_confirmed', 'safety_confirmed', 'terms_accepted', 'privacy_consented'] as $field)@error($field)<p class="error-text">{{ $message }}</p>@enderror @endforeach
                        </section>

                        <div class="verification-actions"><p>Submitting does not publish a listing. Once approved, you can create listings for any supported category.</p><button class="button button-primary" type="submit">{{ $application ? 'Update and resubmit' : 'Submit host application' }}</button></div>

                        <dialog class="selfie-camera-dialog" data-selfie-camera-dialog aria-labelledby="selfie-camera-title">
                            <div class="selfie-camera-panel">
                                <header><div><span class="eyebrow">Live camera</span><h2 id="selfie-camera-title" data-camera-title>Take selfie</h2></div><button class="icon-only-button" type="button" data-camera-close aria-label="Close camera"><x-fa-icon name="xmark" /></button></header>
                                <p data-camera-instructions>Center your face inside the guide.</p>
                                <div class="camera-capture-stage" data-camera-stage>
                                    <video data-camera-video autoplay muted playsinline></video>
                                    <img data-camera-photo alt="Captured selfie preview" hidden>
                                    <span class="camera-face-guide" aria-hidden="true"></span>
                                    <span class="camera-id-guide" aria-hidden="true"><i>Hold ID here</i></span>
                                </div>
                                <canvas data-camera-canvas hidden></canvas>
                                <p class="camera-status" data-camera-status role="status" aria-live="polite">Allow camera access when prompted.</p>
                                <div class="camera-actions">
                                    <button class="button button-ghost" type="button" data-camera-cancel>Cancel</button>
                                    <button class="button button-primary" type="button" data-camera-capture disabled>Take photo</button>
                                    <button class="button button-ghost" type="button" data-camera-retake hidden>Retake</button>
                                    <button class="button button-primary" type="button" data-camera-use hidden>Use this photo</button>
                                </div>
                            </div>
                        </dialog>
                    </form>
                @elseif ($application)
                    <section class="application-summary-card">
                        <div class="application-card-heading"><div><span class="eyebrow">Submitted details</span><h2>Host setup</h2></div></div>
                        <dl class="application-detail-list">
                            <div><dt>Account type</dt><dd>{{ str($application->account_type)->title() }}</dd></div>
                            @if($application->business_name)<div><dt>Business</dt><dd>{{ $application->business_name }}</dd></div>@endif
                            <div><dt>Experience</dt><dd>{{ str($application->hosting_experience)->replace('_', ' ')->title() }}</dd></div>
                            <div><dt>Payout</dt><dd>{{ str($application->payout_method)->replace('_', ' ')->title() }} · {{ $application->payout_provider }} · {{ $maskedPayout }}</dd></div>
                            <div class="wide"><dt>Hosting plan</dt><dd>{{ $application->motivation }}</dd></div>
                        </dl>
                    </section>
                @endif

                @if($application?->histories->isNotEmpty())
                    <section class="application-timeline-card"><div class="application-card-heading"><div><span class="eyebrow">Audit trail</span><h2>Application history</h2></div></div><ol>@foreach($application->histories as $history)<li><span></span><div><strong>{{ str($history->to_status)->replace('_', ' ')->title() }}</strong><small>{{ $history->created_at->format('M j, Y · g:i A') }} · {{ $history->actor?->name ?? 'System' }}</small>@if($history->note)<p>{{ $history->note }}</p>@endif</div></li>@endforeach</ol></section>
                @endif
            </section>
        </main>
    </div>
@endsection
