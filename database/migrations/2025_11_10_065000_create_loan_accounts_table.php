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
        Schema::create('loan_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('account_number')->unique();
            $table->string('application_number');
            $table->string('loan_code');
            $table->decimal('loan_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->integer('tenure'); // in months
            $table->integer('emi_day');
            $table->string('payment_method');
            $table->decimal('total_payable', 15, 2)->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->nullable();
            $table->decimal('prepayment_amount', 15, 2)->default(0);
            $table->integer('grace_period_days')->nullable();
            $table->decimal('penalty', 15, 2)->default(0);
            $table->enum('penalty_type', ['percentage', 'rupees'])->default('percentage');
            $table->string('transaction_id')->nullable();
            $table->string('utr_number')->nullable();
            $table->enum('status', ['active', 'closed', 'defaulted'])->default('active');
            $table->decimal('disbursed_amount', 15, 2)->nullable();
            $table->integer('foreclosure_eligibility_months')->nullable();
            $table->decimal('foreclosure_charges_percentage', 5, 2)->nullable();
            $table->boolean('is_foreclosed')->default(false);
            $table->decimal('foreclosure_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('foreclosure_processed_by')->nullable();
            $table->text('foreclosure_notes')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_accounts');
    }
};
