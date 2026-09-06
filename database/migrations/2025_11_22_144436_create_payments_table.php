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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('loan_account_id')->constrained('loan_accounts')->onDelete('cascade');

            $table->foreignId('emi_id')->nullable()->constrained('emis')->onDelete('cascade');

            $table->string('order_id');
            $table->string('payment_id')->nullable();
            $table->string('signature')->nullable();

            $table->integer('amount');
            $table->unsignedBigInteger('amount_paise');

            $table->enum('payment_type', ['PARTIAL', 'EMI', 'PREPAYMENT', 'FORECLOSURE']);

            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
            $table->json('payload')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
