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
        // 1. Staffs table
        Schema::create('staffs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->decimal('salary_amount', 15, 2);
            $table->string('profile_photo')->nullable();
            $table->string('aadhar_photo')->nullable();
            $table->string('bank_account_photo')->nullable();
            $table->json('salary_details')->nullable(); // Bank name, account number, IFSC, etc.
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Staff Attendances
        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staffs')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'half_day'])->default('present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
        });

        // 3. Staff Expenses (Reimbursements, etc.)
        Schema::create('staff_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staffs')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description');
            $table->timestamps();
        });

        // 4. Staff Advances (Deductions)
        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staffs')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('description');
            $table->timestamps();
        });

        // 5. Holidays
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('name');
            $table->string('type')->default('government'); // government, company, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('staff_advances');
        Schema::dropIfExists('staff_expenses');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staffs');
    }
};
