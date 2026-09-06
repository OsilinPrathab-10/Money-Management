<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [

            // Users
            'user.create',
            'user.view',
            'user.update',
            'user.delete',

            // Clients
            'client.view',
            'client.update',
            'client.delete',

            // KYC
            'kyc.view',
            'kyc.approve',
            'kyc.reject',

            // Loan Product
            'loan-product.create',
            'loan-product.view',
            'loan-product.update',
            'loan-product.delete',

            // Loan Type
            'loan-type.create',
            'loan-type.view',
            'loan-type.update',
            'loan-type.delete',

            // Loan Application
            'loan-application.view',
            'loan-application.approve',
            'loan-application.reject',
            'loan-application.disbursement',

            // Loan Account
            'loan-account.view',
            'loan-account.foreclose',

            // EMI
            'emi.view',

            // Agent
            'agent.view',
            'agent.update',
            'agent.delete',

            // Support Tickets
            'support.view',
            'support.update',
            'support.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $staff = Role::firstOrCreate(['name' => 'Staff']);

        // Admin gets ALL permissions
        $admin->givePermissionTo(Permission::all());

        // Staff gets selected permissions (example)
        $staff->givePermissionTo([
            'user.view',

            'client.view', 'client.update',

            'kyc.view', 'kyc.approve', 'kyc.reject',

            'loan-product.view',
            'loan-type.view',

            'loan-application.view', 'loan-application.approve', 'loan-application.reject', 'loan-application.disbursement',

            'loan-account.view',

            'emi.view',

            'agent.view',
            'support.view', 'support.update',
        ]);

        // Agent gets limited permissions
        $agent = Role::firstOrCreate(['name' => 'Agent']);
        $agent->givePermissionTo([
            'client.view',
            'kyc.view',
            'loan-application.view',
            'loan-account.view',
            'emi.view',
            'agent.view',
        ]);
    }
}
