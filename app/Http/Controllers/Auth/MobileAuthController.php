<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MobileAuthHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MobileAuthController extends Controller
{
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
        $user = $handoff->consume((string) $request->query('token'));

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
