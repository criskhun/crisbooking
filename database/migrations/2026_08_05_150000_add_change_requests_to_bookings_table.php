<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dateTime('change_start_at')->nullable()->after('end_at');
            $table->dateTime('change_end_at')->nullable()->after('change_start_at');
            $table->unsignedInteger('change_party_size')->nullable()->after('party_size');
            $table->string('change_request_status', 20)->nullable()->after('change_party_size');
            $table->text('change_request_note')->nullable()->after('change_request_status');
            $table->timestamp('change_requested_at')->nullable()->after('change_request_note');
            $table->timestamp('change_reviewed_at')->nullable()->after('change_requested_at');

            $table->index(['unit_id', 'change_request_status']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['unit_id', 'change_request_status']);
            $table->dropColumn([
                'change_start_at',
                'change_end_at',
                'change_party_size',
                'change_request_status',
                'change_request_note',
                'change_requested_at',
                'change_reviewed_at',
            ]);
        });
    }
};
