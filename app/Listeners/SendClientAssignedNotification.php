<?php

namespace App\Listeners;

use App\Events\ClientAssignedToAgentEvent;
use App\Models\Agent;
use App\Models\Client;
use App\Services\AppNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendClientAssignedNotification
{
    public function handle(ClientAssignedToAgentEvent $event): void
    {
        $clientId = (int) $event->client->id;
        $agentId = (int) $event->agent->id;

        dispatch(function () use ($clientId, $agentId) {
            try {
                $client = Client::find($clientId);
                $agent = Agent::find($agentId);
                if (! $client || ! $agent) {
                    return;
                }
                app(AppNotificationService::class)->clientAssigned($client, $agent);
            } catch (Throwable $e) {
                Log::error('SendClientAssignedNotification failed: ' . $e->getMessage());
            }
        })->afterResponse();
    }
}
