<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->longText('payload');
            $table->timestamps();

            $table->index(['host_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_drafts');
    }
};
