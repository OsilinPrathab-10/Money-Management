<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds penalty-specific charge_type column to loan_configurations table
     * so penalty rows can store 'fixed' or 'percentage'.
     * (The table already has charge_type but with enum constraint; we add a dedicated
     *  nullable string column penalty_charge_type to avoid enum conflicts.)
     */
    public function up(): void
    {
        Schema::table('loan_configurations', function (Blueprint $table) {
            $table->string('penalty_charge_type')->default('fixed')->after('charge_type')
                  ->comment('Penalty type for penalty config rows: fixed or percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_configurations', function (Blueprint $table) {
            $table->dropColumn('penalty_charge_type');
        });
    }
};
