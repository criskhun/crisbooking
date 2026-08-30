<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFinancialEntryRevision extends Model
{
    protected $fillable = [
        'booking_financial_entry_id',
        'edited_by_user_id',
        'before_values',
        'after_values',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
        ];
    }

    public function financialEntry(): BelongsTo
    {
        return $this->belongsTo(BookingFinancialEntry::class, 'booking_financial_entry_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
