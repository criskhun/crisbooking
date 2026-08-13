<?php

namespace App\Services;

use App\Mail\InactiveUserAlertMail;
use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class AppNotificationService
{
    public function send(User $user, string $type, string $title, string $body, string $url): UserNotification
    {
        $notification = $user->appNotifications()->create(compact('type', 'title', 'body', 'url'));
        $this->sendPush($user, $notification);
        $this->sendEmailIfInactive($user, $notification);

        return $notification;
    }

    private function sendEmailIfInactive(User $user, UserNotification $notification): void
    {
        if (! filled($user->email)) {
            return;
        }

        $lastSeenAt = User::query()->whereKey($user->getKey())->value('last_seen_at');
        $inactiveMinutes = max(1, (int) config('services.notifications.inactive_email_after_minutes', 5));

        if ($lastSeenAt && Carbon::parse($lastSeenAt)->gt(now()->subMinutes($inactiveMinutes))) {
            return;
        }

        try {
            Mail::to($user->email)->send(new InactiveUserAlertMail($user, $notification));
        } catch (\Throwable $exception) {
            Log::warning('Inactive-user email notification could not be sent.', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);
        }
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
