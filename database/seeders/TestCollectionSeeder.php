<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmiCollection;
use App\Models\Agent;
use App\Models\Emi;

class TestCollectionSeeder extends Seeder
{
    public function run()
    {
        // Get first agent and EMI
        $agent = Agent::first();
        $emi = Emi::first();

        if ($agent && $emi) {
            EmiCollection::create([
                'emi_id' => $emi->id,
                'agent_id' => $agent->id,
                'amount' => 5000.00,
                'payment_method' => 'in_hand',
                'payment_type' => 'overdue',
                'status' => 'in_progress',
                'collected_at' => now(),
                'remarks' => 'Test collection'
            ]);

            echo "Test collection created successfully!\n";
        } else {
            echo "No agent or EMI found. Please create them first.\n";
        }
    }
}
