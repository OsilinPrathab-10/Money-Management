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
            $table->boolean('aadhaar_verified')->default(false)->after('aadhaar_image_back');
            $table->boolean('pan_verified')->default(false)->after('pan_image');
            $table->boolean('bank_verified')->default(false)->after('bank_statement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropColumn(['aadhaar_verified', 'pan_verified', 'bank_verified']);
        });
    }
};
