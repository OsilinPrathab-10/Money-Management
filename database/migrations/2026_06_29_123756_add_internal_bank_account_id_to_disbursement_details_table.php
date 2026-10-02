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
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->foreignId('internal_bank_account_id')->nullable()->constrained('bank_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disbursement_details', function (Blueprint $table) {
            $table->dropForeign(['internal_bank_account_id']);
            $table->dropColumn('internal_bank_account_id');
        });
    }
};
