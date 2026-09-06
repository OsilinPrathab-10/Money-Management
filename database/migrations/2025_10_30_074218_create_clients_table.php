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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable()->unique();
            $table->string('client_phone')->nullable()->unique()->index();
            $table->string('profile_image')->nullable();
            $table->string('alternate_phone')->nullable();
            $table->string('aadhaar_number', 12)->unique()->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('care_of', 100)->nullable();
            $table->string('flat', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('landmark', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable()->index();
            $table->string('district', 100)->nullable();
            $table->string('subdistrict', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('post_office', 100)->nullable();
            $table->string('vtc', 100)->nullable();
            $table->string('aadhaar_photo_path')->nullable();
            $table->string('marital_status')->nullable();
            $table->integer('cibil_score')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->enum('risk_level', ['low', 'medium', 'high'])->default('low')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('agents')->onDelete('set null');
            $table->enum('status', ['active', 'inactive', 'blacklist'])->default('active')->index();
            $table->string('remarks')->nullable();
            $table->boolean('accepted_terms')->default(false);
            $table->boolean('accepted_privacy')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
