<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class KycBankAccountValidationUnitTest extends TestCase
{
    /**
     * Test the KYC and Client registration bank account validation rules.
     */
    public function test_bank_account_validation_rules()
    {
        $rules = [
            'account_number' => ['nullable', 'string', 'regex:/^[0-9]+$/'],
        ];

        // 1. Valid numbers (only numeric characters, any length)
        $validNumbers = [
            '12345678',
            '123456789',
            '9876543210',
            '123456789012345',
            '123456789012345678',
            '1234567890123456789021'
        ];

        foreach ($validNumbers as $num) {
            $validator = Validator::make(['account_number' => $num], $rules);
            $this->assertTrue($validator->passes(), "Should pass: {$num}");
        }

        // 2. Invalid numbers (non-numeric)
        $invalidNumbers = [
            '12345678a', // alphanumeric
            '123 456789', // contains space
        ];

        foreach ($invalidNumbers as $num) {
            $validator = Validator::make(['account_number' => $num], $rules);
            $this->assertTrue($validator->fails(), "Should fail: {$num}");
        }
    }
}
