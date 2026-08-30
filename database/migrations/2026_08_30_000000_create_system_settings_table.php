<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name', 80)->default('Davao Rent Zone');
            $table->string('short_name', 30)->default('DRZ');
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('support_email')->nullable();
            $table->string('support_phone', 40)->nullable();
            $table->string('primary_color', 7)->default('#173c34');
            $table->string('secondary_color', 7)->default('#0f2d27');
            $table->string('accent_color', 7)->default('#d9ed8b');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
