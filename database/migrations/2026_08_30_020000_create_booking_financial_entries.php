<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('security_deposit_amount', 12, 2)->default(0)->after('total_amount');
        });

        Schema::create('booking_financial_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30)->index();
            $table->string('category', 50)->index();
            $table->decimal('amount', 12, 2);
            $table->string('notes', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['booking_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_financial_entries');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('security_deposit_amount'));
    }
};
