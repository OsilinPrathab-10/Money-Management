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
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('client_name')->nullable();
            $table->string('loan_code_name')->nullable();
            $table->string('loan_code')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('payment_amount')->nullable();
            $table->string('payment_date')->nullable();
            $table->string('payment_time')->nullable();
            $table->string('payment_status')->nullable();
            $table->string('payment_remark')->nullable();
            $table->string('payment_created_by')->nullable();
            $table->string('payment_created_at')->nullable();
            $table->string('payment_updated_at')->nullable();
            $table->string('payment_deleted_at')->nullable();
            $table->unsignedBigInteger('emi_id')->nullable();
            $table->string('reason_to_undo')->nullable();  
            $table->unsignedBigInteger('loan_account_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('admin_name')->nullable();
            $table->string('action_type'); // UNDO or DELETE
            $table->longText('previous_payment_data')->nullable(); // Store serialized previous payment
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
