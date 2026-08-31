<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('management_type', 30)->default('owner_managed');
            $table->string('owner_name', 120)->nullable();
            $table->decimal('owner_share_percentage', 5, 2)->default(100);
            $table->decimal('manager_share_percentage', 5, 2)->default(0);
            $table->string('share_basis', 30)->default('operating_profit');
            $table->decimal('initial_asset_value', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('unit_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 50)->index();
            $table->string('classification', 20)->default('operating')->index();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('payable')->index();
            $table->date('incurred_on');
            $table->date('due_on')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('vendor_name', 120)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'incurred_on'], 'unit_cost_period_idx');
        });

        Schema::create('unit_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('category', 50)->index();
            $table->decimal('monthly_amount', 12, 2);
            $table->date('start_month');
            $table->unsignedSmallInteger('term_months');
            $table->unsignedTinyInteger('due_day')->default(1);
            $table->string('status', 20)->default('active')->index();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('unit_obligation_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_obligation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('installment_month');
            $table->decimal('amount', 12, 2);
            $table->timestamp('paid_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['unit_obligation_id', 'installment_month'], 'unit_obligation_installment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_obligation_payments');
        Schema::dropIfExists('unit_obligations');
        Schema::dropIfExists('unit_costs');
        Schema::dropIfExists('unit_financial_profiles');
    }
};
