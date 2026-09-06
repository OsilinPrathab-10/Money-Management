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
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->integer('emi_start_month')->nullable()->after('emi_day');
            $table->integer('emi_start_year')->nullable()->after('emi_start_month');
            $table->integer('emi_start_week')->nullable()->after('emi_start_year');
            $table->integer('emi_start_day')->nullable()->after('emi_start_week');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['emi_start_month', 'emi_start_year', 'emi_start_week', 'emi_start_day']);
        });
    }
};
