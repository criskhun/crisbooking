<?php

namespace App\Http\Controllers;

use App\Models\BookingExpense;
use App\Models\BookingFinancialEntry;
use App\Models\FinancialAccount;
use App\Models\UnitCost;
use App\Models\UnitObligationPayment;
use App\Services\AccountingLedgerService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(Request $request, AccountingLedgerService $ledger): View
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $validated = $request->validate([
            'account' => ['nullable', 'integer'],
            'month' => ['nullable', 'date_format:Y-m'],
            'direction' => ['nullable', Rule::in(['in', 'out'])],
        ]);
        $selectedAccount = filled($validated['account'] ?? null)
            ? $request->user()->financialAccounts()->findOrFail($validated['account'])
            : null;
        $month = filled($validated['month'] ?? null)
            ? Carbon::createFromFormat('!Y-m', $validated['month'])->startOfMonth()
            : null;
        $direction = $validated['direction'] ?? null;
        $report = $ledger->report($request->user(), $selectedAccount, $month, $direction);

        return view('accounting.index', compact('report', 'selectedAccount', 'month', 'direction') + [
            'accountTypeOptions' => FinancialAccount::TYPE_LABELS,
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isHost() || $request->user()->is_admin, 403);
        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer'],
            'source_type' => ['required', Rule::in([
                'booking_financial_entry', 'booking_expense', 'unit_cost', 'unit_obligation_payment',
            ])],
            'source_id' => ['required', 'integer'],
        ]);
        $account = $request->user()->financialAccounts()->where('is_active', true)->findOrFail($validated['financial_account_id']);

        $source = match ($validated['source_type']) {
            'booking_financial_entry' => BookingFinancialEntry::with('booking.unit')->findOrFail($validated['source_id']),
            'booking_expense' => BookingExpense::with('booking.unit')->findOrFail($validated['source_id']),
            'unit_cost' => UnitCost::with('unit')->findOrFail($validated['source_id']),
            'unit_obligation_payment' => UnitObligationPayment::with('obligation.unit')->findOrFail($validated['source_id']),
        };
        $ownedBy = match ($validated['source_type']) {
            'booking_financial_entry' => $source->booking->unit->host_id,
            'booking_expense' => $source->booking->unit->host_id,
            'unit_cost' => $source->unit->host_id,
            'unit_obligation_payment' => $source->obligation->unit->host_id,
        };
        abort_unless($ownedBy === $request->user()->id, 403);

        $isCashMovement = match ($validated['source_type']) {
            'booking_financial_entry' => $source->movesCash(),
            'booking_expense' => in_array($source->status, ['paid', 'payment_received'], true) && $source->paid_at !== null,
            'unit_cost' => $source->status === 'paid' && $source->paid_at !== null,
            'unit_obligation_payment' => true,
        };
        abort_unless($isCashMovement, 422, 'Only completed cash movements can be assigned to an account.');
        $source->update(['financial_account_id' => $account->id]);

        return back()->with('status', 'Ledger transaction assigned to '.$account->displayLabel().'.');
    }
}
