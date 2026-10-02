<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->string('menu_key', 191);
            $table->timestamps();

            $table->unique(['role_id', 'menu_key']);
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->index('menu_key');
        });

        Schema::create('user_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('menu_key', 191);
            $table->timestamps();

            $table->unique(['user_id', 'menu_key']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('menu_key');
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'use_custom_menus')) {
                $table->boolean('use_custom_menus')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'use_custom_menus')) {
                $table->dropColumn('use_custom_menus');
            }
        });

        Schema::dropIfExists('user_menus');
        Schema::dropIfExists('role_menus');
    }
};
