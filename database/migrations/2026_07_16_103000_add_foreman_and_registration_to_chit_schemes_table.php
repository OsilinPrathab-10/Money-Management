<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chit_schemes')) {
            return;
        }

        Schema::table('chit_schemes', function (Blueprint $table) {
            if (! Schema::hasColumn('chit_schemes', 'foreman_commission_month')) {
                $table->unsignedTinyInteger('foreman_commission_month')->default(1)->after('duration_months');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_type')) {
                $table->string('registration_type', 20)->default('non_registered')->after('scheme_type');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_number')) {
                $table->string('registration_number', 100)->nullable()->after('registration_type');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('registration_number');
            }

            if (! Schema::hasColumn('chit_schemes', 'registering_authority')) {
                $table->string('registering_authority', 150)->nullable()->after('registration_date');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_office')) {
                $table->string('registration_office', 150)->nullable()->after('registering_authority');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_certificate_number')) {
                $table->string('registration_certificate_number', 100)->nullable()->after('registration_office');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_certificate_path')) {
                $table->string('registration_certificate_path')->nullable()->after('registration_certificate_number');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_valid_from')) {
                $table->date('registration_valid_from')->nullable()->after('registration_certificate_path');
            }

            if (! Schema::hasColumn('chit_schemes', 'registration_valid_until')) {
                $table->date('registration_valid_until')->nullable()->after('registration_valid_from');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('chit_schemes')) {
            return;
        }

        Schema::table('chit_schemes', function (Blueprint $table) {
            $columns = [
                'foreman_commission_month',
                'registration_type',
                'registration_number',
                'registration_date',
                'registering_authority',
                'registration_office',
                'registration_certificate_number',
                'registration_certificate_path',
                'registration_valid_from',
                'registration_valid_until',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('chit_schemes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
