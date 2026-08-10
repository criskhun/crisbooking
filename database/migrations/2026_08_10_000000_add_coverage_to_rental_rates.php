<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_rates', function (Blueprint $table) {
            $table->string('coverage', 20)->default('standard')->after('unit_id');
            $table->dropUnique(['unit_id', 'period']);
            $table->unique(['unit_id', 'coverage', 'period']);
        });

        DB::table('unit_rates')
            ->whereIn('unit_id', DB::table('units')->where('category', 'car')->select('id'))
            ->update(['coverage' => 'within_city']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('rental_coverage', 20)->nullable()->after('rate_period');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('rental_coverage');
        });

        DB::table('unit_rates')->where('coverage', 'out_of_town')->delete();
        DB::table('unit_rates')->where('coverage', 'within_city')->update(['coverage' => 'standard']);

        Schema::table('unit_rates', function (Blueprint $table) {
            $table->dropUnique(['unit_id', 'coverage', 'period']);
            $table->dropColumn('coverage');
            $table->unique(['unit_id', 'period']);
        });
    }
};
