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
        Schema::create('emi_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_id')->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', ['direct', 'in_hand', 'payment_link']);
            $table->enum('payment_type', ['overdue', 'partial'])->default('overdue');
            $table->enum('status', ['in_progress', 'verified', 'rejected', 'completed'])->default('in_progress');
            $table->string('payment_reference')->nullable();
            $table->string('proof_image_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('collected_at')->useCurrent();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emi_collections');
    }
};
