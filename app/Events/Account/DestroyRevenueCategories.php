<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\RevenueCategories;

class DestroyRevenueCategories
{
    use Dispatchable;

    public function __construct(
        public RevenueCategories $revenuecategories
    ) {}
}
