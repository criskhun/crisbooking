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

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured yet. Add your Google OAuth credentials to the application environment.',
            ]);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }

        $email = Str::lower((string) $googleUser->getEmail());
        $isVerified = filter_var(
            data_get($googleUser->user, 'email_verified', data_get($googleUser->user, 'verified_email', false)),
            FILTER_VALIDATE_BOOL
        );

        if ($email === '' || ! $isVerified) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google did not provide a verified email address for this account.',
            ]);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();

            if ($user?->google_id && $user->google_id !== $googleUser->getId()) {
                return redirect()->route('login')->withErrors([
                    'google' => 'This email address is already connected to another Google account.',
                ]);
            }
        }

        if ($user) {
            if (! $user->is_active) {
                return redirect()->route('login')->withErrors([
                    'google' => 'This account has been suspended. Contact an administrator for help.',
                ]);
            }

            $user->forceFill([
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Str::random(64),
                'google_id' => $googleUser->getId(),
                'google_avatar' => $googleUser->getAvatar(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'You are signed in with Google.');
    }
}
