<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('category', 30);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('pricing_unit', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['host_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
