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
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('remember_token');
            });
        }

        if (Schema::hasTable('clients') && ! Schema::hasColumn('clients', 'fcm_token')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }

        if (Schema::hasTable('clients') && Schema::hasColumn('clients', 'fcm_token')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
