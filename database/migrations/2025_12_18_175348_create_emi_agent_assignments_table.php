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
        Schema::create('emi_agent_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('emi_id')->constrained('emis')->cascadeOnDelete();

            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();

            $table->enum('status', [
                'assigned',
                'visited',
                'resolved',
                'unreachable',
                'reassigned'
            ])->default('assigned');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['emi_id', 'agent_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emi_agent_assignments');
    }
};
