<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('accounting_type', 30)->default('assets')->after('user_id')->index();
            $table->string('account_category', 60)->default('cash_and_cash_equivalents')->after('accounting_type')->index();
        });

        DB::table('financial_accounts')->orderBy('id')->each(function (object $account): void {
            $accountingType = in_array($account->category, ['assets', 'liabilities', 'equity', 'revenue', 'expenses'], true)
                ? $account->category
                : 'assets';

            $accountCategory = match ($accountingType) {
                'liabilities' => str_contains(strtolower($account->name), 'guest deposit') ? 'customer_guest_deposits' : 'accounts_payable',
                'equity' => str_contains(strtolower($account->name), 'drawing') ? 'owners_drawings' : 'owners_capital',
                'revenue' => str_contains(strtolower($account->name), 'condo')
                    ? 'condo_rental_income'
                    : (str_contains(strtolower($account->name), 'car') ? 'car_rental_income' : 'other_income'),
                'expenses' => $this->expenseCategory($account->name),
                default => str_contains(strtolower($account->name), 'receivable') ? 'accounts_receivable' : 'cash_and_cash_equivalents',
            };

            DB::table('financial_accounts')->where('id', $account->id)->update([
                'accounting_type' => $accountingType,
                'account_category' => $accountCategory,
            ]);
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->string('category', 30)->default('assets')->after('user_id')->index();
        });

        DB::table('financial_accounts')->update(['category' => DB::raw('accounting_type')]);

        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropIndex(['accounting_type']);
            $table->dropIndex(['account_category']);
            $table->dropColumn(['accounting_type', 'account_category']);
        });
    }

    private function expenseCategory(string $name): string
    {
        $name = strtolower($name);

        return match (true) {
            str_contains($name, 'drinking'), str_contains($name, 'supply'), str_contains($name, 'amenit') => 'supplies_and_amenities',
            str_contains($name, 'electric'), str_contains($name, 'water'), str_contains($name, 'internet') => 'utilities',
            str_contains($name, 'clean'), str_contains($name, 'laundry') => 'cleaning_and_housekeeping',
            str_contains($name, 'repair'), str_contains($name, 'maintenance') => 'repairs_and_maintenance',
            str_contains($name, 'platform'), str_contains($name, 'bank'), str_contains($name, 'payment fee') => 'bank_and_payment_fees',
            default => 'miscellaneous_expenses',
        };
    }
};
