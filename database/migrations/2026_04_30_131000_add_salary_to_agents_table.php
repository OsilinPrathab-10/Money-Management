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
        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'salary_amount')) {
                $table->decimal('salary_amount', 15, 2)->nullable()->after('location_id');
            }
            if (!Schema::hasColumn('agents', 'salary_details')) {
                $table->json('salary_details')->nullable()->after('salary_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['salary_amount', 'salary_details']);
        });
    }
};
