<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobile_app_settings', function (Blueprint $table) {
            $table->json('loan_banner_images')->nullable()->after('banner_images');
            $table->json('chit_banner_images')->nullable()->after('loan_banner_images');
            $table->json('fd_banner_images')->nullable()->after('chit_banner_images');
        });
    }

    public function down(): void
    {
        Schema::table('mobile_app_settings', function (Blueprint $table) {
            $table->dropColumn(['loan_banner_images', 'chit_banner_images', 'fd_banner_images']);
        });
    }
};
