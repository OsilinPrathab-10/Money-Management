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
        Schema::create('loan_applications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('application_number')->unique();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->string('loan_code');
            $table->decimal('loan_amount_min', 15, 2)->nullable();
            $table->decimal('loan_amount_max', 15, 2)->nullable();
            $table->decimal('loan_amount', 15, 2)->nullable(); // chosen/fixed amount
            $table->integer('tenure_min')->nullable();
            $table->integer('tenure_max')->nullable();
            $table->integer('tenure')->nullable(); // chosen/fixed tenure
            $table->tinyInteger('emi_day')->nullable();
            $table->enum('payment_gateway', ['razor-pay', 'cash-free', 'pay-U'])->default('razor-pay');
            $table->enum('payment_method', ['e-nach', 'manual'])->default('manual');
            $table->enum('status', ['pending','approved','process','rejected','disbursed'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            $table->longText('loan_code_video')->nullable();
            $table->string('loan_agreement_pdf')->nullable();
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('total_payable', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();

            $table->foreign('loan_code')->references('loan_code')->on('loan_products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};
