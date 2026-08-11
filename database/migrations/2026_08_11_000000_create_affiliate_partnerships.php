<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->string('referral_code', 32)->nullable()->unique();
            $table->text('application_message');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['marketer_id', 'host_id']);
            $table->index(['host_id', 'status']);
            $table->index(['marketer_id', 'status']);
        });

        Schema::create('affiliate_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['affiliate_partnership_id', 'created_at']);
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->foreignId('affiliate_partnership_id')->nullable()->after('host_id')->constrained()->nullOnDelete();
            $table->decimal('affiliate_commission_percentage', 5, 2)->nullable()->after('affiliate_partnership_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('affiliate_partnership_id')->nullable()->after('inquiry_id')->constrained()->nullOnDelete();
            $table->decimal('affiliate_commission_percentage', 5, 2)->nullable()->after('affiliate_partnership_id');
            $table->decimal('affiliate_commission_amount', 12, 2)->nullable()->after('affiliate_commission_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_partnership_id');
            $table->dropColumn(['affiliate_commission_percentage', 'affiliate_commission_amount']);
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_partnership_id');
            $table->dropColumn('affiliate_commission_percentage');
        });

        Schema::dropIfExists('affiliate_messages');
        Schema::dropIfExists('affiliate_partnerships');
    }
};
