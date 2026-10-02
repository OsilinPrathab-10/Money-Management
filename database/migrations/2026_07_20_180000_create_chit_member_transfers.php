<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chit_member_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_code', 32)->unique();
            $table->foreignId('group_id')->constrained('chit_groups')->cascadeOnDelete();
            $table->foreignId('outgoing_member_id')->constrained('group_members');
            $table->foreignId('incoming_member_id')->constrained('group_members');
            $table->unsignedInteger('ticket_number');
            $table->date('transfer_date');
            $table->unsignedInteger('completed_rounds')->default(0);
            $table->unsignedInteger('remaining_installments')->default(0);
            $table->decimal('paid_installments_total', 15, 2)->default(0);
            $table->decimal('outstanding_at_transfer', 15, 2)->default(0);
            $table->decimal('takeover_amount', 15, 2)->default(0);
            $table->decimal('outgoing_settlement_amount', 15, 2)->default(0);
            $table->decimal('transfer_fee', 15, 2)->default(0);
            $table->string('takeover_payment_mode', 32)->nullable();
            $table->string('takeover_reference_no', 64)->nullable();
            $table->string('outgoing_settlement_mode', 32)->nullable();
            $table->string('outgoing_settlement_reference_no', 64)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->timestamps();

            $table->index(['group_id', 'transfer_date']);
        });

        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'transferred_to_member_id')) {
                $table->unsignedBigInteger('transferred_to_member_id')->nullable()->after('signed_agreement');
                $table->unsignedBigInteger('transferred_from_member_id')->nullable()->after('transferred_to_member_id');
                $table->foreign('transferred_to_member_id')->references('id')->on('group_members')->nullOnDelete();
                $table->foreign('transferred_from_member_id')->references('id')->on('group_members')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('group_members', 'status')) {
            DB::statement("ALTER TABLE group_members MODIFY COLUMN status ENUM(
                'applied', 'approved', 'active', 'defaulted', 'completed', 'withdrawn', 'rejected', 'transferred'
            ) DEFAULT 'applied'");
        }
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'transferred_to_member_id')) {
                $table->dropForeign(['transferred_to_member_id']);
                $table->dropForeign(['transferred_from_member_id']);
                $table->dropColumn(['transferred_to_member_id', 'transferred_from_member_id']);
            }
        });

        Schema::dropIfExists('chit_member_transfers');

        if (Schema::hasColumn('group_members', 'status')) {
            DB::statement("ALTER TABLE group_members MODIFY COLUMN status ENUM(
                'applied', 'approved', 'active', 'defaulted', 'completed', 'withdrawn'
            ) DEFAULT 'applied'");
        }
    }
};
