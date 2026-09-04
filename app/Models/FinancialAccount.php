<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    public const CATEGORY_LABELS = [
        'assets' => 'Assets',
        'revenue' => 'Revenue',
        'expenses' => 'Expenses',
        'liabilities' => 'Liabilities',
        'equity' => 'Equity',
    ];

    public const CATEGORY_ACCOUNT_SUGGESTIONS = [
        'assets' => ['BDO', 'GCash', 'Cash', 'Security Deposits Receivable'],
        'revenue' => ['Condo Rental Income', 'Car Rental Income'],
        'expenses' => ['Electricity', 'Water', 'Drinking Water', 'Cleaning', 'Repairs', 'Platform Fees'],
        'liabilities' => ['Payables', 'Guest Deposits'],
        'equity' => ['Owner’s Capital', 'Owner’s Drawings'],
    ];

    public const TYPE_LABELS = [
        'cash' => 'Cash on hand',
        'bank' => 'Bank account',
        'e_wallet' => 'E-wallet',
        'credit_card' => 'Credit card',
        'other' => 'Other account',
    ];

    protected $fillable = [
        'user_id', 'category', 'name', 'type', 'institution_name', 'last_four',
        'opening_balance', 'opened_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opened_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookingFinancialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class);
    }

    public function bookingExpenses(): HasMany
    {
        return $this->hasMany(BookingExpense::class);
    }

    public function unitCosts(): HasMany
    {
        return $this->hasMany(UnitCost::class);
    }

    public function obligationPayments(): HasMany
    {
        return $this->hasMany(UnitObligationPayment::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? str($this->type)->replace('_', ' ')->title();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? str($this->category)->replace('_', ' ')->title();
    }

    public function displayLabel(): string
    {
        $suffix = $this->last_four ? ' · •••• '.$this->last_four : '';

        return $this->name.$suffix;
    }

    public function selectionLabel(): string
    {
        return $this->categoryLabel().' → '.$this->displayLabel();
    }
}
