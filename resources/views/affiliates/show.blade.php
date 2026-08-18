@extends('layouts.app')

@section('title', 'Affiliate partnership — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php($viewerIsHost = auth()->id() === $affiliate->host_id || auth()->user()->is_admin)
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><span class="form-kicker">Affiliate partnership #{{ $affiliate->id }}</span><h1>{{ $affiliate->marketer->name }} × {{ $affiliate->host->name }}</h1></div>@include('partials.user-badge')</header>
            @if(session('status'))<div class="flash-message flash-dashboard" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif

            <div class="affiliate-detail-layout">
                <div>
                    <section class="affiliate-application-card">
                        <div><span class="affiliate-status status-{{ $affiliate->status }}">{{ str($affiliate->status)->title() }}</span><span class="eyebrow">Application</span><h2>{{ $affiliate->marketer->name }} wants to market {{ $affiliate->host->name }}’s listings</h2><p>{{ $affiliate->application_message }}</p>@if($affiliate->review_note)<blockquote>{{ $affiliate->review_note }}</blockquote>@endif</div>
                        @if($viewerIsHost && $affiliate->status === 'pending')
                            <form method="POST" action="{{ route('affiliates.review', $affiliate) }}">@csrf @method('PATCH')<div class="field-group"><label for="commission_percentage">Commission on each confirmed referred sale</label><div class="percentage-input"><input id="commission_percentage" name="commission_percentage" type="number" min="0.01" max="100" step="0.01" value="{{ old('commission_percentage', 10) }}"><span>%</span></div></div><div class="field-group"><label for="review_note">Note to applicant <span class="optional-label">Optional</span></label><textarea id="review_note" name="review_note" rows="3" maxlength="1000">{{ old('review_note') }}</textarea></div><div class="affiliate-review-actions"><button class="button button-primary" type="submit" name="status" value="accepted">Accept & set commission</button><button class="button danger-action" type="submit" name="status" value="rejected" formnovalidate>Decline</button></div></form>
                        @elseif($affiliate->isAccepted())
                            <div class="affiliate-commission-callout"><small>Commission per confirmed sale</small><strong>{{ number_format($affiliate->commission_percentage, 2) }}%</strong><span>The recorded rate is locked into each referred inquiry.</span></div>
                        @endif
                    </section>

                    @if($affiliate->isAccepted())
                        <section class="overview-section"><div class="overview-section-heading"><div><span class="eyebrow">Tracked links</span><h2>Listings ready to share</h2><p>Clients can view these pages without an account. Sign-in is required when they inquire or book.</p></div></div><div class="affiliate-link-list">@forelse($affiliate->host->units as $unit)<article>@if($unit->primaryImagePath())<img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="">@else<span class="affiliate-link-placeholder">{{ ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'][$unit->category] ?? '◇' }}</span>@endif<div><small>{{ str($unit->category)->replace('_', ' ')->title() }}</small><strong>{{ $unit->name }}</strong><input value="{{ $unit->publicUrl($affiliate->referral_code) }}" readonly></div><button type="button" data-affiliate-copy="{{ $unit->publicUrl($affiliate->referral_code) }}">Copy link</button><a href="{{ $unit->publicUrl($affiliate->referral_code) }}" target="_blank" rel="noopener">Preview</a></article>@empty<div class="overview-empty"><strong>The host has no active listing to share.</strong></div>@endforelse</div></section>

                        <section class="overview-section"><div class="overview-section-heading"><div><span class="eyebrow">Sales ledger</span><h2>Referred bookings</h2></div></div><div class="affiliate-sales-list">@forelse($affiliate->bookings as $booking)<article><span><strong>{{ $booking->unit->name }}</strong><small>{{ $booking->client->name }} · {{ $booking->created_at->format('M j, Y') }}</small></span><span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span><b>{{ $booking->status === 'confirmed' ? '₱'.number_format($booking->affiliate_commission_amount, 2) : $booking->statusLabel() }}</b></article>@empty<div class="overview-empty compact"><strong>No referred booking yet.</strong><p>Bookings that begin through the tracked links will appear here.</p></div>@endforelse</div></section>
                        @php($myAffiliateReview = $affiliate->reviews->firstWhere('reviewer_id', auth()->id()))
                        <section class="overview-section affiliate-partnership-review"><div class="overview-section-heading"><div><span class="eyebrow">Partnership reputation</span><h2>Review {{ $viewerIsHost ? $affiliate->marketer->name : $affiliate->host->name }}</h2><p>This review appears on their {{ $viewerIsHost ? 'affiliate' : 'host' }} profile.</p></div></div>@if($myAffiliateReview)<div class="review-submitted"><strong>{{ str_repeat('★', $myAffiliateReview->rating) }}{{ str_repeat('☆', 5 - $myAffiliateReview->rating) }}</strong><p>{{ $myAffiliateReview->comment }}</p></div>@else<form method="POST" action="{{ route('affiliates.reviews.store', $affiliate) }}" class="review-form">@csrf<fieldset><legend>Your rating</legend><div class="review-star-options">@foreach(range(5, 1) as $star)<input id="affiliate-rating-{{ $star }}" name="rating" type="radio" value="{{ $star }}" required><label for="affiliate-rating-{{ $star }}">★</label>@endforeach</div></fieldset><div class="field-group"><label for="affiliate_review_comment">Public comment</label><textarea id="affiliate_review_comment" name="comment" rows="3" minlength="10" maxlength="1500" required></textarea></div><button class="button button-primary" type="submit">Publish review</button></form>@endif</section>
                    @endif
                </div>

                <aside class="affiliate-chat-card">
                    <span class="eyebrow">Direct conversation</span><h2>Host & affiliate chat</h2>
                    <div class="affiliate-message-list">@forelse($affiliate->messages as $message)<article @class(['mine' => $message->sender_id === auth()->id()])><small>{{ $message->sender_id === auth()->id() ? 'You' : $message->sender->name }}</small><p>{{ $message->body }}</p><time>{{ $message->created_at->format('M j, g:i A') }}</time></article>@empty<div class="overview-empty compact"><strong>Start the conversation.</strong><p>Discuss audiences, campaign ideas, listing details, and commission expectations here.</p></div>@endforelse</div>
                    <form method="POST" action="{{ route('affiliates.messages.store', $affiliate) }}">@csrf<div class="field-group"><label for="message">Message</label><textarea id="message" name="message" rows="4" maxlength="2000" required placeholder="Write a message…"></textarea></div><button class="button button-primary" type="submit">Send message</button></form>
                </aside>
            </div>
        </main>
    </div>
    <script>document.querySelectorAll('[data-affiliate-copy]').forEach((button) => button.addEventListener('click', async () => { await navigator.clipboard.writeText(button.dataset.affiliateCopy); button.textContent = 'Copied!'; }));</script>
@endsection
