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
        Schema::table('loan_types', function (Blueprint $table) {
            $table->string('loan_type_icon')->nullable()->change();
            $table->string('loan_type_image')->nullable()->change();
            $table->string('loan_type_banner')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_types', function (Blueprint $table) {
            $table->string('loan_type_icon')->nullable(false)->change();
            $table->string('loan_type_image')->nullable(false)->change();
            $table->string('loan_type_banner')->nullable(false)->change();
        });
    }
};
