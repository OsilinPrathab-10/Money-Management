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
        // For MySQL, we use a raw query to change the enum
        DB::statement("ALTER TABLE loan_applications MODIFY COLUMN status ENUM('pending', 'approved', 'process', 'processing', 'rejected', 'disbursed', 'in_progress') NOT NULL DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE loan_applications MODIFY COLUMN status ENUM('pending', 'approved', 'process', 'rejected', 'disbursed') NOT NULL DEFAULT 'pending'");
    }
};
