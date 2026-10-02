<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (!Schema::hasColumn('group_members', 'chit_need_month')) {
                $table->unsignedTinyInteger('chit_need_month')->nullable()->after('chit_need_date');
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'processing_fee')) {
                $table->decimal('processing_fee', 15, 2)->default(0)->after('payout_amount');
            }
            if (!Schema::hasColumn('payouts', 'document_charges')) {
                $table->decimal('document_charges', 15, 2)->default(0)->after('processing_fee');
            }
            if (!Schema::hasColumn('payouts', 'other_charges')) {
                $table->decimal('other_charges', 15, 2)->default(0)->after('document_charges');
            }
            if (!Schema::hasColumn('payouts', 'net_payout_amount')) {
                $table->decimal('net_payout_amount', 15, 2)->nullable()->after('other_charges');
            }
        });

        if (Schema::hasColumn('group_members', 'chit_need_month') && Schema::hasColumn('group_members', 'chit_need_date')) {
            \Illuminate\Support\Facades\DB::table('group_members')
                ->whereNull('chit_need_month')
                ->whereNotNull('chit_need_date')
                ->orderBy('id')
                ->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        \Illuminate\Support\Facades\DB::table('group_members')
                            ->where('id', $row->id)
                            ->update(['chit_need_month' => (int) date('n', strtotime($row->chit_need_date))]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'chit_need_month')) {
                $table->dropColumn('chit_need_month');
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            $cols = ['processing_fee', 'document_charges', 'other_charges', 'net_payout_amount'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('payouts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
