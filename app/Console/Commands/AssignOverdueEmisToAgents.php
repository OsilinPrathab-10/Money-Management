<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Emi;
use App\Models\Agent;
use App\Models\EmiAgentAssignment;

class AssignOverdueEmisToAgents extends Command
{
    protected $signature = 'assign:overdue-emis';

    protected $description = 'Assign overdue EMIs to agents based on location';

    public function handle()
    {
        Log::info('Overdue EMI agent assignment started at ' . now());

        // Get overdue EMIs which are NOT assigned yet
        $overdueEmis = Emi::where('status', 'overdue')
            ->whereDoesntHave('activeAssignment')
            ->with('loanAccount.client')
            ->chunkById(50, function ($emis) {

                DB::transaction(function () use ($emis) {

                    foreach ($emis as $emi) {
                        $client = $emi->loanAccount->client;

                        if (!$client || !$client->location_id) {
                            Log::warning("EMI {$emi->id} skipped — client/location missing");
                            continue;
                        }

                        // Step 1: Check if client already has an active agent (Sticky Agent Logic)
                        $existingAssignment = EmiAgentAssignment::whereHas('emi.loanAccount', function ($q) use ($client) {
                                $q->where('client_id', $client->id);
                            })
                            ->whereIn('status', ['assigned', 'visited'])
                            ->whereHas('agent', fn($a) => $a->where('status', 'active')) // Ensure agent is still active
                            ->first();

                        if ($existingAssignment) {
                            $agent = Agent::find($existingAssignment->agent_id);
                            Log::info("Sticky Assignment: EMI {$emi->id} assigned to existing agent {$agent->id} for Client {$client->id}");
                        } else {
                            // Step 2: Find new agent in SAME location (Load Balancing)
                            $agent = Agent::where('status', 'active')
                                ->where('location_id', $client->location_id)
                                ->withCount([
                                    'emiAssignments as active_cases_count' => function ($q) {
                                        $q->whereIn('status', ['assigned', 'visited']);
                                    }
                                ])
                                ->orderBy('active_cases_count') // load balancing
                                ->first();
                        }

                        if (!$agent) {
                            Log::warning("No agent found for EMI {$emi->id} (location {$client->location_id})");
                            continue;
                        }

                        $client = $emi->loanAccount->client;

                        if (!$client->assigned_to) {
                            $client->update([
                                'assigned_to' => $agent->id,
                            ]);
                        }

                        EmiAgentAssignment::updateOrCreate(
                            ['emi_id' => $emi->id, 'agent_id' => $agent->id],
                            [
                                'assigned_at' => now(),
                                'status'      => 'assigned',
                            ]
                        );
                    }
                });
            });

        Log::info('Overdue EMI agent assignment completed');

        $this->info('Overdue EMIs assigned successfully.');

        return 0;
    }
}
