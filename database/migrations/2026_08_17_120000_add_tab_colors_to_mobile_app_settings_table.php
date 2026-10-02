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
        if (Schema::hasTable('mobile_app_settings') && ! Schema::hasColumn('mobile_app_settings', 'overview_tab_color')) {
            Schema::table('mobile_app_settings', function (Blueprint $table) {
                $table->string('overview_tab_color')->default('#696CFF')->after('background_color');
                $table->string('loan_tab_color')->default('#00CFDD')->after('overview_tab_color');
                $table->string('chit_tab_color')->default('#7367F0')->after('loan_tab_color');
                $table->string('fixed_deposit_tab_color')->default('#FF4C51')->after('chit_tab_color');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mobile_app_settings')) {
            Schema::table('mobile_app_settings', function (Blueprint $table) {
                $columnsToDrop = [];
                foreach (['overview_tab_color', 'loan_tab_color', 'chit_tab_color', 'fixed_deposit_tab_color'] as $col) {
                    if (Schema::hasColumn('mobile_app_settings', $col)) {
                        $columnsToDrop[] = $col;
                    }
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
