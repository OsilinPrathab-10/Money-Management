<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\Vendor;

class DestroyVendor
{
    use Dispatchable;

    public function __construct(
        public Vendor $vendor
    ) {}
}