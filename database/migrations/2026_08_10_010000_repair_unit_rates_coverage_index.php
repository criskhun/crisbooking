<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('unit_rates', 'coverage')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->string('coverage', 20)->default('standard')->after('unit_id');
            });
        }

        // A standalone unit_id index keeps the foreign key supported while the
        // legacy unit_id + period unique key is removed on MySQL.
        if (! Schema::hasIndex('unit_rates', ['unit_id'])) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->index('unit_id');
            });
        }

        if (Schema::hasIndex('unit_rates', 'unit_rates_unit_id_period_unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->dropUnique('unit_rates_unit_id_period_unique');
            });
        }

        if (! Schema::hasIndex('unit_rates', ['unit_id', 'coverage', 'period'], 'unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->unique(['unit_id', 'coverage', 'period']);
            });
        }

        DB::table('unit_rates')
            ->where('coverage', 'standard')
            ->whereIn('unit_id', DB::table('units')->where('category', 'car')->select('id'))
            ->update(['coverage' => 'within_city']);
    }

    public function down(): void
    {
        // This migration repairs a potentially partial production migration.
        // Rolling it back must not reintroduce the incompatible legacy index.
    }
};
