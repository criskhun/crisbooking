@extends('layouts.app')

@section('title', 'Affiliate partnership — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    @php
        $viewerIsHost = auth()->id() === $affiliate->host_id || auth()->user()->is_admin;
        $viewerCanRateAffiliate = auth()->id() === $affiliate->host_id;
        $assignedUnitIds = collect(old('unit_ids', $affiliate->units->pluck('id')->all()))->map(fn ($id) => (int) $id);
    @endphp
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
                            <form method="POST" action="{{ route('affiliates.review', $affiliate) }}" class="affiliate-assignment-form">@csrf @method('PATCH')
                                <div class="field-group"><label for="commission_percentage">Commission on each confirmed referred sale</label><div class="percentage-input"><input id="commission_percentage" name="commission_percentage" type="number" min="0.01" max="100" step="0.01" value="{{ old('commission_percentage', 10) }}"><span>%</span></div></div>
                                <fieldset class="affiliate-unit-options"><legend>Listings this affiliate may market</legend><p>Select at least one listing before accepting.</p>@foreach($affiliate->host->units as $unit)<label><input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" @checked($assignedUnitIds->contains($unit->id))><span><strong>{{ $unit->name }}</strong><small>{{ str($unit->category)->replace('_', ' ')->title() }}</small></span></label>@endforeach</fieldset>
                                <div class="field-group"><label for="review_note">Note to applicant <span class="optional-label">Optional</span></label><textarea id="review_note" name="review_note" rows="3" maxlength="1000">{{ old('review_note') }}</textarea></div>
                                <div class="affiliate-review-actions"><button class="button button-primary" type="submit" name="status" value="accepted">Accept & assign listings</button><button class="button danger-action" type="submit" name="status" value="rejected" formnovalidate>Decline</button></div>
                            </form>
                        @elseif($affiliate->isAccepted())
                            @if($viewerIsHost)
                                <form method="POST" action="{{ route('affiliates.assignments.update', $affiliate) }}" class="affiliate-assignment-form">@csrf @method('PATCH')
                                    <span class="eyebrow">Manage affiliate</span><h3>Listings and commission</h3>
                                    <div class="field-group"><label for="commission_percentage">Commission per confirmed sale</label><div class="percentage-input"><input id="commission_percentage" name="commission_percentage" type="number" min="0.01" max="100" step="0.01" value="{{ old('commission_percentage', $affiliate->commission_percentage) }}" required><span>%</span></div></div>
                                    <fieldset class="affiliate-unit-options"><legend>Assigned listings</legend><p>Only selected listings will accept this affiliate’s referral code.</p>@foreach($affiliate->host->units as $unit)<label><input type="checkbox" name="unit_ids[]" value="{{ $unit->id }}" @checked($assignedUnitIds->contains($unit->id))><span><strong>{{ $unit->name }}</strong><small>{{ str($unit->category)->replace('_', ' ')->title() }}</small></span></label>@endforeach</fieldset>
                                    <button class="button button-primary" type="submit">Save affiliate assignments</button>
                                </form>
                            @else
                                <div class="affiliate-commission-callout"><small>Commission per confirmed sale</small><strong>{{ number_format($affiliate->commission_percentage, 2) }}%</strong><span>{{ $affiliate->units->count() }} assigned {{ Str::plural('listing', $affiliate->units->count()) }}. The recorded rate is locked into each referred inquiry.</span></div>
                            @endif
                        @endif
                    </section>

                    @if($affiliate->isAccepted())
                        <section class="overview-section"><div class="overview-section-heading"><div><span class="eyebrow">Tracked links</span><h2>Assigned listings ready to share</h2><p>Only listings assigned by the host appear here and accept this affiliate’s referral code.</p></div></div><div class="affiliate-link-list">@forelse($affiliate->units->where('is_active', true) as $unit)<article>@if($unit->primaryImagePath())<img src="{{ Storage::disk('public')->url($unit->primaryImagePath()) }}" alt="">@else<span class="affiliate-link-placeholder">{{ ['car' => '🚗', 'condo' => '🏢', 'driving' => '🛞', 'pet_transport' => '🐾'][$unit->category] ?? '◇' }}</span>@endif<div><small>{{ str($unit->category)->replace('_', ' ')->title() }}</small><strong>{{ $unit->name }}</strong><input value="{{ $unit->publicUrl($affiliate->referral_code) }}" readonly></div><button type="button" data-affiliate-copy="{{ $unit->publicUrl($affiliate->referral_code) }}">Copy link</button><a href="{{ $unit->publicUrl($affiliate->referral_code) }}" target="_blank" rel="noopener">Preview</a></article>@empty<div class="overview-empty"><strong>No active listing is assigned.</strong><p>The host can update assignments in the management form above.</p></div>@endforelse</div></section>

                        <section class="overview-section"><div class="overview-section-heading"><div><span class="eyebrow">Sales ledger</span><h2>Referred bookings</h2></div></div><div class="affiliate-sales-list">@forelse($affiliate->bookings as $booking)<article><span><strong>{{ $booking->unit->name }}</strong><small>{{ $booking->client->name }} · {{ $booking->created_at->format('M j, Y') }}</small></span><span class="booking-status status-{{ $booking->status }}">{{ $booking->statusLabel() }}</span><b>{{ $booking->status === 'confirmed' ? '₱'.number_format($booking->affiliate_commission_amount, 2) : $booking->statusLabel() }}</b></article>@empty<div class="overview-empty compact"><strong>No referred booking yet.</strong><p>Bookings that begin through the tracked links will appear here.</p></div>@endforelse</div></section>
                        @php
                            $affiliateRating = $affiliate->reviews
                                ->first(fn ($review) => $review->reviewee_id === $affiliate->marketer_id && $review->reviewee_context === 'affiliate');
                        @endphp
                        <section class="overview-section affiliate-partnership-review">
                            <div class="overview-section-heading"><div><span class="eyebrow">Partnership reputation</span><h2>{{ $viewerCanRateAffiliate ? 'Rate '.$affiliate->marketer->name : 'Your affiliate rating' }}</h2><p>{{ $viewerCanRateAffiliate ? 'Your review appears on this affiliate’s public profile.' : 'Only the host can submit this rating. You can view it here and on your public profile.' }}</p></div></div>
                            @if($affiliateRating)
                                <div class="review-submitted"><strong>{{ str_repeat('★', $affiliateRating->rating) }}{{ str_repeat('☆', 5 - $affiliateRating->rating) }}</strong><p>{{ $affiliateRating->comment }}</p><small>Rated by {{ $affiliate->host->name }}</small></div>
                            @elseif($viewerCanRateAffiliate)
                                <form method="POST" action="{{ route('affiliates.reviews.store', $affiliate) }}" class="review-form">@csrf<fieldset><legend>Your rating</legend><div class="review-star-options">@foreach(range(5, 1) as $star)<input id="affiliate-rating-{{ $star }}" name="rating" type="radio" value="{{ $star }}" required><label for="affiliate-rating-{{ $star }}">★</label>@endforeach</div></fieldset><div class="field-group"><label for="affiliate_review_comment">Public comment</label><textarea id="affiliate_review_comment" name="comment" rows="3" minlength="10" maxlength="1500" required></textarea></div><button class="button button-primary" type="submit">Publish affiliate review</button></form>
                            @else
                                <div class="overview-empty compact"><strong>No host rating yet.</strong><p>Your host has not published an affiliate review for this partnership.</p></div>
                            @endif
                        </section>
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
