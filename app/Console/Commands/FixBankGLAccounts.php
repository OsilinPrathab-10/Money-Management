<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Account\BankAccount;
use App\Models\Account\AccountType;
use App\Models\Account\ChartOfAccount;

class FixBankGLAccounts extends Command
{
    protected $signature = 'fix:bank-gl-accounts';
    protected $description = 'Fix Bank Accounts missing GL Accounts';

    public function handle()
    {
        $bankAccounts = BankAccount::whereNull('gl_account_id')->get();
        $this->info("Found " . $bankAccounts->count() . " bank accounts missing GL Account.");

        foreach ($bankAccounts as $bankaccount) {
            $accountType = AccountType::where(function($q) {
                $q->where('code', 'BANK_CASH')->orWhere('name', 'like', '%Bank%');
            })->where('created_by', $bankaccount->created_by)->first();

            if (!$accountType) {
                $accountType = AccountType::firstOrCreate([
                    'name' => 'Bank & Cash Accounts',
                    'created_by' => $bankaccount->created_by,
                ], [
                    'code' => 'BANK_CASH',
                    'description' => 'Liquid assets in banks and petty cash'
                ]);
            }

            $code = '101' . str_pad($bankaccount->id, 3, '0', STR_PAD_LEFT);

            $glAccount = ChartOfAccount::create([
                'account_code' => $code,
                'account_name' => $bankaccount->bank_name . ' - ' . $bankaccount->account_number,
                'level' => 1,
                'normal_balance' => 'debit',
                'opening_balance' => $bankaccount->opening_balance,
                'current_balance' => $bankaccount->current_balance,
                'is_active' => true,
                'is_system_account' => true,
                'account_type_id' => $accountType->id,
                'creator_id' => $bankaccount->creator_id,
                'created_by' => $bankaccount->created_by,
            ]);

            $bankaccount->gl_account_id = $glAccount->id;
            $bankaccount->save();
            $this->info("Fixed Bank Account ID: " . $bankaccount->id);
        }

        $this->info("Done fixing GL accounts!");
    }
}
