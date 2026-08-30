<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SystemSetting extends Model
{
    protected $fillable = [
        'site_name',
        'short_name',
        'tagline',
        'description',
        'support_email',
        'support_phone',
        'primary_color',
        'secondary_color',
        'accent_color',
        'logo_path',
        'favicon_path',
        'updated_by',
    ];

    public static function defaults(): array
    {
        return [
            'site_name' => config('app.name', 'Davao Rent Zone'),
            'short_name' => 'DRZ',
            'tagline' => 'Rentals and services in one place',
            'description' => 'Find trusted rentals and local services, check availability, and book with confidence.',
            'support_email' => null,
            'support_phone' => null,
            'primary_color' => '#173c34',
            'secondary_color' => '#0f2d27',
            'accent_color' => '#d9ed8b',
        ];
    }

    public static function fallback(): self
    {
        return new self(self::defaults());
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getLogoUrlAttribute(): string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : asset('images/davao-rent-zone-logo-mark.svg');
    }

    public function getFaviconUrlAttribute(): string
    {
        return $this->favicon_path
            ? Storage::disk('public')->url($this->favicon_path)
            : asset('favicon.svg');
    }
}
