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
        Schema::create('loan_document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type')->unique(); // e.g. 'noc', 'loan_agreement'
            $table->string('title');
            $table->text('header')->nullable();
            $table->text('footer')->nullable();
            $table->text('logo_path')->nullable();
            $table->longText('body');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_document_templates');
    }
};
