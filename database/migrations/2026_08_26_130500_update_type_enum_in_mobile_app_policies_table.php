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
        // Modify ENUM column in MySQL directly as Doctrine/DBAL might have issues altering enums
        DB::statement("ALTER TABLE mobile_app_policies MODIFY COLUMN type ENUM('privacy_policy', 'terms_conditions', 'about_us') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE mobile_app_policies MODIFY COLUMN type ENUM('privacy_policy', 'terms_conditions') NOT NULL");
    }
};
