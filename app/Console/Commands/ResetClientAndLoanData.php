<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetClientAndLoanData extends Command
{
    protected $signature = 'db:reset-client-data {--force : Force the reset without confirmation prompt}';
    protected $description = 'Reset all client, loan, collection and related transactional data to scratch, and remove leftover chit columns/data';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Are you sure you want to reset all client and loan data to scratch?')) {
            $this->warn('Action aborted.');
            return self::SUCCESS;
        }

        $this->info('Starting database reset to scratch...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // 1. Identify admin user IDs to keep (admin only)
        $keepUserIds = collect([1]); // Super Admin
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
            if ($adminRoleId) {
                $adminUsers = DB::table('model_has_roles')
                    ->where('role_id', $adminRoleId)
                    ->pluck('model_id');
                $keepUserIds = $keepUserIds->merge($adminUsers);
            }
        }
        $keepUserIds = $keepUserIds->unique()->filter()->values()->all();
        $this->info('Preserving ' . count($keepUserIds) . ' Admin user(s): ' . implode(', ', $keepUserIds));

        // 2. Delete non-admin users and their relations
        $nonAdminQuery = DB::table('users')->whereNotIn('id', $keepUserIds);
        $nonAdminUserIds = $nonAdminQuery->pluck('id')->all();
        $nonAdminUserCount = count($nonAdminUserIds);

        if (! empty($nonAdminUserIds)) {
            if (Schema::hasTable('personal_access_tokens')) {
                DB::table('personal_access_tokens')
                    ->where('tokenable_type', 'App\\Models\\User')
                    ->whereIn('tokenable_id', $nonAdminUserIds)
                    ->delete();
            }
            if (Schema::hasTable('model_has_roles')) {
                DB::table('model_has_roles')->whereIn('model_id', $nonAdminUserIds)->delete();
            }
            if (Schema::hasTable('model_has_permissions')) {
                DB::table('model_has_permissions')->whereIn('model_id', $nonAdminUserIds)->delete();
            }
            if (Schema::hasTable('user_menus')) {
                DB::table('user_menus')->whereIn('user_id', $nonAdminUserIds)->delete();
            }
            DB::table('users')->whereIn('id', $nonAdminUserIds)->delete();
            $this->info("Deleted {$nonAdminUserCount} non-admin user login(s).");
        }

        // Clean any orphan model_has_roles
        if (Schema::hasTable('model_has_roles')) {
            DB::table('model_has_roles')->whereNotIn('model_id', $keepUserIds)->delete();
        }

        // 3. Tables to truncate and reset AUTO_INCREMENT to 1
        $tablesToReset = [
            'clients',
            'kyc_details',
            'nominees',
            'employee_information',
            'guarantors',
            'account_deletion_requests',
            'credit_score_histories',
            'loan_applications',
            'loan_application_details',
            'loan_accounts',
            'loan_topups',
            'disbursement_details',
            'emis',
            'emi_agent_assignments',
            'emi_collections',
            'emi_followups',
            'emi_reminder_logs',
            'collection_logs',
            'collection_notes',
            'client_loan_documents',
            'recoveries',
            'payments',
            'payment_audit_logs',
            'customer_payments',
            'customer_payment_allocations',
            'customer_wallets',
            'wallet_transactions',
            'credit_notes',
            'credit_note_items',
            'credit_note_item_taxes',
            'credit_note_applications',
            'debit_notes',
            'debit_note_items',
            'debit_note_item_taxes',
            'debit_note_applications',
            'revenues',
            'expenses',
            'bank_transfers',
            'journal_entries',
            'journal_entry_items',
            'fixed_deposits',
            'fixed_deposit_applications',
            'fixed_deposit_transactions',
            'fixed_deposit_audit_logs',
            'fixed_deposit_interest_payouts',
            'fixed_deposit_renewals',
            'support_tickets',
            'support_ticket_replies',
            'support_ticket_attachments',
            'customer_notifications',
            'admin_notifications',
            'agent_notifications',
            'sms_otp_logs',
            'user_otps',
            'user_devices',
            'user_call_logs',
            'user_contacts',
            'user_live_locations',
            'user_sms_messages',
            'verification_api_hits',
            'login_logs',
            'agent_activities',
            'agent_visit_logs',
            'agent_daily_logs',
            'jobs',
            'failed_jobs',
            'job_batches',
            'sessions',
            'personal_access_tokens',
            'staff_advances',
            'staff_attendances',
            'staff_expenses',
            'locations',
            'districts',
            'states',
            'staffs',
        ];

        foreach ($tablesToReset as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
                $this->line("Reset [{$table}]: cleared {$count} record(s), AUTO_INCREMENT set to 1.");
            }
        }

        // 4. Reset bank balances and bank transactions
        if (Schema::hasTable('bank_accounts') && Schema::hasTable('bank_transactions')) {
            DB::table('bank_transactions')->truncate();
            DB::statement("ALTER TABLE `bank_transactions` AUTO_INCREMENT = 1");

            // Reset current balance to opening balance
            DB::statement('UPDATE `bank_accounts` SET `current_balance` = `opening_balance`');

            // Seed clean opening balance transactions for accounts with opening_balance > 0
            $accounts = DB::table('bank_accounts')->where('opening_balance', '>', 0)->get();
            foreach ($accounts as $acc) {
                DB::table('bank_transactions')->insert([
                    'bank_account_id' => $acc->id,
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => 'credit',
                    'reference_number' => 'OPENING-BAL',
                    'description' => 'Opening Balance',
                    'amount' => $acc->opening_balance,
                    'running_balance' => $acc->opening_balance,
                    'transaction_status' => 'cleared',
                    'reconciliation_status' => 'unreconciled',
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->info("Reset bank balances and initialized {$accounts->count()} opening balance transaction(s).");
        }

        // 5. Clean up leftover chit columns from mobile_app_settings if present
        if (Schema::hasTable('mobile_app_settings')) {
            Schema::table('mobile_app_settings', function ($table) {
                if (Schema::hasColumn('mobile_app_settings', 'chit_tab_color')) {
                    $table->dropColumn('chit_tab_color');
                }
                if (Schema::hasColumn('mobile_app_settings', 'chit_banner_images')) {
                    $table->dropColumn('chit_banner_images');
                }
            });
            $this->info('Checked mobile_app_settings schema: chit columns removed.');
        }

        // 6. Clean up chit reminder templates if any remain in sms_templates
        if (Schema::hasTable('sms_templates')) {
            $deletedTemplates = DB::table('sms_templates')
                ->where('identifier', 'like', '%chit%')
                ->orWhere('name', 'like', '%chit%')
                ->delete();
            if ($deletedTemplates > 0) {
                $this->info("Removed {$deletedTemplates} leftover chit template(s) from sms_templates.");
            }
        }

        // 7. Clean cache
        if (Schema::hasTable('cache')) {
            DB::table('cache')->truncate();
        }
        if (Schema::hasTable('cache_locks')) {
            DB::table('cache_locks')->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Database reset to scratch completed successfully!');

        return self::SUCCESS;
    }
}

