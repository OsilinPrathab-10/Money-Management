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
        Schema::create('mobile_app_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('app_type', ['customer', 'agent'])->unique();
            $table->string('app_logo')->nullable();
            $table->string('splash_image')->nullable();
            $table->string('primary_color')->default('#696CFF');
            $table->string('secondary_color')->default('#8592A3');
            $table->string('background_color')->default('#F5F5F9');
            $table->json('banner_images')->nullable();
            $table->enum('theme_mode', ['light', 'dark', 'system'])->default('light');
            $table->text('welcome_message')->nullable();
            $table->boolean('maintenance_mode')->default(false);
            $table->timestamps();
        });

        Schema::create('mobile_app_policies', function (Blueprint $table) {
            $table->id();
            $table->enum('app_type', ['customer', 'agent']);
            $table->enum('type', ['privacy_policy', 'terms_conditions']);
            $table->string('title');
            $table->string('version')->default('v1.0');
            $table->date('effective_date')->nullable();
            $table->longText('content')->nullable();
            $table->enum('status', ['active', 'draft', 'archived'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_app_policies');
        Schema::dropIfExists('mobile_app_settings');
    }
};
