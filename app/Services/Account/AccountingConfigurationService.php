<?php

namespace App\Services\Account;

use App\Models\Account\AccountingConfiguration;

class AccountingConfigurationService
{
    public function isGstEnabled(): bool
    {
        return AccountingConfiguration::get('gst_enabled', '0') === '1';
    }

    public function gstPercentage(): float
    {
        return (float) AccountingConfiguration::get('gst_percentage', '18.00');
    }

    public function snapshot(): array
    {
        return [
            'gst_enabled' => $this->isGstEnabled(),
            'gst_percentage' => $this->gstPercentage(),
        ];
    }
}
