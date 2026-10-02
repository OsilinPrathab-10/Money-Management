<?php

namespace App\Events;

use App\Models\Payout;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSettlementApplicationEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Payout $payout,
        public string $source = 'admin'
    ) {}
}
