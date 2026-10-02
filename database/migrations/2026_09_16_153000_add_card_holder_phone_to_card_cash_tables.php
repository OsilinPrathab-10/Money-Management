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
        // 1. Add card_holder_phone to credit_card_customer_cards
        if (Schema::hasTable('credit_card_customer_cards') && !Schema::hasColumn('credit_card_customer_cards', 'card_holder_phone')) {
            Schema::table('credit_card_customer_cards', function (Blueprint $table) {
                $table->string('card_holder_phone', 20)->nullable()->after('csr_bank_name');
            });
        }

        // 2. Add card_holder_phone to card_cash_leads
        if (Schema::hasTable('card_cash_leads') && !Schema::hasColumn('card_cash_leads', 'card_holder_phone')) {
            Schema::table('card_cash_leads', function (Blueprint $table) {
                $table->string('card_holder_phone', 20)->nullable()->after('csr_bank_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('credit_card_customer_cards') && Schema::hasColumn('credit_card_customer_cards', 'card_holder_phone')) {
            Schema::table('credit_card_customer_cards', function (Blueprint $table) {
                $table->dropColumn('card_holder_phone');
            });
        }

        if (Schema::hasTable('card_cash_leads') && Schema::hasColumn('card_cash_leads', 'card_holder_phone')) {
            Schema::table('card_cash_leads', function (Blueprint $table) {
                $table->dropColumn('card_holder_phone');
            });
        }
    }
};
