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
        Schema::create('application_infos', function (Blueprint $table) {
            $table->id();

            // For web or android
            $table->enum('platform', ['web', 'android'])->default('android');

            // Version details
            $table->string('version_name')->default('1.0.0');   // e.g. "v1.0.0"
            $table->integer('version_code')->default(1);        // numeric version for tracking

            // Basic app info
            $table->string('app_name')->default(config('app.name'));
            $table->string('package_name')->nullable();          // For Android

            // Optional metadata
            $table->text('release_notes')->nullable();           // Description of new updates
            $table->boolean('force_update')->default(false);     // For Android updates

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_infos');
    }
};
