<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payouts') && ! Schema::hasColumn('payouts', 'banking_charges')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->decimal('banking_charges', 12, 2)->default(0)->after('other_charges');
            });
        }

        if (Schema::hasTable('loan_products') && ! Schema::hasColumn('loan_products', 'banking_charges')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->decimal('banking_charges', 12, 2)->nullable()->after('other_charges');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payouts') && Schema::hasColumn('payouts', 'banking_charges')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->dropColumn('banking_charges');
            });
        }

        if (Schema::hasTable('loan_products') && Schema::hasColumn('loan_products', 'banking_charges')) {
            Schema::table('loan_products', function (Blueprint $table) {
                $table->dropColumn('banking_charges');
            });
        }
    }
};
