<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'token', 'token_hash', 'platform', 'device_name', 'user_agent', 'last_registered_at'])]
class NativePushSubscription extends Model
{
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_registered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
