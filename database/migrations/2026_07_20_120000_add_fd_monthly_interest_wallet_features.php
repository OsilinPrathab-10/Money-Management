<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->decimal('interest_paid_to_wallet', 15, 2)->default(0)->after('interest_amount');
            $table->date('last_interest_payout_date')->nullable()->after('interest_paid_to_wallet');
            $table->boolean('monthly_interest_to_wallet')->default(true)->after('last_interest_payout_date');
        });

        Schema::create('fixed_deposit_interest_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_deposit_id')->constrained('fixed_deposits')->cascadeOnDelete();
            $table->unsignedSmallInteger('payout_month');
            $table->unsignedSmallInteger('payout_year');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('interest_amount', 15, 2);
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fixed_deposit_id', 'payout_year', 'payout_month'], 'fd_interest_payout_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposit_interest_payouts');

        Schema::table('fixed_deposits', function (Blueprint $table) {
            $table->dropColumn(['interest_paid_to_wallet', 'last_interest_payout_date', 'monthly_interest_to_wallet']);
        });
    }
};
