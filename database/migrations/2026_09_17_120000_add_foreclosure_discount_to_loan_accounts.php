<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_discount_percentage')) {
                $table->decimal('foreclosure_discount_percentage', 5, 2)->nullable()->after('foreclosure_charges_amount');
            }
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_discount_amount')) {
                $table->decimal('foreclosure_discount_amount', 15, 2)->nullable()->after('foreclosure_discount_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_accounts', function (Blueprint $table) {
            foreach (['foreclosure_discount_amount', 'foreclosure_discount_percentage'] as $column) {
                if (Schema::hasColumn('loan_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
