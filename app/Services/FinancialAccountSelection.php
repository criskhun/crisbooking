<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class FinancialAccountSelection
{
    public function resolve(User $owner, mixed $accountId, string $field = 'financial_account_id'): FinancialAccount
    {
        if (! filled($accountId)) {
            $message = $owner->financialAccounts()->where('is_active', true)->exists()
                ? 'Choose the account that received or sent this money.'
                : 'Add a cash, bank, or e-wallet account in Accounting before recording this payment.';

            throw ValidationException::withMessages([$field => $message]);
        }

        $account = $owner->financialAccounts()->where('is_active', true)->find($accountId);
        if (! $account) {
            throw ValidationException::withMessages([$field => 'Choose an active financial account owned by this booking host.']);
        }

        return $account;
    }
}
