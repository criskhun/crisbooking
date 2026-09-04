<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialAccountController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $request->mergeIfMissing(['category' => 'assets']);
        $this->normalizeMoney($request);
        $validated = $request->validate($this->rules($request));
        $request->user()->financialAccounts()->create($this->attributes($validated, true));

        return back()->with('status', 'Accounting account added. It is now available in categorized transaction selectors.');
    }

    public function update(Request $request, FinancialAccount $financialAccount): RedirectResponse
    {
        abort_unless($financialAccount->user_id === $request->user()->id, 403);
        $request->mergeIfMissing(['category' => $financialAccount->category]);
        $this->normalizeMoney($request);
        $validated = $request->validate($this->rules($request, $financialAccount));
        $financialAccount->update($this->attributes($validated));

        return back()->with('status', 'Accounting account updated. Existing ledger records were preserved.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request, ?FinancialAccount $account = null): array
    {
        return [
            'category' => ['required', Rule::in(array_keys(FinancialAccount::CATEGORY_LABELS))],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('financial_accounts')->where('user_id', $request->user()->id)->ignore($account?->id),
            ],
            'type' => ['required', Rule::in(array_keys(FinancialAccount::TYPE_LABELS))],
            'institution_name' => ['nullable', 'string', 'max:120'],
            'last_four' => ['nullable', 'regex:/^\d{2,4}$/'],
            'opening_balance' => ['required', 'numeric', 'between:-999999999.99,999999999.99'],
            'opened_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function attributes(array $validated, bool $defaultActive = false): array
    {
        return [
            'category' => $validated['category'],
            'name' => trim($validated['name']),
            'type' => $validated['type'],
            'institution_name' => filled($validated['institution_name'] ?? null) ? trim($validated['institution_name']) : null,
            'last_four' => filled($validated['last_four'] ?? null) ? $validated['last_four'] : null,
            'opening_balance' => round((float) $validated['opening_balance'], 2),
            'opened_on' => $validated['opened_on'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? $defaultActive),
        ];
    }

    private function normalizeMoney(Request $request): void
    {
        if ($request->filled('opening_balance')) {
            $request->merge(['opening_balance' => str_replace([',', '₱', ' '], '', (string) $request->input('opening_balance'))]);
        }
    }
}
