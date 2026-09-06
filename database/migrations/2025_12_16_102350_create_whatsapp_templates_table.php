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
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_name'); // Admin-friendly label
            $table->string('event_type'); // kyc_approved, loan_approved, etc.
            $table->string('provider')->default('gallabox'); // Provider name
            $table->string('provider_template_name'); // Exact template name from Gallabox
            $table->json('variables')->nullable(); // Variable mappings
            $table->boolean('is_active')->default(true); // Enable/disable status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
