<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE emis MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partial', 'closed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::table('emis')->where('status', 'closed')->update(['status' => 'overdue']);
        DB::statement("ALTER TABLE emis MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partial') NOT NULL DEFAULT 'pending'");
    }
};
