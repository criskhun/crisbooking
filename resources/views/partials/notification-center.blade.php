<div class="notification-center"
     data-notification-center
     data-index-url="{{ route('notifications.index') }}"
     data-read-all-url="{{ route('notifications.read-all') }}"
     data-read-url-template="{{ url('/notifications/__ID__/read') }}"
     data-subscribe-url="{{ route('push-subscriptions.store') }}"
     data-unsubscribe-url="{{ route('push-subscriptions.destroy') }}"
     data-vapid-public-key="{{ config('services.webpush.public_key') }}">
    <button class="notification-bell" type="button" data-notification-toggle aria-expanded="false" aria-label="Open notifications">
        <span aria-hidden="true">♢</span>
        <b data-notification-count hidden>0</b>
    </button>
    <section class="notification-panel" data-notification-panel hidden aria-label="Notifications">
        <header><div><span class="profile-label">Updates</span><h2>Notifications</h2></div><button type="button" data-notifications-read-all>Mark all read</button></header>
        <div class="notification-list" data-notification-list></div>
        <p class="notification-empty" data-notification-empty>No notifications yet.</p>
        <footer>
            <button type="button" data-push-toggle>Enable mobile notifications</button>
            <small data-push-status>Receive updates even when the app is closed.</small>
        </footer>
    </section>
</div>
