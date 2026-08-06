<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('desired_start_at');
            $table->dateTime('desired_end_at');
            $table->unsignedInteger('party_size')->default(1);
            $table->string('status', 30)->default('open');
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['host_id', 'status']);
        });

        Schema::create('inquiry_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'created_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            $table->unique('inquiry_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['inquiry_id']);
            $table->dropConstrainedForeignId('inquiry_id');
        });

        Schema::dropIfExists('inquiry_messages');
        Schema::dropIfExists('inquiries');
    }
};
