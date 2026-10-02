<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_applications')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('loan_applications', 'applied_at')) {
                    $table->dateTime('applied_at')->nullable()->after('client_id');
                }
            });

            // Backfill existing rows with created_at timestamp
            DB::statement("UPDATE loan_applications SET applied_at = created_at WHERE applied_at IS NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('loan_applications')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                if (Schema::hasColumn('loan_applications', 'applied_at')) {
                    $table->dropColumn('applied_at');
                }
            });
        }
    }
};
