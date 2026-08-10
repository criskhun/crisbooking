<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_applications', function (Blueprint $table) {
            $table->string('face_selfie_path')->nullable()->after('business_document_path');
            $table->string('id_selfie_path')->nullable()->after('face_selfie_path');
        });
    }

    public function down(): void
    {
        Schema::table('host_applications', function (Blueprint $table) {
            $table->dropColumn(['face_selfie_path', 'id_selfie_path']);
        });
    }
};
