<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any existing soft-deleted group members that still have a low member_number
        // to a high number (900000 + id) to release the unique key constraint.
        DB::table('group_members')
            ->whereNotNull('deleted_at')
            ->where('member_number', '<', 900000)
            ->update([
                'member_number' => DB::raw('900000 + id')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse operation needed as this is a data cleanup migration.
    }
};
