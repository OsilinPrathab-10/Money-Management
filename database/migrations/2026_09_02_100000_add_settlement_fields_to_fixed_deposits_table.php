<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_deposits', 'payment_proof')) {
                $table->string('payment_proof')->nullable()->after('closure_transaction_ref');
            }
            if (!Schema::hasColumn('fixed_deposits', 'customer_bank_name')) {
                $table->string('customer_bank_name')->nullable()->after('payment_proof');
            }
            if (!Schema::hasColumn('fixed_deposits', 'customer_account_number')) {
                $table->string('customer_account_number')->nullable()->after('customer_bank_name');
            }
            if (!Schema::hasColumn('fixed_deposits', 'customer_ifsc_code')) {
                $table->string('customer_ifsc_code')->nullable()->after('customer_account_number');
            }
            if (!Schema::hasColumn('fixed_deposits', 'customer_branch_name')) {
                $table->string('customer_branch_name')->nullable()->after('customer_ifsc_code');
            }
            if (!Schema::hasColumn('fixed_deposits', 'customer_holder_name')) {
                $table->string('customer_holder_name')->nullable()->after('customer_branch_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropColumn([
                'payment_proof',
                'customer_bank_name',
                'customer_account_number',
                'customer_ifsc_code',
                'customer_branch_name',
                'customer_holder_name',
            ]);
        });
    }
};
