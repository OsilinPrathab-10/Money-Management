<?php

namespace App\Events;

use App\Models\FixedDepositApplication;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FdApplicationApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FixedDepositApplication $application
    ) {}
}
