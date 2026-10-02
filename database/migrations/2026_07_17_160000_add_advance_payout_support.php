<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chit_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('chit_groups', 'registration_type')) {
                $table->string('registration_type', 20)->default('non_registered')->after('scheme_type');
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'payout_kind')) {
                $table->string('payout_kind', 20)->default('original')->after('month_number');
            }

            if (!Schema::hasColumn('payouts', 'original_payout_id')) {
                $table->unsignedBigInteger('original_payout_id')->nullable()->after('payout_kind');
                $table->foreign('original_payout_id')->references('id')->on('payouts')->nullOnDelete();
            }
        });

        if (Schema::hasTable('chit_groups') && Schema::hasTable('chit_schemes')) {
            DB::table('chit_groups')
                ->orderBy('id')
                ->get(['id', 'scheme_id'])
                ->each(function ($group) {
                    $registrationType = DB::table('chit_schemes')
                        ->where('id', $group->scheme_id)
                        ->value('registration_type') ?? 'non_registered';

                    DB::table('chit_groups')
                        ->where('id', $group->id)
                        ->update(['registration_type' => $registrationType]);
                });
        }

        if (Schema::hasTable('payouts')) {
            DB::table('payouts')->update(['payout_kind' => 'original']);
        }

        Schema::table('payouts', function (Blueprint $table) {
            if ($this->indexExists('payouts', 'payouts_group_month_unique')) {
                $table->dropUnique('payouts_group_month_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            if (!$this->indexExists('payouts', 'payouts_group_month_unique')) {
                $table->unique(['group_id', 'month_number'], 'payouts_group_month_unique');
            }

            if (Schema::hasColumn('payouts', 'original_payout_id')) {
                $table->dropForeign(['original_payout_id']);
                $table->dropColumn('original_payout_id');
            }

            if (Schema::hasColumn('payouts', 'payout_kind')) {
                $table->dropColumn('payout_kind');
            }
        });

        Schema::table('chit_groups', function (Blueprint $table) {
            if (Schema::hasColumn('chit_groups', 'registration_type')) {
                $table->dropColumn('registration_type');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return !empty($connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        ));
    }
};
