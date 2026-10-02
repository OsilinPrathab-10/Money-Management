<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('card_cash_leads') || Schema::hasColumn('card_cash_leads', 'due_date')) {
            return;
        }

        Schema::table('card_cash_leads', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('lead_date');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('card_cash_leads') || ! Schema::hasColumn('card_cash_leads', 'due_date')) {
            return;
        }

        Schema::table('card_cash_leads', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
