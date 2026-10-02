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
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'custom_installment_amount')) {
                $table->decimal('custom_installment_amount', 15, 2)->nullable()->after('signed_agreement');
            }
            if (!Schema::hasColumn('group_members', 'custom_installment_start_month')) {
                $table->integer('custom_installment_start_month')->nullable()->after('custom_installment_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'custom_installment_start_month')) {
                $table->dropColumn('custom_installment_start_month');
            }
            if (Schema::hasColumn('group_members', 'custom_installment_amount')) {
                $table->dropColumn('custom_installment_amount');
            }
        });
    }
};
