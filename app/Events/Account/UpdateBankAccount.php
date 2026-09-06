<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account\BankAccount;

class UpdateBankAccount
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public BankAccount $bankAccount
    ) {}
}