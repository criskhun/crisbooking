<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingDetailRevision extends Model
{
    protected $fillable = [
        'booking_id',
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

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }

    /** @return array<int, array{label:string, before:string, after:string}> */
    public function changedDetails(): array
    {
        $labels = [
            'starts' => 'Start',
            'ends' => 'End',
            'guests_pax' => 'Guests / pax',
            'sales_source' => 'Sales source',
            'external_customer' => 'External customer',
            'affiliate' => 'Affiliate',
            'package' => 'Package',
        ];

        return collect($labels)
            ->filter(fn (string $label, string $key) => ($this->before_values[$key] ?? null) !== ($this->after_values[$key] ?? null))
            ->map(fn (string $label, string $key) => [
                'label' => $label,
                'before' => filled($this->before_values[$key] ?? null) ? (string) $this->before_values[$key] : 'Not set',
                'after' => filled($this->after_values[$key] ?? null) ? (string) $this->after_values[$key] : 'Not set',
            ])
            ->values()
            ->all();
    }
}
