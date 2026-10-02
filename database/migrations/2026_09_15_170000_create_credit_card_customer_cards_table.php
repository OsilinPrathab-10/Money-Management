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
        // 1. Dedicated customer credit cards table (1 customer can have multiple cards)
        if (!Schema::hasTable('credit_card_customer_cards')) {
            Schema::create('credit_card_customer_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('credit_card_customers')->cascadeOnDelete();
                $table->string('card_name'); // e.g. HDFC Regalia Gold, SBI SimplyCLICK
                $table->string('card_number', 16); // 16 numeric digits
                $table->string('csr_bank_name'); // Issuing bank e.g. HDFC Bank, SBI
                $table->string('card_network', 50)->nullable(); // Visa, Mastercard, RuPay, Amex
                $table->string('card_type', 50)->nullable(); // credit, debit, corporate
                $table->string('expiry_month', 2)->nullable();
                $table->string('expiry_year', 4)->nullable();
                $table->boolean('is_primary')->default(false);
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                // Unique per customer + card_number to avoid exact duplicate entries for same customer
                $table->unique(['customer_id', 'card_number'], 'unique_customer_card');
                $table->index(['customer_id', 'status']);
            });
        }

        // 2. Add customer_card_id to card_cash_leads if not already present
        if (Schema::hasTable('card_cash_leads') && !Schema::hasColumn('card_cash_leads', 'customer_card_id')) {
            Schema::table('card_cash_leads', function (Blueprint $table) {
                $table->foreignId('customer_card_id')
                    ->nullable()
                    ->after('credit_card_customer_id')
                    ->constrained('credit_card_customer_cards')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('card_cash_leads') && Schema::hasColumn('card_cash_leads', 'customer_card_id')) {
            Schema::table('card_cash_leads', function (Blueprint $table) {
                $table->dropForeign(['customer_card_id']);
                $table->dropColumn('customer_card_id');
            });
        }

        Schema::dropIfExists('credit_card_customer_cards');
    }
};
