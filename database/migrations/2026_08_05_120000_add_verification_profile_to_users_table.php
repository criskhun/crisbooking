<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('role');
            $table->date('date_of_birth')->nullable()->after('phone');
            $table->string('nationality', 100)->nullable()->after('date_of_birth');
            $table->text('address')->nullable()->after('nationality');
            $table->string('city', 120)->nullable()->after('address');
            $table->text('bio')->nullable()->after('city');
            $table->string('emergency_contact_name')->nullable()->after('bio');
            $table->string('emergency_contact_phone', 40)->nullable()->after('emergency_contact_name');
            $table->string('government_id_type', 60)->nullable()->after('emergency_contact_phone');
            $table->text('government_id_number')->nullable()->after('government_id_type');
            $table->string('government_id_path')->nullable()->after('government_id_number');
            $table->timestamp('profile_completed_at')->nullable()->after('government_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'date_of_birth', 'nationality', 'address', 'city', 'bio',
                'emergency_contact_name', 'emergency_contact_phone', 'government_id_type',
                'government_id_number', 'government_id_path', 'profile_completed_at',
            ]);
        });
    }
};
