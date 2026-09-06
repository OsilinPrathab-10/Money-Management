<?php

namespace Database\Seeders;

use App\Models\Appearance;
use Illuminate\Database\Seeder;

class AppearanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default web appearance settings
        Appearance::updateOrCreate(
            ['type' => 'web'],
            [
                'primary_color' => '#696cff',
                'secondary_color' => '#8592a3',
                'title' => 'Loan App',
                'subtitle' => 'Loan Management System',
                'logo' => '',
                'logo_dark' => '',
                'favicon' => '',
                'footer_text' => '© 2025 Loan App. All rights reserved.'
            ]
        );

        // Create default app appearance settings
        Appearance::updateOrCreate(
            ['type' => 'app'],
            [
                'primary_color' => '#696cff',
                'secondary_color' => '#8592a3',
                'title' => 'Loan App',
                'subtitle' => 'Mobile App',
                'logo' => '',
                'logo_dark' => '',
                'favicon' => '',
                'footer_text' => ''
            ]
        );
    }
}
