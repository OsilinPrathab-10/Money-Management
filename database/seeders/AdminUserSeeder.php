<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'phone' => '9999999999',
                'password' => Hash::make('admin@123'), // change this later
            ]
        );

        $staff = User::firstOrCreate(
            ['email' => 'staff@example.com'],
            [
                'name' => 'Staff User',
                'phone' => '9999999998',
                'password' => Hash::make('staff@123'),
            ]
        );

        $admin->assignRole('Admin');
        $staff->assignRole('Staff');

        $cibil = User::firstOrCreate(
            ['email' => 'cibil@example.com'],
            [
                'name' => 'CIBIL Verifier',
                'phone' => '9876500002',
                'password' => Hash::make('cibil@123'),
            ]
        );
        $cibil->syncRoles(['CreditVerifier']);
        }
}
