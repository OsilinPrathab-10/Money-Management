<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\CreditScoreHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreditScoreSeeder extends Seeder
{
    /**
     * Demo CIBIL / credit score history rows + a client used for testing fetches.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $createdBy = $admin?->id ?? User::query()->value('id');

        // Step 1: Handle User
        $clientUser = User::updateOrCreate(
            ['email' => 'cibil.demo.client@example.com'],
            [
                'name' => 'CIBIL Demo Client',
                'phone' => '9876500003',
                'password' => Hash::make('Client@123'),
            ]
        );

        // Step 2: Handle Client Profile
        // FIX: Look up by client_email to prevent the duplicate email crash.
        $client = Client::updateOrCreate(
            ['client_email' => 'cibil.demo.client@example.com'], 
            [
                'client_phone' => '9876500003',
                'user_id' => $clientUser->id,
                'client_name' => 'CIBIL Demo Client',
                'aadhaar_number' => '999900001111', 
                'cibil_score' => 720,
                'status' => 'active',
                'accepted_terms' => 1,
                'accepted_privacy' => 1,
                'risk_level' => 'low',
            ]
        );

        // Step 3: Check if history already exists
        if (CreditScoreHistory::where('client_id', $client->id)->exists()) {
            $this->command?->info('Credit score histories already present — skipping sample rows.');
            return;
        }

        // Step 4: Seed Histories
        $samples = [
            [
                'client_id' => $client->id,
                'applicant_name' => $client->client_name,
                'pan_number' => 'ABCDE1234F',
                'aadhar_number' => $client->aadhaar_number,
                'email' => $client->client_email,
                'phone' => $client->client_phone,
                'date_of_birth' => '1992-01-01',
                'score' => 720,
                'rating' => 'Good',
                'status' => 'demo',
                'report_json' => ['source' => 'seed', 'note' => 'Sample bureau-style payload'],
                'error_message' => null,
            ],
            [
                'client_id' => null,
                'applicant_name' => 'Sample Applicant',
                'pan_number' => 'SAMPLE99X',
                'aadhar_number' => null,
                'email' => 'sample@example.com',
                'phone' => '9123456789',
                'date_of_birth' => '1995-05-05',
                'score' => 680,
                'rating' => 'Fair',
                'status' => 'success',
                'report_json' => ['source' => 'seed'],
                'error_message' => null,
            ],
        ];

        foreach ($samples as $row) {
            CreditScoreHistory::create(array_merge($row, [
                'created_by' => $createdBy,
            ]));
        }

        $this->command?->info('Seeded sample credit score history + CIBIL demo client.');
    }
}