<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\CustomerPayment;

class DestroyCustomerPayment
{
    use Dispatchable;

    public function __construct(
        public CustomerPayment $customerPayment
    ) {}
}
