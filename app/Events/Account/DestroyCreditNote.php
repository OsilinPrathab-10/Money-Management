<?php

namespace App\Events\Account;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account\CreditNote;

class DestroyCreditNote
{

    use Dispatchable;

    public function __construct(
        public CreditNote $creditNote
    ) {}
}