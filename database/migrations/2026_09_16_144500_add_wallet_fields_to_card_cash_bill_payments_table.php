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
        Schema::table('card_cash_bill_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_bill_payments', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->after('payment_source_id')->constrained('credit_card_wallets')->nullOnDelete();
            }
            if (!Schema::hasColumn('card_cash_bill_payments', 'is_split_wallet')) {
                $table->boolean('is_split_wallet')->default(false)->after('wallet_id');
            }
            if (!Schema::hasColumn('card_cash_bill_payments', 'wallet_split_breakdown')) {
                $table->json('wallet_split_breakdown')->nullable()->after('is_split_wallet');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_cash_bill_payments', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_bill_payments', 'wallet_id')) {
                $table->dropForeign(['wallet_id']);
                $table->dropColumn(['wallet_id', 'is_split_wallet', 'wallet_split_breakdown']);
            }
        });
    }
};
