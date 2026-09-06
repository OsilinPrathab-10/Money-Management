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
        Schema::create('emis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_account_id')->constrained('loan_accounts')->onDelete('cascade');
            $table->integer('instalment_number');
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->date('due_date');
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->date('last_penalty_date')->nullable();
            $table->decimal('partial_paid_amount', 15, 2)->default(0);
            $table->boolean('is_partial_paid')->default(false);
            $table->boolean('allow_partial_payment')->default(true);
            $table->date('partial_paid_date')->nullable();

            // Balance tracking for cascading partial payments
            $table->decimal('previous_balance', 15, 2)->default(0)->comment('Balance brought forward from previous month');
            $table->decimal('total_due', 15, 2)->default(0)->comment('previous_balance + total_amount');
            $table->decimal('balance_forward', 15, 2)->default(0)->comment('Amount moving to next month');
            $table->decimal('pending_amount', 15, 2)->default(0)->comment('Remaining unpaid amount');

            $table->enum('status', ['pending', 'paid', 'overdue','partial'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('receipt_url')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emis');
    }
};
