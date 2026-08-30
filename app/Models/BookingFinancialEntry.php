<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingFinancialEntry extends Model
{
    public const CATEGORY_LABELS = [
        'full_payment' => 'Full payment',
        'downpayment' => 'Downpayment / reservation',
        'balance_payment' => 'Balance collection',
        'security_deposit' => 'Security deposit collected',
        'security_deposit_refund' => 'Security deposit returned',
        'security_deposit_application' => 'Security deposit applied to charges',
        'damage' => 'Damage fee',
        'late_checkout' => 'Late check-out penalty',
        'smoking' => 'Smoking penalty',
        'excessive_cleaning' => 'Garbage / excessive cleaning',
        'other_penalty' => 'Other penalty or charge',
    ];

    protected $fillable = ['booking_id', 'recorded_by_user_id', 'kind', 'category', 'amount', 'notes', 'occurred_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryRevision::class)->latest();
    }

    public function label(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? str($this->category)->replace('_', ' ')->title();
    }
}
