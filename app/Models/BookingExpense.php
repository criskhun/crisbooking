<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingExpense extends Model
{
    public const RESIDENCE_CATEGORIES = [
        'cleaning' => 'Cleaning fee',
        'laundry' => 'Laundry',
        'drinking_water' => 'Drinking water',
        'guest_supplies' => 'Guest supplies',
        'utilities' => 'Utilities',
        'property_maintenance' => 'Property maintenance',
        'other' => 'Other expense',
    ];

    public const CAR_CATEGORIES = [
        'delivery' => 'Vehicle delivery',
        'car_wash' => 'Carwash',
        'fuel' => 'Fuel',
        'vehicle_maintenance' => 'Vehicle maintenance',
        'repair' => 'Repair or parts',
        'toll_parking' => 'Toll or parking',
        'other' => 'Other expense',
    ];

    protected $fillable = [
        'booking_id', 'recorded_by_user_id', 'financial_account_id', 'provider_user_id', 'service_unit_id', 'service_provider_application_id',
        'category', 'vendor_name', 'amount', 'status', 'notes', 'completion_images',
        'payment_proof_path', 'payment_proof_name', 'scheduled_at', 'completed_at', 'paid_at',
        'payment_received_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
            'payment_received_at' => 'datetime',
            'completion_images' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function serviceUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'service_unit_id');
    }

    public function providerApplication(): BelongsTo
    {
        return $this->belongsTo(ServiceProviderApplication::class, 'service_provider_application_id');
    }

    public static function categoryOptions(string $bookingCategory): array
    {
        return $bookingCategory === 'car' ? self::CAR_CATEGORIES : self::RESIDENCE_CATEGORIES;
    }

    public static function compatibleProviderServices(string $expenseCategory): array
    {
        return match ($expenseCategory) {
            'repair' => ['vehicle_maintenance'],
            'property_maintenance', 'guest_supplies', 'utilities', 'fuel', 'toll_parking' => ['other'],
            default => [$expenseCategory],
        };
    }

    public function categoryLabel(): string
    {
        return (self::RESIDENCE_CATEGORIES + self::CAR_CATEGORIES)[$this->category]
            ?? str($this->category)->replace('_', ' ')->title();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'assigned' => 'Assigned',
            'completed' => 'Completed — payment pending',
            'paid' => 'Paid — awaiting provider confirmation',
            'payment_received' => 'Closed — payment received',
            'cancelled' => 'Cancelled',
            default => 'Recorded',
        };
    }
}
