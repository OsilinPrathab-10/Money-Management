<?php

namespace App\Console\Commands;

use App\Models\ApiConfiguration;
use App\Models\PaymentGateway;
use Illuminate\Console\Command;

class EncryptCredentials extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credentials:encrypt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt existing API and payment gateway credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting credential encryption...');
        $this->newLine();

        // Encrypt API Configurations
        $this->info('Encrypting API configurations...');
        $apiConfigs = ApiConfiguration::all();
        $apiCount = 0;

        foreach ($apiConfigs as $config) {
            try {
                // Re-saving will trigger encryption via the encrypted cast
                $config->save();
                $apiCount++;
                $this->line("  ✓ Encrypted: {$config->service}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to encrypt {$config->service}: {$e->getMessage()}");
            }
        }

        $this->info("Encrypted {$apiCount} API configuration(s)");
        $this->newLine();

        // Encrypt Payment Gateways
        $this->info('Encrypting payment gateways...');
        $gateways = PaymentGateway::all();
        $gatewayCount = 0;

        foreach ($gateways as $gateway) {
            try {
                // Re-saving will trigger encryption via the encrypted cast
                $gateway->save();
                $gatewayCount++;
                $this->line("  ✓ Encrypted: {$gateway->name}");
            } catch (\Exception $e) {
                $this->error("  ✗ Failed to encrypt {$gateway->name}: {$e->getMessage()}");
            }
        }

        $this->info("Encrypted {$gatewayCount} payment gateway(s)");
        $this->newLine();

        // Summary
        $this->info('✅ Encryption complete!');
        $this->table(
            ['Type', 'Count'],
            [
                ['API Configurations', $apiCount],
                ['Payment Gateways', $gatewayCount],
                ['Total', $apiCount + $gatewayCount],
            ]
        );

        $this->newLine();
        $this->warn('⚠️  Important: Never change your APP_KEY after encrypting data!');
        
        return Command::SUCCESS;
    }
}
