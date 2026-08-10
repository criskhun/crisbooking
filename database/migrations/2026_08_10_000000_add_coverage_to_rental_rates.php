<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL may use the old composite unique index to support the unit_id
        // foreign key. Give that foreign key its own index before replacing it.
        if (! Schema::hasIndex('unit_rates', ['unit_id'])) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->index('unit_id');
            });
        }

        if (! Schema::hasColumn('unit_rates', 'coverage')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->string('coverage', 20)->default('standard')->after('unit_id');
            });
        }

        if (Schema::hasIndex('unit_rates', ['unit_id', 'period'], 'unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->dropUnique(['unit_id', 'period']);
            });
        }

        if (! Schema::hasIndex('unit_rates', ['unit_id', 'coverage', 'period'], 'unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->unique(['unit_id', 'coverage', 'period']);
            });
        }

        DB::table('unit_rates')
            ->whereIn('unit_id', DB::table('units')->where('category', 'car')->select('id'))
            ->update(['coverage' => 'within_city']);

        if (! Schema::hasColumn('bookings', 'rental_coverage')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('rental_coverage', 20)->nullable()->after('rate_period');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'rental_coverage')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('rental_coverage');
            });
        }

        if (Schema::hasColumn('unit_rates', 'coverage')) {
            DB::table('unit_rates')->where('coverage', 'out_of_town')->delete();
            DB::table('unit_rates')->where('coverage', 'within_city')->update(['coverage' => 'standard']);
        }

        if (Schema::hasIndex('unit_rates', ['unit_id', 'coverage', 'period'], 'unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->dropUnique(['unit_id', 'coverage', 'period']);
            });
        }

        if (Schema::hasColumn('unit_rates', 'coverage')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->dropColumn('coverage');
            });
        }

        if (! Schema::hasIndex('unit_rates', ['unit_id', 'period'], 'unique')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->unique(['unit_id', 'period']);
            });
        }

        if (Schema::hasIndex('unit_rates', ['unit_id']) && Schema::hasIndex('unit_rates', 'unit_rates_unit_id_index')) {
            Schema::table('unit_rates', function (Blueprint $table) {
                $table->dropIndex(['unit_id']);
            });
        }
    }
};
