<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\Unit;
use App\Models\UnitObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitFinanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_track_unit_profit_shares_costs_asset_value_and_monthly_payables(): void
    {
        $host = User::factory()->host()->create(['name' => 'Managing Host']);
        $unit = $this->unit($host, 'Managed Profit Condo', 'condo');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => User::factory()->create()->id,
            'start_at' => now()->startOfMonth()->addDays(2),
            'end_at' => now()->startOfMonth()->addDays(4),
            'status' => 'confirmed',
            'total_amount' => 10000,
            'party_size' => 2,
        ]);
        BookingExpense::create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $host->id,
            'category' => 'cleaning',
            'amount' => 1000,
            'status' => 'recorded',
        ]);
        $pendingBooking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => User::factory()->create()->id,
            'booking_origin' => 'manual',
            'source_channel' => 'facebook',
            'external_customer_name' => 'Pending Facebook Customer',
            'start_at' => now()->startOfMonth()->addDays(8),
            'end_at' => now()->startOfMonth()->addDays(9),
            'status' => 'pending',
            'total_amount' => 4000,
            'party_size' => 2,
        ]);

        $this->actingAs($host)->patch(route('sales.units.financial-profile.update', $unit), [
            'management_type' => 'managed_for_owner',
            'owner_name' => 'Davao Property Owner Corp.',
            'owner_share_percentage' => 70,
            'manager_share_percentage' => 30,
            'share_basis' => 'operating_profit',
            'initial_asset_value' => '500,000.00',
        ])->assertRedirect()->assertSessionHas('status');

        foreach ([
            ['category' => 'electricity', 'amount' => 500, 'status' => 'paid'],
            ['category' => 'repair', 'amount' => 300, 'status' => 'payable'],
            ['category' => 'capital_improvement', 'amount' => 2000, 'status' => 'paid'],
        ] as $cost) {
            $this->actingAs($host)->post(route('sales.units.costs.store', $unit), [
                ...$cost,
                'incurred_on' => now()->toDateString(),
                'due_on' => now()->toDateString(),
                'notes' => 'Unit finance test record.',
            ])->assertRedirect();
        }

        $this->actingAs($host)->post(route('sales.units.obligations.store', $unit), [
            'name' => '24-month condo amortization',
            'category' => 'amortization',
            'monthly_amount' => '1,000.00',
            'start_month' => now()->format('Y-m'),
            'term_months' => 24,
            'due_day' => 15,
        ])->assertRedirect()->assertSessionHas('status');

        $obligation = UnitObligation::query()->sole();
        $response = $this->actingAs($host)->get(route('sales.index', [
            'unit' => $unit->id,
            'month' => now()->format('Y-m'),
        ]));
        $response->assertOk()
            ->assertSee('Managed Profit Condo')
            ->assertSee('Davao Property Owner Corp.')
            ->assertSee('₱10,000.00')
            ->assertSee('₱1,800.00')
            ->assertSee('₱8,200.00')
            ->assertSee('₱5,740.00')
            ->assertSee('₱2,460.00')
            ->assertSee('₱502,000.00')
            ->assertSee('24-month condo amortization')
            ->assertSee('0/24 paid')
            ->assertSee('₱1,300.00')
            ->assertSeeText('View & print report')
            ->assertSee('data-unit-profit-report-dialog', false)
            ->assertSee('data-unit-profit-report-print', false)
            ->assertSee('Booking acquisition ledger')
            ->assertSee('#'.$booking->id)
            ->assertSee('#'.$pendingBooking->id)
            ->assertSee('Pending Facebook Customer')
            ->assertSee('Facebook / social media')
            ->assertSee('Confirmed booking totals');

        $this->actingAs($host)->post(route('sales.units.obligations.payments.store', [$unit, $obligation]), [
            'installment_month' => now()->format('Y-m'),
            'amount' => '1,000.00',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertDatabaseHas('unit_obligation_payments', [
            'unit_obligation_id' => $obligation->id,
            'amount' => 1000,
        ]);

        $this->actingAs($host)->get(route('sales.index', [
            'unit' => $unit->id,
            'month' => now()->format('Y-m'),
        ]))->assertOk()
            ->assertSee('1/24 paid')
            ->assertSee('₱5,200.00')
            ->assertSee('₱300.00');
    }

    public function test_unit_finance_changes_are_private_to_the_unit_host_or_admin(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $unit = $this->unit($host, 'Private Finance Car', 'car');

        $this->actingAs($otherHost)->post(route('sales.units.costs.store', $unit), [
            'category' => 'repair',
            'amount' => 1200,
            'status' => 'payable',
            'incurred_on' => now()->toDateString(),
        ])->assertForbidden();
        $this->assertDatabaseCount('unit_costs', 0);

        $this->actingAs($host)->from(route('sales.index', ['unit' => $unit->id]))
            ->patch(route('sales.units.financial-profile.update', $unit), [
                'management_type' => 'managed_for_owner',
                'owner_name' => 'External Owner',
                'owner_share_percentage' => 60,
                'manager_share_percentage' => 30,
                'share_basis' => 'gross_sales',
                'initial_asset_value' => 100000,
            ])->assertRedirect(route('sales.index', ['unit' => $unit->id]))
            ->assertSessionHasErrors('owner_share_percentage');
    }

    public function test_unit_payable_month_can_only_be_recorded_once(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Payment Schedule Car', 'car');
        $obligation = UnitObligation::create([
            'unit_id' => $unit->id,
            'created_by_user_id' => $host->id,
            'name' => 'Vehicle financing',
            'category' => 'amortization',
            'monthly_amount' => 9000,
            'start_month' => now()->startOfMonth(),
            'term_months' => 60,
            'due_day' => 10,
            'status' => 'active',
        ]);
        $payload = ['installment_month' => now()->format('Y-m'), 'amount' => 9000];

        $this->actingAs($host)->post(route('sales.units.obligations.payments.store', [$unit, $obligation]), $payload)->assertRedirect();
        $this->actingAs($host)->from(route('sales.index', ['unit' => $unit->id]))
            ->post(route('sales.units.obligations.payments.store', [$unit, $obligation]), $payload)
            ->assertRedirect(route('sales.index', ['unit' => $unit->id]))
            ->assertSessionHasErrors('installment_month');
        $this->assertDatabaseCount('unit_obligation_payments', 1);
    }

    private function unit(User $host, string $name, string $category): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'unit',
            'category' => $category,
            'location' => 'Davao City',
            'rules' => 'Keep in good condition.',
            'capacity' => 4,
            'price' => 2500,
            'pricing_unit' => 'day',
            'is_active' => true,
        ]);
    }
}
