<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCost extends Model
{
    public const CATEGORY_LABELS = [
        'electricity' => 'Electricity bill',
        'water' => 'Water bill',
        'internet' => 'Internet / connectivity',
        'maintenance' => 'Routine maintenance',
        'repair' => 'Repair or replacement parts',
        'insurance' => 'Insurance',
        'association_dues' => 'Association dues',
        'registration_tax' => 'Registration, tax, or permit',
        'capital_improvement' => 'Capital improvement / added unit value',
        'other' => 'Other unit cost',
    ];

    protected $fillable = [
        'unit_id', 'recorded_by_user_id', 'financial_account_id', 'category', 'classification', 'amount',
        'status', 'incurred_on', 'due_on', 'paid_at', 'vendor_name', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? str($this->category)->replace('_', ' ')->title();
    }
}
