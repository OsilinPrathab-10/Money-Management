<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_notifications')) {
            return;
        }

        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->string('notification_type')->default('general');
            $table->string('notification_id')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('notification_type_label')->nullable();
            $table->string('icon')->default('bell');
            $table->string('priority')->default('medium');
            $table->json('action_data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'read_at']);
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
    }
};
