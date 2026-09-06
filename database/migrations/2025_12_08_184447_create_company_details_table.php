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
    Schema::create('company_details', function (Blueprint $table) {
           $table->id();

           // Basic Company Information
           $table->string('company_name');
           $table->string('company_slogan');
           $table->string('company_email');
           $table->string('company_mobile', 20);
           $table->string('alternate_mobile', 20)->nullable();

           // Support & Communication
           $table->string('support_email')->nullable();
           $table->string('support_mobile', 20)->nullable();
           $table->string('website_url')->nullable();

           // Branding
           $table->string('logo_path')->nullable();

           // Address
           $table->string('address_line1')->nullable();
           $table->string('address_line2')->nullable();
           $table->string('city', 100)->nullable();
           $table->string('state', 100)->nullable();
           $table->string('pincode', 15)->nullable();
           $table->string('country', 100)->default('India');

           // Legal Details
           $table->string('gst_number', 50)->nullable();
           $table->string('pan_number', 50)->nullable();
           $table->string('cin_number', 50)->nullable();

           //social links
           $table->string('facebook_url')->nullable();
           $table->string('twitter_url')->nullable();
           $table->string('linkedin_url')->nullable();
           $table->string('instagram_url')->nullable();

           //agent contact info 
           $table->string('agent_contact_email')->nullable();
           $table->string('agent_contact_mobile')->nullable();
           $table->string('working_hours')->nullable();
           
           $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_details');
    }
};
