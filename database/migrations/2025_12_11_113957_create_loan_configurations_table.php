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
        Schema::create('loan_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // 'foreclosure' or 'prepayment'
            $table->integer('eligibility_months')->nullable();
            $table->decimal('charges_percentage', 5, 2)->nullable();
            $table->enum('charge_type', ['percentage', 'flat'])->default('percentage');
            $table->decimal('charge_value', 15, 2)->default(0);
            $table->decimal('extra_charge', 5, 2)->nullable();
            
            // Partial Payment Configuration
            $table->decimal('minimum_partial_percentage', 5, 2)->nullable()->comment('Minimum % of EMI for partial payment');
            $table->enum('partial_payment_timing', ['anytime', 'before_due', 'after_due'])->default('anytime');
            $table->enum('penalty_calculation_method', ['emi_amount', 'emi_plus_partial_remaining'])->default('emi_amount');
            
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_configurations');
    }
};
