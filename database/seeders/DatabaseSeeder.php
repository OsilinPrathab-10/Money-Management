<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\PageSeeder;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\SupportTicketSeeder;
use Database\Seeders\SmsTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\LoanSeeder;
use Database\Seeders\DocumentTemplateSeeder;
use Database\Seeders\BankSeeder;
use Database\Seeders\AccountModuleDemoSeeder;
use Database\Seeders\CreditScoreSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            PageSeeder::class,
            AdminUserSeeder::class,
            SupportTicketSeeder::class,
            SmsTemplateSeeder::class,
            LoanSeeder::class,
            DocumentTemplateSeeder::class,
            BankSeeder::class,
            AccountModuleDemoSeeder::class,
            CreditScoreSeeder::class,
            ProfessionalDataMasterSeeder::class,
        ]);
    }
}
