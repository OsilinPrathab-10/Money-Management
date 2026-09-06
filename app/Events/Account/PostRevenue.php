<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\Revenue;

class PostRevenue
{
    use Dispatchable;

    public function __construct(
        public Revenue $revenue
    ) {}
}
