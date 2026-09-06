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
            $table->dropForeign('loan_applications_loan_code_foreign');
            $table->foreign('loan_code')
                ->references('loan_code')
                ->on('loan_products')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropForeign('loan_applications_loan_code_foreign');
            $table->foreign('loan_code')
                ->references('loan_code')
                ->on('loan_products')
                ->onDelete('cascade');
        });
    }
};
