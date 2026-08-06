<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->text('wifi_details')->nullable()->after('gps_details');
            $table->string('wifi_qr_path')->nullable()->after('wifi_details');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['wifi_details', 'wifi_qr_path']);
        });
    }
};
