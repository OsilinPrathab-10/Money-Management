<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\BankAccount;

class DestroyBankAccount
{
    use Dispatchable;

    public function __construct(
        public BankAccount $bankAccount
    ) {}
}