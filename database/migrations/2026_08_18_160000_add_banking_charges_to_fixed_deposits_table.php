<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_deposits') && ! Schema::hasColumn('fixed_deposits', 'banking_charges')) {
            Schema::table('fixed_deposits', function (Blueprint $table) {
                $table->decimal('banking_charges', 12, 2)->default(0)->after('other_charges');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fixed_deposits') && Schema::hasColumn('fixed_deposits', 'banking_charges')) {
            Schema::table('fixed_deposits', function (Blueprint $table) {
                $table->dropColumn('banking_charges');
            });
        }
    }
};
