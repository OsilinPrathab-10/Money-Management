<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\Customer;

class DestroyCustomer
{
    use Dispatchable;

    public function __construct(
        public Customer $customer
    ) {}
}