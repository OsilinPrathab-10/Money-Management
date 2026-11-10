<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->decimal('interest_rate', 10, 6)->nullable()->change();

            if (! Schema::hasColumn('loan_applications', 'interest_source')) {
                $table->enum('interest_source', ['product', 'manual'])->default('product')->after('loan_mode');
            }
            if (! Schema::hasColumn('loan_applications', 'manual_interest_amount')) {
                $table->decimal('manual_interest_amount', 15, 2)->nullable()->after('interest_source');
            }
        });

        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->decimal('interest_rate', 10, 6)->change();
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['interest_source', 'manual_interest_amount']);
            $table->decimal('interest_rate', 5, 2)->nullable()->change();
        });

        Schema::table('loan_accounts', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->change();
        });
    }
};
