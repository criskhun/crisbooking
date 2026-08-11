<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePartnership extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketer_id', 'host_id', 'status', 'commission_percentage', 'referral_code',
        'application_message', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'commission_percentage' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketer_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AffiliateMessage::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function involves(User $user): bool
    {
        return $user->is_admin || $this->marketer_id === $user->id || $this->host_id === $user->id;
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }
}
