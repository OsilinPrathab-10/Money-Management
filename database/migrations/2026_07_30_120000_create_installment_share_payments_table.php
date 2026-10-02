<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_share_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->foreignId('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('ownership_percentage', 5, 2)->default(100);
            $table->string('payment_mode', 30)->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->text('remarks')->nullable();
            $table->date('paid_date')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->timestamps();

            $table->index(['installment_id', 'client_id']);
            $table->index(['client_id', 'paid_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_share_payments');
    }
};
