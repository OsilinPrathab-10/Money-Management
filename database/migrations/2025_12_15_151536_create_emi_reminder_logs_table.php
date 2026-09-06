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
        Schema::create('emi_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_account_id')->constrained('loan_accounts')->onDelete('cascade');
            $table->foreignId('emi_id')->constrained('emis')->onDelete('cascade');
            $table->enum('reminder_type', ['before_due', 'due_today', 'overdue', 'urgent_overdue']);
            $table->timestamp('sent_at');
            $table->string('channel')->default('email');
            $table->timestamps();

            // Indexes for faster queries
            $table->index(['emi_id', 'reminder_type']);
            $table->index('loan_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emi_reminder_logs');
    }
};
