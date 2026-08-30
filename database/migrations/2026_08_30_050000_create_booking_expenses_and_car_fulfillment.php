<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('fulfillment_method', 20)->nullable()->after('rental_coverage');
            $table->string('delivery_address', 500)->nullable()->after('fulfillment_method');
        });

        Schema::create('booking_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id');
            $table->foreign('booking_id', 'booking_expense_booking_fk')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable();
            $table->foreign('recorded_by_user_id', 'booking_expense_recorder_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('provider_user_id')->nullable();
            $table->foreign('provider_user_id', 'booking_expense_provider_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreignId('service_unit_id')->nullable();
            $table->foreign('service_unit_id', 'booking_expense_service_fk')->references('id')->on('units')->nullOnDelete();
            $table->string('category', 50)->index();
            $table->string('vendor_name', 120)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('recorded')->index();
            $table->string('notes', 500)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'created_at'], 'booking_expense_timeline_idx');
            $table->index(['provider_user_id', 'status'], 'booking_expense_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_expenses');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['fulfillment_method', 'delivery_address']);
        });
    }
};
