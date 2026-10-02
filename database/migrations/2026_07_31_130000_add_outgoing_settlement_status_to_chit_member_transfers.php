<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chit_member_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('chit_member_transfers', 'outgoing_settlement_status')) {
                $table->string('outgoing_settlement_status', 20)
                    ->default('pending')
                    ->after('outgoing_settlement_reference_no');
            }
            if (! Schema::hasColumn('chit_member_transfers', 'outgoing_settlement_paid_at')) {
                $table->timestamp('outgoing_settlement_paid_at')
                    ->nullable()
                    ->after('outgoing_settlement_status');
            }
        });

        // Legacy transfers already paid outgoing buyout at transfer time.
        if (Schema::hasColumn('chit_member_transfers', 'outgoing_settlement_status')) {
            DB::table('chit_member_transfers')
                ->where('outgoing_settlement_status', 'pending')
                ->whereNull('outgoing_settlement_paid_at')
                ->update([
                    'outgoing_settlement_status' => 'paid',
                    'outgoing_settlement_paid_at' => DB::raw('created_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('chit_member_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('chit_member_transfers', 'outgoing_settlement_paid_at')) {
                $table->dropColumn('outgoing_settlement_paid_at');
            }
            if (Schema::hasColumn('chit_member_transfers', 'outgoing_settlement_status')) {
                $table->dropColumn('outgoing_settlement_status');
            }
        });
    }
};
