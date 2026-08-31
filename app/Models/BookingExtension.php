<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExtension extends Model
{
    protected $fillable = [
        'booking_id',
        'created_by_user_id',
        'duration_unit',
        'duration_quantity',
        'previous_end_at',
        'new_end_at',
        'additional_amount',
        'payment_status',
        'charge_entry_id',
        'payment_entry_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'duration_quantity' => 'integer',
            'previous_end_at' => 'datetime',
            'new_end_at' => 'datetime',
            'additional_amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function chargeEntry(): BelongsTo
    {
        return $this->belongsTo(BookingFinancialEntry::class, 'charge_entry_id');
    }

    public function paymentEntry(): BelongsTo
    {
        return $this->belongsTo(BookingFinancialEntry::class, 'payment_entry_id');
    }

    public function durationLabel(): string
    {
        return $this->duration_quantity.' '.str($this->duration_unit)->plural($this->duration_quantity);
    }

    public function paymentStatusLabel(): string
    {
        return $this->payment_status === 'paid' ? 'Paid' : 'Added to collectibles';
    }
}
