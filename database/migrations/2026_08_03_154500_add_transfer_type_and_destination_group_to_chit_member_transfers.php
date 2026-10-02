<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chit_member_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('chit_member_transfers', 'transfer_type')) {
                $table->string('transfer_type', 32)->default('same_group')->after('transfer_code');
            }
            if (! Schema::hasColumn('chit_member_transfers', 'destination_group_id')) {
                $table->foreignId('destination_group_id')
                    ->nullable()
                    ->after('group_id')
                    ->constrained('chit_groups')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('chit_member_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('chit_member_transfers', 'destination_group_id')) {
                $table->dropConstrainedForeignId('destination_group_id');
            }
            if (Schema::hasColumn('chit_member_transfers', 'transfer_type')) {
                $table->dropColumn('transfer_type');
            }
        });
    }
};
