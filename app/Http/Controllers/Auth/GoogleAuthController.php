<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobileAuthHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, MobileAuthHandoff $handoff): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            if ($request->query('mobile') === 'android') {
                return redirect()->away($handoff->error('google', 'Google sign-in is not configured yet.'));
            }

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured yet. Add your Google OAuth credentials to the application environment.',
            ]);
        }

        if ($request->query('mobile') === 'android') {
            $request->session()->put(MobileAuthHandoff::SESSION_KEY, 'android');
        } else {
            $request->session()->forget(MobileAuthHandoff::SESSION_KEY);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, MobileAuthHandoff $handoff): RedirectResponse
    {
        $mobileTarget = $request->session()->pull(MobileAuthHandoff::SESSION_KEY);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure($handoff, $mobileTarget, 'Google sign-in could not be completed. Please try again.');
        }

        $email = Str::lower((string) $googleUser->getEmail());
        $isVerified = filter_var(
            data_get($googleUser->user, 'email_verified', data_get($googleUser->user, 'verified_email', false)),
            FILTER_VALIDATE_BOOL
        );

        if ($email === '' || ! $isVerified) {
            return $this->failure($handoff, $mobileTarget, 'Google did not provide a verified email address for this account.');
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if (! $user) {
            $user = User::query()->where('email', $email)->first();

            if ($user?->google_id && $user->google_id !== $googleUser->getId()) {
                return $this->failure($handoff, $mobileTarget, 'This email address is already connected to another Google account.');
            }
        }

        if ($user) {
            if (! $user->is_active) {
                return $this->failure($handoff, $mobileTarget, 'This account has been suspended. Contact an administrator for help.');
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

        if ($mobileTarget === 'android') {
            $redirect = $handoff->issue($user);

            return redirect()->away($redirect);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('status', 'You are signed in with Google.');
    }

    private function failure(MobileAuthHandoff $handoff, mixed $mobileTarget, string $message): RedirectResponse
    {
        if ($mobileTarget === 'android') {
            return redirect()->away($handoff->error('google', $message));
        }

        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
