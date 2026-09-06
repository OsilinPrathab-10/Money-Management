<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->string('aadhaar_image')->nullable()->after('aadhaar_name');
            $table->string('pan_image')->nullable()->after('pan_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropColumn(['aadhaar_image', 'pan_image']);
        });
    }
};
