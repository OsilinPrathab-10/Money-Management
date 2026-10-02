<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the overly strict unique constraint on (group_id, winner_member_id).
     *
     * This constraint prevents re-application after rejection/cancellation.
     * The application-level assertNoDuplicate() in ChitPayoutService already
     * handles the real business rule: block only pending/processing/paid duplicates,
     * but ALLOW re-apply when the previous application was cancelled/rejected.
     *
     * We keep a non-unique index on (group_id, winner_member_id) for query performance.
     */
    public function up(): void
    {
        // Create the replacement index first. MySQL refuses to drop the unique index while it
        // is the only one backing the group_id foreign key.
        $plainIndexExists = collect(DB::select("SHOW INDEX FROM payouts WHERE Key_name = 'payouts_group_winner_index'"))->isNotEmpty();
        if (!$plainIndexExists) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->index(['group_id', 'winner_member_id'], 'payouts_group_winner_index');
            });
        }

        // Drop the unique constraint — safely, checking if it exists first
        $indexExists = collect(DB::select("SHOW INDEX FROM payouts WHERE Key_name = 'payouts_group_winner_unique'"))->isNotEmpty();

        if ($indexExists) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->dropUnique('payouts_group_winner_unique');
            });
        }
    }

    /**
     * Restore the unique constraint (rollback).
     */
    public function down(): void
    {
        // Drop the plain index first
        $plainIndexExists = collect(DB::select("SHOW INDEX FROM payouts WHERE Key_name = 'payouts_group_winner_index'"))->isNotEmpty();
        if ($plainIndexExists) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->dropIndex('payouts_group_winner_index');
            });
        }

        // Restore the unique constraint
        Schema::table('payouts', function (Blueprint $table) {
            $table->unique(['group_id', 'winner_member_id'], 'payouts_group_winner_unique');
        });
    }
};
