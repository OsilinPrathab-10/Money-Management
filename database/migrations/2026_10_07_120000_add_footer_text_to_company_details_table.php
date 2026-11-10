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
        if (Schema::hasTable('company_details') && !Schema::hasColumn('company_details', 'footer_text')) {
            Schema::table('company_details', function (Blueprint $table) {
                $table->text('footer_text')->nullable()->after('working_hours');
            });
        }

        if (Schema::hasTable('appearances') && Schema::hasColumn('appearances', 'footer_text')) {
            Schema::table('appearances', function (Blueprint $table) {
                $table->text('footer_text')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('company_details') && Schema::hasColumn('company_details', 'footer_text')) {
            Schema::table('company_details', function (Blueprint $table) {
                $table->dropColumn('footer_text');
            });
        }
    }
};
