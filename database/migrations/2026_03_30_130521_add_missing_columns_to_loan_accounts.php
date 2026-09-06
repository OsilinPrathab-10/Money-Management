<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix loan_accounts table
        Schema::table('loan_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_accounts', 'emi_amount')) {
                $table->decimal('emi_amount', 15, 2)->after('tenure')->default(0);
            }
            if (!Schema::hasColumn('loan_accounts', 'emi_day')) {
                $table->integer('emi_day')->after('emi_amount')->default(1);
            }
            if (!Schema::hasColumn('loan_accounts', 'total_payable')) {
                $table->decimal('total_payable', 15, 2)->after('emi_day')->default(0);
            }
            if (!Schema::hasColumn('loan_accounts', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->after('total_payable')->default(0);
            }
            if (!Schema::hasColumn('loan_accounts', 'outstanding_amount')) {
                $table->decimal('outstanding_amount', 15, 2)->after('paid_amount')->default(0);
            }
            if (!Schema::hasColumn('loan_accounts', 'payment_method')) {
                $table->string('payment_method')->after('status')->nullable()->default('bank_transfer');
            }
        });

        // Fix emis table
        Schema::table('emis', function (Blueprint $table) {
            if (!Schema::hasColumn('emis', 'principal_amount')) {
                $table->decimal('principal_amount', 15, 2)->after('instalment_number')->default(0);
            }
            if (!Schema::hasColumn('emis', 'interest_amount')) {
                $table->decimal('interest_amount', 15, 2)->after('principal_amount')->default(0);
            }
            if (!Schema::hasColumn('emis', 'pending_amount')) {
                $table->decimal('pending_amount', 15, 2)->after('paid_amount')->default(0);
            }
            if (!Schema::hasColumn('emis', 'total_due')) {
                $table->decimal('total_due', 15, 2)->after('pending_amount')->default(0);
            }
        });

        // Fix status column for clients
        DB::statement("ALTER TABLE clients MODIFY COLUMN status ENUM('active', 'inactive', 'pending', 'rejected', 'blacklist') DEFAULT 'active'");
    }

    public function down(): void
    {
        // Logic to remove columns if you ever rollback
    }
};