<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chit_schemes', function (Blueprint $table) {
            $table->json('payout_schedule')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chit_schemes', function (Blueprint $table) {
            $table->dropColumn('payout_schedule');
        });
    }
};
