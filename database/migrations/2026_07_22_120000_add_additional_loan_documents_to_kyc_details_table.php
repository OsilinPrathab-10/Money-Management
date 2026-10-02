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
        Schema::table('kyc_details', function (Blueprint $table) {
            if (!Schema::hasColumn('kyc_details', 'rc_book_image')) {
                $table->string('rc_book_image')->nullable()->after('bank_statement');
            }
            if (!Schema::hasColumn('kyc_details', 'driving_licence_image')) {
                $table->string('driving_licence_image')->nullable()->after('rc_book_image');
            }
            if (!Schema::hasColumn('kyc_details', 'vehicle_number')) {
                $table->string('vehicle_number')->nullable()->after('driving_licence_image');
            }
            if (!Schema::hasColumn('kyc_details', 'home_loan_document')) {
                $table->string('home_loan_document')->nullable()->after('vehicle_number');
            }
            if (!Schema::hasColumn('kyc_details', 'additional_documents')) {
                $table->json('additional_documents')->nullable()->after('home_loan_document');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $columns = ['rc_book_image', 'driving_licence_image', 'vehicle_number', 'home_loan_document', 'additional_documents'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('kyc_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
