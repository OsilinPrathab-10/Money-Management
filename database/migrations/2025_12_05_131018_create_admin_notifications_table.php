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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // emi_overdue, new_loan_application, new_user_registration, payment_received
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable(); // URL to view details
            $table->string('icon')->nullable(); // icon class or name
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->foreignId('related_id')->nullable(); // ID of related entity (loan, user, payment, etc.)
            $table->timestamps();
            
            $table->index(['is_read', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
