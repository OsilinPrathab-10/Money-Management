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
        Schema::create('employee_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->enum('employment_type', ['salaried', 'self_employed']);
            $table->string('company_name')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('employee_id_image')->nullable();
            $table->enum('job_type', ['full_time', 'part_time', 'contract'])->nullable();
            $table->decimal('monthly_salary', 10, 2)->nullable();
            $table->string('salary_credit_bank')->nullable();
            $table->string('work_experience')->nullable();
            $table->json('payslip_documents')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_type')->nullable();
            $table->string('business_category')->nullable();
            $table->integer('years_in_business')->nullable();
            $table->decimal('monthly_turnover', 12, 2)->nullable();
            $table->text('business_address')->nullable();
            $table->string('gst_number')->nullable();
            $table->json('business_proof_documents')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_information');
    }
};
