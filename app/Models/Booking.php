<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    public const MANUAL_SOURCE_OPTIONS = [
        'direct' => 'Direct / Davao Rent Zone',
        'airbnb' => 'Airbnb',
        'booking_com' => 'Booking.com',
        'agoda' => 'Agoda',
        'facebook' => 'Facebook / social media',
        'walk_in_phone' => 'Walk-in / phone',
        'affiliate' => 'Affiliate offline sale',
        'other' => 'Other source',
    ];

    protected $fillable = [
        'unit_id',
        'inquiry_id',
        'unit_rate_id',
        'client_id',
        'booked_by_user_id',
        'booking_origin',
        'source_channel',
        'source_details',
        'external_customer_name',
        'start_at',
        'end_at',
        'change_start_at',
        'change_end_at',
        'status',
        'rate_period',
        'rental_coverage',
        'fulfillment_method',
        'delivery_address',
        'rate_quantity',
        'package_breakdown',
        'additional_charges',
        'total_amount',
        'security_deposit_amount',
        'party_size',
        'change_party_size',
        'change_package_breakdown',
        'change_request_status',
        'change_request_note',
        'change_requested_at',
        'change_reviewed_at',
        'notes',
        'payment_proof_path',
        'payment_proof_name',
        'payment_submitted_at',
        'payment_reviewed_at',
        'affiliate_partnership_id',
        'affiliate_commission_percentage',
        'affiliate_commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'change_start_at' => 'datetime',
            'change_end_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'security_deposit_amount' => 'decimal:2',
            'rate_quantity' => 'integer',
            'package_breakdown' => 'array',
            'additional_charges' => 'array',
            'party_size' => 'integer',
            'change_party_size' => 'integer',
            'change_package_breakdown' => 'array',
            'change_requested_at' => 'datetime',
            'change_reviewed_at' => 'datetime',
            'payment_submitted_at' => 'datetime',
            'payment_reviewed_at' => 'datetime',
            'affiliate_commission_percentage' => 'decimal:2',
            'affiliate_commission_amount' => 'decimal:2',
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

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by_user_id');
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(UnitRate::class, 'unit_rate_id');
    }

    public function affiliatePartnership(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartnership::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function financialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BookingExpense::class)->latest();
    }

    public function detailRevisions(): HasMany
    {
        return $this->hasMany(BookingDetailRevision::class)->latest();
    }

    public function expenseTotal(): float
    {
        return round((float) $this->expenses->where('status', '!=', 'cancelled')->sum('amount'), 2);
    }

    public function netRevenueAmount(): float
    {
        return round($this->revenueAmount() - $this->expenseTotal(), 2);
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', ['pre_approved', 'payment_submitted', 'confirmed']);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'pre_approved', 'payment_submitted', 'confirmed']);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pre_approved' => 'Pre-approved',
            'payment_submitted' => 'Payment submitted',
            'unavailable' => 'No longer available',
            default => ucfirst($this->status),
        };
    }

    public function isManualBooking(): bool
    {
        return $this->booking_origin === 'manual';
    }

    public function sourceLabel(): string
    {
        return self::MANUAL_SOURCE_OPTIONS[$this->source_channel] ?? 'Davao Rent Zone';
    }

    public function sourceDisplayLabel(): string
    {
        return $this->sourceLabel().($this->source_details ? ' · '.$this->source_details : '');
    }

    public function customerDisplayName(): string
    {
        if ($this->isManualBooking()) {
            return $this->external_customer_name ?: 'External customer';
        }

        return $this->client?->name ?? 'Booking customer';
    }

    public function durationDays(): int
    {
        return max(1, (int) $this->start_at->copy()->startOfDay()->diffInDays($this->end_at->copy()->startOfDay()));
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
        return round(max(0, (float) $this->total_amount - $this->refundableDepositAmount()) + $this->chargeTotal(), 2);
    }

    public function chargeTotal(): float
    {
        return round((float) $this->financialEntries->where('kind', 'charge')->sum('amount'), 2);
    }

    public function paymentTotal(): float
    {
        return round((float) $this->financialEntries->whereIn('kind', ['payment', 'deposit_application'])->sum('amount'), 2);
    }

    public function outstandingBalance(): float
    {
        return round(max(0, $this->revenueAmount() - $this->paymentTotal()), 2);
    }

    public function securityDepositRequired(): float
    {
        return round((float) $this->security_deposit_amount + $this->refundableDepositAmount(), 2);
    }

    public function securityDepositHeld(): float
    {
        $collected = (float) $this->financialEntries->where('kind', 'deposit')->sum('amount');
        $released = (float) $this->financialEntries->whereIn('kind', ['deposit_refund', 'deposit_application'])->sum('amount');

        return round(max(0, $collected - $released), 2);
    }

    public function paymentStatusLabel(): string
    {
        if ($this->outstandingBalance() <= 0) {
            return 'Fully paid';
        }

        return $this->paymentTotal() > 0 ? 'Partially paid' : 'Unpaid';
    }
}
