<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'status')) {
            DB::statement("ALTER TABLE group_members MODIFY COLUMN status ENUM(
                'applied', 'approved', 'active', 'defaulted', 'completed', 'withdrawn', 'rejected', 'transferred', 'frozen'
            ) DEFAULT 'applied'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'status')) {
            DB::statement("ALTER TABLE group_members MODIFY COLUMN status ENUM(
                'applied', 'approved', 'active', 'defaulted', 'completed', 'withdrawn', 'rejected', 'transferred'
            ) DEFAULT 'applied'");
        }
    }
};
