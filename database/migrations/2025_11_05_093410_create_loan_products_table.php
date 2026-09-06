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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('loan_name');
            $table->foreignId('loan_type_id')->constrained('loan_types')->onDelete('cascade');
            $table->string('loan_code', 50)->unique();
            $table->decimal('loan_amount_min', 15, 2);
            $table->decimal('loan_amount_max', 15, 2);
            $table->decimal('interest_rate', 5, 2);
            $table->string('interest_type')->default('reducing');
            $table->string('term_unit')->default('months');
            $table->integer('min_tenture'); //months based
            $table->integer('max_tenture');
            $table->decimal('processing_fee', 15, 2)->nullable();
            $table->decimal('document_charges', 15, 2)->nullable();
            $table->decimal('other_charges', 15, 2)->nullable();
            $table->boolean('require_collateral')->default(false);
            $table->integer('default_term')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
