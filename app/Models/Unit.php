<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Unit extends Model
{
    use HasFactory;

    protected $hidden = [
        'gps_details',
        'wifi_details',
        'wifi_qr_path',
    ];

    protected $fillable = [
        'host_id',
        'name',
        'kind',
        'category',
        'location',
        'latitude',
        'longitude',
        'description',
        'rules',
        'photo_path',
        'car_details',
        'gps_details',
        'wifi_details',
        'wifi_qr_path',
        'property_details',
        'capacity',
        'price',
        'pricing_unit',
        'sale_percentage',
        'calendar_color',
        'calendar_secondary_color',
        'calendar_use_gradient',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'sale_percentage' => 'decimal:2',
            'calendar_use_gradient' => 'boolean',
            'car_details' => 'array',
            'gps_details' => 'encrypted:array',
            'wifi_details' => 'encrypted:array',
            'property_details' => 'array',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Guest reviews written after a booking of this listing.
     */
    public function listingReviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, Booking::class)
            ->where('reviewee_context', 'host');
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class);
    }

    public function affiliatePartnerships(): BelongsToMany
    {
        return $this->belongsToMany(AffiliatePartnership::class)->withTimestamps();
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_units')->withTimestamps();
    }

    public function rates(): HasMany
    {
        return $this->hasMany(UnitRate::class)
            ->orderByRaw("CASE coverage WHEN 'within_city' THEN 1 WHEN 'out_of_town' THEN 2 ELSE 3 END")
            ->orderByRaw("CASE period WHEN '12_hours' THEN 1 WHEN 'day' THEN 2 WHEN 'week' THEN 3 WHEN 'month' THEN 4 ELSE 5 END");
    }

    public function ratesForCoverage(string $coverage): HasMany
    {
        return $this->rates()->where('coverage', $coverage);
    }

    public function images(): HasMany
    {
        return $this->hasMany(UnitImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function financialProfile(): HasOne
    {
        return $this->hasOne(UnitFinancialProfile::class);
    }

    public function costs(): HasMany
    {
        return $this->hasMany(UnitCost::class)->latest('incurred_on');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(UnitObligation::class)->latest();
    }

    public function publicUrl(?string $referralCode = null): string
    {
        return route('listings.show', array_filter(['unit' => $this, 'ref' => $referralCode]));
    }

    public function isPackageRental(): bool
    {
        return in_array($this->category, ['car', 'condo'], true);
    }

    public function hasRentalRates(): bool
    {
        return ! $this->isPackageRental() || $this->rates->isNotEmpty();
    }

    public function hasSale(): bool
    {
        return (float) $this->sale_percentage > 0;
    }

    public function discountedPrice(float|int|string|null $price): float
    {
        $amount = (float) $price;

        return $this->hasSale()
            ? round($amount * (1 - ((float) $this->sale_percentage / 100)), 2)
            : round($amount, 2);
    }

    public function startingPrice(): float
    {
        $price = $this->isPackageRental() ? $this->rates->min('price') : $this->price;

        return round((float) $price, 2);
    }

    public function primaryImagePath(): ?string
    {
        return $this->images->first()?->path ?? $this->photo_path;
    }

    public function condoCheckInTime(): string
    {
        $time = (string) ($this->property_details['check_in_time'] ?? '14:00');

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : '14:00';
    }

    public function condoCheckOutTime(): string
    {
        $time = (string) ($this->property_details['check_out_time'] ?? '12:00');

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) ? $time : '12:00';
    }

    /**
     * Apply the host's fixed property arrival and departure times.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function standardizeBookingPeriod(Carbon $start, Carbon $end): array
    {
        if ($this->category !== 'condo') {
            return [$start, $end];
        }

        [$checkInHour, $checkInMinute] = array_map('intval', explode(':', $this->condoCheckInTime()));
        [$checkOutHour, $checkOutMinute] = array_map('intval', explode(':', $this->condoCheckOutTime()));

        return [
            $start->copy()->setTime($checkInHour, $checkInMinute),
            $end->copy()->setTime($checkOutHour, $checkOutMinute),
        ];
    }

    public function scopeAvailableBetween(Builder $query, mixed $start, mixed $end): Builder
    {
        return $query->where('is_active', true)->whereDoesntHave('bookings', function (Builder $bookings) use ($start, $end) {
            $bookings->blocking()->where('start_at', '<', $end)->where('end_at', '>', $start);
        });
    }
}
