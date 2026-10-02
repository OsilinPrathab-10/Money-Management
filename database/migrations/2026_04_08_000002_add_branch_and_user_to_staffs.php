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
        if (Schema::hasTable('staffs')) {
            Schema::table('staffs', function (Blueprint $table) {
                if (!Schema::hasColumn('staffs', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('set null');
                }
                if (!Schema::hasColumn('staffs', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('branches')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('staffs')) {
            Schema::table('staffs', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['user_id']);
                $table->dropColumn(['branch_id', 'user_id']);
            });
        }
    }
};
