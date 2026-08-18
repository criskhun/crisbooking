<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_partnership_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['affiliate_partnership_id', 'unit_id'], 'affiliate_unit_unique');
        });

        $now = now();
        DB::table('affiliate_partnerships')
            ->join('units', 'units.host_id', '=', 'affiliate_partnerships.host_id')
            ->where('affiliate_partnerships.status', 'accepted')
            ->where('units.is_active', true)
            ->select(['affiliate_partnerships.id as affiliate_partnership_id', 'units.id as unit_id'])
            ->orderBy('affiliate_partnerships.id')
            ->chunk(500, function ($assignments) use ($now): void {
                DB::table('affiliate_partnership_unit')->insertOrIgnore($assignments->map(fn ($assignment) => [
                    'affiliate_partnership_id' => $assignment->affiliate_partnership_id,
                    'unit_id' => $assignment->unit_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

        DB::table('inquiry_price_proposals')
            ->where('status', 'pending')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('bookings')
                    ->whereColumn('bookings.inquiry_id', 'inquiry_price_proposals.inquiry_id');
            })
            ->update([
                'status' => 'superseded',
                'responded_at' => $now,
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_partnership_unit');
    }
};
