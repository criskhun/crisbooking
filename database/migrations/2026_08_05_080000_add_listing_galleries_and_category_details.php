<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['unit_id', 'sort_order']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->json('car_details')->nullable()->after('photo_path');
            $table->json('property_details')->nullable()->after('car_details');
        });

        DB::table('units')
            ->whereNotNull('photo_path')
            ->orderBy('id')
            ->each(function (object $unit): void {
                DB::table('unit_images')->insert([
                    'unit_id' => $unit->id,
                    'path' => $unit->photo_path,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['car_details', 'property_details']);
        });

        Schema::dropIfExists('unit_images');
    }
};
