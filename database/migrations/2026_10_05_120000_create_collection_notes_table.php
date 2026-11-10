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
        if (!Schema::hasTable('collection_notes')) {
            Schema::create('collection_notes', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->index(); // 'loan' or 'chit'
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->foreignId('loan_account_id')->nullable()->constrained('loan_accounts')->nullOnDelete();
                $table->foreignId('emi_id')->nullable()->constrained('emis')->cascadeOnDelete();
                if (Schema::hasTable('group_members')) {
                    $table->foreignId('group_member_id')->nullable()->constrained('group_members')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('group_member_id')->nullable()->index();
                }
                if (Schema::hasTable('installments')) {
                    $table->foreignId('installment_id')->nullable()->constrained('installments')->cascadeOnDelete();
                } else {
                    $table->unsignedBigInteger('installment_id')->nullable()->index();
                }
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
                $table->text('note');
                $table->date('reminder_date')->nullable()->index();
                $table->time('reminder_time')->nullable();
                $table->string('status', 50)->default('postponed'); // postponed, promise_to_pay, client_not_available, visit_scheduled, general
                $table->boolean('is_completed')->default(false);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['type', 'emi_id']);
                $table->index(['type', 'installment_id']);
                $table->index(['reminder_date', 'is_completed']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collection_notes');
    }
};
