<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use App\Models\Account\AccountType;

class UpdateAccountType
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public AccountType $accounttype
    ) {}
}
