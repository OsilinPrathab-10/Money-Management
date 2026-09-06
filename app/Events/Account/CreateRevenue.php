<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\Revenue;

class CreateRevenue
{
    use Dispatchable;

    public function __construct(
        public Request $request,
        public Revenue $revenue
    ) {}
}
