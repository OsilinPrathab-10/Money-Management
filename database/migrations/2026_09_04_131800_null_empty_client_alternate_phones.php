<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients') || ! Schema::hasColumn('clients', 'alternate_phone')) {
            return;
        }

        // Unique index allows many NULLs but only one ''. Clear blanks so updates don't 500.
        DB::table('clients')->where('alternate_phone', '')->update(['alternate_phone' => null]);
    }

    public function down(): void
    {
        //
    }
};
