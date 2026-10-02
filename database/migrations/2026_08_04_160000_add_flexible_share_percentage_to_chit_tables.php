<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table) {
                if (! Schema::hasColumn('group_members', 'share_percentage')) {
                    $table->decimal('share_percentage', 5, 2)->default(100.00)->after('is_shared');
                }
                if (! Schema::hasColumn('group_members', 'share_installment')) {
                    $table->decimal('share_installment', 12, 2)->nullable()->after('share_percentage');
                }
                if (! Schema::hasColumn('group_members', 'share_interest')) {
                    $table->decimal('share_interest', 12, 2)->nullable()->after('share_installment');
                }
                if (! Schema::hasColumn('group_members', 'share_payout')) {
                    $table->decimal('share_payout', 12, 2)->nullable()->after('share_interest');
                }
            });
        }

        if (Schema::hasTable('installments')) {
            Schema::table('installments', function (Blueprint $table) {
                if (! Schema::hasColumn('installments', 'share_percentage')) {
                    $table->decimal('share_percentage', 5, 2)->nullable()->after('amount');
                }
            });
        }

        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                if (! Schema::hasColumn('payouts', 'share_percentage')) {
                    $table->decimal('share_percentage', 5, 2)->nullable()->after('payout_amount');
                }
            });
        }

        if (Schema::hasTable('chit_collections')) {
            Schema::table('chit_collections', function (Blueprint $table) {
                if (! Schema::hasColumn('chit_collections', 'share_percentage')) {
                    $table->decimal('share_percentage', 5, 2)->nullable()->after('amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('group_members')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropColumn(['share_percentage', 'share_installment', 'share_interest', 'share_payout']);
            });
        }

        if (Schema::hasTable('installments')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->dropColumn(['share_percentage']);
            });
        }

        if (Schema::hasTable('payouts')) {
            Schema::table('payouts', function (Blueprint $table) {
                $table->dropColumn(['share_percentage']);
            });
        }

        if (Schema::hasTable('chit_collections')) {
            Schema::table('chit_collections', function (Blueprint $table) {
                $table->dropColumn(['share_percentage']);
            });
        }
    }
};
