<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles for web guard
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Agent', 'guard_name' => 'web']);
        
        // ADD THIS LINE BELOW:
        Role::firstOrCreate(['name' => 'CreditVerifier', 'guard_name' => 'web']);
    }
}