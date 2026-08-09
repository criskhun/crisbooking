@extends('layouts.app')

@section('title', $profileUser->name.' — Verified profile')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ url()->previous() }}">← Back</a><h1>Booking partner profile</h1></div>@include('partials.user-badge')</header>
            <section class="public-profile-card">
                <div class="public-profile-hero"><span>{{ strtoupper(substr($profileUser->name, 0, 1)) }}</span><div><small>{{ ucfirst($profileUser->role) }} profile</small><h2>{{ $profileUser->name }}</h2><p>{{ $profileUser->city }}, {{ $profileUser->nationality }}</p></div><strong>✓ Identity details complete</strong></div>
                <div class="profile-validation-grid">
                    <section><span class="eyebrow">About</span><p>{{ $profileUser->bio }}</p></section>
                    <section><span class="eyebrow">Contact</span><dl><div><dt>Email</dt><dd>{{ $profileUser->email }}</dd></div><div><dt>Mobile</dt><dd>{{ $profileUser->phone }}</dd></div><div><dt>Address</dt><dd>{{ collect([$profileUser->address, $profileUser->barangay, $profileUser->city, $profileUser->province, $profileUser->country])->filter()->join(', ') }}</dd></div></dl></section>
                    <section><span class="eyebrow">Identity check</span><dl><div><dt>ID type</dt><dd>{{ str($profileUser->government_id_type)->replace('_', ' ')->title() }}</dd></div><div><dt>ID number</dt><dd>Ending in {{ substr($profileUser->government_id_number, -4) }}</dd></div><div><dt>Date of birth</dt><dd>{{ $profileUser->date_of_birth?->format('M j, Y') }}</dd></div></dl><a class="profile-document-link" href="{{ route('profiles.document.preview', $profileUser) }}">View private ID document →</a></section>
                    <section><span class="eyebrow">Emergency contact</span><dl><div><dt>Name</dt><dd>{{ $profileUser->emergency_contact_name }}</dd></div><div><dt>Mobile</dt><dd>{{ $profileUser->emergency_contact_phone }}</dd></div></dl></section>
                </div>
                <p class="profile-privacy-note">Use these details only to validate and coordinate this booking relationship.</p>
            </section>
        </main>
    </div>
@endsection
