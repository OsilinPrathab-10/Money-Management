<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Emi;

class PaymentReceivedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $emi;
    public $amount;

    public function __construct(Emi $emi, $amount)
    {
        $this->emi = $emi;
        $this->amount = $amount;
    }
}
