<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Account\AccountCategory;
use App\Models\Account\AccountType;
use App\Models\Account\BankAccount;
use App\Models\Account\ChartOfAccount;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds ERP-style accounting demo data for the logged-in admin user scope (created_by = admin id).
 * Run: php artisan db:seed --class=AccountModuleDemoSeeder
 */
class AccountModuleDemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'admin@loanesy.com')->first()
            ?? User::query()->orderBy('id')->first();

        if (! $user) {
            $this->command?->warn('No user found. Run AdminUserSeeder first.');

            return;
        }

        $uid = (int) $user->id;

        DB::transaction(function () use ($uid) {
            // --- Account categories & types (foundation for chart of accounts)
            $catAssets = AccountCategory::firstOrCreate(
                ['code' => 'ASSETS_CURR'],
                [
                    'name' => 'Current Assets',
                    'type' => 'assets',
                    'description' => 'Current assets including bank and cash balances',
                    'is_active' => true,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $catRevenue = AccountCategory::firstOrCreate(
                ['code' => 'REV_OPERATING'],
                [
                    'name' => 'Operating Revenue',
                    'type' => 'revenue',
                    'description' => 'Main revenue from loan operations and services',
                    'is_active' => true,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $catExpenses = AccountCategory::firstOrCreate(
                ['code' => 'EXP_ADMIN'],
                [
                    'name' => 'Administrative Expenses',
                    'type' => 'expenses',
                    'description' => 'General and administrative operational expenses',
                    'is_active' => true,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $catLoanOps = AccountCategory::firstOrCreate(
                ['code' => 'LOAN_OPS'],
                [
                    'name' => 'Loan Operations',
                    'type' => 'expenses',
                    'description' => 'Direct expenses and outflows related to loan disbursements',
                    'is_active' => true,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $typeBank = AccountType::firstOrCreate(
                ['code' => 'BANK_CASH', 'created_by' => $uid],
                [
                    'category_id' => $catAssets->id,
                    'name' => 'Bank & Cash Accounts',
                    'normal_balance' => 'debit',
                    'description' => 'Liquid assets in banks and petty cash',
                    'is_active' => true,
                    'is_system_type' => false,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $typeRev = AccountType::firstOrCreate(
                ['code' => 'REV_SERVICE', 'created_by' => $uid],
                [
                    'category_id' => $catRevenue->id,
                    'name' => 'Service & Interest Revenue',
                    'normal_balance' => 'credit',
                    'description' => 'Revenue from services and interest income',
                    'is_active' => true,
                    'is_system_type' => false,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $typeExp = AccountType::firstOrCreate(
                ['code' => 'EXP_GENERAL', 'created_by' => $uid],
                [
                    'category_id' => $catExpenses->id,
                    'name' => 'General Operational Expense',
                    'normal_balance' => 'debit',
                    'description' => 'Monthly operational and recurring expenses',
                    'is_active' => true,
                    'is_system_type' => false,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            // --- Chart of accounts (codes aligned with Revenue/Expense controller filters)
            $glBank = ChartOfAccount::firstOrCreate(
                ['account_code' => '1010', 'created_by' => $uid],
                [
                    'account_name' => 'HDFC Operational Account',
                    'account_type_id' => $typeBank->id,
                    'parent_account_id' => null,
                    'level' => 1,
                    'normal_balance' => 'debit',
                    'opening_balance' => 500000,
                    'current_balance' => 500000,
                    'is_active' => true,
                    'is_system_account' => false,
                    'description' => 'Primary operational bank G/L',
                    'creator_id' => $uid,
                ]
            );

            $glRevenue = ChartOfAccount::firstOrCreate(
                ['account_code' => '4100', 'created_by' => $uid],
                [
                    'account_name' => 'Consultancy income',
                    'account_type_id' => $typeRev->id,
                    'parent_account_id' => null,
                    'level' => 1,
                    'normal_balance' => 'credit',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                    'is_system_account' => false,
                    'description' => 'Revenue from consulting and advisory',
                    'creator_id' => $uid,
                ]
            );

            $glExpense = ChartOfAccount::firstOrCreate(
                ['account_code' => '5100', 'created_by' => $uid],
                [
                    'account_name' => 'Office Rent & Maintenance',
                    'account_type_id' => $typeExp->id,
                    'parent_account_id' => null,
                    'level' => 1,
                    'normal_balance' => 'debit',
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'is_active' => true,
                    'is_system_account' => false,
                    'description' => 'Fixed office operational costs',
                    'creator_id' => $uid,
                ]
            );

            // --- Bank account (must link to GL in 1000–1099 range for ERP bank module)
            $bank = BankAccount::firstOrCreate(
                ['account_number' => 'ACC-HDFC-001', 'created_by' => $uid],
                [
                    'account_name' => 'HDFC Corporate Current Account',
                    'bank_name' => 'HDFC Bank',
                    'branch_name' => 'Koramangala Branch',
                    'account_type' => 'current',
                    'opening_balance' => 5000000,
                    'current_balance' => 5000000,
                    'is_active' => true,
                    'gl_account_id' => $glBank->id,
                    'creator_id' => $uid,
                ]
            );

            $revCat = RevenueCategories::firstOrCreate(
                ['category_code' => 'REV_PROC', 'created_by' => $uid],
                [
                    'category_name' => 'Loan processing fees',
                    'description' => 'Income from new loan application processing',
                    'is_active' => true,
                    'gl_account_id' => $glRevenue->id,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $revEmiCat = RevenueCategories::firstOrCreate(
                ['category_code' => 'REV_EMI', 'created_by' => $uid],
                [
                    'category_name' => 'EMI Interest Income',
                    'description' => 'Interest portion of EMI payments received from clients',
                    'is_active' => true,
                    'gl_account_id' => $glRevenue->id,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $expCat = ExpenseCategories::firstOrCreate(
                ['category_code' => 'EXP_ADMIN_01', 'created_by' => $uid],
                [
                    'category_name' => 'Office Rental',
                    'description' => 'Monthly head office rental expense',
                    'is_active' => true,
                    'gl_account_id' => $glExpense->id,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            $expDisburseCat = ExpenseCategories::firstOrCreate(
                ['category_code' => 'EXP_LOAN_DISB', 'created_by' => $uid],
                [
                    'category_name' => 'Loan Disbursement Outflow',
                    'description' => 'Funds disbursed to clients for approved loans',
                    'is_active' => true,
                    'gl_account_id' => $glExpense->id,
                    'creator_id' => $uid,
                    'created_by' => $uid,
                ]
            );

            // --- Sample transactions (Realistic)
            $statuses = ['draft', 'approved', 'posted'];
            $revenueDescs = ['Professional services', 'Consulting fee', 'Processing fee income', 'Interest income'];
            $expenseDescs = ['Office maintenance', 'Electricity bill', 'Internet subscription', 'Stationery items'];
            
            for ($i = 1; $i <= 10; $i++) {
                $ref = 'TXN-REV-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                if (Revenue::where('created_by', $uid)->where('reference_number', $ref)->doesntExist()) {
                    $status = $statuses[array_rand($statuses)];
                    Revenue::create([
                        'revenue_date' => now()->subDays(rand(1, 30)),
                        'category_id' => $revCat->id,
                        'bank_account_id' => $bank->id,
                        'chart_of_account_id' => $glRevenue->id,
                        'amount' => rand(5000, 50000),
                        'description' => $revenueDescs[array_rand($revenueDescs)] . ' - ' . $i,
                        'reference_number' => $ref,
                        'status' => $status,
                        'approved_by' => $status !== 'draft' ? $uid : null,
                        'creator_id' => $uid,
                        'created_by' => $uid,
                    ]);
                }
            }

            for ($i = 1; $i <= 10; $i++) {
                $ref = 'TXN-EXP-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                if (Expense::where('created_by', $uid)->where('reference_number', $ref)->doesntExist()) {
                    $status = $statuses[array_rand($statuses)];
                    Expense::create([
                        'expense_date' => now()->subDays(rand(1, 30)),
                        'category_id' => $expCat->id,
                        'bank_account_id' => $bank->id,
                        'chart_of_account_id' => $glExpense->id,
                        'amount' => rand(1000, 15000),
                        'description' => $expenseDescs[array_rand($expenseDescs)] . ' - ' . $i,
                        'reference_number' => $ref,
                        'status' => $status,
                        'approved_by' => $status !== 'draft' ? $uid : null,
                        'creator_id' => $uid,
                        'created_by' => $uid,
                    ]);
                }
            }
        });

        $this->command?->info('Account module realistic data seeded for user: '.$user->email);
    }
}
