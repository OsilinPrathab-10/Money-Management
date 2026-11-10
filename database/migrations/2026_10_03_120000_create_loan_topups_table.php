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
        if (!Schema::hasColumn('loan_accounts', 'topup_amount')) {
            Schema::table('loan_accounts', function (Blueprint $table) {
                $table->decimal('topup_amount', 15, 2)->default(0)->after('prepayment_amount');
            });
        }

        if (!Schema::hasTable('loan_topups')) {
            Schema::create('loan_topups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_account_id')->constrained('loan_accounts')->onDelete('cascade');
                $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
                $table->decimal('topup_amount', 15, 2);
                $table->decimal('previous_loan_amount', 15, 2)->default(0);
                $table->decimal('new_loan_amount', 15, 2)->default(0);
                $table->decimal('previous_outstanding_amount', 15, 2)->default(0);
                $table->decimal('new_outstanding_amount', 15, 2)->default(0);
                $table->decimal('previous_emi_amount', 15, 2)->default(0);
                $table->decimal('new_emi_amount', 15, 2)->default(0);
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->integer('previous_tenure')->default(0);
                $table->integer('new_tenure')->default(0);
                $table->integer('additional_tenure')->default(0);
                $table->decimal('processing_fee', 15, 2)->default(0);
                $table->decimal('disbursed_amount', 15, 2)->default(0);
                $table->string('payment_method')->default('bank_transfer');
                $table->unsignedBigInteger('internal_bank_account_id')->nullable();
                $table->string('utr_number')->nullable();
                $table->dateTime('disbursed_at')->nullable();
                $table->date('next_due_date')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['loan_account_id', 'created_at']);
                $table->index(['client_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_topups');

        if (Schema::hasColumn('loan_accounts', 'topup_amount')) {
            Schema::table('loan_accounts', function (Blueprint $table) {
                $table->dropColumn('topup_amount');
            });
        }
    }
};
