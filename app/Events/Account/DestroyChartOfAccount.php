<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Account\ChartOfAccount;

class DestroyChartOfAccount
{
    use Dispatchable;

    public function __construct(
        public ChartOfAccount $chartofaccount
    ) {}
}
