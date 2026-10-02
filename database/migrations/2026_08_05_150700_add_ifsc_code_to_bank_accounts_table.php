<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_accounts')) {
            return;
        }

        Schema::table('bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('bank_accounts', 'ifsc_code')) {
                $table->string('ifsc_code', 20)->nullable()->after('routing_number');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bank_accounts')) {
            return;
        }

        Schema::table('bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('bank_accounts', 'ifsc_code')) {
                $table->dropColumn('ifsc_code');
            }
        });
    }
};
