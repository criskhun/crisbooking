<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('account_type', 20);
            $table->string('business_name')->nullable();
            $table->text('business_registration_number')->nullable();
            $table->string('business_document_path')->nullable();
            $table->string('hosting_experience', 30);
            $table->text('motivation');
            $table->string('payout_method', 30);
            $table->string('payout_provider', 120);
            $table->string('payout_account_name');
            $table->text('payout_account_number');
            $table->timestamp('authority_confirmed_at');
            $table->timestamp('safety_confirmed_at');
            $table->timestamp('terms_accepted_at');
            $table->timestamp('privacy_consented_at');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });

        Schema::create('host_application_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['host_application_id', 'created_at'], 'host_application_history_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_application_status_histories');
        Schema::dropIfExists('host_applications');
    }
};
