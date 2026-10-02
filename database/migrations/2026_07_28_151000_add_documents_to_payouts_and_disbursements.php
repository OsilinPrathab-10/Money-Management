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
            if (!Schema::hasColumn('payouts', 'settlement_document')) {
                $table->string('settlement_document')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('payouts', 'collateral_document')) {
                $table->string('collateral_document')->nullable()->after('settlement_document');
            }
            if (!Schema::hasColumn('payouts', 'other_document')) {
                $table->string('other_document')->nullable()->after('collateral_document');
            }
            if (!Schema::hasColumn('payouts', 'additional_documents')) {
                $table->json('additional_documents')->nullable()->after('other_document');
            }
        });

        Schema::table('disbursement_details', function (Blueprint $table) {
            if (!Schema::hasColumn('disbursement_details', 'collateral_document')) {
                $table->string('collateral_document')->nullable()->after('internal_bank_account_id');
            }
            if (!Schema::hasColumn('disbursement_details', 'other_document')) {
                $table->string('other_document')->nullable()->after('collateral_document');
            }
            if (!Schema::hasColumn('disbursement_details', 'additional_documents')) {
                $table->json('additional_documents')->nullable()->after('other_document');
            }
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'other_document')) {
                $table->string('other_document')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('loan_applications', 'collateral_document')) {
                $table->string('collateral_document')->nullable()->after('other_document');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            foreach (['settlement_document', 'collateral_document', 'other_document', 'additional_documents'] as $col) {
                if (Schema::hasColumn('payouts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('disbursement_details', function (Blueprint $table) {
            foreach (['collateral_document', 'other_document', 'additional_documents'] as $col) {
                if (Schema::hasColumn('disbursement_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('loan_applications', function (Blueprint $table) {
            foreach (['other_document', 'collateral_document'] as $col) {
                if (Schema::hasColumn('loan_applications', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
