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
        if (Schema::hasTable('appearances')) {
            Schema::table('appearances', function (Blueprint $table) {
                if (!Schema::hasColumn('appearances', 'footer_link_text')) {
                    $table->string('footer_link_text')->nullable()->default('')->after('footer_text');
                }
                if (!Schema::hasColumn('appearances', 'footer_link_url')) {
                    $table->string('footer_link_url')->nullable()->default('')->after('footer_link_text');
                }
                if (!Schema::hasColumn('appearances', 'footer_custom_note')) {
                    $table->string('footer_custom_note')->nullable()->default('')->after('footer_link_url');
                }
            });
        }

        if (Schema::hasTable('company_details')) {
            Schema::table('company_details', function (Blueprint $table) {
                if (!Schema::hasColumn('company_details', 'footer_link_text')) {
                    $table->string('footer_link_text')->nullable()->default('')->after('footer_text');
                }
                if (!Schema::hasColumn('company_details', 'footer_link_url')) {
                    $table->string('footer_link_url')->nullable()->default('')->after('footer_link_text');
                }
                if (!Schema::hasColumn('company_details', 'footer_custom_note')) {
                    $table->string('footer_custom_note')->nullable()->default('')->after('footer_link_url');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('appearances')) {
            Schema::table('appearances', function (Blueprint $table) {
                $columns = ['footer_link_text', 'footer_link_url', 'footer_custom_note'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('appearances', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('company_details')) {
            Schema::table('company_details', function (Blueprint $table) {
                $columns = ['footer_link_text', 'footer_link_url', 'footer_custom_note'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('company_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
