<?php

namespace App\Http\Controllers;

use App\Models\NativePushSubscription;
use App\Services\FirebaseCloudMessaging;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NativePushSubscriptionController extends Controller
{
    public function store(Request $request, FirebaseCloudMessaging $firebase): JsonResponse
    {
        if (! $firebase->isConfigured()) {
            return response()->json([
                'message' => 'Native push delivery is not configured on the server.',
            ], 503);
        }

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
            'platform' => ['required', Rule::in(['android'])],
            'device_name' => ['nullable', 'string', 'max:160'],
        ]);

        NativePushSubscription::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $validated['token'])],
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'last_registered_at' => now(),
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:4096'],
        ]);

        $request->user()->nativePushSubscriptions()
            ->where('token_hash', hash('sha256', $validated['token']))
            ->delete();

        return response()->json(['subscribed' => false]);
    }
}
