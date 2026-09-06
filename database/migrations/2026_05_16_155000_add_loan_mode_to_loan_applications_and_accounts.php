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
            $table->enum('loan_mode', ['emi', 'interest_only'])->default('emi')->after('loan_code');
        });

        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->enum('loan_mode', ['emi', 'interest_only'])->default('emi')->after('loan_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn('loan_mode');
        });

        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->dropColumn('loan_mode');
        });
    }
};
