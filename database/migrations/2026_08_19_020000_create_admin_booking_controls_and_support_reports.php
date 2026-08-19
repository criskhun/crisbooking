<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 40);
            $table->string('subject', 160);
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->text('admin_response')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reporter_id', 'created_at']);
        });

        Schema::create('booking_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_booking_id')->index();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('host_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_origin', 20);
            $table->string('booking_status', 20);
            $table->string('source_channel', 40)->nullable();
            $table->string('unit_name');
            $table->string('host_name');
            $table->string('customer_name')->nullable();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('total_amount', 12, 2);
            $table->text('removal_reason');
            $table->json('booking_snapshot');
            $table->timestamp('removed_at');
            $table->timestamps();

            $table->index(['host_id', 'removed_at']);
            $table->index(['unit_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_deletions');
        Schema::dropIfExists('support_reports');
    }
};
