<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add soft delete (deleted_at) columns to group_members and installments tables.
     * This enables cascade soft-delete when a chit group is soft-deleted.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('group_members', 'deleted_at')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }

        if (!Schema::hasColumn('installments', 'deleted_at')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('group_members', 'deleted_at')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('installments', 'deleted_at')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
