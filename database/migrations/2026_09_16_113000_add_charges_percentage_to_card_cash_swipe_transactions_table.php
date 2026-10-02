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
        Schema::table('card_cash_swipe_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_swipe_transactions', 'charges_percentage')) {
                $table->decimal('charges_percentage', 5, 2)->default(0)->after('charges');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_cash_swipe_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_swipe_transactions', 'charges_percentage')) {
                $table->dropColumn('charges_percentage');
            }
        });
    }
};
