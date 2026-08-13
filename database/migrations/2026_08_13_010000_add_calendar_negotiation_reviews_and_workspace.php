<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_feed_token', 64)->nullable()->unique()->after('remember_token');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->decimal('agreed_price', 12, 2)->nullable()->after('party_size');
            $table->timestamp('price_agreed_at')->nullable()->after('agreed_price');
        });

        Schema::create('inquiry_price_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['inquiry_id', 'status']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_partnership_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $table->string('reviewee_context', 20);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->timestamps();

            $table->unique(['booking_id', 'reviewer_id', 'reviewee_id']);
            $table->unique(['affiliate_partnership_id', 'reviewer_id', 'reviewee_id'], 'reviews_affiliate_reviewer_reviewee_unique');
            $table->index(['reviewee_id', 'reviewee_context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('inquiry_price_proposals');

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropColumn(['agreed_price', 'price_agreed_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['calendar_feed_token']);
            $table->dropColumn('calendar_feed_token');
        });
    }
};
