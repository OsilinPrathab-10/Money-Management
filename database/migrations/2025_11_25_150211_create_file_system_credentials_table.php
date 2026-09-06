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
        Schema::create('file_system_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('s3'); // s3, azure, google_cloud, etc.
            $table->boolean('is_enabled')->default(false);
            $table->text('access_key_id')->nullable(); // Encrypted
            $table->text('secret_access_key')->nullable(); // Encrypted
            $table->string('region')->nullable();
            $table->string('bucket')->nullable();
            $table->string('url')->nullable();
            $table->string('endpoint')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_system_credentials');
    }
};
