<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\VendorPayment;

class DestroyVendorPayment
{
    use Dispatchable;

    public function __construct(
        public VendorPayment $vendorPayment
    ) {}
}