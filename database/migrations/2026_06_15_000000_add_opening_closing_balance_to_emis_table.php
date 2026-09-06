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
        Schema::table('emis', function (Blueprint $table) {
            if (!Schema::hasColumn('emis', 'opening_balance')) {
                $table->decimal('opening_balance', 15, 2)->default(0)->after('due_date');
            }
            if (!Schema::hasColumn('emis', 'closing_balance')) {
                $table->decimal('closing_balance', 15, 2)->default(0)->after('opening_balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emis', function (Blueprint $table) {
            $table->dropColumn(['opening_balance', 'closing_balance']);
        });
    }
};
