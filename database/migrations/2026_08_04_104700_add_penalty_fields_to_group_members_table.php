<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_members')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'penalty_enabled')) {
                $table->boolean('penalty_enabled')->default(false)->after('remarks');
            }
            if (! Schema::hasColumn('group_members', 'penalty_type')) {
                $table->string('penalty_type', 20)->nullable()->after('penalty_enabled');
            }
            if (! Schema::hasColumn('group_members', 'penalty_value')) {
                $table->decimal('penalty_value', 15, 2)->nullable()->after('penalty_type');
            }
            if (! Schema::hasColumn('group_members', 'penalty_grace_days')) {
                $table->unsignedInteger('penalty_grace_days')->nullable()->after('penalty_value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('group_members')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('group_members', 'penalty_enabled') ? 'penalty_enabled' : null,
                Schema::hasColumn('group_members', 'penalty_type') ? 'penalty_type' : null,
                Schema::hasColumn('group_members', 'penalty_value') ? 'penalty_value' : null,
                Schema::hasColumn('group_members', 'penalty_grace_days') ? 'penalty_grace_days' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
