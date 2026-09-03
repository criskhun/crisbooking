<?php

namespace App\Support;

use App\Models\MobileAuthToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileAuthHandoff
{
    public const SESSION_KEY = 'oauth_mobile_target';

    public function issue(User $user): string
    {
        $plainToken = Str::random(64);

        MobileAuthToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes(3),
        ]);

        return 'davaorentzone://auth/callback?token='.rawurlencode($plainToken);
    }

    public function error(string $provider, string $message): string
    {
        return 'davaorentzone://auth/callback?'.http_build_query([
            'provider' => $provider,
            'error' => $message,
        ], encoding_type: PHP_QUERY_RFC3986);
    }

    public function consume(string $plainToken): ?User
    {
        if (strlen($plainToken) !== 64) {
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
}
