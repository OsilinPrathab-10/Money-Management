<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AgentActivity;
use App\Models\Emi;
use App\Models\EmiAgentAssignment;
use Carbon\Carbon;

class AgentActivitySeeder extends Seeder
{
    public function run()
    {
        $assignments = EmiAgentAssignment::all();

        foreach ($assignments as $assignment) {

            $emiId = $assignment->emi_id;
            $agentId = $assignment->agent_id;

            $activities = [
                [
                    'type' => 'call',
                    'description' => 'Customer not answering',
                    'action_at' => Carbon::now()->subDays(3),
                ],
                [
                    'type' => 'note',
                    'description' => 'He told call back tomorrow',
                    'action_at' => Carbon::now()->subDays(2),
                ],
                [
                    'type' => 'visit',
                    'description' => 'Visited home, customer not available',
                    'action_at' => Carbon::now()->subDay(),
                ],
                [
                    'type' => 'payment',
                    'description' => 'Partial payment confirmed',
                    'action_at' => Carbon::now(),
                ],
            ];

            foreach ($activities as $activity) {
                AgentActivity::create([
                    'emi_id' => $emiId,
                    'agent_id' => $agentId,
                    'type' => $activity['type'],
                    'description' => $activity['description'],
                    'action_at' => $activity['action_at'],
                ]);
            }
        }
    }
}

