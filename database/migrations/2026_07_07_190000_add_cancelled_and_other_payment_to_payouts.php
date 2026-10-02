<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payouts MODIFY COLUMN status ENUM('pending','processing','paid','failed','cancelled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE payouts MODIFY COLUMN payment_mode ENUM('cash','bank_transfer','upi','cheque','other') NOT NULL DEFAULT 'bank_transfer'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payouts MODIFY COLUMN payment_mode ENUM('cash','bank_transfer','upi','cheque') NOT NULL DEFAULT 'bank_transfer'");
        DB::statement("ALTER TABLE payouts MODIFY COLUMN status ENUM('pending','processing','paid','failed') NOT NULL DEFAULT 'pending'");
    }
};
