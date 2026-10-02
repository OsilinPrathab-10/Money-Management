<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Update group_members table
        Schema::table('group_members', function (Blueprint $table) {
            // Drop existing foreign key and column if exists
            if (Schema::hasColumn('group_members', 'referred_by')) {
                try {
                    $table->dropForeign(['referred_by']);
                } catch (\Exception $e) {}
                $table->dropColumn('referred_by');
            }
            
            $table->unsignedBigInteger('referred_by_agent_id')->nullable()->after('client_id');
            $table->unsignedBigInteger('referred_by_client_id')->nullable()->after('referred_by_agent_id');
            
            $table->foreign('referred_by_agent_id')->references('id')->on('agents')->nullOnDelete();
            $table->foreign('referred_by_client_id')->references('id')->on('clients')->nullOnDelete();
        });

        // 2. Update chit_referral_bonuses table
        Schema::table('chit_referral_bonuses', function (Blueprint $table) {
            if (Schema::hasColumn('chit_referral_bonuses', 'referrer_id')) {
                try {
                    $table->dropForeign(['referrer_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('referrer_id');
            }
            
            $table->unsignedBigInteger('referrer_agent_id')->nullable()->after('group_member_id');
            $table->unsignedBigInteger('referrer_client_id')->nullable()->after('referrer_agent_id');
            
            $table->foreign('referrer_agent_id')->references('id')->on('agents')->cascadeOnDelete();
            $table->foreign('referrer_client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        // 3. Update chit_configurations default calculation base to 'chit_value'
        DB::table('chit_configurations')
            ->where('key', 'referral_calculation_base')
            ->update(['value' => 'chit_value']);
    }

    public function down(): void
    {
        Schema::table('chit_referral_bonuses', function (Blueprint $table) {
            try {
                $table->dropForeign(['referrer_agent_id']);
                $table->dropForeign(['referrer_client_id']);
            } catch (\Exception $e) {}
            $table->dropColumn(['referrer_agent_id', 'referrer_client_id']);
            
            $table->unsignedBigInteger('referrer_id')->nullable()->after('group_member_id');
            $table->foreign('referrer_id')->references('id')->on('agents')->cascadeOnDelete();
        });

        Schema::table('group_members', function (Blueprint $table) {
            try {
                $table->dropForeign(['referred_by_agent_id']);
                $table->dropForeign(['referred_by_client_id']);
            } catch (\Exception $e) {}
            $table->dropColumn(['referred_by_agent_id', 'referred_by_client_id']);
            
            $table->unsignedBigInteger('referred_by')->nullable()->after('client_id');
            $table->foreign('referred_by')->references('id')->on('agents')->nullOnDelete();
        });
    }
};
