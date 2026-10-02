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
        Schema::table('agents', function (Blueprint $table) {
            if (!Schema::hasColumn('agents', 'account_holder_name')) {
                $table->string('account_holder_name')->nullable()->after('salary_details');
            }
            if (!Schema::hasColumn('agents', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('account_holder_name');
            }
            if (!Schema::hasColumn('agents', 'account_number')) {
                $table->string('account_number')->nullable()->after('bank_name');
            }
            if (!Schema::hasColumn('agents', 'ifsc_code')) {
                $table->string('ifsc_code')->nullable()->after('account_number');
            }
            if (!Schema::hasColumn('agents', 'branch_name')) {
                $table->string('branch_name')->nullable()->after('ifsc_code');
            }
            if (!Schema::hasColumn('agents', 'upi_id')) {
                $table->string('upi_id')->nullable()->after('branch_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn([
                'account_holder_name',
                'bank_name',
                'account_number',
                'ifsc_code',
                'branch_name',
                'upi_id'
            ]);
        });
    }
};
