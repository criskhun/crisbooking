@extends('layouts.app')

@section('title', 'Verification profile — MyBooking')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">Trust & safety</span><h1>Verification profile</h1></div>
                @include('partials.user-badge')
            </header>

            <section class="verification-shell">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @error('profile')<div class="oauth-error account-alert" role="alert">{{ $message }}</div>@enderror

                <div @class(['verification-status-card', 'complete' => $profileUser->hasCompleteProfile()])>
                    <span>{{ $profileUser->hasCompleteProfile() ? '✓' : '!' }}</span>
                    <div><small>{{ $profileUser->hasCompleteProfile() ? 'Profile complete' : 'Action required' }}</small><h2>{{ $profileUser->hasCompleteProfile() ? 'You can inquire, chat, and transact.' : 'Complete this profile before you continue.' }}</h2><p>Your contact details and ID are shared only with your booking partner and administrators for validation.</p></div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="verification-form">
                    @csrf @method('PUT')
                    <section>
                        <div class="verification-section-heading"><span>01</span><div><h2>Personal information</h2><p>Use the same details shown on your identification.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="name">Full legal name</label><input id="name" name="name" value="{{ old('name', $profileUser->name) }}" required>@error('name')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="date_of_birth">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" max="{{ today()->subYears(18)->format('Y-m-d') }}" value="{{ old('date_of_birth', $profileUser->date_of_birth?->format('Y-m-d')) }}" required>@error('date_of_birth')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="nationality">Nationality</label><input id="nationality" name="nationality" value="{{ old('nationality', $profileUser->nationality) }}" required>@error('nationality')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group verification-wide"><label for="bio">About you</label><textarea id="bio" name="bio" rows="4" minlength="20" maxlength="1000" placeholder="Briefly describe yourself and anything helpful for a booking partner to know." required>{{ old('bio', $profileUser->bio) }}</textarea>@error('bio')<p class="error-text">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section>
                        <div class="verification-section-heading"><span>02</span><div><h2>Contact information</h2><p>Both clients and hosts need a reliable way to coordinate.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="phone">Mobile number</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone', $profileUser->phone) }}" required>@error('phone')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label>Email address</label><input value="{{ $profileUser->email }}" disabled><small class="field-help">Verified through your account.</small></div>
                            <div class="field-group verification-wide"><label for="address">Complete address</label><input id="address" name="address" autocomplete="street-address" value="{{ old('address', $profileUser->address) }}" required>@error('address')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="city">City / municipality</label><input id="city" name="city" value="{{ old('city', $profileUser->city) }}" required>@error('city')<p class="error-text">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section>
                        <div class="verification-section-heading"><span>03</span><div><h2>Emergency contact</h2><p>Required for safety and urgent coordination.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="emergency_contact_name">Contact person</label><input id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $profileUser->emergency_contact_name) }}" required>@error('emergency_contact_name')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="emergency_contact_phone">Contact number</label><input id="emergency_contact_phone" name="emergency_contact_phone" type="tel" value="{{ old('emergency_contact_phone', $profileUser->emergency_contact_phone) }}" required>@error('emergency_contact_phone')<p class="error-text">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section>
                        <div class="verification-section-heading"><span>04</span><div><h2>Government-issued ID</h2><p>Upload a clear image or PDF. The file stays private.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="government_id_type">ID type</label><select id="government_id_type" name="government_id_type" required><option value="">Select ID</option>@foreach (['national_id' => 'National ID', 'drivers_license' => "Driver's license", 'passport' => 'Passport', 'sss' => 'SSS ID', 'umid' => 'UMID', 'postal_id' => 'Postal ID', 'voters_id' => "Voter's ID", 'other' => 'Other government ID'] as $value => $label)<option value="{{ $value }}" @selected(old('government_id_type', $profileUser->government_id_type) === $value)>{{ $label }}</option>@endforeach</select>@error('government_id_type')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="government_id_number">ID number</label><input id="government_id_number" name="government_id_number" value="{{ old('government_id_number', $profileUser->government_id_number) }}" required>@error('government_id_number')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group verification-wide"><label for="government_id">ID document {{ $profileUser->government_id_path ? '(replace only if needed)' : '' }}</label><input id="government_id" name="government_id" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" @required(! $profileUser->government_id_path)>@if ($profileUser->government_id_path)<small class="field-help">A private ID document is already on file. <a href="{{ route('profiles.document', $profileUser) }}" target="_blank">View current document</a></small>@endif @error('government_id')<p class="error-text">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <div class="verification-actions"><p>By saving, you confirm that these details are accurate and belong to you.</p><button class="button button-primary" type="submit">Save verification profile</button></div>
                </form>
            </section>
        </main>
    </div>
@endsection
