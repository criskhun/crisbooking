<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');
        });

        Schema::create('unit_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['unit_id', 'period']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('unit_rate_id')->nullable()->after('unit_id')->constrained()->nullOnDelete();
            $table->string('rate_period', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_rate_id');
            $table->dropColumn('rate_period');
        });

        Schema::dropIfExists('unit_rates');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
    }
};
