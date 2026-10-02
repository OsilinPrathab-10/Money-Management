<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->boolean('kyc_skipped')->default(false)->after('bank_verified');
            $table->unsignedBigInteger('kyc_skipped_by')->nullable()->after('kyc_skipped');
            $table->timestamp('kyc_skipped_at')->nullable()->after('kyc_skipped_by');
            $table->text('kyc_skip_remarks')->nullable()->after('kyc_skipped_at');

            $table->foreign('kyc_skipped_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kyc_details', function (Blueprint $table) {
            $table->dropForeign(['kyc_skipped_by']);
            $table->dropColumn(['kyc_skipped', 'kyc_skipped_by', 'kyc_skipped_at', 'kyc_skip_remarks']);
        });
    }
};
