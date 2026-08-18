@php
    $calendarFeedUrl = auth()->user()->calendar_feed_token
        ? route('calendar.feed', ['user' => auth()->user(), 'token' => auth()->user()->calendar_feed_token])
        : null;
    $webcalUrl = $calendarFeedUrl ? preg_replace('/^https?:\/\//', 'webcal://', $calendarFeedUrl) : null;
    $googleSubscribeUrl = $calendarFeedUrl ? 'https://calendar.google.com/calendar/render?cid='.urlencode($webcalUrl) : null;
@endphp
<details class="calendar-integration-card">
    <summary><span>▣</span><div><strong>Connect your calendar</strong><small>Sync bookings with Google Calendar or iPhone / Apple Calendar</small></div><b>Set up</b></summary>
    <div class="calendar-integration-body">
        @if ($calendarFeedUrl)
            <p>This private subscription includes pending requests, payment steps, and confirmed bookings, and updates automatically when your calendar app refreshes.</p>
            <div class="calendar-integration-actions"><a class="button button-primary" href="{{ $googleSubscribeUrl }}" target="_blank" rel="noopener">Add to Google Calendar</a><a class="button button-ghost" href="{{ $webcalUrl }}">Add to iPhone / Apple</a></div>
            <label><span>Private subscription URL</span><div><input value="{{ $calendarFeedUrl }}" readonly data-calendar-feed-url><button type="button" data-copy-calendar-feed>Copy</button></div><small>Keep this link private. Anyone who has it can read your booking schedule.</small></label>
            <form method="POST" action="{{ route('calendar.integration.refresh') }}" onsubmit="return confirm('Create a new private link? Your existing Google or Apple subscription will stop updating until you add the new link.')">@csrf<button type="submit">Reset private link</button></form>
        @else
            <p>Create one private calendar subscription that works with Google Calendar, iPhone, iPad, and Mac Calendar. You can reset it anytime.</p>
            <form method="POST" action="{{ route('calendar.integration.refresh') }}">@csrf<button class="button button-primary" type="submit">Create calendar connection</button></form>
        @endif
    </div>
</details>
