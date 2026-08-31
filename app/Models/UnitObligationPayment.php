<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitObligationPayment extends Model
{
    protected $fillable = [
        'unit_obligation_id', 'recorded_by_user_id', 'installment_month', 'amount', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'installment_month' => 'date',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function obligation(): BelongsTo
    {
        return $this->belongsTo(UnitObligation::class, 'unit_obligation_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
