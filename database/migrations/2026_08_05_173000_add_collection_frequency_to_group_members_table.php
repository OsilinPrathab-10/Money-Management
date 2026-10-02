<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_members', 'collection_frequency')) {
                $table->string('collection_frequency', 20)
                    ->default('monthly')
                    ->after('share_percentage')
                    ->comment('How installment is collected: monthly|weekly|daily');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_members', 'collection_frequency')) {
                $table->dropColumn('collection_frequency');
            }
        });
    }
};
