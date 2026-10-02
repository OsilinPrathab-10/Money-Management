<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Services\Account\AccountingTags;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix manual withdrawals that were erroneously stored as 'credit'
        DB::table('bank_transactions')
            ->where(function ($q) {
                $q->where('description', 'like', 'Withdrawn by:%')
                  ->orWhere(function ($sub) {
                      $sub->where('entry_tag', 'manual_entry')
                          ->where('description', 'like', '%Withdrawn%');
                  });
            })
            ->where('transaction_type', 'credit')
            ->update([
                'transaction_type' => 'debit',
                'module_tag' => AccountingTags::MODULE_OTHER,
                'entry_tag' => AccountingTags::ENTRY_WITHDRAWAL,
            ]);

        // Fix manual deposits that were erroneously stored as 'debit'
        DB::table('bank_transactions')
            ->where(function ($q) {
                $q->where('description', 'like', 'Deposited by:%')
                  ->orWhere(function ($sub) {
                      $sub->where('entry_tag', 'manual_entry')
                          ->where('description', 'like', '%Deposited%');
                  });
            })
            ->where('transaction_type', 'debit')
            ->update([
                'transaction_type' => 'credit',
                'module_tag' => AccountingTags::MODULE_OTHER,
                'entry_tag' => AccountingTags::ENTRY_DEPOSIT,
            ]);

        // Clean up remaining manual_entry tags for manual deposits / withdrawals
        DB::table('bank_transactions')
            ->where('description', 'like', 'Withdrawn by:%')
            ->where(function ($q) {
                $q->where('entry_tag', 'manual_entry')
                  ->orWhere('module_tag', 'manual');
            })
            ->update([
                'module_tag' => AccountingTags::MODULE_OTHER,
                'entry_tag' => AccountingTags::ENTRY_WITHDRAWAL,
            ]);

        DB::table('bank_transactions')
            ->where('description', 'like', 'Deposited by:%')
            ->where(function ($q) {
                $q->where('entry_tag', 'manual_entry')
                  ->orWhere('module_tag', 'manual');
            })
            ->update([
                'module_tag' => AccountingTags::MODULE_OTHER,
                'entry_tag' => AccountingTags::ENTRY_DEPOSIT,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: Data fix migration
    }
};
