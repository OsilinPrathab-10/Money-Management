<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_deposit_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('fixed_deposit_schemes')->cascadeOnDelete();
            $table->decimal('deposit_amount', 15, 2);
            $table->unsignedInteger('tenure');
            $table->string('tenure_type', 20)->default('months');
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->decimal('interest_amount', 15, 2)->nullable();
            $table->decimal('maturity_amount', 15, 2)->nullable();
            $table->date('deposit_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->string('nominee_name')->nullable();
            $table->string('nominee_relation')->nullable();
            $table->string('payout_option', 40)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('booked_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->unsignedBigInteger('booked_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('fixed_deposit_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_deposit_applications');
    }
};
