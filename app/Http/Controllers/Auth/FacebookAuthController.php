<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;
use Throwable;

class FacebookAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.facebook.client_id') || ! config('services.facebook.client_secret')) {
            return redirect()->route('login')->withErrors([
                'facebook' => 'Facebook sign-in is not configured yet. Add your Meta App ID and App Secret to the application environment.',
            ]);
        }

        return Socialite::driver('facebook')->scopes(['email'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $facebookUser = Socialite::driver('facebook')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'facebook' => 'Facebook sign-in could not be completed. Please try again.',
            ]);
        }

        $email = Str::lower((string) $facebookUser->getEmail());

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login')->withErrors([
                'facebook' => 'Facebook did not provide an email address. Please use Google or email registration instead.',
            ]);
        }

        $user = User::query()->where('facebook_id', $facebookUser->getId())->first();

        if (! $user && User::query()->where('email', $email)->exists()) {
            return redirect()->route('login')->withErrors([
                'facebook' => 'An account already uses this email. Sign in with its existing method; Facebook can be linked from account settings later.',
            ]);
        }

        if ($user) {
            if (! $user->is_active) {
                return redirect()->route('login')->withErrors([
                    'facebook' => 'This account has been suspended. Contact an administrator for help.',
                ]);
            }

            $user->forceFill([
                'facebook_avatar' => $facebookUser->getAvatar(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $facebookUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Str::random(64),
                'facebook_id' => $facebookUser->getId(),
                'facebook_avatar' => $facebookUser->getAvatar(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('status', 'You are signed in with Facebook.');
    }
}
