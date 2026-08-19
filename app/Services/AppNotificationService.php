<?php

namespace App\Services;

use App\Mail\UnseenNotificationMail;
use App\Models\Booking;
use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class AppNotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        string $body,
        string $url,
        ?string $dedupeKey = null,
    ): UserNotification {
        $attributes = compact('type', 'title', 'body', 'url');
        $notification = $dedupeKey
            ? $user->appNotifications()->firstOrCreate(['dedupe_key' => $dedupeKey], $attributes)
            : $user->appNotifications()->create($attributes);

        if (! $notification->wasRecentlyCreated) {
            return $notification;
        }

        $this->sendPush($user, $notification);

        return $notification;
    }

    /**
     * Create time-sensitive reminders. Dedupe keys make repeated scheduler runs safe.
     *
     * @return array{created: int}
     */
    public function generateBookingReminders(int $limit = 500): array
    {
        $created = 0;
        $limit = max(1, $limit);

        Booking::query()
            ->with(['unit.host', 'client'])
            ->where('status', 'confirmed')
            ->where('booking_origin', 'platform')
            ->where('start_at', '>', now())
            ->where('start_at', '<=', now()->addDay())
            ->oldest('start_at')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use (&$created): void {
                $start = $booking->start_at->format('M j, Y \a\t g:i A');
                $recipients = [
                    [$booking->client, 'client'],
                    [$booking->unit->host, 'host'],
                ];

                foreach ($recipients as [$recipient, $role]) {
                    $notification = $this->send(
                        $recipient,
                        'booking_reminder',
                        'Booking starts within 24 hours',
                        $booking->unit->name.' is scheduled for '.$start.'.',
                        route('bookings.show', $booking),
                        "booking:{$booking->id}:start-reminder:{$role}",
                    );
                    $created += (int) $notification->wasRecentlyCreated;
                }
            });

        Booking::query()
            ->with(['unit.host', 'client'])
            ->where('status', 'confirmed')
            ->where('booking_origin', 'platform')
            ->where('end_at', '<=', now())
            ->where('end_at', '>=', now()->subDays(7))
            ->latest('end_at')
            ->limit($limit)
            ->get()
            ->each(function (Booking $booking) use (&$created): void {
                $recipients = [
                    [$booking->client, 'client'],
                    [$booking->unit->host, 'host'],
                ];

                foreach ($recipients as [$recipient, $role]) {
                    $notification = $this->send(
                        $recipient,
                        'review_reminder',
                        'Share your booking experience',
                        'Your booking for '.$booking->unit->name.' has ended. You can now review the other party.',
                        route('bookings.show', $booking),
                        "booking:{$booking->id}:review-reminder:{$role}",
                    );
                    $created += (int) $notification->wasRecentlyCreated;
                }
            });

        return compact('created');
    }

    /**
     * Send one email for each notification the recipient has not seen in the app.
     *
     * @return array{sent: int, failed: int}
     */
    public function sendUnseenEmailFallbacks(int $limit = 500): array
    {
        $delay = max(1, (int) config('services.notifications.email_fallback_after_minutes', 5));
        $notifications = UserNotification::query()
            ->with('user')
            ->whereNull('seen_at')
            ->whereNull('read_at')
            ->whereNull('email_sent_at')
            ->where(fn ($query) => $query
                ->whereNull('email_claimed_at')
                ->orWhere('email_claimed_at', '<', now()->subMinutes(15)))
            ->where('created_at', '<=', now()->subMinutes($delay))
            ->whereHas('user', fn ($query) => $query->whereNotNull('email')->where('email', '!=', ''))
            ->oldest('id')
            ->limit(max(1, $limit))
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            $claimed = UserNotification::query()
                ->whereKey($notification->id)
                ->whereNull('seen_at')
                ->whereNull('read_at')
                ->whereNull('email_sent_at')
                ->where(fn ($query) => $query
                    ->whereNull('email_claimed_at')
                    ->orWhere('email_claimed_at', '<', now()->subMinutes(15)))
                ->update(['email_claimed_at' => now()]);

            if (! $claimed) {
                continue;
            }

            $notification->refresh()->load('user');

            if ($notification->seen_at || $notification->read_at || ! filled($notification->user?->email)) {
                $notification->update(['email_claimed_at' => null]);

                continue;
            }

            try {
                Mail::to($notification->user->email)->send(new UnseenNotificationMail($notification->user, $notification));
                $notification->update(['email_sent_at' => now(), 'email_claimed_at' => null]);
                $sent++;
            } catch (\Throwable $exception) {
                $notification->update(['email_claimed_at' => null]);
                $failed++;
                Log::warning('Unseen-notification email could not be sent.', [
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return compact('sent', 'failed');
    }

    private function sendPush(User $user, UserNotification $notification): void
    {
        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');

        if (! $publicKey || ! $privateKey || $user->pushSubscriptions()->doesntExist()) {
            return;
        }

        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => config('services.webpush.subject', config('app.url')),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]]);
            $payload = json_encode([
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->url,
                'notification_id' => $notification->id,
                'icon' => asset('icons/icon-192.png'),
                'badge' => asset('icons/icon-192.png'),
            ], JSON_THROW_ON_ERROR);

            $user->pushSubscriptions()->each(function (StoredPushSubscription $stored) use ($webPush, $payload) {
                $subscription = Subscription::create([
                    'endpoint' => $stored->endpoint,
                    'publicKey' => $stored->public_key,
                    'authToken' => $stored->auth_token,
                    'contentEncoding' => $stored->content_encoding,
                ]);
                $report = $webPush->sendOneNotification($subscription, $payload, ['TTL' => 86400, 'urgency' => 'high']);

                if ($report->isSubscriptionExpired()) {
                    $stored->delete();
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('Web push notification could not be sent.', [
                'notification_id' => $notification->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
