<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('calendar_color', 7)->nullable()->after('sale_percentage');
            $table->string('calendar_secondary_color', 7)->nullable()->after('calendar_color');
            $table->boolean('calendar_use_gradient')->default(false)->after('calendar_secondary_color');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['calendar_color', 'calendar_secondary_color', 'calendar_use_gradient']);
        });
    }
};
