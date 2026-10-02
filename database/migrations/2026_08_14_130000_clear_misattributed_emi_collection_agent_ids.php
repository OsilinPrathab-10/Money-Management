<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear agent_id on office (Admin/Staff) collections that were wrongly
     * attributed to the client's assigned agent.
     *
     * Keep agent_id when:
     * - the verifying user is the collecting agent, or
     * - remarks indicate a real agent field collection ([Agent ...]).
     */
    public function up(): void
    {
        DB::statement("
            UPDATE emi_collections ec
            INNER JOIN agents a ON a.id = ec.agent_id
            SET ec.agent_id = NULL
            WHERE ec.agent_id IS NOT NULL
              AND ec.verified_by IS NOT NULL
              AND (a.user_id IS NULL OR a.user_id <> ec.verified_by)
              AND (
                ec.remarks IS NULL
                OR (
                  ec.remarks NOT LIKE '%[Agent Collected]%'
                  AND ec.remarks NOT LIKE '%[Agent Bulk Collected]%'
                  AND ec.remarks NOT LIKE '%[Agent Collected via Client EMI View]%'
                  AND ec.remarks NOT LIKE '%[Agent Collected via Partial Modal]%'
                  AND ec.remarks NOT LIKE '%[Agent Updated via Client EMI View]%'
                )
              )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible data correction
    }
};
