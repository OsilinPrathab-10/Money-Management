<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_topups')) {
            Schema::table('loan_topups', function (Blueprint $table) {
                if (!Schema::hasColumn('loan_topups', 'frequency')) {
                    $table->string('frequency', 20)->default('monthly')->after('new_tenure');
                }
                if (!Schema::hasColumn('loan_topups', 'emi_day')) {
                    $table->integer('emi_day')->nullable()->after('frequency');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_topups')) {
            Schema::table('loan_topups', function (Blueprint $table) {
                if (Schema::hasColumn('loan_topups', 'emi_day')) {
                    $table->dropColumn('emi_day');
                }
                if (Schema::hasColumn('loan_topups', 'frequency')) {
                    $table->dropColumn('frequency');
                }
            });
        }
    }
};
