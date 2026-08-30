<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_provider_applications', function (Blueprint $table) {
            $table->json('application_images')->nullable()->after('application_message');
        });

        Schema::table('booking_expenses', function (Blueprint $table) {
            $table->json('completion_images')->nullable()->after('notes');
            $table->string('payment_proof_path', 500)->nullable()->after('completion_images');
            $table->string('payment_proof_name')->nullable()->after('payment_proof_path');
            $table->timestamp('payment_received_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('booking_expenses', function (Blueprint $table) {
            $table->dropColumn(['completion_images', 'payment_proof_path', 'payment_proof_name', 'payment_received_at']);
        });

        Schema::table('service_provider_applications', function (Blueprint $table) {
            $table->dropColumn('application_images');
        });
    }
};
