<?php

namespace App\Services;

use App\Models\NativePushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseCloudMessaging
{
    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        $credentialsPath = $this->credentialsPath();

        return filled(config('services.firebase.project_id'))
            && $credentialsPath !== null
            && is_file($credentialsPath)
            && is_readable($credentialsPath);
    }

    public function send(User $user, UserNotification $notification): void
    {
        if (! $this->isConfigured() || $user->nativePushSubscriptions()->doesntExist()) {
            return;
        }

        try {
            $accessToken = $this->accessToken();
            $projectId = (string) config('services.firebase.project_id');
        } catch (\Throwable $exception) {
            $this->logFailure($notification, $exception->getMessage());

            return;
        }

        $user->nativePushSubscriptions()->each(function (NativePushSubscription $subscription) use ($accessToken, $projectId, $notification): void {
            try {
                $response = Http::acceptJson()
                    ->withToken($accessToken)
                    ->timeout(12)
                    ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                        'message' => [
                            'token' => $subscription->token,
                            'notification' => [
                                'title' => $notification->title,
                                'body' => $notification->body,
                            ],
                            'data' => [
                                'url' => $notification->url,
                                'notification_id' => (string) $notification->id,
                                'type' => $notification->type,
                            ],
                            'android' => [
                                'priority' => 'high',
                                'notification' => [
                                    'channel_id' => 'davao_rent_zone_updates',
                                    'sound' => 'default',
                                    'tag' => 'notification-'.$notification->id,
                                ],
                            ],
                        ],
                    ]);

                if ($this->tokenIsInvalid($response->status(), $response->json())) {
                    $subscription->delete();

                    return;
                }

                if ($response->failed()) {
                    $this->logFailure($notification, 'FCM returned HTTP '.$response->status().'.');
                }
            } catch (\Throwable $exception) {
                $this->logFailure($notification, $exception->getMessage());
            }
        });
    }

    protected function accessToken(): string
    {
        $projectId = (string) config('services.firebase.project_id');

        return Cache::remember('firebase.messaging.access-token.'.hash('sha256', $projectId), now()->addMinutes(50), function (): string {
            $credentialsPath = $this->credentialsPath();

            if ($credentialsPath === null) {
                throw new RuntimeException('Firebase service account credentials are missing.');
            }

            $credentials = json_decode((string) file_get_contents($credentialsPath), true, flags: JSON_THROW_ON_ERROR);
            if (($credentials['type'] ?? null) !== 'service_account') {
                throw new RuntimeException('Firebase credentials must be a service account JSON file.');
            }

            $token = (new ServiceAccountCredentials(self::MESSAGING_SCOPE, $credentials))->fetchAuthToken();
            if (! filled($token['access_token'] ?? null)) {
                throw new RuntimeException('Firebase did not issue an access token.');
            }

            return $token['access_token'];
        });
    }

    private function credentialsPath(): ?string
    {
        $path = config('services.firebase.credentials');

        return is_string($path) && $path !== '' ? $path : null;
    }

    private function tokenIsInvalid(int $status, mixed $payload): bool
    {
        if (! in_array($status, [400, 404], true) || ! is_array($payload)) {
            return false;
        }

        $statusName = data_get($payload, 'error.status');
        $fcmError = collect(data_get($payload, 'error.details', []))
            ->firstWhere('@type', 'type.googleapis.com/google.firebase.fcm.v1.FcmError');

        return $statusName === 'NOT_FOUND'
            || data_get($fcmError, 'errorCode') === 'UNREGISTERED';
    }

    private function logFailure(UserNotification $notification, string $message): void
    {
        Log::warning('Native push notification could not be sent.', [
            'notification_id' => $notification->id,
            'exception' => $message,
        ]);
    }
}
