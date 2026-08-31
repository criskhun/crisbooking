<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitFinancialProfileController extends Controller
{
    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $this->authorizeUnit($request, $unit);
        $this->normalizeMoneyInput($request, 'initial_asset_value');
        $validated = $request->validate([
            'management_type' => ['required', Rule::in(['owner_managed', 'managed_for_owner'])],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'owner_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'manager_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'share_basis' => ['required', Rule::in(['gross_sales', 'operating_profit'])],
            'initial_asset_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        if ($validated['management_type'] === 'managed_for_owner' && blank($validated['owner_name'] ?? null)) {
            throw ValidationException::withMessages(['owner_name' => 'Enter the owner’s name for a managed unit.']);
        }
        if (round((float) $validated['owner_share_percentage'] + (float) $validated['manager_share_percentage'], 2) !== 100.0) {
            throw ValidationException::withMessages(['owner_share_percentage' => 'Owner and manager shares must total exactly 100%.']);
        }
        if ($validated['management_type'] === 'owner_managed') {
            $validated['owner_name'] = $unit->host->name;
            $validated['owner_share_percentage'] = 100;
            $validated['manager_share_percentage'] = 0;
        }

        $unit->financialProfile()->updateOrCreate([], [
            ...$validated,
            'owner_name' => trim((string) $validated['owner_name']),
        ]);

        return back()->with('status', 'Unit ownership, sharing, and asset value settings saved.');
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
