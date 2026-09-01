<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 30)->index();
            $table->string('institution_name', 120)->nullable();
            $table->string('last_four', 4)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opened_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });

        Schema::table('booking_financial_entries', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('recorded_by_user_id')->constrained('financial_accounts')->nullOnDelete();
        });
        Schema::table('booking_expenses', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('recorded_by_user_id')->constrained('financial_accounts')->nullOnDelete();
        });
        Schema::table('unit_costs', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('recorded_by_user_id')->constrained('financial_accounts')->nullOnDelete();
        });
        Schema::table('unit_obligation_payments', function (Blueprint $table) {
            $table->foreignId('financial_account_id')->nullable()->after('recorded_by_user_id')->constrained('financial_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unit_obligation_payments', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_account_id'));
        Schema::table('unit_costs', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_account_id'));
        Schema::table('booking_expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_account_id'));
        Schema::table('booking_financial_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('financial_account_id'));
        Schema::dropIfExists('financial_accounts');
    }
};
