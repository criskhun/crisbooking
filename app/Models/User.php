<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'email_verified_at', 'password', 'google_id', 'google_avatar', 'facebook_id', 'facebook_avatar', 'profile_image_path', 'calendar_feed_token', 'is_admin', 'is_active', 'role', 'phone', 'date_of_birth', 'nationality', 'address', 'country', 'province', 'city', 'barangay', 'bio', 'emergency_contact_name', 'emergency_contact_phone', 'government_id_type', 'government_id_number', 'government_id_path', 'profile_completed_at', 'last_seen_at'])]
#[Hidden(['password', 'remember_token', 'calendar_feed_token', 'government_id_number', 'government_id_path'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'date_of_birth' => 'date',
            'government_id_number' => 'encrypted',
            'profile_completed_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class, 'host_id');
    }

    public function favoriteUnits(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'favorite_units')->withTimestamps();
    }

    public function profileImages(): HasMany
    {
        return $this->hasMany(ProfileImage::class)->latest();
    }

    public function avatarUrl(): ?string
    {
        if ($this->profile_image_path) {
            return Storage::disk('public')->url($this->profile_image_path);
        }

        return $this->google_avatar ?: $this->facebook_avatar;
    }

    public function publicHostName(): string
    {
        $application = $this->relationLoaded('hostApplication')
            ? $this->hostApplication
            : $this->hostApplication()->first();

        return $application?->status === HostApplication::STATUS_APPROVED && filled($application->business_name)
            ? $application->business_name
            : $this->name;
    }

    public function unitDrafts(): HasMany
    {
        return $this->hasMany(UnitDraft::class, 'host_id');
    }

    public function hostApplication(): HasOne
    {
        return $this->hasOne(HostApplication::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function supportReports(): HasMany
    {
        return $this->hasMany(SupportReport::class, 'reporter_id');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function clientInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'client_id');
    }

    public function hostInquiries(): HasMany
    {
        return $this->hasMany(Inquiry::class, 'host_id');
    }

    public function marketerAffiliatePartnerships(): HasMany
    {
        return $this->hasMany(AffiliatePartnership::class, 'marketer_id');
    }

    public function hostAffiliatePartnerships(): HasMany
    {
        return $this->hasMany(AffiliatePartnership::class, 'host_id');
    }

    public function serviceAssignments(): HasMany
    {
        return $this->hasMany(BookingExpense::class, 'provider_user_id');
    }

    public function hasCompleteProfile(): bool
    {
        return $this->profile_completed_at !== null
            && collect([
                $this->phone, $this->date_of_birth, $this->nationality, $this->address,
                $this->country, $this->province, $this->city, $this->barangay, $this->bio, $this->emergency_contact_name,
                $this->emergency_contact_phone, $this->government_id_type,
                $this->government_id_number, $this->government_id_path,
            ])->every(fn ($value) => filled($value));
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function inquiryAttentionCount(): int
    {
        return Inquiry::query()
            ->when(! $this->is_admin, fn ($query) => $query->where(function ($participants) {
                $participants->where('client_id', $this->id)->orWhere('host_id', $this->id);
            }))
            ->whereHas('messages', fn ($messages) => $messages
                ->where('sender_id', '!=', $this->id)
                ->whereNull('read_at'))
            ->count();
    }
}
