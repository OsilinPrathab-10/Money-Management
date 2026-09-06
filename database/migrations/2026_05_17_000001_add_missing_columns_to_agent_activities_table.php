<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds missing columns: method, reference, remarks to the agent_activities table.
     */
    public function up(): void
    {
        Schema::table('agent_activities', function (Blueprint $table) {
            if (!Schema::hasColumn('agent_activities', 'method')) {
                $table->string('method')->nullable()->after('description');
            }
            if (!Schema::hasColumn('agent_activities', 'reference')) {
                $table->string('reference')->nullable()->after('method');
            }
            if (!Schema::hasColumn('agent_activities', 'remarks')) {
                $table->text('remarks')->nullable()->after('reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_activities', function (Blueprint $table) {
            $table->dropColumn(['method', 'reference', 'remarks']);
        });
    }
};
