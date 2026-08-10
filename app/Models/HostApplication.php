<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostApplication extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_NEEDS_CHANGES = 'needs_changes';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'status',
        'account_type',
        'business_name',
        'business_registration_number',
        'business_document_path',
        'face_selfie_path',
        'id_selfie_path',
        'hosting_experience',
        'motivation',
        'payout_method',
        'payout_provider',
        'payout_account_name',
        'payout_account_number',
        'authority_confirmed_at',
        'safety_confirmed_at',
        'terms_accepted_at',
        'privacy_consented_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $hidden = [
        'business_registration_number',
        'business_document_path',
        'face_selfie_path',
        'id_selfie_path',
        'payout_account_number',
    ];

    protected function casts(): array
    {
        return [
            'business_registration_number' => 'encrypted',
            'payout_account_number' => 'encrypted',
            'authority_confirmed_at' => 'datetime',
            'safety_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_consented_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(HostApplicationStatusHistory::class)->latest();
    }

    public function canBeEditedByApplicant(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEEDS_CHANGES, self::STATUS_REJECTED], true)
            || ($this->status === self::STATUS_SUBMITTED && $this->needsIdentityImages());
    }

    public function needsIdentityImages(): bool
    {
        return ! $this->face_selfie_path || ! $this->id_selfie_path;
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }
}
