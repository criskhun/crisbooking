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
                <aside class="inquiry-context-card">
                    @php $partner = auth()->id() === $inquiry->client_id ? $inquiry->host : $inquiry->client; @endphp
                    <div class="context-partner"><span>{{ strtoupper(substr($partner->name, 0, 1)) }}</span><div><small>{{ auth()->id() === $inquiry->client_id ? 'Host' : 'Booking customer' }}</small><strong>{{ $partner->name }}</strong><em>✓ Profile complete</em></div></div>
                    <a class="button button-ghost" href="{{ route('profiles.show', $partner) }}">View validation profile</a>
                    <div class="context-details"><span><small>Listing</small><strong>{{ $inquiry->unit->name }}</strong></span><span><small>Desired schedule</small><strong>{{ $inquiry->desired_start_at->format('M j') }} – {{ $inquiry->desired_end_at->format('M j, Y') }}</strong></span><span><small>Party</small><strong>{{ $inquiry->party_size }} {{ Str::plural('person', $inquiry->party_size) }}</strong></span></div>
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
