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
        Schema::create('agent_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('agent_id');
            $table->string('notification_type'); // 'broadcast', 'system', etc.
            $table->string('notification_id')->nullable(); // Nullable for broadcast notifications
            
            // Broadcast notification fields
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('notification_type_label')->nullable(); // 'general', 'offer', etc.
            $table->string('icon')->default('notification');
            $table->string('priority')->default('medium'); // 'low', 'medium', 'high'
            $table->json('action_data')->nullable();
            
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_notifications');
    }
};
