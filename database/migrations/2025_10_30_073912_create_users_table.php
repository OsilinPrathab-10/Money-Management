<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Legacy migration name kept for databases that already ran this batch.
 * Real schema is created in 0001_01_01_000000_create_users_table.php (runs first).
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};
