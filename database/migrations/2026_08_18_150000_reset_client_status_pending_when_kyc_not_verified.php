<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Business rule: a client can be "active" only after admin verifies KYC.
 *
 * Earlier, editing a client from the admin client-view page saved status = active
 * (the status dropdown had no "pending" option), so unverified clients became active.
 *
 * This migration puts those clients back to "pending" — but only when they have
 * no financial activity (no loan account, no chit membership), so live accounts
 * are never disturbed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasTable('kyc_details')) {
            return;
        }

        $verifiedClientIds = DB::table('kyc_details')
            ->where('status', 'verified')
            ->pluck('client_id')
            ->filter()
            ->all();

        $query = DB::table('clients')
            ->whereIn('status', ['active', 'verified']);

        if ($verifiedClientIds !== []) {
            $query->whereNotIn('id', $verifiedClientIds);
        }

        // Never touch clients with live loan accounts.
        if (Schema::hasTable('loan_accounts')) {
            $query->whereNotIn('id', function ($sub) {
                $sub->select('client_id')->from('loan_accounts')->whereNotNull('client_id');
            });
        }

        // Never touch clients holding chit memberships.
        if (Schema::hasTable('group_members')) {
            $query->whereNotIn('id', function ($sub) {
                $sub->select('client_id')->from('group_members')->whereNotNull('client_id');
            });
        }

        $query->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // No safe rollback: previous per-client status is not recoverable.
    }
};
