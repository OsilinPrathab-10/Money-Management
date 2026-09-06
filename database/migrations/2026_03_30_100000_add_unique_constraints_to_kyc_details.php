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
            $table->string('pan_number')->nullable()->unique()->change();
            $table->string('account_number')->nullable()->unique()->change();
            $table->string('aadhaar_number')->nullable()->unique()->change();
        });
        
        Schema::table('clients', function (Blueprint $table) { 
            $table->string('alternate_phone')->nullable()->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropUnique(['pan_number']);
            $table->dropUnique(['account_number']);
            $table->dropUnique(['aadhaar_number']);
        });
        
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['alternate_phone']);
        });
    }
};
