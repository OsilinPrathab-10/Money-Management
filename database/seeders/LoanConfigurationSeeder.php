<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LoanConfiguration;

class LoanConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Foreclosure Configuration
        LoanConfiguration::updateOrCreate(
            ['type' => 'foreclosure'],
            [
                'eligibility_months' => null, 
                'charges_percentage' => 1.00, 
                'extra_charge' => 0.00,
                'is_active' => false,
            ]
        );

        // Prepayment Configuration
        LoanConfiguration::updateOrCreate(
            ['type' => 'prepayment'],
            [
                'eligibility_months' => null, 
                'charges_percentage' => 0.00, 
                'extra_charge' => 0.00,
                'is_active' => false,
            ]
        );

        $this->command->info('Loan configurations seeded successfully!');
    }
}
