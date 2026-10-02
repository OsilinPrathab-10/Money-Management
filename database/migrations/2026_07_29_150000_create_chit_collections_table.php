<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chit_collections')) {
            return;
        }

        Schema::create('chit_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('chit_groups')->nullOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('group_members')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->string('payment_type')->default('full');
            $table->string('payment_reference')->nullable();
            $table->string('status')->default('in_progress');
            $table->timestamp('collected_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'collected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chit_collections');
    }
};
