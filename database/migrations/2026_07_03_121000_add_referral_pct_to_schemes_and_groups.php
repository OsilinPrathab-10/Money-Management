<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('chit_schemes') && !Schema::hasColumn('chit_schemes', 'referral_commission_pct')) {
            Schema::table('chit_schemes', function (Blueprint $table) {
                $table->decimal('referral_commission_pct', 5, 2)->nullable()->after('commission_pct');
            });
        }

        if (Schema::hasTable('chit_groups') && !Schema::hasColumn('chit_groups', 'referral_commission_pct')) {
            Schema::table('chit_groups', function (Blueprint $table) {
                $table->decimal('referral_commission_pct', 5, 2)->nullable()->after('commission_pct');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chit_groups') && Schema::hasColumn('chit_groups', 'referral_commission_pct')) {
            Schema::table('chit_groups', function (Blueprint $table) {
                $table->dropColumn('referral_commission_pct');
            });
        }

        if (Schema::hasTable('chit_schemes') && Schema::hasColumn('chit_schemes', 'referral_commission_pct')) {
            Schema::table('chit_schemes', function (Blueprint $table) {
                $table->dropColumn('referral_commission_pct');
            });
        }
    }
};
