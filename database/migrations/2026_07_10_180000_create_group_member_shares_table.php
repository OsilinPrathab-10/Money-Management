<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('client_id');
        });

        Schema::create('group_member_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_member_id')->constrained('group_members')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->decimal('ownership_percentage', 5, 2);
            $table->decimal('share_amount', 14, 2)->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['group_member_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_member_shares');

        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('is_shared');
        });
    }
};
