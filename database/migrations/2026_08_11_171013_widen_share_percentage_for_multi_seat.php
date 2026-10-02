<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow cumulative multi-seat share % (e.g. 500% for 5 seats).
     * decimal(5,2) maxes at 999.99 — widen so a full group of seats can be held on one membership.
     */
    public function up(): void
    {
        $tables = ['group_members', 'installments', 'payouts', 'chit_collections'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'share_percentage')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('share_percentage', 8, 2)->nullable()->change();
            });
        }

        // Restore default on group_members after nullable change.
        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'share_percentage')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->decimal('share_percentage', 8, 2)->default(100.00)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        $tables = ['group_members', 'installments', 'payouts', 'chit_collections'];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'share_percentage')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('share_percentage', 5, 2)->nullable()->change();
            });
        }

        if (Schema::hasTable('group_members') && Schema::hasColumn('group_members', 'share_percentage')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->decimal('share_percentage', 5, 2)->default(100.00)->nullable(false)->change();
            });
        }
    }
};
