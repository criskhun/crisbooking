<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingExpense;
use App\Models\FinancialAccount;
use App\Models\Unit;
use App\Models\UnitObligation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_register_manage_and_protect_multiple_financial_accounts(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();

        $this->actingAs($host)->post(route('accounting.accounts.store'), [
            'accounting_type' => 'assets',
            'account_category' => 'cash_and_cash_equivalents',
            'name' => 'BDO Operations',
            'type' => 'bank',
            'institution_name' => 'BDO',
            'last_four' => '4821',
            'opening_balance' => '12,500.00',
            'opened_on' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHas('status');

        $account = FinancialAccount::query()->sole();
        $this->assertTrue($account->is_active);
        $this->assertSame('assets', $account->accounting_type);
        $this->assertSame('cash_and_cash_equivalents', $account->account_category);
        $this->assertSame('Assets → Cash & Cash Equivalents → BDO Operations · •••• 4821', $account->selectionLabel());
        $this->assertSame('BDO Operations · •••• 4821', $account->displayLabel());
        $this->assertSame('12500.00', $account->opening_balance);

        $this->actingAs($otherHost)->patch(route('accounting.accounts.update', $account), [
            'name' => 'Stolen account',
            'type' => 'cash',
            'opening_balance' => 0,
            'is_active' => 1,
        ])->assertForbidden();

        $this->actingAs($host)->patch(route('accounting.accounts.update', $account), [
            'accounting_type' => 'assets',
            'account_category' => 'cash_and_cash_equivalents',
            'name' => 'BDO Operations',
            'type' => 'bank',
            'institution_name' => 'BDO',
            'last_four' => '4821',
            'opening_balance' => '12,500.00',
            'opened_on' => today()->toDateString(),
            'is_active' => 0,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertFalse($account->fresh()->is_active);
        $this->actingAs(User::factory()->create())->get(route('accounting.index'))->assertForbidden();
    }

    public function test_accounts_are_registered_and_filtered_by_accounting_category(): void
    {
        $host = User::factory()->host()->create();
        $unit = $this->unit($host, 'Category Filter Condo');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'start_at' => now()->subDay(),
            'end_at' => now(),
            'status' => 'confirmed',
            'total_amount' => 3000,
            'party_size' => 2,
        ]);

        foreach ([
            ['accounting_type' => 'assets', 'account_category' => 'cash_and_cash_equivalents', 'name' => 'GCash – Facebook Bookings', 'type' => 'e_wallet'],
            ['accounting_type' => 'revenue', 'account_category' => 'condo_rental_income', 'name' => 'Condo Rentals – Direct', 'type' => 'other'],
            ['accounting_type' => 'expenses', 'account_category' => 'supplies_and_amenities', 'name' => 'Drinking Water Expense', 'type' => 'other'],
            ['accounting_type' => 'liabilities', 'account_category' => 'customer_guest_deposits', 'name' => 'Guest Deposits', 'type' => 'other'],
            ['accounting_type' => 'equity', 'account_category' => 'owners_capital', 'name' => 'Owner’s Capital', 'type' => 'other'],
        ] as $accountData) {
            $this->actingAs($host)->post(route('accounting.accounts.store'), $accountData + [
                'opening_balance' => 0,
                'is_active' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $revenue = FinancialAccount::where('name', 'Condo Rentals – Direct')->sole();
        $expense = FinancialAccount::where('name', 'Drinking Water Expense')->sole();
        $booking->financialEntries()->create([
            'recorded_by_user_id' => $host->id,
            'financial_account_id' => $revenue->id,
            'kind' => 'payment',
            'category' => 'downpayment',
            'amount' => 1200,
            'occurred_at' => now(),
        ]);
        BookingExpense::create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $host->id,
            'financial_account_id' => $expense->id,
            'category' => 'utilities',
            'amount' => 250,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->actingAs($host)->get(route('accounting.index'))
            ->assertOk()
            ->assertSee('Cash &amp; Cash Equivalents', false)
            ->assertSee('Accounts Receivable')
            ->assertSee('Property &amp; Equipment', false)
            ->assertSee('Car Rental Income')
            ->assertSee('Airport Transport Income')
            ->assertSee('Supplies &amp; Amenities', false)
            ->assertSee('Accounts Payable')
            ->assertSee('Owner’s Drawings')
            ->assertSee('Assets → Cash &amp; Cash Equivalents → GCash – Facebook Bookings', false)
            ->assertSee('Expenses → Supplies &amp; Amenities → Drinking Water Expense', false);

        $this->actingAs($host)->get(route('accounting.index', [
            'accounting_type' => 'expenses',
            'account_category' => 'supplies_and_amenities',
        ]))
            ->assertOk()
            ->assertViewHas('report', fn (array $report) =>
                $report['summary']['transaction_count'] === 1
                && $report['summary']['money_in'] === 0.0
                && $report['summary']['money_out'] === 250.0
                && $report['movements']->first()['account']->is($expense)
            );

        $this->actingAs($host)->get(route('accounting.index', [
            'accounting_type' => 'assets',
            'account_category' => 'utilities',
        ]))->assertSessionHasErrors('account_category');
    }

    public function test_ledger_combines_collections_deposits_services_costs_and_financing_by_account(): void
    {
        $host = User::factory()->host()->create();
        $cash = $this->financialAccount($host, [
            'name' => 'Business cash',
            'opening_balance' => 1000,
        ]);
        $bank = $this->financialAccount($host, [
            'name' => 'BPI Operations',
            'type' => 'bank',
            'institution_name' => 'BPI',
            'last_four' => '1108',
            'opening_balance' => 5000,
        ]);
        $unit = $this->unit($host, 'Ledger View Condo');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booked_by_user_id' => $host->id,
            'booking_origin' => 'manual',
            'source_channel' => 'direct',
            'external_customer_name' => 'Cashflow Guest',
            'start_at' => now()->subDays(3),
            'end_at' => now()->subDays(2),
            'status' => 'confirmed',
            'total_amount' => 5000,
            'security_deposit_amount' => 500,
            'party_size' => 2,
        ]);

        $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'payment',
            'category' => 'downpayment',
            'amount' => 2000,
            'financial_account_id' => $bank->id,
            'notes' => 'Bank transfer collection.',
        ])->assertRedirect();
        $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
            'kind' => 'deposit',
            'amount' => 500,
            'financial_account_id' => $cash->id,
        ])->assertRedirect();
        $this->actingAs($host)->post(route('sales.units.costs.store', $unit), [
            'category' => 'electricity',
            'amount' => 300,
            'status' => 'paid',
            'incurred_on' => today()->toDateString(),
            'financial_account_id' => $cash->id,
            'vendor_name' => 'Davao Light',
        ])->assertRedirect();

        BookingExpense::create([
            'booking_id' => $booking->id,
            'recorded_by_user_id' => $host->id,
            'financial_account_id' => $bank->id,
            'category' => 'cleaning',
            'vendor_name' => 'Clean Davao',
            'amount' => 250,
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $obligation = UnitObligation::create([
            'unit_id' => $unit->id,
            'created_by_user_id' => $host->id,
            'name' => 'Monthly mortgage',
            'category' => 'amortization',
            'monthly_amount' => 700,
            'start_month' => now()->startOfMonth(),
            'term_months' => 12,
            'due_day' => 1,
            'status' => 'active',
        ]);
        $this->actingAs($host)->post(route('sales.units.obligations.payments.store', [$unit, $obligation]), [
            'installment_month' => now()->format('Y-m'),
            'amount' => 700,
            'financial_account_id' => $bank->id,
        ])->assertRedirect();

        $response = $this->actingAs($host)->get(route('accounting.index'));
        $response->assertOk()
            ->assertSee('Accounting ledger')
            ->assertSee('Cashflow Guest')
            ->assertSee('Bank transfer collection.')
            ->assertSee('Davao Light')
            ->assertSee('Clean Davao')
            ->assertSee('Monthly mortgage')
            ->assertSee('BPI Operations · •••• 1108');
        $response->assertViewHas('report', function (array $report) use ($cash, $bank) {
            $summaries = $report['account_summaries']->keyBy(fn ($row) => $row['account']->id);

            return $report['summary']['money_in'] === 2500.0
                && $report['summary']['money_out'] === 1250.0
                && $report['summary']['net_cash_flow'] === 1250.0
                && $report['summary']['account_balance'] === 7250.0
                && $report['summary']['transaction_count'] === 5
                && $report['summary']['unassigned_count'] === 0
                && $summaries[$cash->id]['balance'] === 1200.0
                && $summaries[$bank->id]['balance'] === 6050.0;
        });

        $this->actingAs($host)->get(route('accounting.index', [
            'account' => $bank->id,
            'direction' => 'out',
            'month' => now()->format('Y-m'),
        ]))->assertOk()->assertViewHas('report', fn (array $report) =>
            $report['summary']['money_in'] === 0.0
            && $report['summary']['money_out'] === 950.0
            && $report['summary']['transaction_count'] === 2
        );
    }

    public function test_cash_movements_require_an_active_account_owned_by_the_host_and_legacy_rows_can_be_assigned(): void
    {
        $host = User::factory()->host()->create();
        $otherHost = User::factory()->host()->create();
        $hostAccount = $this->financialAccount($host);
        $otherAccount = $this->financialAccount($otherHost);
        $inactiveAccount = $this->financialAccount($host, ['name' => 'Archived cash', 'is_active' => false]);
        $unit = $this->unit($host, 'Account Security Car', 'car');
        $booking = Booking::create([
            'unit_id' => $unit->id,
            'client_id' => $host->id,
            'booking_origin' => 'manual',
            'start_at' => now()->subDay(),
            'end_at' => now(),
            'status' => 'confirmed',
            'total_amount' => 3000,
            'party_size' => 1,
        ]);

        foreach ([$otherAccount, $inactiveAccount] as $invalidAccount) {
            $this->actingAs($host)->post(route('bookings.financial-entries.store', $booking), [
                'kind' => 'payment',
                'category' => 'downpayment',
                'amount' => 500,
                'financial_account_id' => $invalidAccount->id,
            ])->assertSessionHasErrors('financial_account_id');
        }
        $this->assertDatabaseCount('booking_financial_entries', 0);

        $legacyEntry = $booking->financialEntries()->create([
            'recorded_by_user_id' => $host->id,
            'kind' => 'payment',
            'category' => 'downpayment',
            'amount' => 1000,
            'occurred_at' => now(),
        ]);
        $this->actingAs($host)->get(route('accounting.index'))
            ->assertOk()
            ->assertSee('1 historical transaction')
            ->assertSee('Pending account');

        $this->actingAs($host)->patch(route('accounting.transactions.assign'), [
            'source_type' => 'booking_financial_entry',
            'source_id' => $legacyEntry->id,
            'financial_account_id' => $hostAccount->id,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame($hostAccount->id, $legacyEntry->fresh()->financial_account_id);
        $this->actingAs($host)->get(route('accounting.index'))
            ->assertOk()
            ->assertViewHas('report', fn (array $report) =>
                $report['summary']['unassigned_count'] === 0
                && $report['summary']['money_in'] === 1000.0
            );
    }

    private function unit(User $host, string $name): Unit
    {
        return Unit::create([
            'host_id' => $host->id,
            'name' => $name,
            'kind' => 'unit',
            'category' => str_contains($name, 'Car') ? 'car' : 'condo',
            'location' => 'Davao City',
            'rules' => 'Keep in good condition.',
            'capacity' => 4,
            'price' => 2500,
            'pricing_unit' => 'day',
            'is_active' => true,
        ]);
    }
}
