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
        Schema::create('emi_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emi_id')->constrained('emis')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('followup_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index(['emi_id', 'agent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emi_followups');
    }
};
