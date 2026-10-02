<?php

namespace App\Events;

use App\Models\Agent;
use App\Models\Client;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientAssignedToAgentEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Client $client,
        public Agent $agent
    ) {}
}
