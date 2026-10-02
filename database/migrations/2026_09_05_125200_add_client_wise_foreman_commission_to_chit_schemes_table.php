<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chit_schemes')) {
            return;
        }

        Schema::table('chit_schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('chit_schemes', 'client_wise_foreman_commission')) {
                $table->decimal('client_wise_foreman_commission', 12, 2)->nullable()->after('foreman_commission_month');
            }

            if (! Schema::hasColumn('chit_schemes', 'client_wise_foreman_collection_month')) {
                $table->unsignedTinyInteger('client_wise_foreman_collection_month')->nullable()->after('client_wise_foreman_commission');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chit_schemes')) {
            return;
        }

        Schema::table('chit_schemes', function (Blueprint $table) {
            if (Schema::hasColumn('chit_schemes', 'client_wise_foreman_collection_month')) {
                $table->dropColumn('client_wise_foreman_collection_month');
            }

            if (Schema::hasColumn('chit_schemes', 'client_wise_foreman_commission')) {
                $table->dropColumn('client_wise_foreman_commission');
            }
        });
    }
};
