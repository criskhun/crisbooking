<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('booked_by_user_id')->nullable()->after('client_id')->constrained('users')->nullOnDelete();
            $table->string('booking_origin', 20)->default('platform')->after('booked_by_user_id')->index();
            $table->string('source_channel', 40)->nullable()->after('booking_origin')->index();
            $table->string('source_details', 160)->nullable()->after('source_channel');
            $table->string('external_customer_name', 120)->nullable()->after('source_details');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by_user_id');
            $table->dropIndex(['booking_origin']);
            $table->dropIndex(['source_channel']);
            $table->dropColumn(['booking_origin', 'source_channel', 'source_details', 'external_customer_name']);
        });
    }
};
