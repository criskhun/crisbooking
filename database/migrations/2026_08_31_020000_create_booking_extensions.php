<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('duration_unit', 10);
            $table->unsignedInteger('duration_quantity');
            $table->timestamp('previous_end_at');
            $table->timestamp('new_end_at');
            $table->decimal('additional_amount', 12, 2);
            $table->string('payment_status', 20);
            $table->foreignId('charge_entry_id')->nullable()->constrained('booking_financial_entries')->nullOnDelete();
            $table->foreignId('payment_entry_id')->nullable()->constrained('booking_financial_entries')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'created_at'], 'booking_extension_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_extensions');
    }
};
