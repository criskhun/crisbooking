<?php

namespace App\Support;

use App\Models\MobileAuthToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAuthHandoff
{
    public const SESSION_KEY = 'oauth_mobile_target';

    public const APP_ATTEMPT_SESSION_KEY = 'mobile_oauth_attempt_hash';

    public const BROWSER_TOKEN_SESSION_KEY = 'oauth_mobile_handoff_token';

    public function prepare(Request $request): string
    {
        $plainToken = bin2hex(random_bytes(32));

        $request->session()->put(
            self::APP_ATTEMPT_SESSION_KEY,
            hash('sha256', $plainToken),
        );

        return $plainToken;
    }

    public function rememberBrowserToken(Request $request, mixed $plainToken): void
    {
        if (! $this->hasValidFormat($plainToken)) {
            $request->session()->forget(self::BROWSER_TOKEN_SESSION_KEY);

            return;
        }

        $request->session()->put(self::BROWSER_TOKEN_SESSION_KEY, $plainToken);
    }

    public function pullBrowserToken(Request $request): ?string
    {
        $plainToken = $request->session()->pull(self::BROWSER_TOKEN_SESSION_KEY);

        return $this->hasValidFormat($plainToken) ? $plainToken : null;
    }

    public function belongsToAppSession(Request $request, mixed $plainToken): bool
    {
        $expectedHash = $request->session()->get(self::APP_ATTEMPT_SESSION_KEY);

        return is_string($expectedHash)
            && $this->hasValidFormat($plainToken)
            && hash_equals($expectedHash, hash('sha256', $plainToken));
    }

    public function isReady(string $plainToken): bool
    {
        if (! $this->hasValidFormat($plainToken)) {
            return false;
        }

        return MobileAuthToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function issue(User $user, ?string $preparedToken = null): string
    {
        $plainToken = $this->hasValidFormat($preparedToken)
            ? $preparedToken
            : Str::random(64);

        MobileAuthToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(3),
        ]);

        return route('auth.mobile.return', ['token' => $plainToken]);
    }

    public function error(string $provider, string $message): string
    {
        return route('auth.mobile.return', [
            'provider' => $provider,
            'error' => $message,
        ]);
    }

    public function consume(string $plainToken): ?User
    {
        if (! $this->hasValidFormat($plainToken)) {
            return null;
        }

        return DB::transaction(function () use ($plainToken): ?User {
            $token = MobileAuthToken::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $plainToken))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $token) {
                return null;
            }

            $token->forceFill(['used_at' => now()])->save();

            return $token->user;
        });
    }

    private function hasValidFormat(mixed $plainToken): bool
    {
        return is_string($plainToken)
            && strlen($plainToken) === 64
            && ctype_alnum($plainToken);
    }
}
