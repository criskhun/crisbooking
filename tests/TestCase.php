<?php

namespace Tests;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function financialAccount(User $owner, array $attributes = []): FinancialAccount
    {
        return FinancialAccount::create(array_merge([
            'user_id' => $owner->id,
            'accounting_type' => 'assets',
            'account_category' => 'cash_and_cash_equivalents',
            'name' => 'Business cash',
            'type' => 'cash',
            'opening_balance' => 0,
            'opened_on' => today(),
            'is_active' => true,
        ], $attributes));
    }
}
