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
        // 1. Agent Attendances
        if (!Schema::hasTable('agent_attendances')) {
            Schema::create('agent_attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
                $table->date('date');
                $table->enum('status', ['present', 'absent', 'half_day'])->default('present');
                $table->string('remarks')->nullable();
                $table->timestamps();

                $table->unique(['agent_id', 'date']);
            });
        }

        // 2. Agent Expenses
        if (!Schema::hasTable('agent_expenses')) {
            Schema::create('agent_expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
                $table->decimal('amount', 15, 2);
                $table->date('date');
                $table->string('description');
                $table->enum('category', ['travel', 'petrol', 'other'])->default('other');
                $table->timestamps();
            });
        }

        // 3. Agent Advances
        if (!Schema::hasTable('agent_advances')) {
            Schema::create('agent_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
                $table->decimal('amount', 15, 2);
                $table->date('date');
                $table->string('description');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_advances');
        Schema::dropIfExists('agent_expenses');
        Schema::dropIfExists('agent_attendances');
    }
};
