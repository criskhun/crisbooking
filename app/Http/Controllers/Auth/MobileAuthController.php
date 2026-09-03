<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\MobileAuthHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MobileAuthController extends Controller
{
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
