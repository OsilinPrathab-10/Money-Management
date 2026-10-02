<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposit_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_code', 32)->unique();
            $table->string('name');
            $table->enum('deposit_type', ['simple_interest', 'compound_interest']);
            $table->decimal('min_deposit_amount', 15, 2);
            $table->decimal('max_deposit_amount', 15, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->enum('interest_frequency', ['monthly', 'quarterly', 'half_yearly', 'yearly'])->default('yearly');
            $table->unsignedInteger('min_tenure');
            $table->unsignedInteger('max_tenure');
            $table->enum('tenure_type', ['months', 'years'])->default('months');
            $table->boolean('premature_withdrawal_allowed')->default(false);
            $table->enum('premature_penalty_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('premature_penalty_value', 15, 2)->nullable();
            $table->boolean('auto_renewal')->default(false);
            $table->enum('renewal_type', ['principal_only', 'principal_interest'])->nullable();
            $table->enum('default_payout_option', ['wallet', 'chit', 'bank_transfer'])->default('wallet');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->unique()->constrained('clients')->cascadeOnDelete();
            $table->decimal('balance', 15, 2)->default(0);
            $table->enum('status', ['active', 'inactive', 'frozen'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id'], 'wallet_txn_ref_idx');
        });

        Schema::create('fixed_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('fd_number', 32)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('fixed_deposit_schemes')->restrictOnDelete();
            $table->decimal('deposit_amount', 15, 2);
            $table->date('deposit_date');
            $table->date('start_date');
            $table->date('maturity_date');
            $table->unsignedInteger('tenure');
            $table->enum('tenure_type', ['months', 'years'])->default('months');
            $table->decimal('interest_rate', 8, 4);
            $table->enum('interest_type', ['simple_interest', 'compound_interest']);
            $table->enum('interest_frequency', ['monthly', 'quarterly', 'half_yearly', 'yearly'])->default('yearly');
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('maturity_amount', 15, 2)->default(0);
            $table->string('nominee_name')->nullable();
            $table->string('nominee_relation')->nullable();
            $table->enum('payout_option', ['wallet', 'chit', 'bank_transfer'])->default('wallet');
            $table->boolean('auto_renewal')->default(false);
            $table->enum('renewal_type', ['principal_only', 'principal_interest'])->nullable();
            $table->enum('status', [
                'active',
                'matured',
                'closed',
                'premature_closed',
                'renewed',
                'cancelled',
            ])->default('active');
            $table->text('remarks')->nullable();

            // Bank transfer payout details
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('utr_reference')->nullable();
            $table->date('payment_date')->nullable();

            // Manual closure
            $table->date('closure_date')->nullable();
            $table->decimal('closure_amount', 15, 2)->nullable();
            $table->string('closure_payment_mode')->nullable();
            $table->string('closure_transaction_ref')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('closure_remarks')->nullable();

            $table->foreignId('renewed_from_id')->nullable()->constrained('fixed_deposits')->nullOnDelete();
            $table->timestamp('maturity_processed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'maturity_date'], 'fd_status_maturity_idx');
            $table->index(['client_id', 'status'], 'fd_client_status_idx');
        });

        Schema::create('fixed_deposit_renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('old_fd_id')->constrained('fixed_deposits')->cascadeOnDelete();
            $table->foreignId('new_fd_id')->constrained('fixed_deposits')->cascadeOnDelete();
            $table->enum('renewal_type', ['principal_only', 'principal_interest']);
            $table->decimal('principal_carried', 15, 2);
            $table->decimal('interest_carried', 15, 2)->default(0);
            $table->timestamp('renewed_at');
            $table->foreignId('renewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('fixed_deposit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_deposit_id')->constrained('fixed_deposits')->cascadeOnDelete();
            $table->string('transaction_type', 64);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->string('payment_mode')->nullable();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fixed_deposit_id', 'transaction_type'], 'fd_txn_type_idx');
        });

        Schema::create('fixed_deposit_chit_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_deposit_id')->constrained('fixed_deposits')->cascadeOnDelete();
            $table->foreignId('fd_transaction_id')->nullable()->constrained('fixed_deposit_transactions')->nullOnDelete();
            $table->foreignId('chit_group_id')->nullable()->constrained('chit_groups')->nullOnDelete();
            $table->enum('allocation_type', [
                'pending_installment',
                'advance',
                'settlement',
                'join_new',
                'chit_wallet',
            ]);
            $table->decimal('amount', 15, 2);
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fixed_deposit_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_deposit_id')->nullable()->constrained('fixed_deposits')->nullOnDelete();
            $table->foreignId('scheme_id')->nullable()->constrained('fixed_deposit_schemes')->nullOnDelete();
            $table->string('action', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('updated_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at'], 'fd_audit_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposit_audit_logs');
        Schema::dropIfExists('fixed_deposit_chit_allocations');
        Schema::dropIfExists('fixed_deposit_transactions');
        Schema::dropIfExists('fixed_deposit_renewals');
        Schema::dropIfExists('fixed_deposits');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('fixed_deposit_schemes');
    }
};
