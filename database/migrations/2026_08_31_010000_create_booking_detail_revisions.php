<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_detail_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('before_values');
            $table->json('after_values');
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['booking_id', 'created_at'], 'booking_detail_revision_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_detail_revisions');
    }
};
