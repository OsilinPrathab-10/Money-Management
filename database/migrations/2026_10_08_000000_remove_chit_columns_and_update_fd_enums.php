<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop leftover chit columns from mobile_app_settings
        if (Schema::hasTable('mobile_app_settings')) {
            Schema::table('mobile_app_settings', function (Blueprint $table) {
                if (Schema::hasColumn('mobile_app_settings', 'chit_tab_color')) {
                    $table->dropColumn('chit_tab_color');
                }
                if (Schema::hasColumn('mobile_app_settings', 'chit_banner_images')) {
                    $table->dropColumn('chit_banner_images');
                }
            });
        }

        // 2. Clean FD payout option enums (remove 'chit', support 'wallet', 'cash', 'bank_transfer')
        if (Schema::hasTable('fixed_deposit_schemes') && Schema::hasColumn('fixed_deposit_schemes', 'default_payout_option')) {
            DB::statement("UPDATE `fixed_deposit_schemes` SET `default_payout_option` = 'wallet' WHERE `default_payout_option` = 'chit'");
            DB::statement("ALTER TABLE `fixed_deposit_schemes` MODIFY `default_payout_option` ENUM('wallet', 'cash', 'bank_transfer') NOT NULL DEFAULT 'wallet'");
        }

        if (Schema::hasTable('fixed_deposits') && Schema::hasColumn('fixed_deposits', 'payout_option')) {
            DB::statement("UPDATE `fixed_deposits` SET `payout_option` = 'wallet' WHERE `payout_option` = 'chit'");
            DB::statement("ALTER TABLE `fixed_deposits` MODIFY `payout_option` ENUM('wallet', 'cash', 'bank_transfer') NOT NULL DEFAULT 'wallet'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_app_settings')) {
            Schema::table('mobile_app_settings', function (Blueprint $table) {
                if (! Schema::hasColumn('mobile_app_settings', 'chit_tab_color')) {
                    $table->string('chit_tab_color')->default('#7367F0')->nullable()->after('loan_tab_color');
                }
                if (! Schema::hasColumn('mobile_app_settings', 'chit_banner_images')) {
                    $table->json('chit_banner_images')->nullable()->after('loan_banner_images');
                }
            });
        }

        if (Schema::hasTable('fixed_deposit_schemes') && Schema::hasColumn('fixed_deposit_schemes', 'default_payout_option')) {
            DB::statement("ALTER TABLE `fixed_deposit_schemes` MODIFY `default_payout_option` ENUM('wallet', 'chit', 'bank_transfer') NOT NULL DEFAULT 'wallet'");
        }

        if (Schema::hasTable('fixed_deposits') && Schema::hasColumn('fixed_deposits', 'payout_option')) {
            DB::statement("ALTER TABLE `fixed_deposits` MODIFY `payout_option` ENUM('wallet', 'chit', 'bank_transfer') NOT NULL DEFAULT 'wallet'");
        }
    }
};

