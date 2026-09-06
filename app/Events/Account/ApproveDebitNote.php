<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\DebitNote;

class ApproveDebitNote
{
    use Dispatchable;

    public function __construct(
        public DebitNote $debitNote
    ) {}
}
