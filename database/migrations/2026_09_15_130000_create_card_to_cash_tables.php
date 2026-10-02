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
        // 1. Credit Card Customers
        Schema::create('credit_card_customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_number', 50)->unique();
            $table->string('customer_name');
            $table->string('phone_number', 20)->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Credit Card Wallets (created before payment sources and swipes so foreign keys resolve)
        Schema::create('credit_card_wallets', function (Blueprint $table) {
            $table->id();
            $table->string('wallet_name');
            $table->string('wallet_code', 50)->unique();
            $table->string('wallet_type', 50)->default('general'); // slp, own, company, general
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 3. Credit Card Wallet Transactions (Ledger)
        Schema::create('credit_card_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('credit_card_wallets')->cascadeOnDelete();
            $table->enum('transaction_type', ['credit', 'debit', 'reversal', 'adjustment']);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('description')->nullable();
            $table->dateTime('transaction_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'cc_wallet_txn_ref_idx');
        });

        // 4. Withdrawal Gateways (for Swipe)
        Schema::create('card_cash_withdrawal_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_name');
            $table->string('gateway_code', 50)->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('wallet_supported')->default(true);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 5. Payment Sources (for Bill Payments)
        Schema::create('card_cash_payment_sources', function (Blueprint $table) {
            $table->id();
            $table->string('source_name');
            $table->enum('source_type', ['gateway', 'account', 'wallet'])->default('gateway');
            $table->string('account_number_or_reference')->nullable();
            $table->foreignId('wallet_id')->nullable()->constrained('credit_card_wallets')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 6. Card to Cash Leads
        Schema::create('card_cash_leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_number', 50)->unique();
            $table->foreignId('credit_card_customer_id')->constrained('credit_card_customers')->cascadeOnDelete();
            $table->string('card_name');
            $table->string('csr_bank_name');
            $table->dateTime('lead_date');
            $table->string('phone_number', 20)->index();
            $table->enum('transaction_type', ['bill_payment', 'swipe'])->index();
            $table->decimal('requested_amount', 15, 2);
            $table->string('status', 50)->default('new')->index();
            // new, processing, approved, rejected, payment_processing, payment_success, return_pending, return_processed, completed, cancelled, failed
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // 7. Card to Cash Bill Payments
        Schema::create('card_cash_bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('card_cash_leads')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('credit_card_customers')->cascadeOnDelete();
            $table->foreignId('payment_source_id')->nullable()->constrained('card_cash_payment_sources')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('gateway_reference')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->dateTime('payment_date');
            $table->string('status', 50)->default('pending');
            $table->string('screenshot_proof')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 8. Card to Cash Swipe Transactions
        Schema::create('card_cash_swipe_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('card_cash_leads')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('credit_card_customers')->cascadeOnDelete();
            $table->foreignId('withdrawal_gateway_id')->nullable()->constrained('card_cash_withdrawal_gateways')->nullOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('credit_card_wallets')->nullOnDelete();
            $table->decimal('swipe_amount', 15, 2);
            $table->decimal('charges', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2);
            $table->string('gateway_reference')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('screenshot_proof')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 9. Card to Cash Settings
        Schema::create('card_cash_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 10. Card to Cash Returns (Settlements to customer)
        Schema::create('card_cash_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('card_cash_leads')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('credit_card_customers')->cascadeOnDelete();
            $table->enum('return_method', ['card', 'upi', 'imps', 'other'])->default('card');
            $table->decimal('return_percentage', 5, 2)->default(99.00);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('charges', 15, 2)->default(0);
            $table->decimal('return_amount', 15, 2);
            $table->string('payment_reference')->nullable();
            $table->string('upi_id')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('payment_proof')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        // 11. Card to Cash Activity Logs (Timeline)
        Schema::create('card_cash_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('card_cash_leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_cash_activity_logs');
        Schema::dropIfExists('card_cash_returns');
        Schema::dropIfExists('card_cash_settings');
        Schema::dropIfExists('card_cash_swipe_transactions');
        Schema::dropIfExists('card_cash_bill_payments');
        Schema::dropIfExists('card_cash_leads');
        Schema::dropIfExists('card_cash_payment_sources');
        Schema::dropIfExists('card_cash_withdrawal_gateways');
        Schema::dropIfExists('credit_card_wallet_transactions');
        Schema::dropIfExists('credit_card_wallets');
        Schema::dropIfExists('credit_card_customers');
    }
};
