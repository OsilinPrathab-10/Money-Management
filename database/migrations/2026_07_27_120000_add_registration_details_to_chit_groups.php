<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chit_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('chit_groups', 'registration_number')) {
                $table->string('registration_number', 100)->nullable()->after('registration_type');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('registration_number');
            }
            if (!Schema::hasColumn('chit_groups', 'registering_authority')) {
                $table->string('registering_authority', 150)->nullable()->after('registration_date');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_office')) {
                $table->string('registration_office', 150)->nullable()->after('registering_authority');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_certificate_number')) {
                $table->string('registration_certificate_number', 100)->nullable()->after('registration_office');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_certificate_path')) {
                $table->string('registration_certificate_path')->nullable()->after('registration_certificate_number');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_valid_from')) {
                $table->date('registration_valid_from')->nullable()->after('registration_certificate_path');
            }
            if (!Schema::hasColumn('chit_groups', 'registration_valid_until')) {
                $table->date('registration_valid_until')->nullable()->after('registration_valid_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chit_groups', function (Blueprint $table) {
            $cols = [
                'registration_number', 'registration_date', 'registering_authority',
                'registration_office', 'registration_certificate_number',
                'registration_certificate_path', 'registration_valid_from', 'registration_valid_until',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('chit_groups', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
