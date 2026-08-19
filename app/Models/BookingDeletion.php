<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDeletion extends Model
{
    protected $fillable = [
        'original_booking_id', 'unit_id', 'host_id', 'client_id', 'removed_by',
        'booking_origin', 'booking_status', 'source_channel', 'unit_name', 'host_name',
        'customer_name', 'start_at', 'end_at', 'total_amount', 'removal_reason',
        'booking_snapshot', 'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'booking_snapshot' => 'array',
            'removed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function remover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
