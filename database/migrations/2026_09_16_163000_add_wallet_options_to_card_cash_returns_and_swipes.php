<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Modify card_cash_returns table
        Schema::table('card_cash_returns', function (Blueprint $table) {
            // Change return_method from enum to string to support 'wallet' and custom methods
            $table->string('return_method', 50)->default('card')->change();

            if (!Schema::hasColumn('card_cash_returns', 'wallet_id')) {
                $table->foreignId('wallet_id')->nullable()->after('return_method')->constrained('credit_card_wallets')->nullOnDelete();
            }
            if (!Schema::hasColumn('card_cash_returns', 'is_split_wallet')) {
                $table->boolean('is_split_wallet')->default(false)->after('wallet_id');
            }
            if (!Schema::hasColumn('card_cash_returns', 'wallet_split_breakdown')) {
                $table->json('wallet_split_breakdown')->nullable()->after('is_split_wallet');
            }
        });

        // 2. Modify card_cash_swipe_transactions table
        Schema::table('card_cash_swipe_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_swipe_transactions', 'is_split_wallet')) {
                $table->boolean('is_split_wallet')->default(false)->after('wallet_id');
            }
            if (!Schema::hasColumn('card_cash_swipe_transactions', 'wallet_split_breakdown')) {
                $table->json('wallet_split_breakdown')->nullable()->after('is_split_wallet');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_cash_swipe_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_swipe_transactions', 'is_split_wallet')) {
                $table->dropColumn(['is_split_wallet', 'wallet_split_breakdown']);
            }
        });

        Schema::table('card_cash_returns', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_returns', 'wallet_id')) {
                $table->dropForeign(['wallet_id']);
                $table->dropColumn(['wallet_id', 'is_split_wallet', 'wallet_split_breakdown']);
            }
        });
    }
};
