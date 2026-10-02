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
        Schema::table('card_cash_leads', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_leads', 'card_number')) {
                $table->string('card_number', 20)->nullable()->after('card_name');
            }
        });

        Schema::table('card_cash_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_returns', 'is_split')) {
                $table->boolean('is_split')->default(false)->after('return_method');
                $table->decimal('card_percentage', 5, 2)->nullable()->after('is_split');
                $table->decimal('card_amount', 15, 2)->nullable()->after('card_percentage');
                $table->decimal('cash_percentage', 5, 2)->nullable()->after('card_amount');
                $table->decimal('cash_amount', 15, 2)->nullable()->after('cash_percentage');
                $table->json('split_breakdown')->nullable()->after('cash_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_cash_leads', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_leads', 'card_number')) {
                $table->dropColumn('card_number');
            }
        });

        Schema::table('card_cash_returns', function (Blueprint $table) {
            $table->dropColumn([
                'is_split',
                'card_percentage',
                'card_amount',
                'cash_percentage',
                'cash_amount',
                'split_breakdown',
            ]);
        });
    }
};
