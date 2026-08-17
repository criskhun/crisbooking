<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('dedupe_key', 180)->nullable()->after('url');
            $table->timestamp('seen_at')->nullable()->after('dedupe_key');
            $table->timestamp('email_claimed_at')->nullable()->after('read_at');
            $table->timestamp('email_sent_at')->nullable()->after('email_claimed_at');

            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['seen_at', 'email_sent_at', 'created_at'], 'notification_email_fallback_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropIndex('notification_email_fallback_index');
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropColumn(['dedupe_key', 'seen_at', 'email_claimed_at', 'email_sent_at']);
        });
    }
};
