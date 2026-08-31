@extends('layouts.app')

@section('title', $profileUser->name.' — Verified profile')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ url()->previous() }}"><x-fa-icon name="arrow-left" /> Back</a><h1>Booking partner profile</h1></div>@include('partials.user-badge')</header>
            <section class="public-profile-card">
                <div class="public-profile-hero">@include('partials.avatar', ['avatarUser' => $profileUser, 'avatarClass' => 'public-profile-avatar'])<div><small>{{ ucfirst($profileUser->role) }} profile</small><h2>{{ $profileUser->name }}</h2><p>{{ $profileUser->city }}, {{ $profileUser->nationality }}</p></div><strong><x-fa-icon name="circle-check" /> Identity details complete</strong></div>
                <div class="profile-validation-grid">
                    <section><span class="eyebrow">About</span><p>{{ $profileUser->bio }}</p></section>
                    <section><span class="eyebrow">Contact</span><dl><div><dt>Email</dt><dd>{{ $profileUser->email }}</dd></div><div><dt>Mobile</dt><dd>{{ $profileUser->phone }}</dd></div><div><dt>Address</dt><dd>{{ collect([$profileUser->address, $profileUser->barangay, $profileUser->city, $profileUser->province, $profileUser->country])->filter()->join(', ') }}</dd></div></dl></section>
                    <section><span class="eyebrow">Identity check</span><dl><div><dt>ID type</dt><dd>{{ str($profileUser->government_id_type)->replace('_', ' ')->title() }}</dd></div><div><dt>ID number</dt><dd>Ending in {{ substr($profileUser->government_id_number, -4) }}</dd></div><div><dt>Date of birth</dt><dd>{{ $profileUser->date_of_birth?->format('M j, Y') }}</dd></div></dl><a class="profile-document-link" href="{{ route('profiles.document.preview', $profileUser) }}">View private ID document →</a></section>
                    <section><span class="eyebrow">Emergency contact</span><dl><div><dt>Name</dt><dd>{{ $profileUser->emergency_contact_name }}</dd></div><div><dt>Mobile</dt><dd>{{ $profileUser->emergency_contact_phone }}</dd></div></dl></section>
                </div>
                <section class="profile-reviews-section">
                    <div class="profile-reviews-heading"><div><span class="eyebrow">Booking reputation</span><h2>Ratings and comments</h2></div><span>{{ $profileUser->reviewsReceived->count() }} {{ Str::plural('review', $profileUser->reviewsReceived->count()) }}</span></div>
                    @if ($reviewSummaries->isNotEmpty())<div class="profile-rating-summary">@foreach(['host' => 'As a host', 'client' => 'As a client', 'affiliate' => 'As an affiliate'] as $context => $label)@if($reviewSummaries->has($context))<article><small>{{ $label }}</small><strong><x-fa-icon name="star" class="fa-rating" /> {{ number_format($reviewSummaries[$context]['average'], 1) }}</strong><span>{{ $reviewSummaries[$context]['count'] }} {{ Str::plural('review', $reviewSummaries[$context]['count']) }}</span></article>@endif @endforeach</div>@endif
                    <div class="profile-review-list">@forelse($profileUser->reviewsReceived as $review)<article><div>@include('partials.avatar', ['avatarUser' => $review->reviewer, 'avatarClass' => 'reviewer-avatar'])<div><strong>{{ $review->reviewer->name }}</strong><small>{{ str($review->reviewee_context)->title() }} review · {{ $review->created_at->format('M j, Y') }}</small></div><b><x-rating-stars :rating="$review->rating" /></b></div><p>{{ $review->comment }}</p>@if($review->booking)<small>Booking: {{ $review->booking->unit->name }}</small>@else<small>Affiliate partnership review</small>@endif</article>@empty<div class="overview-empty compact"><strong>No reviews yet.</strong><p>Reviews appear after completed confirmed bookings or accepted affiliate partnerships.</p></div>@endforelse</div>
                </section>
                <p class="profile-privacy-note">Use these details only to validate and coordinate this booking relationship.</p>
            </section>
        </main>
    </div>
@endsection
