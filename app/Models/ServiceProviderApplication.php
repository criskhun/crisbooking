<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProviderApplication extends Model
{
    public const SERVICE_OPTIONS = [
        'cleaning' => 'Cleaning',
        'laundry' => 'Laundry',
        'drinking_water' => 'Drinking water delivery',
        'delivery' => 'Vehicle delivery',
        'car_wash' => 'Carwash',
        'vehicle_maintenance' => 'Vehicle maintenance / repairs',
        'other' => 'Other operational help',
    ];

    protected $fillable = [
        'applicant_user_id', 'host_id', 'services', 'status', 'application_message', 'application_images',
        'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['services' => 'array', 'application_images' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(BookingExpense::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'accepted' => 'Approved provider',
            'declined' => 'Declined',
            default => 'Pending review',
        };
    }

    public function serviceLabels(): string
    {
        return collect($this->services)
            ->map(fn ($service) => self::SERVICE_OPTIONS[$service] ?? str($service)->replace('_', ' ')->title())
            ->join(', ');
    }
}
