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
        Schema::table('loan_configurations', function (Blueprint $table) {
            $table->integer('eligibility_weeks')->nullable()->after('eligibility_months');
            $table->integer('eligibility_days')->nullable()->after('eligibility_weeks');
            $table->decimal('charges_percentage_weekly', 5, 2)->nullable()->after('charges_percentage');
            $table->decimal('charges_percentage_daily', 5, 2)->nullable()->after('charges_percentage_weekly');
            $table->decimal('charge_value_weekly', 15, 2)->default(0)->after('charge_value');
            $table->decimal('charge_value_daily', 15, 2)->default(0)->after('charge_value_weekly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_configurations', function (Blueprint $table) {
            $table->dropColumn([
                'eligibility_weeks',
                'eligibility_days',
                'charges_percentage_weekly',
                'charges_percentage_daily',
                'charge_value_weekly',
                'charge_value_daily'
            ]);
        });
    }
};
