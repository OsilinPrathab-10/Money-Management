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
        // 1. Update emi_collections where agent_id IS NULL using verified_by user's agent ID
        DB::statement("
            UPDATE emi_collections ec
            JOIN agents a ON a.user_id = ec.verified_by
            JOIN users u ON u.id = ec.verified_by
            JOIN model_has_roles mhr ON mhr.model_id = u.id AND mhr.model_type = 'App\\\\Models\\\\User'
            JOIN roles r ON r.id = mhr.role_id AND r.name = 'Agent'
            SET ec.agent_id = a.id
            WHERE ec.agent_id IS NULL
        ");

        // 2. Update remaining emi_collections where agent_id IS NULL using client assigned_to or added_by agent
        DB::statement("
            UPDATE emi_collections ec
            JOIN emis e ON e.id = ec.emi_id
            JOIN loan_accounts la ON la.id = e.loan_account_id
            JOIN clients c ON c.id = la.client_id
            SET ec.agent_id = COALESCE(c.assigned_to, c.added_by)
            WHERE ec.agent_id IS NULL
              AND (c.assigned_to IS NOT NULL OR c.added_by IS NOT NULL)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed for data backfill
    }
};
