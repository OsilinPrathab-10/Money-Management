<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Companies Master Table
        if (!Schema::hasTable('card_cash_companies')) {
            Schema::create('card_cash_companies', function (Blueprint $table) {
                $table->id();
                $table->string('company_name');
                $table->string('company_code', 50)->nullable()->unique();
                $table->string('contact_person')->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 100)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. Bank Names Master Table
        if (!Schema::hasTable('card_cash_banks')) {
            Schema::create('card_cash_banks', function (Blueprint $table) {
                $table->id();
                $table->string('bank_name');
                $table->string('bank_code', 50)->nullable();
                $table->string('ifsc_prefix', 20)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            // Seed initial banks from existing banks table or common banks if empty
            if (Schema::hasTable('banks')) {
                $existingBanks = DB::table('banks')->select('bank_name', 'ifsc_code')->get();
                foreach ($existingBanks as $b) {
                    DB::table('card_cash_banks')->insertOrIgnore([
                        'bank_name' => $b->bank_name,
                        'bank_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $b->bank_name), 0, 8)),
                        'ifsc_prefix' => substr($b->ifsc_code ?? '', 0, 4),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (DB::table('card_cash_banks')->count() === 0) {
                $defaults = ['HDFC Bank', 'State Bank of India', 'ICICI Bank', 'Axis Bank', 'Kotak Mahindra Bank', 'Punjab National Bank', 'Bank of Baroda', 'Canara Bank', 'Union Bank of India', 'IndusInd Bank'];
                foreach ($defaults as $d) {
                    DB::table('card_cash_banks')->insert([
                        'bank_name' => $d,
                        'bank_code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $d), 0, 8)),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3. Add company_id and percentage columns to card_cash_withdrawal_gateways
        Schema::table('card_cash_withdrawal_gateways', function (Blueprint $table) {
            if (!Schema::hasColumn('card_cash_withdrawal_gateways', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('gateway_code')->constrained('card_cash_companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('card_cash_withdrawal_gateways', 'debit_percentage')) {
                $table->decimal('debit_percentage', 5, 2)->default(0.00)->after('wallet_supported');
            }
            if (!Schema::hasColumn('card_cash_withdrawal_gateways', 'credit_percentage')) {
                $table->decimal('credit_percentage', 5, 2)->default(0.00)->after('debit_percentage');
            }
            if (!Schema::hasColumn('card_cash_withdrawal_gateways', 'prepaid_percentage')) {
                $table->decimal('prepaid_percentage', 5, 2)->default(0.00)->after('credit_percentage');
            }
            if (!Schema::hasColumn('card_cash_withdrawal_gateways', 'business_percentage')) {
                $table->decimal('business_percentage', 5, 2)->default(0.00)->after('prepaid_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_cash_withdrawal_gateways', function (Blueprint $table) {
            if (Schema::hasColumn('card_cash_withdrawal_gateways', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
            $cols = ['debit_percentage', 'credit_percentage', 'prepaid_percentage', 'business_percentage'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('card_cash_withdrawal_gateways', $c)) {
                    $table->dropColumn($c);
                }
            }
        });

        Schema::dropIfExists('card_cash_banks');
        Schema::dropIfExists('card_cash_companies');
    }
};
