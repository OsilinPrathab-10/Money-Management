<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\LoanAccount;

class LoanAccountNumberTest extends TestCase
{
    /**
     * Test formatting loan amounts for account number prefix.
     */
    public function test_format_amount_for_account_number()
    {
        $this->assertEquals('01H', LoanAccount::formatAmountForAccountNumber(100));
        $this->assertEquals('05H', LoanAccount::formatAmountForAccountNumber(500));
        $this->assertEquals('1K', LoanAccount::formatAmountForAccountNumber(1000));
        $this->assertEquals('10K', LoanAccount::formatAmountForAccountNumber(10000));
        $this->assertEquals('1L', LoanAccount::formatAmountForAccountNumber(100000));
        
        // Non-pointwise value checks
        $this->assertEquals('296K', LoanAccount::formatAmountForAccountNumber(296330));
        $this->assertEquals('297K', LoanAccount::formatAmountForAccountNumber(296900));
        $this->assertEquals('101K', LoanAccount::formatAmountForAccountNumber(100500));
        $this->assertEquals('15H', LoanAccount::formatAmountForAccountNumber(1500));
    }
}
