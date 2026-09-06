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
        // Use raw SQL to safely convert ENUM to VARCHAR to prevent any doctrine/dbal or schema builder issues
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE emi_collections MODIFY COLUMN status VARCHAR(255) DEFAULT 'in_progress'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE emi_collections MODIFY COLUMN status ENUM('in_progress', 'verified', 'rejected', 'completed') DEFAULT 'in_progress'");
    }
};
