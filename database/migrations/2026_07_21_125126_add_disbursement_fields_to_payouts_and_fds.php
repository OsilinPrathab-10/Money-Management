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
        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'internal_bank_account_id')) {
                $table->unsignedBigInteger('internal_bank_account_id')->nullable()->after('payment_mode');
                $table->foreign('internal_bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            }
        });

        Schema::table('fixed_deposits', function (Blueprint $table) {
            if (!Schema::hasColumn('fixed_deposits', 'processing_fee')) {
                $table->decimal('processing_fee', 15, 2)->default(0)->after('maturity_amount');
            }
            if (!Schema::hasColumn('fixed_deposits', 'document_charges')) {
                $table->decimal('document_charges', 15, 2)->default(0)->after('processing_fee');
            }
            if (!Schema::hasColumn('fixed_deposits', 'other_charges')) {
                $table->decimal('other_charges', 15, 2)->default(0)->after('document_charges');
            }
            if (!Schema::hasColumn('fixed_deposits', 'internal_bank_account_id')) {
                $table->unsignedBigInteger('internal_bank_account_id')->nullable()->after('other_charges');
                $table->foreign('internal_bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (Schema::hasColumn('payouts', 'internal_bank_account_id')) {
                $table->dropForeign(['internal_bank_account_id']);
                $table->dropColumn('internal_bank_account_id');
            }
        });

        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropForeign(['internal_bank_account_id']);
            $table->dropColumn(['processing_fee', 'document_charges', 'other_charges', 'internal_bank_account_id']);
        });
    }
};
