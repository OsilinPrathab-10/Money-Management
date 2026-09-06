<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nominees', function (Blueprint $table) {
            $table->string('nominee1_name')->nullable()->change();
            $table->string('nominee1_relationship')->nullable()->change();
            $table->string('nominee1_mobile')->nullable()->change();
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('phone')->nullable()->change();
            $table->string('relationship')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nominees', function (Blueprint $table) {
            $table->string('nominee1_name')->nullable(false)->change();
            $table->string('nominee1_relationship')->nullable(false)->change();
            $table->string('nominee1_mobile')->nullable(false)->change();
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('phone')->nullable(false)->change();
            $table->string('relationship')->nullable(false)->change();
        });
    }
};
