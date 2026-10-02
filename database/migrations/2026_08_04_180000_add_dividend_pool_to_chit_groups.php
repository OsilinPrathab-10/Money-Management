<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chit_groups', function (Blueprint $table) {
            if (! Schema::hasColumn('chit_groups', 'dividend_pool_balance')) {
                $table->decimal('dividend_pool_balance', 15, 2)->default(0)->after('chit_value');
            }
        });

        Schema::create('chit_dividend_pool_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('chit_groups')->cascadeOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained('payouts')->nullOnDelete();
            $table->unsignedInteger('month_number')->nullable();
            $table->string('entry_type', 20); // credit | debit
            $table->decimal('amount', 15, 2);
            $table->decimal('chit_value', 15, 2)->nullable();
            $table->decimal('payout_amount', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'month_number']);
            $table->unique(['payout_id', 'entry_type'], 'chit_dividend_pool_payout_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chit_dividend_pool_entries');

        Schema::table('chit_groups', function (Blueprint $table) {
            if (Schema::hasColumn('chit_groups', 'dividend_pool_balance')) {
                $table->dropColumn('dividend_pool_balance');
            }
        });
    }
};
