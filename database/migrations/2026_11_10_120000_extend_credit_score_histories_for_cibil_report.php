<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_score_histories', function (Blueprint $table) {
            $table->string('gender', 16)->nullable()->after('phone');
            $table->string('provider', 32)->nullable()->after('status');
            $table->boolean('is_mock')->default(false)->after('provider');
            $table->string('doc_id', 128)->nullable()->after('is_mock');
            $table->text('credit_report_link')->nullable()->after('doc_id');
            $table->timestamp('link_expires_at')->nullable()->after('credit_report_link');
            $table->text('consolidated_pdf_url')->nullable()->after('link_expires_at');
            $table->string('pdf_path', 512)->nullable()->after('consolidated_pdf_url');
            $table->string('pdf_source', 32)->nullable()->after('pdf_path');
            $table->string('pdf_status', 32)->nullable()->after('pdf_source');
            $table->text('pdf_error')->nullable()->after('pdf_status');
            $table->timestamp('pdf_saved_at')->nullable()->after('pdf_error');
            $table->timestamp('linked_at')->nullable()->after('client_id');
            $table->foreignId('linked_by')->nullable()->after('linked_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('credit_score_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_by');
            $table->dropColumn([
                'gender',
                'provider',
                'is_mock',
                'doc_id',
                'credit_report_link',
                'link_expires_at',
                'consolidated_pdf_url',
                'pdf_path',
                'pdf_source',
                'pdf_status',
                'pdf_error',
                'pdf_saved_at',
                'linked_at',
            ]);
        });
    }
};
