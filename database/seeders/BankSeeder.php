<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class BankSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = storage_path('app/bankdata.json');

        if (!file_exists($filePath)) {
            $this->command->error('Bank data file not found at ' . $filePath);
            return;
        }

        try {
            $raw = file_get_contents($filePath);
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $this->command->error('Unable to read bank data: ' . $exception->getMessage());
            return;
        }

        $banks = data_get($decoded, 'data', []);

        if (empty($banks)) {
            $this->command->warn('No bank data found in file.');
            return;
        }

        // Clear existing banks
        Bank::query()->truncate();

        $count = 0;
        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['ifsc_code' => $bank['IFSCCODE'] ?? null],
                [
                    'bank_id' => $bank['BankID'] ?? null,
                    'bank_name' => $bank['BankName'] ?? null,
                ]
            );
            $count++;
        }

        $this->command->info("Successfully seeded {$count} banks into the database.");
    }
}
