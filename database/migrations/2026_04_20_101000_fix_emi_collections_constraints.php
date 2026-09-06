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
        Schema::table('emi_collections', function (Blueprint $table) {
            // Make agent_id nullable
            $table->foreignId('agent_id')->nullable()->change();
            
            // Change payment_method and payment_type to strings for flexibility
            $table->string('payment_method')->change();
            $table->string('payment_type')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emi_collections', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
            $table->enum('payment_method', ['direct', 'in_hand', 'payment_link'])->change();
            $table->enum('payment_type', ['overdue', 'partial'])->change();
        });
    }
};
