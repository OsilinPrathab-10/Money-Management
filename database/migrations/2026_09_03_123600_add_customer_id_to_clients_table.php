<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        if (! Schema::hasColumn('clients', 'customer_id')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('customer_id', 32)->nullable()->after('id');
            });
        }

        Client::backfillMissingCustomerIds();

        try {
            Schema::table('clients', function (Blueprint $table) {
                $table->unique('customer_id');
            });
        } catch (\Throwable $e) {
            // Unique index may already exist.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'customer_id')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            try {
                $table->dropUnique(['customer_id']);
            } catch (\Throwable $e) {
            }
            $table->dropColumn('customer_id');
        });
    }
};
