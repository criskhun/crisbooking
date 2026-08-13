<?php

namespace App\Services;

use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class AppNotificationService
{
    public function send(User $user, string $type, string $title, string $body, string $url): UserNotification
    {
        $notification = $user->appNotifications()->create(compact('type', 'title', 'body', 'url'));
        $this->sendPush($user, $notification);

        return $notification;
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
