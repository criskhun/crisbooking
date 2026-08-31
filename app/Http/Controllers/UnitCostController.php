<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\UnitCost;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitCostController extends Controller
{
    public function store(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeUnit($request, $unit);
        $this->normalizeMoneyInput($request, 'amount');
        $validated = $request->validate([
            'category' => ['required', Rule::in(array_keys(UnitCost::CATEGORY_LABELS))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'status' => ['required', Rule::in(['payable', 'paid'])],
            'incurred_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'vendor_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $unit->costs()->create([
            'recorded_by_user_id' => $request->user()->id,
            'category' => $validated['category'],
            'classification' => $validated['category'] === 'capital_improvement' ? 'capital' : 'operating',
            'amount' => round((float) $validated['amount'], 2),
            'status' => $validated['status'],
            'incurred_on' => $validated['incurred_on'],
            'due_on' => $validated['due_on'] ?? null,
            'paid_at' => $validated['status'] === 'paid' ? now() : null,
            'vendor_name' => filled($validated['vendor_name'] ?? null) ? trim($validated['vendor_name']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ]);

        return back()->with('status', $validated['category'] === 'capital_improvement'
            ? 'Capital improvement recorded and added to the unit’s tracked value.'
            : 'Unit cost recorded.');
    }

    public function markPaid(Request $request, Unit $unit, UnitCost $cost): RedirectResponse
    {
        abort_unless($cost->unit_id === $unit->id, 404);
        $this->authorizeUnit($request, $unit);
        $validated = $request->validate(['paid_at' => ['nullable', 'date']]);
        $cost->update([
            'status' => 'paid',
            'paid_at' => ! empty($validated['paid_at']) ? Carbon::parse($validated['paid_at']) : now(),
        ]);

        return back()->with('status', 'Unit payable marked paid.');
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
