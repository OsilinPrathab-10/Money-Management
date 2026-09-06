<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FeatureActivationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable or disable maintenance mode for the application',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('feature_activations')->insert($settings);
    }
}
