<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_provider_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_user_id');
            $table->foreign('applicant_user_id', 'provider_application_applicant_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('host_id');
            $table->foreign('host_id', 'provider_application_host_fk')->references('id')->on('users')->cascadeOnDelete();
            $table->json('services');
            $table->string('status', 20)->default('pending')->index();
            $table->text('application_message');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['applicant_user_id', 'host_id'], 'provider_application_pair_unique');
            $table->index(['host_id', 'status'], 'provider_application_host_status_idx');
        });

        Schema::table('booking_expenses', function (Blueprint $table) {
            $table->foreignId('service_provider_application_id')->nullable()->after('service_unit_id');
            $table->foreign('service_provider_application_id', 'booking_expense_application_fk')
                ->references('id')->on('service_provider_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_expenses', function (Blueprint $table) {
            $table->dropForeign('booking_expense_application_fk');
            $table->dropColumn('service_provider_application_id');
        });
        Schema::dropIfExists('service_provider_applications');
    }
};
