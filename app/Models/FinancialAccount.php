<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    public const ACCOUNTING_TYPE_LABELS = [
        'assets' => 'Assets',
        'liabilities' => 'Liabilities',
        'equity' => 'Equity',
        'revenue' => 'Revenue',
        'expenses' => 'Expenses',
    ];

    public const ACCOUNT_CATEGORY_LABELS = [
        'assets' => [
            'cash_and_cash_equivalents' => 'Cash & Cash Equivalents',
            'accounts_receivable' => 'Accounts Receivable',
            'property_and_equipment' => 'Property & Equipment',
            'furniture_and_fixtures' => 'Furniture & Fixtures',
            'appliances' => 'Appliances',
        ],
        'liabilities' => [
            'accounts_payable' => 'Accounts Payable',
            'customer_guest_deposits' => 'Customer/Guest Deposits',
            'loans_and_payables' => 'Loans & Payables',
        ],
        'equity' => [
            'owners_capital' => 'Owner’s Capital',
            'owners_drawings' => 'Owner’s Drawings',
            'retained_earnings' => 'Retained Earnings',
        ],
        'revenue' => [
            'condo_rental_income' => 'Condo Rental Income',
            'car_rental_income' => 'Car Rental Income',
            'airport_transport_income' => 'Airport Transport Income',
            'pet_transport_income' => 'Pet Transport Income',
            'other_service_income' => 'Other Service Income',
            'other_income' => 'Other Income',
        ],
        'expenses' => [
            'utilities' => 'Utilities',
            'cleaning_and_housekeeping' => 'Cleaning & Housekeeping',
            'repairs_and_maintenance' => 'Repairs & Maintenance',
            'supplies_and_amenities' => 'Supplies & Amenities',
            'advertising_and_marketing' => 'Advertising & Marketing',
            'transportation_and_delivery' => 'Transportation & Delivery',
            'bank_and_payment_fees' => 'Bank & Payment Fees',
            'guest_refunds_and_discounts' => 'Guest Refunds & Discounts',
            'miscellaneous_expenses' => 'Miscellaneous Expenses',
        ],
    ];

    public const ACCOUNT_CATEGORY_DESCRIPTIONS = [
        'utilities' => 'electricity, water, internet',
        'cleaning_and_housekeeping' => 'cleaning services, laundry',
        'supplies_and_amenities' => 'toiletries, tissue, drinking water',
    ];

    public const TYPE_LABELS = [
        'cash' => 'Cash on hand',
        'bank' => 'Bank account',
        'e_wallet' => 'E-wallet',
        'credit_card' => 'Credit card',
        'other' => 'Other account',
    ];

    protected $fillable = [
        'user_id', 'accounting_type', 'account_category', 'name', 'type', 'institution_name', 'last_four',
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

    public static function categoriesFor(?string $accountingType): array
    {
        return self::ACCOUNT_CATEGORY_LABELS[$accountingType] ?? [];
    }

    public function accountingTypeLabel(): string
    {
        return self::ACCOUNTING_TYPE_LABELS[$this->accounting_type] ?? str($this->accounting_type)->replace('_', ' ')->title();
    }

    public function accountCategoryLabel(): string
    {
        return self::ACCOUNT_CATEGORY_LABELS[$this->accounting_type][$this->account_category]
            ?? str($this->account_category)->replace('_', ' ')->title();
    }

    public function displayLabel(): string
    {
        $suffix = $this->last_four ? ' · •••• '.$this->last_four : '';

        return $this->name.$suffix;
    }

    public function selectionLabel(): string
    {
        return $this->accountingTypeLabel().' → '.$this->accountCategoryLabel().' → '.$this->displayLabel();
    }
}
