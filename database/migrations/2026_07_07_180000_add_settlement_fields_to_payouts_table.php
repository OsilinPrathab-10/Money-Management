<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->unsignedInteger('month_number')->nullable()->after('winner_member_id');
            $table->unsignedBigInteger('initiated_by')->nullable()->after('processed_by');

            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['group_id', 'month_number'], 'payouts_group_month_unique');
            $table->unique(['group_id', 'winner_member_id'], 'payouts_group_winner_unique');
        });

        DB::table('payouts')
            ->orderBy('id')
            ->get()
            ->each(function ($payout, $index) {
                $monthNumber = DB::table('auctions')
                    ->where('id', $payout->auction_id)
                    ->value('month_number');

                if (!$monthNumber) {
                    $monthNumber = $index + 1;
                }

                DB::table('payouts')
                    ->where('id', $payout->id)
                    ->update(['month_number' => $monthNumber]);
            });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropForeign(['initiated_by']);
            $table->dropUnique('payouts_group_month_unique');
            $table->dropUnique('payouts_group_winner_unique');
            $table->dropColumn(['month_number', 'initiated_by']);
        });
    }
};
