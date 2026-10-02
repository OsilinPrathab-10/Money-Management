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
        Schema::table('chit_schemes', function (Blueprint $table) {
            if (!Schema::hasColumn('chit_schemes', 'post_payout_installment_adjustment')) {
                $table->decimal('post_payout_installment_adjustment', 15, 2)->nullable()->after('installment_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chit_schemes', function (Blueprint $table) {
            if (Schema::hasColumn('chit_schemes', 'post_payout_installment_adjustment')) {
                $table->dropColumn('post_payout_installment_adjustment');
            }
        });
    }
};
