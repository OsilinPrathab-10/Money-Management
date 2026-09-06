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
        Schema::create('appearances', function (Blueprint $table) {
            $table->id();
            $table->string('primary_color')->default('#696cff');
            $table->string('secondary_color')->default('#8592a3');
            $table->string('title')->default('Loan App');
            $table->string('subtitle')->default('');
            $table->enum('type', ['web', 'app'])->default('web');
            $table->string('logo')->default('');
            $table->string('logo_dark')->default('');
            $table->string('favicon')->default('');
            $table->string('footer_text')->default('');
            $table->string('theme_mode')->default('light');
            $table->string('loader_animation')->default('loader1');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appearances');
    }
};
