<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminSetting;

class AdminSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Homepage Settings
            ['key' => 'admin_title', 'value' => 'Finova', 'type' => 'text'],
            ['key' => 'admin_subtitle', 'value' => 'Loan Management System', 'type' => 'text'],
            
            // Footer Settings
            ['key' => 'footer_company_name', 'value' => 'Codepluse Gen pvt Ltd', 'type' => 'text'],
            ['key' => 'footer_copyright', 'value' => '© 2025 All rights reserved.', 'type' => 'text'],
            
            // Appearance Settings
            ['key' => 'primary_color', 'value' => '#5f61e6', 'type' => 'color'],
            ['key' => 'theme_mode', 'value' => 'light', 'type' => 'text'],
            ['key' => 'theme_skin', 'value' => 'default', 'type' => 'text'],
            ['key' => 'semi_dark', 'value' => '0', 'type' => 'text'],
            ['key' => 'menu_style', 'value' => 'vertical', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            AdminSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'type' => $setting['type']
                ]
            );
        }
    }
}
