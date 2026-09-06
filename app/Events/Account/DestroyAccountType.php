<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\AccountType;

class DestroyAccountType
{
    use Dispatchable;

    public function __construct(
        public AccountType $accounttype
    ) {}
}
