<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\UnitObligation;
use App\Services\FinancialAccountSelection;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitObligationController extends Controller
{
    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeUnit($request, $unit);
        $this->normalizeMoneyInput($request, 'monthly_amount');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(UnitObligation::CATEGORY_LABELS))],
            'monthly_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'start_month' => ['required', 'date_format:Y-m'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $unit->obligations()->create([
            'created_by_user_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'category' => $validated['category'],
            'monthly_amount' => round((float) $validated['monthly_amount'], 2),
            'start_month' => Carbon::createFromFormat('!Y-m', $validated['start_month'])->startOfMonth(),
            'term_months' => $validated['term_months'],
            'due_day' => $validated['due_day'],
            'status' => 'active',
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ]);

        return back()->with('status', 'Monthly payable schedule created.');
    }

    public function recordPayment(Request $request, Unit $unit, UnitObligation $obligation, FinancialAccountSelection $accountSelection): RedirectResponse
    {
        abort_unless($obligation->unit_id === $unit->id, 404);
        $this->authorizeUnit($request, $unit);
        $this->normalizeMoneyInput($request, 'amount');
        $validated = $request->validate([
            'installment_month' => ['required', 'date_format:Y-m'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'financial_account_id' => ['nullable', 'integer'],
        ]);
        $financialAccount = $accountSelection->resolve($unit->host()->firstOrFail(), $validated['financial_account_id'] ?? null);
        $month = Carbon::createFromFormat('!Y-m', $validated['installment_month'])->startOfMonth();
        if ($month->lt($obligation->start_month->copy()->startOfMonth()) || $month->gt($obligation->endMonth())) {
            throw ValidationException::withMessages(['installment_month' => 'Choose a month inside this payable’s term.']);
        }
        if ((float) $validated['amount'] < (float) $obligation->monthly_amount) {
            throw ValidationException::withMessages(['amount' => 'Enter at least the scheduled monthly amount for this installment.']);
        }

        try {
            $obligation->payments()->create([
                'recorded_by_user_id' => $request->user()->id,
                'financial_account_id' => $financialAccount->id,
                'installment_month' => $month,
                'amount' => round((float) $validated['amount'], 2),
                'paid_at' => ! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : now(),
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages(['installment_month' => 'This installment month is already marked paid.']);
            }
            throw $exception;
        }

        if ($obligation->payments()->count() >= $obligation->term_months) {
            $obligation->update(['status' => 'completed']);
        }

        return back()->with('status', 'Monthly payable installment recorded as paid.');
    }

    public function updateStatus(Request $request, Unit $unit, UnitObligation $obligation): RedirectResponse
    {
        abort_unless($obligation->unit_id === $unit->id, 404);
        $this->authorizeUnit($request, $unit);
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'completed', 'cancelled'])]]);
        $obligation->update(['status' => $validated['status']]);

        return back()->with('status', 'Monthly payable status updated.');
    }

    private function authorizeUnit(Request $request, Unit $unit): void
    {
        abort_unless($request->user()->is_admin || $unit->host_id === $request->user()->id, 403);
    }

    private function normalizeMoneyInput(Request $request, string $field): void
    {
        if ($request->filled($field)) {
            $request->merge([$field => str_replace([',', '₱', ' '], '', (string) $request->input($field))]);
        }
    }
}
