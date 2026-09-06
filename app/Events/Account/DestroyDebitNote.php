<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\DebitNote;

class DestroyDebitNote
{
    use Dispatchable;

    public function __construct(
        public DebitNote $debitNote
    ) {}
}
