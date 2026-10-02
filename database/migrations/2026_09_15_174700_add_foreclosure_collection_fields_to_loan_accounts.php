<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_interest_amount')) {
                $table->decimal('foreclosure_interest_amount', 15, 2)->nullable()->after('foreclosure_amount');
            }
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_charges_amount')) {
                $table->decimal('foreclosure_charges_amount', 15, 2)->nullable()->after('foreclosure_interest_amount');
            }
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_payment_method')) {
                $table->string('foreclosure_payment_method', 50)->nullable()->after('foreclosure_charges_amount');
            }
            if (! Schema::hasColumn('loan_accounts', 'foreclosure_bank_account_id')) {
                $table->unsignedBigInteger('foreclosure_bank_account_id')->nullable()->after('foreclosure_payment_method');
                $table->foreign('foreclosure_bank_account_id')
                    ->references('id')
                    ->on('bank_accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('loan_accounts', 'foreclosure_bank_account_id')) {
                $table->dropForeign(['foreclosure_bank_account_id']);
                $table->dropColumn('foreclosure_bank_account_id');
            }
            foreach (['foreclosure_payment_method', 'foreclosure_charges_amount', 'foreclosure_interest_amount'] as $column) {
                if (Schema::hasColumn('loan_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
