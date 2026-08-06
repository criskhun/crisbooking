<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook_id')->nullable()->unique()->after('google_avatar');
            $table->string('facebook_avatar', 2048)->nullable()->after('facebook_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['facebook_id']);
            $table->dropColumn(['facebook_id', 'facebook_avatar']);
        });
    }
};
