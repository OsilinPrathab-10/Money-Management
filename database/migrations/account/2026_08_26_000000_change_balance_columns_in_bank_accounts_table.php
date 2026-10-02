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
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 2)->default(0)->change();
            $table->decimal('current_balance', 15, 2)->default(0)->change();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->change();
            $table->decimal('running_balance', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->decimal('opening_balance', 10, 2)->default(0)->change();
            $table->decimal('current_balance', 10, 2)->default(0)->change();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('running_balance', 10, 2)->change();
        });
    }
};
