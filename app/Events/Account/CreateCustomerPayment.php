<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\CustomerPayment;

class CreateCustomerPayment
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public CustomerPayment $customerPayment
    ) {}
}
