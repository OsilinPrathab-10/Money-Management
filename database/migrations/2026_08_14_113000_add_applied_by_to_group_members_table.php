<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_members')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'applied_by')) {
                $table->foreignId('applied_by')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        // Backfill: agent-submitted apps (auto-referrer = applying agent + Applied by remarks).
        $agents = DB::table('agents')->whereNotNull('user_id')->get(['id', 'user_id']);
        foreach ($agents as $agent) {
            $user = DB::table('users')->where('id', $agent->user_id)->first();
            if (! $user || empty($user->name)) {
                continue;
            }

            DB::table('group_members')
                ->whereNull('applied_by')
                ->where('referred_by_agent_id', $agent->id)
                ->where('remarks', 'like', 'Applied by ' . $user->name . '%')
                ->update(['applied_by' => $agent->user_id]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('group_members') || ! Schema::hasColumn('group_members', 'applied_by')) {
            return;
        }

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_by');
        });
    }
};
