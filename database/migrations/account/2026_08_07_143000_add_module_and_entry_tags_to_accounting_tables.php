<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bank_transactions', 'revenues', 'expenses'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'module_tag')) {
                    $table->string('module_tag', 20)->nullable()->index()->after('description');
                }
                if (! Schema::hasColumn($tableName, 'entry_tag')) {
                    $table->string('entry_tag', 40)->nullable()->index()->after('module_tag');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['bank_transactions', 'revenues', 'expenses'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'entry_tag')) {
                    $table->dropColumn('entry_tag');
                }
                if (Schema::hasColumn($tableName, 'module_tag')) {
                    $table->dropColumn('module_tag');
                }
            });
        }
    }
};
