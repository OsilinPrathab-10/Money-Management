<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_otp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('purpose', 40); // first_login | forgot_mpin | forgot_password
            $table->string('mobile', 15);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('status', 20)->default('sent'); // sent | failed | test
            $table->string('provider', 40)->nullable();
            $table->string('provider_message', 500)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['purpose', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('mobile');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_otp_logs');
    }
};
