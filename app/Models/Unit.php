<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
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

    public function primaryImagePath(): ?string
    {
        return $this->images->first()?->path ?? $this->photo_path;
    }

    public function scopeAvailableBetween(Builder $query, mixed $start, mixed $end): Builder
    {
        return $query->where('is_active', true)->whereDoesntHave('bookings', function (Builder $bookings) use ($start, $end) {
            $bookings->blocking()->where('start_at', '<', $end)->where('end_at', '>', $start);
        });
    }
}
