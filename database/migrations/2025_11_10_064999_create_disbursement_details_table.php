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
        Schema::create('disbursement_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('loan_application_id')->constrained('loan_applications')->onDelete('cascade');

            $table->string('transaction_id')->nullable();
            $table->string('utr_number')->nullable();

            $table->string('bank_account_number');
            $table->string('ifsc_code');
            $table->string('holder_name');
            $table->string('account_type'); // savings, current, etc.
            $table->string('bank_name');

            $table->decimal('disbursement_amount', 12, 2);

            $table->timestamp('disburse_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursement_details');
    }
};
