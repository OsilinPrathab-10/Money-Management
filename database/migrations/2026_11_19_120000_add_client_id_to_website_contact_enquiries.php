<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_contact_enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('website_contact_enquiries', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('status')->constrained('clients')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('website_contact_enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('website_contact_enquiries', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
        });
    }
};