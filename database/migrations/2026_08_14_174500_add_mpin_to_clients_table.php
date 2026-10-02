<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'mpin_hash')) {
                $table->string('mpin_hash')->nullable()->after('status');
            }
            if (! Schema::hasColumn('clients', 'mpin_set_at')) {
                $table->timestamp('mpin_set_at')->nullable()->after('mpin_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'mpin_set_at')) {
                $table->dropColumn('mpin_set_at');
            }
            if (Schema::hasColumn('clients', 'mpin_hash')) {
                $table->dropColumn('mpin_hash');
            }
        });
    }
};
