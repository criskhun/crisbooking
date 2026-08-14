@extends('layouts.app')

@section('title', 'Inquiry for '.$inquiry->unit->name.' — Davao Rent Zone')
@section('body-class', 'dashboard-body')

@section('content')
    <div class="dashboard-shell">
        @include('partials.dashboard-sidebar')
        <main class="dashboard-main">
            <header class="dashboard-header"><div><a class="back-link" href="{{ $inquiry->booking ? route('bookings.show', $inquiry->booking) : route('inquiries.index') }}">← {{ $inquiry->booking ? 'Back to booking details' : 'All inquiries' }}</a><h1>{{ $inquiry->unit->name }}</h1></div>@include('partials.user-badge')</header>
            <section class="conversation-layout">
                @php $canAttachImages = $inquiry->booking?->status === 'confirmed'; @endphp
                <div class="chat-card" data-realtime-chat data-messages-url="{{ route('inquiries.messages.index', $inquiry) }}" data-typing-url="{{ route('inquiries.typing', $inquiry) }}">
                    @if (session('status'))<div class="flash-message account-alert">{{ session('status') }}</div>@endif
                    @if ($errors->any())<div class="oauth-error account-alert" role="alert">{{ $errors->first() }}</div>@endif
                    <div class="chat-heading"><div><span class="eyebrow">Inquiry #{{ $inquiry->id }}</span><h2>Live chat</h2><p>{{ $inquiry->desired_start_at->format('M j, g:i A') }} – {{ $inquiry->desired_end_at->format('M j, g:i A') }} · {{ $inquiry->party_size }} {{ Str::plural('person', $inquiry->party_size) }}</p></div><div class="chat-heading-status"><span class="live-chat-status"><i></i> Live</span><span class="inquiry-status status-{{ $inquiry->status }}">{{ str($inquiry->status)->replace('_', ' ')->title() }}</span></div></div>
                    <div class="message-thread" data-message-thread>
                        @foreach ($inquiry->messages as $message)
                            <article @class(['chat-message', 'mine' => $message->sender_id === auth()->id()]) data-message-id="{{ $message->id }}"><div><strong>{{ $message->sender_id === auth()->id() ? 'You' : $message->sender->name }}</strong><time>{{ $message->created_at->format('M j, g:i A') }}</time></div>@if ($message->body !== '')<p>{{ $message->body }}</p>@endif @if ($message->attachment_path)<a class="chat-image-attachment" href="{{ route('inquiries.attachments.show', $message) }}" target="_blank"><img src="{{ route('inquiries.attachments.show', $message) }}" alt="{{ $message->attachment_name ?: 'Chat attachment' }}"><small>{{ $message->attachment_name }}</small></a>@endif</article>
                        @endforeach
                        @if ($inquiry->booking)
                            <article class="chat-booking-request" data-booking-request-id="{{ $inquiry->booking->id }}">
                                <span>{{ $inquiry->booking->status === 'pending' ? '◷' : ($inquiry->booking->status === 'confirmed' ? '✓' : '×') }}</span>
                                <div><small>Booking request</small><strong>{{ auth()->id() === $inquiry->host_id ? $inquiry->client->name.' requested this booking.' : 'Your booking request was sent.' }}</strong><p>{{ $inquiry->booking->start_at->format('M j, Y · g:i A') }} – {{ $inquiry->booking->end_at->format('M j, Y · g:i A') }} · ₱{{ number_format($inquiry->booking->total_amount, 2) }}</p><a href="{{ route('bookings.show', $inquiry->booking) }}">View request</a></div>
                                <em class="booking-status status-{{ $inquiry->booking->status }}">{{ ucfirst($inquiry->booking->status) }}</em>
                            </article>
                        @endif
                    </div>
                    <div class="chat-typing-indicator" data-typing-indicator hidden><span></span><span></span><span></span><strong data-typing-text></strong></div>
                    @if ($inquiry->status !== 'closed')
                        <form method="POST" action="{{ route('inquiries.messages.store', $inquiry) }}" enctype="multipart/form-data" class="chat-composer" data-chat-composer>
                            @csrf
                            <div class="chat-composer-main">
                                <label for="message" class="sr-only">Message</label>
                                <textarea id="message" name="message" rows="3" maxlength="2000" placeholder="Write a message…" @required(! $canAttachImages)>{{ old('message') }}</textarea>
                                <div class="chat-composer-tools">
                                    <button class="chat-tool-button" type="button" data-emoji-toggle aria-expanded="false" aria-label="Add emoji">☺ <span>Emoji</span></button>
                                    <div class="emoji-picker" data-emoji-picker hidden>
                                        @foreach (['😀','😂','😊','😍','👍','🙏','🎉','❤️','🚗','🏢','📍','🕐','✅','❓','👋','🐾'] as $emoji)<button type="button" data-emoji="{{ $emoji }}" aria-label="Add {{ $emoji }}">{{ $emoji }}</button>@endforeach
                                    </div>
                                    @if ($canAttachImages)
                                        <label class="chat-tool-button chat-attachment-button" for="attachment">▧ <span>Attach image</span></label>
                                        <input id="attachment" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" data-chat-attachment>
                                        <small data-attachment-name></small>
                                    @else
                                        <span class="chat-attachment-locked" title="Image attachments unlock after booking approval">🔒 Images unlock after approval</span>
                                    @endif
                                </div>
                            </div>
                            <button class="button button-primary" type="submit" data-send-message>Send</button>
                            <p class="chat-send-error" data-chat-error hidden></p>
                        </form>
                    @endif
                </div>
                <aside class="inquiry-context-card" data-live-inquiry-context>
                    @php $partner = auth()->id() === $inquiry->client_id ? $inquiry->host : $inquiry->client; @endphp
                    <div class="context-partner">@include('partials.avatar', ['avatarUser' => $partner, 'avatarClass' => 'context-partner-avatar'])<div><small>{{ auth()->id() === $inquiry->client_id ? 'Host' : 'Booking customer' }}</small><strong>{{ $partner->name }}</strong><em>✓ Profile complete</em></div></div>
                    <a class="button button-ghost" href="{{ route('profiles.show', $partner) }}">View validation profile</a>
                    <div class="context-details"><span><small>Listing</small><strong>{{ $inquiry->unit->name }}</strong></span><span><small>Desired schedule</small><strong>{{ $inquiry->desired_start_at->format('M j') }} – {{ $inquiry->desired_end_at->format('M j, Y') }}</strong></span><span><small>Party</small><strong>{{ $inquiry->party_size }} {{ Str::plural('person', $inquiry->party_size) }}</strong></span></div>
                    @php
                        $pendingPriceProposal = $inquiry->priceProposals->firstWhere('status', 'pending');
                        $rateLabels = ['12_hours' => '12 hours', 'day' => '1 day', 'week' => '1 week', 'month' => '1 month'];
                        $coverageLabels = ['within_city' => 'Within Davao City', 'out_of_town' => 'Out of town', 'standard' => 'Standard'];
                    @endphp
                    <section class="standard-inquiry-pricing">
                        <div class="standard-inquiry-pricing-heading"><span>₱</span><div><small>Standard listing price</small><strong>{{ $inquiry->unit->isPackageRental() ? 'Host rates for this listing' : '₱'.number_format($inquiry->unit->price, 2).' / '.$inquiry->unit->pricing_unit }}</strong></div></div>
                        @if ($inquiry->unit->isPackageRental() && $inquiry->unit->rates->isNotEmpty())
                            <div class="standard-inquiry-rate-grid">
                                @foreach ($inquiry->unit->rates->groupBy('coverage') as $coverage => $rates)
                                    <article><small>{{ $coverageLabels[$coverage] ?? str($coverage)->replace('_', ' ')->title() }}</small>@foreach($rates as $rate)<span><b>{{ $rateLabels[$rate->period] ?? str($rate->period)->replace('_', ' ')->title() }}</b><strong>₱{{ number_format($rate->price, 2) }}</strong></span>@endforeach</article>
                                @endforeach
                            </div>
                        @endif
                        <p>Standard pricing applies unless the client and host both accept a negotiated proposal.</p>
                    </section>
                    <section class="price-negotiation-panel">
                        <div class="price-negotiation-heading"><span>↔</span><div><small>Price negotiation</small><strong>{{ $inquiry->agreed_price !== null ? 'Agreed at ₱'.number_format($inquiry->agreed_price, 2) : ($pendingPriceProposal ? 'A proposal needs attention' : 'Optional — request only when needed') }}</strong></div></div>
                        @if ($inquiry->agreed_price !== null)<p class="agreed-price-note">✓ This price is locked as the booking subtotal. Required listing charges, if any, are added separately.</p>@endif
                        @if ($pendingPriceProposal)
                            <article class="price-proposal-current"><small>{{ $pendingPriceProposal->proposed_by === auth()->id() ? 'Your proposal' : $pendingPriceProposal->proposer->name.' proposed' }}</small><strong>₱{{ number_format($pendingPriceProposal->amount, 2) }}</strong>@if($pendingPriceProposal->note)<p>{{ $pendingPriceProposal->note }}</p>@endif
                                @if ($pendingPriceProposal->proposed_by !== auth()->id())<div><form method="POST" action="{{ route('price-proposals.review', $pendingPriceProposal) }}">@csrf @method('PATCH')<button class="button button-primary" name="decision" value="accept" type="submit">Accept price</button></form><form method="POST" action="{{ route('price-proposals.review', $pendingPriceProposal) }}">@csrf @method('PATCH')<button class="button button-ghost" name="decision" value="decline" type="submit">Decline</button></form></div>@else<em>Waiting for {{ auth()->id() === $inquiry->client_id ? 'host' : 'client' }} approval</em>@endif
                            </article>
                        @endif
                        @unless($inquiry->booking)
                            <details class="price-proposal-form"><summary>{{ $pendingPriceProposal ? 'Send a counteroffer' : ($inquiry->agreed_price !== null ? 'Propose a different price' : 'Request price negotiation') }}</summary><form method="POST" action="{{ route('inquiries.price-proposals.store', $inquiry) }}">@csrf<div class="field-group"><label for="amount">Proposed booking subtotal</label><input id="amount" name="amount" type="number" min="1" max="99999999.99" step="0.01" required placeholder="0.00" value="{{ old('amount') }}"></div><div class="field-group"><label for="price_note">Message <span class="optional-label">Optional</span></label><textarea id="price_note" name="note" rows="2" maxlength="1000" placeholder="Explain your offer…">{{ old('note') }}</textarea></div><button class="button button-primary button-full" type="submit">Send price proposal</button></form></details>
                        @endunless
                        @if ($inquiry->priceProposals->where('status', '!=', 'pending')->isNotEmpty())<details class="price-proposal-history"><summary>Price history</summary>@foreach($inquiry->priceProposals->where('status', '!=', 'pending') as $proposal)<span><b>₱{{ number_format($proposal->amount, 2) }}</b><small>{{ ucfirst($proposal->status) }} · {{ $proposal->proposer->name }}</small></span>@endforeach</details>@endif
                    </section>
                    @if ($inquiry->booking)
                        <div class="inquiry-booking-state"><small>Booking request</small><strong>{{ ucfirst($inquiry->booking->status) }}</strong><span>₱{{ number_format($inquiry->booking->total_amount, 2) }}</span></div>
                        <a class="button button-primary button-full booking-detail-return" href="{{ route('bookings.show', $inquiry->booking) }}">View booking details</a>
                        @if ($canAttachImages)<small class="context-note">✓ Booking approved — private image attachments are now enabled in chat.</small>@endif
                    @elseif (auth()->id() === $inquiry->client_id)
                        <a class="button button-primary button-full" href="{{ route('calendar.index', ['mode' => 'book', 'category' => $inquiry->unit->category, 'search' => 1, 'search_start' => $inquiry->desired_start_at->format('Y-m-d\TH:i'), 'search_end' => $inquiry->desired_end_at->format('Y-m-d\TH:i'), 'party_size' => $inquiry->party_size, 'selected_unit' => $inquiry->unit_id]) }}#booking-selection">Continue to booking</a>
                        <small class="context-note">You have completed the required inquiry and may now request the booking.</small>
                    @endif
                </aside>
            </section>
        </main>
    </div>
@endsection
