<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id', 'type', 'title', 'body', 'url', 'dedupe_key',
        'seen_at', 'read_at', 'email_claimed_at', 'email_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'seen_at' => 'datetime',
            'read_at' => 'datetime',
            'email_claimed_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
