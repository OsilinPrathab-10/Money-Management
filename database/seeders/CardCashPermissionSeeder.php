<?php

namespace Database\Seeders;

use App\Models\CardCash\CardCashPaymentSource;
use App\Models\CardCash\CardCashSetting;
use App\Models\CardCash\CardCashWithdrawalGateway;
use App\Models\CardCash\CreditCardWallet;
use App\Models\CardCash\CreditCardWalletTransaction;
use App\Models\RoleMenu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CardCashPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Spatie Permissions
        $permissions = [
            'card_cash.view',
            'card_cash.create',
            'card_cash.edit',
            'card_cash.process',
            'card_cash.approve',
            'card_cash.return',
            'card_cash.wallet_view',
            'card_cash.wallet_manage',
            'card_cash.report',
            'card_cash.delete',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate(['name' => $permName]);
        }

        // Grant to Admin
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $admin->givePermissionTo(Permission::whereIn('name', $permissions)->get());

        // Grant select permissions to Staff
        $staff = Role::where('name', 'Staff')->first();
        if ($staff) {
            $staff->givePermissionTo([
                'card_cash.view',
                'card_cash.create',
                'card_cash.process',
                'card_cash.return',
                'card_cash.wallet_view',
                'card_cash.report',
            ]);
        }

        // 2. Default Card to Cash Settings
        $settings = [
            ['key' => 'card_return_percentage', 'value' => '99.00', 'description' => 'Default percentage returned to customer on Card return (e.g. 99%)'],
            ['key' => 'upi_return_enabled', 'value' => '1', 'description' => 'Enable UPI payment return method'],
            ['key' => 'imps_return_enabled', 'value' => '1', 'description' => 'Enable IMPS / Bank transfer return method'],
            ['key' => 'other_return_enabled', 'value' => '1', 'description' => 'Enable Other payment return method'],
            ['key' => 'min_amount', 'value' => '1000', 'description' => 'Minimum transaction amount'],
            ['key' => 'max_amount', 'value' => '1000000', 'description' => 'Maximum transaction amount'],
            ['key' => 'whatsapp_enabled', 'value' => '1', 'description' => 'Enable customer WhatsApp notifications'],
        ];

        foreach ($settings as $s) {
            CardCashSetting::updateOrCreate(
                ['key' => $s['key']],
                ['value' => $s['value'], 'description' => $s['description']]
            );
        }

        // 3. Default Wallets
        $wallets = [
            [
                'wallet_name' => 'SLP Wallet',
                'wallet_code' => 'SLP_WALLET',
                'wallet_type' => 'slp',
                'opening_balance' => 500000.00,
                'current_balance' => 500000.00,
            ],
            [
                'wallet_name' => 'Own Wallet',
                'wallet_code' => 'OWN_WALLET',
                'wallet_type' => 'own',
                'opening_balance' => 200000.00,
                'current_balance' => 200000.00,
            ],
        ];

        foreach ($wallets as $w) {
            $wallet = CreditCardWallet::firstOrCreate(
                ['wallet_code' => $w['wallet_code']],
                [
                    'wallet_name' => $w['wallet_name'],
                    'wallet_type' => $w['wallet_type'],
                    'opening_balance' => $w['opening_balance'],
                    'current_balance' => $w['current_balance'],
                    'status' => 'active',
                    'remarks' => 'Initial system seed wallet',
                ]
            );

            // Seed initial ledger transaction if none exists
            if (!$wallet->transactions()->exists()) {
                CreditCardWalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'transaction_type' => 'credit',
                    'reference_type' => 'opening_balance',
                    'reference_id' => null,
                    'amount' => $wallet->opening_balance,
                    'balance_before' => 0,
                    'balance_after' => $wallet->opening_balance,
                    'description' => 'Opening balance initialization',
                    'transaction_date' => now(),
                    'created_by' => 1,
                ]);
            }
        }

        // 4. Default Payment Sources
        $slpWallet = CreditCardWallet::where('wallet_code', 'SLP_WALLET')->first();
        $sources = [
            ['source_name' => 'SLP(E) Payment Gateway', 'source_type' => 'gateway', 'account_number_or_reference' => 'SLP-GW-01', 'wallet_id' => optional($slpWallet)->id],
            ['source_name' => 'SLP Direct Gateway', 'source_type' => 'gateway', 'account_number_or_reference' => 'SLP-DIR-01', 'wallet_id' => optional($slpWallet)->id],
            ['source_name' => 'Company Current Bank Account', 'source_type' => 'account', 'account_number_or_reference' => 'ACC-9876543210', 'wallet_id' => null],
        ];

        foreach ($sources as $src) {
            CardCashPaymentSource::firstOrCreate(
                ['source_name' => $src['source_name']],
                [
                    'source_type' => $src['source_type'],
                    'account_number_or_reference' => $src['account_number_or_reference'],
                    'wallet_id' => $src['wallet_id'],
                    'status' => 'active',
                    'remarks' => 'System seed payment source',
                ]
            );
        }

        // 5. Default Withdrawal Gateways
        $gateways = [
            ['gateway_name' => 'SLP Gateway', 'gateway_code' => 'SLP_GW', 'wallet_supported' => true],
            ['gateway_name' => 'Own Gateway', 'gateway_code' => 'OWN_GW', 'wallet_supported' => true],
            ['gateway_name' => 'Pine Labs POS', 'gateway_code' => 'PINE_LABS', 'wallet_supported' => true],
        ];

        foreach ($gateways as $gw) {
            CardCashWithdrawalGateway::firstOrCreate(
                ['gateway_code' => $gw['gateway_code']],
                [
                    'gateway_name' => $gw['gateway_name'],
                    'status' => 'active',
                    'wallet_supported' => $gw['wallet_supported'],
                    'remarks' => 'System seed withdrawal gateway',
                ]
            );
        }

        // 6. Role Menu mapping for Admin and Staff
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole) {
            $menuKeys = [
                'card-to-cash',
                'card-to-cash.dashboard',
                'card-to-cash.leads',
                'card-to-cash.leads.create',
                'card-to-cash.processing',
                'card-to-cash.bill-payments',
                'card-to-cash.swipes',
                'card-to-cash.returns',
                'card-to-cash.customers',
                'card-to-cash.wallets',
                'card-to-cash.reports',
            ];

            foreach ($menuKeys as $key) {
                RoleMenu::firstOrCreate([
                    'role_id' => $adminRole->id,
                    'menu_key' => $key,
                ]);
            }
        }
    }
}
