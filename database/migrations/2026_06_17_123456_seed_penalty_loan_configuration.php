<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\LoanConfiguration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        LoanConfiguration::updateOrCreate(
            ['type' => 'penalty'],
            [
                'charges_percentage' => 0.00, // Default penalty percentage
                'eligibility_days' => 0, // Default grace period in days
                'is_active' => false, // Disabled by default
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        LoanConfiguration::where('type', 'penalty')->delete();
    }
};
