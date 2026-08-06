<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('package_breakdown')->nullable()->after('rate_quantity');
            $table->json('change_package_breakdown')->nullable()->after('change_party_size');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['package_breakdown', 'change_package_breakdown']);
        });
    }
};
