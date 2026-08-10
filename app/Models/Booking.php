<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'inquiry_id',
        'unit_rate_id',
        'client_id',
        'start_at',
        'end_at',
        'change_start_at',
        'change_end_at',
        'status',
        'rate_period',
        'rental_coverage',
        'rate_quantity',
        'package_breakdown',
        'additional_charges',
        'total_amount',
        'party_size',
        'change_party_size',
        'change_package_breakdown',
        'change_request_status',
        'change_request_note',
        'change_requested_at',
        'change_reviewed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'change_start_at' => 'datetime',
            'change_end_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'rate_quantity' => 'integer',
            'package_breakdown' => 'array',
            'additional_charges' => 'array',
            'party_size' => 'integer',
            'change_party_size' => 'integer',
            'change_package_breakdown' => 'array',
            'change_requested_at' => 'datetime',
            'change_reviewed_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(UnitRate::class, 'unit_rate_id');
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'confirmed']);
    }

    public function hasPendingChangeRequest(): bool
    {
        return $this->change_request_status === 'pending';
    }

    public function packageQuantityFor(Carbon $start, Carbon $end): int
    {
        if (! $this->rate_period) {
            return 1;
        }

        if ($this->rate_period === 'month') {
            $quantity = 1;

            while ($start->copy()->addMonthsNoOverflow($quantity)->lt($end)) {
                $quantity++;
            }

            return $quantity;
        }

        $minutesPerPackage = match ($this->rate_period) {
            '12_hours' => 720,
            'day' => 1440,
            'week' => 10080,
            default => max(1, (int) $start->diffInMinutes($end)),
        };

        return max(1, (int) ceil($start->diffInMinutes($end) / $minutesPerPackage));
    }

    public function packageUnitPrice(): float
    {
        $charges = (float) collect($this->additional_charges ?? [])->sum('amount');

        return round(max(0, (float) $this->total_amount - $charges) / max(1, (int) $this->rate_quantity), 2);
    }

    public function packageTotalFor(Carbon $start, Carbon $end): float
    {
        return round($this->packageUnitPrice() * $this->packageQuantityFor($start, $end), 2);
    }

    public function refundableDepositAmount(): float
    {
        return round((float) collect($this->additional_charges ?? [])
            ->filter(fn ($charge) => (bool) ($charge['refundable'] ?? false))
            ->sum('amount'), 2);
    }

    public function revenueAmount(): float
    {
        return round(max(0, (float) $this->total_amount - $this->refundableDepositAmount()), 2);
    }
}
