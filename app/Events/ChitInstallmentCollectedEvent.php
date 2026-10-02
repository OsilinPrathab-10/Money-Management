<?php

namespace App\Events;

use App\Models\Client;
use App\Models\Installment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChitInstallmentCollectedEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Installment $installment,
        public float $amount,
        public ?Client $client = null,
        public string $source = 'admin'
    ) {}
}
