<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\Models\Account\AccountType;

class CreateAccountType
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public AccountType $accounttype
    ) {}
}
