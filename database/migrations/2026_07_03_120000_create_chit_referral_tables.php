<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add referred_by to group_members table
        if (!Schema::hasColumn('group_members', 'referred_by')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->unsignedBigInteger('referred_by')->nullable()->after('client_id');
                $table->foreign('referred_by')->references('id')->on('agents')->nullOnDelete();
            });
        }

        // 2. Create chit_configurations table
        Schema::create('chit_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default referral commission percentage
        DB::table('chit_configurations')->insert([
            [
                'key' => 'referral_commission_percentage',
                'value' => '10.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'referral_calculation_base',
                'value' => 'group_commission', // 'group_commission' or 'chit_value'
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 3. Create chit_referral_bonuses table
        Schema::create('chit_referral_bonuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_member_id');
            $table->unsignedBigInteger('referrer_id');
            $table->decimal('bonus_amount', 15, 2);
            $table->decimal('calculated_percentage', 5, 2);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->date('paid_date')->nullable();
            $table->timestamps();

            $table->foreign('group_member_id')->references('id')->on('group_members')->onDelete('cascade');
            $table->foreign('referrer_id')->references('id')->on('agents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chit_referral_bonuses');
        Schema::dropIfExists('chit_configurations');

        if (Schema::hasColumn('group_members', 'referred_by')) {
            Schema::table('group_members', function (Blueprint $table) {
                $table->dropForeign(['referred_by']);
                $table->dropColumn('referred_by');
            });
        }
    }
};
