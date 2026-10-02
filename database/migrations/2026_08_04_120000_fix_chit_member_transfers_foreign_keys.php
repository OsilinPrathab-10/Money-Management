<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('chit_member_transfers')) {
            Schema::table('chit_member_transfers', function (Blueprint $table) {
                // Drop existing foreign key constraints if they exist
                try {
                    $table->dropForeign(['outgoing_member_id']);
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign(['incoming_member_id']);
                } catch (\Exception $e) {}
            });

            Schema::table('chit_member_transfers', function (Blueprint $table) {
                // Re-add foreign keys with cascade on delete
                $table->foreign('outgoing_member_id')
                    ->references('id')
                    ->on('group_members')
                    ->onDelete('cascade');

                $table->foreign('incoming_member_id')
                    ->references('id')
                    ->on('group_members')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('chit_member_transfers')) {
            Schema::table('chit_member_transfers', function (Blueprint $table) {
                try {
                    $table->dropForeign(['outgoing_member_id']);
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign(['incoming_member_id']);
                } catch (\Exception $e) {}
            });

            Schema::table('chit_member_transfers', function (Blueprint $table) {
                $table->foreign('outgoing_member_id')
                    ->references('id')
                    ->on('group_members');

                $table->foreign('incoming_member_id')
                    ->references('id')
                    ->on('group_members');
            });
        }
    }
};
