<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nominees', function (Blueprint $table) {
            DB::statement('ALTER TABLE nominees MODIFY nominee2_name VARCHAR(255) NULL');
            DB::statement('ALTER TABLE nominees MODIFY nominee2_relationship VARCHAR(255) NULL');
            DB::statement('ALTER TABLE nominees MODIFY nominee2_mobile VARCHAR(15) NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nominees', function (Blueprint $table) {
            DB::statement('ALTER TABLE nominees MODIFY nominee2_name VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE nominees MODIFY nominee2_relationship VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE nominees MODIFY nominee2_mobile VARCHAR(15) NOT NULL');
        });
    }
};





