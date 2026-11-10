<?php

namespace Tests\Unit;

use App\Services\EmiCalculator;
use PHPUnit\Framework\TestCase;

class EmiCalculatorZeroInterestTest extends TestCase
{
    public function test_flat_free_loan_has_zero_interest_and_repayable_principal_only(): void
    {
        $result = (new EmiCalculator())->generateSchedule(
            principal: 120000,
            annualRate: 0,
            term: 12,
            startDate: '2026-07-01',
            frequency: 'monthly',
            interestType: 'flat'
        );

        $this->assertSame(0.0, (float) $result['total_interest']);
        $this->assertSame(120000.0, (float) $result['total_payment']);
        $this->assertCount(12, $result['schedule']);
        $this->assertSame(120000.0, array_sum(array_column($result['schedule'], 'principal')));
        $this->assertSame(0.0, array_sum(array_column($result['schedule'], 'interest')));
    }

    public function test_reducing_free_loan_has_zero_interest_and_repayable_principal_only(): void
    {
        $result = (new EmiCalculator())->generateSchedule(
            principal: 100000,
            annualRate: 0,
            term: 10,
            startDate: '2026-07-01',
            frequency: 'monthly',
            interestType: 'reducing'
        );

        $this->assertSame(0.0, (float) $result['total_interest']);
        $this->assertSame(100000.0, (float) $result['total_payment']);
        $this->assertSame(100000.0, array_sum(array_column($result['schedule'], 'principal')));
    }
}
