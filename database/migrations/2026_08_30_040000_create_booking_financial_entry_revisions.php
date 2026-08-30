<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_financial_entry_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_financial_entry_id');
            $table->foreign('booking_financial_entry_id', 'financial_revision_entry_fk')
                ->references('id')->on('booking_financial_entries')->cascadeOnDelete();
            $table->foreignId('edited_by_user_id')->nullable();
            $table->foreign('edited_by_user_id', 'financial_revision_editor_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->json('before_values');
            $table->json('after_values');
            $table->string('reason', 500);
            $table->timestamps();
            $table->index(['booking_financial_entry_id', 'created_at'], 'financial_entry_revision_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_financial_entry_revisions');
    }
};
