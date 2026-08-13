<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->appNotifications()->latest()->limit(30)->get();

        return response()->json([
            'unread_count' => $request->user()->appNotifications()->whereNull('read_at')->count(),
            'notifications' => $notifications->map(fn (UserNotification $notification) => $this->payload($notification)),
        ]);
    }

    public function read(Request $request, UserNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return response()->json(['read' => true, 'url' => $notification->url]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['read' => true]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        abort_unless(config('services.webpush.public_key') && config('services.webpush.private_key'), 503, 'Push notifications are not configured.');
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:1000'],
            'keys.auth' => ['required', 'string', 'max:1000'],
            'content_encoding' => ['nullable', 'string', 'max:30'],
        ]);

        $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $validated['endpoint'])],
            [
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['content_encoding'] ?? 'aes128gcm',
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]
        );

        return response()->json(['subscribed' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'url', 'max:4000']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $validated['endpoint']))->delete();

        return response()->json(['subscribed' => false]);
    }

    private function payload(UserNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $notification->url,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
            'time' => $notification->created_at->diffForHumans(),
        ];
    }
}
