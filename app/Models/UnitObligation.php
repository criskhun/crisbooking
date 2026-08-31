<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitObligation extends Model
{
    public const CATEGORY_LABELS = [
        'amortization' => 'Monthly amortization / financing',
        'association_dues' => 'Association dues',
        'insurance' => 'Insurance plan',
        'lease' => 'Lease payment',
        'other' => 'Other recurring payable',
    ];

    protected $fillable = [
        'unit_id', 'created_by_user_id', 'name', 'category', 'monthly_amount',
        'start_month', 'term_months', 'due_day', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'monthly_amount' => 'decimal:2',
            'start_month' => 'date',
            'term_months' => 'integer',
            'due_day' => 'integer',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(UnitObligationPayment::class)->orderBy('installment_month');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? str($this->category)->replace('_', ' ')->title();
    }

    public function endMonth(): Carbon
    {
        return $this->start_month->copy()->startOfMonth()->addMonths(max(0, $this->term_months - 1));
    }
}
