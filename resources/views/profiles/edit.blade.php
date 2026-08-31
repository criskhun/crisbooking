@extends('layouts.app')

@section('title', 'Verification profile — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header">
                <div><span class="form-kicker">Trust & safety</span><h1>Verification profile</h1></div>
                @include('partials.user-badge')
            </header>

            <section class="profile-verification-shell">
                @if (session('status'))<div class="flash-message account-alert" role="status">{{ session('status') }}</div>@endif
                @error('profile')<div class="oauth-error account-alert" role="alert">{{ $message }}</div>@enderror

                <section class="profile-photo-manager" data-guide-feature="profile-photos">
                    <div class="profile-photo-manager-heading">
                        @include('partials.avatar', ['avatarUser' => $profileUser, 'avatarClass' => 'profile-photo-current', 'avatarAlt' => 'Current profile photo for '.$profileUser->name])
                        <div><span class="eyebrow">Profile photo</span><h2>Choose how people recognize you</h2><p>Your active photo appears in chats, inquiries, bookings, reviews, client lists, and host pages. Old photos stay here until you delete them.</p></div>
                    </div>
                    <form method="POST" action="{{ route('profile-images.store') }}" enctype="multipart/form-data" class="profile-photo-upload" data-profile-photo-upload>
                        @csrf
                        <label for="profile_image"><span>Upload a new photo</span><small>JPG, PNG, or WebP · up to 5 MB · {{ $profileUser->profileImages->count() }}/20 saved</small></label>
                        <input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" required data-profile-photo-input>
                        <img src="" alt="New profile photo preview" data-profile-photo-preview hidden>
                        <button class="button button-primary" type="submit">Upload and use photo</button>
                        @error('profile_image')<p class="error-text">{{ $message }}</p>@enderror
                    </form>
                    @if ($profileUser->profileImages->isNotEmpty())
                        <div class="profile-photo-history" aria-label="Saved profile photos">
                            @foreach ($profileUser->profileImages as $profileImage)
                                @php($isCurrentPhoto = $profileUser->profile_image_path === $profileImage->path)
                                <article @class(['active' => $isCurrentPhoto])>
                                    <img src="{{ Storage::disk('public')->url($profileImage->path) }}" alt="Saved profile photo from {{ $profileImage->created_at->format('M j, Y') }}">
                                    <div><strong>{{ $isCurrentPhoto ? 'Currently in use' : 'Saved photo' }}</strong><small>{{ $profileImage->created_at->format('M j, Y · g:i A') }}</small></div>
                                    <div class="profile-photo-actions">
                                        @unless($isCurrentPhoto)<form method="POST" action="{{ route('profile-images.select', $profileImage) }}">@csrf @method('PATCH')<button type="submit">Use this</button></form>@endunless
                                        <form method="POST" action="{{ route('profile-images.destroy', $profileImage) }}" onsubmit="return confirm('Delete this saved profile photo?')">@csrf @method('DELETE')<button class="danger" type="submit">Delete</button></form>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @else
                        <p class="profile-photo-empty">No uploaded photos yet. Your social account image or name initial is used as a fallback.</p>
                    @endif
                </section>

                <div @class(['verification-status-card', 'complete' => $profileUser->hasCompleteProfile()])>
                    <span><x-fa-icon :name="$profileUser->hasCompleteProfile() ? 'check' : 'triangle-exclamation'" /></span>
                    <div><small>{{ $profileUser->hasCompleteProfile() ? 'Profile complete' : 'Action required' }}</small><h2>{{ $profileUser->hasCompleteProfile() ? 'You can inquire, chat, and transact.' : 'Complete this profile before you continue.' }}</h2><p>Your contact details and ID are shared only with your booking partner and administrators for validation.</p></div>
                </div>

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="verification-form" data-verification-form data-location-provinces-url="{{ route('profile.locations.provinces') }}" data-location-cities-url="{{ route('profile.locations.cities') }}" data-location-barangays-url="{{ route('profile.locations.barangays') }}">
                    @csrf @method('PUT')
                    <section>
                        <div class="verification-section-heading"><span>01</span><div><h2>Personal information</h2><p>Use the same details shown on your identification.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="name">Full legal name</label><input id="name" name="name" value="{{ old('name', $profileUser->name) }}" required>@error('name')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="date_of_birth">Date of birth</label><div class="profile-date-shell"><input class="profile-date-input" id="date_of_birth" name="date_of_birth" type="date" max="{{ today()->subYears(17)->format('Y-m-d') }}" value="{{ old('date_of_birth', $profileUser->date_of_birth?->format('Y-m-d')) }}" required></div><small class="field-help" data-age-result>Select your birth date. You must be 17 or older.</small>@error('date_of_birth')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="nationality">Nationality</label><div class="searchable-combobox"><input id="nationality" name="nationality" type="search" list="nationality-options" autocomplete="off" placeholder="Search nationality…" value="{{ old('nationality', $profileUser->nationality) }}" required><x-fa-icon name="chevron-down" /><datalist id="nationality-options">@foreach ($nationalities as $nationality)<option value="{{ $nationality }}"></option>@endforeach</datalist></div>@error('nationality')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group verification-wide"><label for="bio">About you</label><textarea id="bio" name="bio" rows="4" minlength="20" maxlength="1000" placeholder="Briefly describe yourself and anything helpful for a booking partner to know." required>{{ old('bio', $profileUser->bio) }}</textarea>@error('bio')<p class="error-text">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section>
                        <div class="verification-section-heading"><span>02</span><div><h2>Contact information</h2><p>Both clients and hosts need a reliable way to coordinate.</p></div></div>
                        <div class="verification-grid">
                            <div class="field-group"><label for="phone">Mobile number</label><input id="phone" name="phone" type="tel" autocomplete="tel" placeholder="0917 123 4567 or +63 917 123 4567" value="{{ old('phone', $profileUser->phone) }}" required><small class="field-help">Include the country code for non-Philippine numbers.</small>@error('phone')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label>Email address</label><input value="{{ $profileUser->email }}" disabled><small class="field-help">Verified through your account.</small></div>
                            <div class="field-group"><label for="country">Country</label><div class="searchable-combobox" data-address-combobox><input id="country" name="country" type="search" data-options-id="country-options" autocomplete="off" placeholder="Search country…" value="{{ old('country', $profileUser->country ?: 'Philippines') }}" required data-country-input><x-fa-icon name="chevron-down" /><datalist id="country-options">@foreach ($countries as $code => $country)<option value="{{ $country }}" data-code="{{ $code }}"></option>@endforeach</datalist></div>@error('country')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="province">Province</label><div class="searchable-combobox" data-address-combobox><input id="province" name="province" type="search" data-options-id="province-options" autocomplete="off" placeholder="Select country first" value="{{ old('province', $profileUser->province) }}" required data-province-input><x-fa-icon name="chevron-down" /><datalist id="province-options"></datalist></div>@error('province')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="city">City / municipality</label><div class="searchable-combobox" data-address-combobox><input id="city" name="city" type="search" data-options-id="city-options" autocomplete="off" placeholder="Select province first" value="{{ old('city', $profileUser->city) }}" required data-city-input><x-fa-icon name="chevron-down" /><datalist id="city-options"></datalist></div>@error('city')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group"><label for="barangay">Barangay</label><div class="searchable-combobox" data-address-combobox><input id="barangay" name="barangay" type="search" data-options-id="barangay-options" autocomplete="off" placeholder="Select city first" value="{{ old('barangay', $profileUser->barangay) }}" required data-barangay-input><x-fa-icon name="chevron-down" /><datalist id="barangay-options"></datalist></div><small class="field-help" data-location-status aria-live="polite">Choose a country to load address suggestions.</small>@error('barangay')<p class="error-text">{{ $message }}</p>@enderror</div>
                            <div class="field-group verification-wide"><label for="address">House/unit and street address</label><input id="address" name="address" autocomplete="street-address" value="{{ old('address', $profileUser->address) }}" required>@error('address')<p class="error-text">{{ $message }}</p>@enderror</div>
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
                            <div class="field-group verification-wide"><label for="government_id">ID document {{ $profileUser->government_id_path ? '(replace only if needed)' : '' }}</label><input id="government_id" name="government_id" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" data-id-document-input @required(! $profileUser->government_id_path)>@error('government_id')<p class="error-text">{{ $message }}</p>@enderror<div class="id-document-preview" data-id-document-preview @if(! $profileUser->government_id_path) hidden @endif>@if ($profileUser->government_id_path)@if (str_ends_with(strtolower($profileUser->government_id_path), '.pdf'))<object data="{{ route('profiles.document', $profileUser) }}" type="application/pdf" data-id-preview-object><p>PDF preview unavailable. <a href="{{ route('profiles.document.preview', $profileUser) }}">Open the document</a>.</p></object>@else<img src="{{ route('profiles.document', $profileUser) }}" alt="Current government ID document" data-id-preview-image>@endif<div class="id-preview-meta"><strong>Current ID document</strong><a href="{{ route('profiles.document.preview', $profileUser) }}">Open full size →</a></div>@endif</div></div>
                        </div>
                    </section>

                    <div class="verification-actions"><p>By saving, you confirm that these details are accurate and belong to you.</p><button class="button button-primary" type="submit">Save verification profile</button></div>
                </form>
            </section>
        </main>
    </div>
@endsection
