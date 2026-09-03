<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MobileAuthHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class MobileAuthController extends Controller
{
    public function attempt(Request $request, MobileAuthHandoff $handoff): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['google', 'facebook'])],
        ]);
        $provider = $validated['provider'];
        $token = $handoff->prepare();

        return response()->json([
            'token' => $token,
            'authorization_url' => route("auth.{$provider}.redirect", [
                'mobile' => 'android',
                'handoff' => $token,
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function status(Request $request, MobileAuthHandoff $handoff): JsonResponse
    {
        $token = (string) $request->query('token');

        return response()->json([
            'ready' => $handoff->isReady($token),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function showReturn(Request $request): Response
    {
        $parameters = array_filter([
            'token' => $request->query('token'),
            'provider' => $request->query('provider'),
            'error' => $request->query('error'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');

        $query = http_build_query($parameters, encoding_type: PHP_QUERY_RFC3986);
        $appUrl = 'davaorentzone://auth/callback'.($query === '' ? '' : '?'.$query);
        $intentUrl = 'intent://auth/callback'.($query === '' ? '' : '?'.$query)
            .'#Intent;scheme=davaorentzone;package=com.davaorentzone.app;end';

        return response()->view('auth.mobile-return', [
            'appUrl' => $appUrl,
            'intentUrl' => $intentUrl,
            'hasError' => isset($parameters['error']),
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function complete(Request $request, MobileAuthHandoff $handoff): RedirectResponse
    {
        $token = (string) $request->query('token');
        $user = $handoff->consume($token);

        if (! $user || ! $user->is_active) {
            return redirect()->route('login')->withErrors([
                'mobile' => 'The mobile sign-in link expired or was already used. Please try again.',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'You are signed in securely on Android.');
    }
}
