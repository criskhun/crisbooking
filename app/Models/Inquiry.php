<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id', 'client_id', 'host_id', 'desired_start_at', 'desired_end_at',
        'party_size', 'status',
    ];

    protected function casts(): array
    {
        return [
            'desired_start_at' => 'datetime',
            'desired_end_at' => 'datetime',
            'party_size' => 'integer',
        ];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class); }
    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id'); }
    public function host(): BelongsTo { return $this->belongsTo(User::class, 'host_id'); }
    public function messages(): HasMany { return $this->hasMany(InquiryMessage::class); }
    public function booking(): HasOne { return $this->hasOne(Booking::class); }

    public function involves(User $user): bool
    {
        return $user->is_admin || $this->client_id === $user->id || $this->host_id === $user->id;
    }
}
