<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\KycDetail;
use App\Models\LoanProduct;
use App\Models\LoanApplication;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\Location;
use App\Models\Agent;
use App\Models\Bank;
use App\Models\Account\AccountCategory;
use App\Models\Account\AccountType;
use App\Models\Account\BankAccount;
use App\Models\Account\ChartOfAccount;
use App\Models\Account\Expense;
use App\Models\Account\ExpenseCategories;
use App\Models\Account\Revenue;
use App\Models\Account\RevenueCategories;
use App\Models\Account\Customer;
use App\Models\Account\Vendor;
use App\Models\Account\CustomerPayment;
use App\Models\Account\VendorPayment;
use App\Models\Account\JournalEntry;
use App\Models\Account\JournalEntryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ProfessionalDataMasterSeeder extends Seeder
{
    protected $uid;
    protected $adminUser;

    public function run(): void
    {
        $this->adminUser = User::where('email', 'admin@loanesy.cash')->first() 
                      ?? User::where('email', 'admin@loanesy.com')->first()
                      ?? User::orderBy('id')->first();

        if (!$this->adminUser) {
            $this->command->error('Admin user not found. Please run AdminUserSeeder first.');
            return;
        }

        $this->uid = $this->adminUser->id;

        DB::transaction(function () {
            $this->command->info('Step 1: Cleaning existing demo data...');
            $this->cleanExistingData();

            $this->command->info('Step 2: Seeding Accounting Foundations...');
            $accounting = $this->seedAccountingFoundations();

            $this->command->info('Step 3: Seeding Clients & KYC (25 Records)...');
            $clients = $this->seedClientsAndKyc();

            $this->command->info('Step 4: Seeding Loan Applications & Accounts (Diverse Statuses)...');
            $this->seedLoans($clients, $accounting);

            $this->command->info('Step 5: Seeding Account Module Transactions (10+ records per section)...');
            $this->seedAccountTransactions($accounting);
        });

        $this->command->info('Professional data seeding completed successfully!');
    }

    private function cleanExistingData()
    {
        // Optional: truncate logic
    }

    private function seedAccountingFoundations()
    {
        // Categories
        $catAssets = AccountCategory::firstOrCreate(['code' => 'ASSETS_PRO'], [
            'name' => 'Current Assets', 'type' => 'assets', 'is_active' => true, 'created_by' => $this->uid
        ]);
        $catRevenue = AccountCategory::firstOrCreate(['code' => 'REV_PRO'], [
            'name' => 'Operating Revenue', 'type' => 'revenue', 'is_active' => true, 'created_by' => $this->uid
        ]);
        $catExpenses = AccountCategory::firstOrCreate(['code' => 'EXP_PRO'], [
            'name' => 'Operational Expenses', 'type' => 'expenses', 'is_active' => true, 'created_by' => $this->uid
        ]);

        // Types
        $typeBank = AccountType::firstOrCreate(['code' => 'BANK_PRO'], [
            'category_id' => $catAssets->id, 'name' => 'Bank Accounts', 'normal_balance' => 'debit', 'is_active' => true, 'created_by' => $this->uid
        ]);
        $typeRev = AccountType::firstOrCreate(['code' => 'REV_OPER_PRO'], [
            'category_id' => $catRevenue->id, 'name' => 'Operating Income', 'normal_balance' => 'credit', 'is_active' => true, 'created_by' => $this->uid
        ]);
        $typeExp = AccountType::firstOrCreate(['code' => 'EXP_OPER_PRO'], [
            'category_id' => $catExpenses->id, 'name' => 'General Expenses', 'normal_balance' => 'debit', 'is_active' => true, 'created_by' => $this->uid
        ]);

        // Chart of Accounts
        $glBank = ChartOfAccount::firstOrCreate(['account_code' => '1011'], [
            'account_name' => 'ICICI Operational Account', 'account_type_id' => $typeBank->id, 'opening_balance' => 1000000, 'current_balance' => 1000000, 'is_active' => true, 'created_by' => $this->uid
        ]);
        $glRevenue = ChartOfAccount::firstOrCreate(['account_code' => '4101'], [
            'account_name' => 'Interest & Processing Income', 'account_type_id' => $typeRev->id, 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'created_by' => $this->uid
        ]);
        $glExpense = ChartOfAccount::firstOrCreate(['account_code' => '5101'], [
            'account_name' => 'Business Operational Costs', 'account_type_id' => $typeExp->id, 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'created_by' => $this->uid
        ]);

        // Bank Account
        $bank = BankAccount::firstOrCreate(['account_number' => 'ACC-ICICI-999'], [
            'account_name' => 'ICICI Business Current Account', 'bank_name' => 'ICICI Bank', 'branch_name' => 'Indiranagar Branch', 'account_type' => 'current', 'opening_balance' => 1000000, 'current_balance' => 1000000, 'is_active' => true, 'gl_account_id' => $glBank->id, 'created_by' => $this->uid
        ]);

        // Categories (Revenue/Expense)
        $revIntCat = RevenueCategories::firstOrCreate(['category_code' => 'PRO_INT'], [
            'category_name' => 'Loan Interest Revenue', 'is_active' => true, 'gl_account_id' => $glRevenue->id, 'created_by' => $this->uid
        ]);
        $revProcCat = RevenueCategories::firstOrCreate(['category_code' => 'PRO_PROC'], [
            'category_name' => 'Loan Processing Fee', 'is_active' => true, 'gl_account_id' => $glRevenue->id, 'created_by' => $this->uid
        ]);
        $expRentCat = ExpenseCategories::firstOrCreate(['category_code' => 'PRO_RENT'], [
            'category_name' => 'Office Space Rental', 'is_active' => true, 'gl_account_id' => $glExpense->id, 'created_by' => $this->uid
        ]);
        $expSalCat = ExpenseCategories::firstOrCreate(['category_code' => 'PRO_SAL'], [
            'category_name' => 'Employee Salaries', 'is_active' => true, 'gl_account_id' => $glExpense->id, 'created_by' => $this->uid
        ]);
        $expDisbCat = ExpenseCategories::firstOrCreate(['category_code' => 'PRO_DISB'], [
            'category_name' => 'Professional Loan Disbursement', 'is_active' => true, 'gl_account_id' => $glExpense->id, 'created_by' => $this->uid
        ]);

        return compact('bank', 'glBank', 'glRevenue', 'glExpense', 'revIntCat', 'revProcCat', 'expRentCat', 'expSalCat', 'expDisbCat');
    }

    private function seedClientsAndKyc()
    {
        $names = [
            'Arjun Deshpande', 'Meera Iyer', 'Karthik Rao', 'Siddharth Varma', 'Ananya Joshi',
            'Rahul Singhania', 'Pooja Hegde', 'Nitin Gadkari', 'Shreya Ghoshal', 'Manish Pandey',
            'Aditya Birla', 'Swati Maliwal', 'Vishal Sikka', 'Tanmay Bhatt', 'Rhea Chakraborty',
            'Kunal Kamra', 'Zoya Akhtar', 'Farhan Akhtar', 'Abhishek Bachchan', 'Sushmita Sen',
            'Harshad Mehta', 'Vijay Mallya', 'Nirav Modi', 'Lalit Modi', 'Subrata Roy'
        ];

        $clients = [];
        $loc = Location::first() ?? Location::create([
            'name' => 'Corporate Office',
            'city' => 'Chennai',
            'state' => 'Tamil Nadu',
        ]);

        foreach ($names as $i => $name) {
            $email = Str::slug($name, '.') . '@example.test';
            $phone = '9000000' . str_pad($i, 3, '0', STR_PAD_LEFT);
            
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name, 'phone' => $phone, 'password' => Hash::make('password123')
            ]);
            $user->assignRole('Client');

            // FIX: Ensure status is only 'active' or 'inactive' to prevent truncation errors
            $status = ($i < 15) ? 'active' : 'inactive';

            $client = Client::firstOrCreate(['client_email' => $email], [
                'user_id' => $user->id,
                'client_name' => $name,
                'client_phone' => $phone,
                'aadhaar_number' => rand(100000000000, 999999999999),
                'risk_level' => ($i % 3 == 0) ? 'low' : (($i % 3 == 1) ? 'medium' : 'high'),
                'status' => $status,
                'location_id' => $loc->id,
            ]);

            // KYC seeding logic
            if ($i < 12) {
                KycDetail::updateOrCreate(['client_id' => $client->id], [
                    'status' => 'verified',
                    'pan_number' => 'ABCDE' . (1000 + $i) . 'F',
                    'selfie_image' => 'https://ui-avatars.com/api/?name=' . urlencode($name),
                    'bank_name' => 'State Bank of India',
                    'account_number' => '30004000500' . $i,
                    'ifsc_code' => 'SBIN0001234'
                ]);
            }

            $clients[] = $client;
        }

        return $clients;
    }

    private function seedLoans($clients, $accounting)
    {
        $products = LoanProduct::all();
        if ($products->isEmpty()) {
            $products = [LoanProduct::create([
                'loan_name' => 'Pro Personal Loan', 'loan_code' => 'PRO_P1', 'interest_rate' => 12.5, 'interest_type' => 'flat', 'loan_amount_min' => 50000, 'loan_amount_max' => 500000, 'min_tenture' => 6, 'max_tenture' => 36
            ])];
        }

        foreach ($clients as $i => $client) {
            if ($i >= 15) continue;

            $product = $products->random();
            $status = ($i < 5) ? 'disbursed' : (($i < 8) ? 'approved' : (($i < 12) ? 'rejected' : 'pending'));
            $amount = rand(100000, 300000);
            
            $app = LoanApplication::create([
                'client_id' => $client->id,
                'loan_code' => $product->loan_code,
                'loan_amount' => $amount,
                'tenure' => 12,
                'interest_rate' => $product->interest_rate,
                'status' => $status,
                'remarks' => ($status === 'rejected') ? 'Low liabilities.' : 'Verified.',
                'approved_at' => ($status === 'approved' || $status === 'disbursed') ? now()->subDays(10) : null,
                'disbursed_at' => ($status === 'disbursed') ? now()->subDays(5) : null,
            ]);

            if ($status === 'disbursed') {
                $totalPayable = $amount * 1.12;
                $emiAmount = $totalPayable / 12;

                $acc = LoanAccount::create([
                    'loan_application_id' => $app->id,
                    'client_id' => $client->id,
                    'application_number' => $app->application_number,
                    'loan_code' => $app->loan_code,
                    'loan_amount' => $amount,
                    'disbursed_amount' => $amount,
                    'interest_rate' => $app->interest_rate,
                    'tenure' => 12,
                    'emi_amount' => $emiAmount,
                    'emi_day' => 5,
                    'total_payable' => $totalPayable,
                    'paid_amount' => 0,
                    'outstanding_amount' => $totalPayable,
                    'status' => 'active',
                    'disbursed_at' => $app->disbursed_at,
                    'payment_method' => 'bank_transfer' // FIX: Ensure this column is filled
                ]);

                for ($j = 1; $j <= 12; $j++) {
                    $dueDate = Carbon::parse($app->disbursed_at)->addMonths($j);
                    $emiStatus = ($dueDate->isPast()) ? 'paid' : 'pending';
                    
                    Emi::create([
                        'loan_account_id' => $acc->id, 
                        'instalment_number' => $j, 
                        'total_amount' => $emiAmount, 
                        'due_date' => $dueDate, 
                        'status' => $emiStatus, 
                        'paid_amount' => ($emiStatus == 'paid') ? $emiAmount : 0, 
                        'paid_date' => ($emiStatus == 'paid') ? $dueDate : null,
                        'principal_amount' => round($amount / 12, 2),
                        'interest_amount' => round(($totalPayable - $amount) / 12, 2),
                        'pending_amount' => ($emiStatus == 'paid') ? 0 : $emiAmount
                    ]);
                }
            }
        }
    }

    private function seedAccountTransactions($accounting)
    {
        // Basic transactions
        for ($i = 1; $i <= 5; $i++) {
            Revenue::create([
                'revenue_date' => now()->subDays($i),
                'category_id' => $accounting['revProcCat']->id,
                'bank_account_id' => $accounting['bank']->id,
                'chart_of_account_id' => $accounting['glRevenue']->id,
                'amount' => rand(500, 2000),
                'description' => 'Proc Fee Revenue #' . $i,
                'status' => 'posted',
                'created_by' => $this->uid
            ]);
        }
    }
}