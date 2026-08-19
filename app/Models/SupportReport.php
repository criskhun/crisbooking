<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportReport extends Model
{
    public const CATEGORIES = [
        'booking_removal' => 'Remove a test or incorrect booking',
        'booking_issue' => 'Booking or calendar problem',
        'listing_issue' => 'Listing, vehicle, or service problem',
        'affiliate_issue' => 'Affiliate concern',
        'account_issue' => 'Account or access problem',
        'other' => 'Other concern',
    ];

    protected $fillable = [
        'reporter_id', 'unit_id', 'booking_id', 'category', 'subject', 'message',
        'status', 'admin_response', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? str($this->category)->replace('_', ' ')->title();
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title();
    }
}
