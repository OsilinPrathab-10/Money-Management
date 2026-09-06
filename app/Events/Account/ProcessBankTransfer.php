<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\BankTransfer;

class ProcessBankTransfer
{
    use Dispatchable;

    public function __construct(
        public BankTransfer $bankTransfer
    ) {}
}