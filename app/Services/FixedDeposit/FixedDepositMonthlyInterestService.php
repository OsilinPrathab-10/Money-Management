<?php

namespace App\Services\FixedDeposit;

use App\Models\FixedDeposit;
use App\Models\FixedDepositInterestPayout;
use App\Models\FixedDepositTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FixedDepositMonthlyInterestService
{
    public function __construct(
        protected FixedDepositInterestService $interestService,
        protected WalletService $walletService,
        protected FixedDepositAccountingService $accountingService,
        protected FixedDepositAuditService $auditService,
    ) {}

    /**
     * Process all due monthly interest payouts for active FDs.
     */
    public function processDuePayouts(?Carbon $asOf = null): array
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $processed = 0;
        $skipped = 0;
        $errors = [];

        FixedDeposit::where('status', 'active')
            ->where('monthly_interest_to_wallet', true)
            ->whereDate('start_date', '<=', $asOf)
            ->orderBy('id')
            ->chunkById(100, function ($deposits) use ($asOf, &$processed, &$skipped, &$errors) {
                foreach ($deposits as $deposit) {
                    try {
                        $count = $this->processDeposit($deposit, $asOf);
                        $processed += $count;
                    } catch (\Throwable $e) {
                        $errors[] = $deposit->fd_number . ': ' . $e->getMessage();
                        $skipped++;
                    }
                }
            });

        return compact('processed', 'skipped', 'errors');
    }

    /**
     * Pay all due monthly interest for one FD up to $asOf.
     */
    public function processDeposit(FixedDeposit $deposit, ?Carbon $asOf = null): int
    {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();
        $paid = 0;

        while (true) {
            $next = $this->nextPayoutPeriod($deposit);
            if (!$next || $next['due_date']->gt($asOf)) {
                break;
            }

            $this->payPeriod($deposit, $next);
            $deposit->refresh();
            $paid++;
        }

        return $paid;
    }

    public function monthlyInterestAmount(FixedDeposit $deposit, int $monthIndex): float
    {
        $principal = (float) $deposit->deposit_amount;
        $rate = (float) $deposit->interest_rate;
        $monthIndex = max(1, $monthIndex);

        if ($deposit->interest_type === 'compound_interest') {
            $monthlyRate = $rate / 12 / 100;
            $prevBalance = $principal * pow(1 + $monthlyRate, $monthIndex - 1);
            $currBalance = $principal * pow(1 + $monthlyRate, $monthIndex);

            return round(max(0, $currBalance - $prevBalance), 2);
        }

        // Simple interest — equal monthly payout on original principal
        return round($principal * ($rate / 100) / 12, 2);
    }

    public function remainingInterest(FixedDeposit $deposit): float
    {
        return round(max(0, (float) $deposit->interest_amount - (float) $deposit->interest_paid_to_wallet), 2);
    }

    public function maturityWalletAmount(FixedDeposit $deposit): float
    {
        return round((float) $deposit->deposit_amount + $this->remainingInterest($deposit), 2);
    }

    private function nextPayoutPeriod(FixedDeposit $deposit): ?array
    {
        $start = $deposit->start_date->copy()->startOfDay();
        $maturity = $deposit->maturity_date->copy()->startOfDay();

        if ($start->gte($maturity)) {
            return null;
        }

        $lastPayout = $deposit->last_interest_payout_date
            ? Carbon::parse($deposit->last_interest_payout_date)->startOfDay()
            : null;

        if ($lastPayout) {
            $periodFrom = $lastPayout->copy()->addDay();
            $dueDate = $lastPayout->copy()->addMonthNoOverflow();
        } else {
            $periodFrom = $start->copy();
            $dueDate = $start->copy()->addMonthNoOverflow();
        }

        if ($dueDate->gt($maturity)) {
            return null;
        }

        $monthIndex = (int) $start->diffInMonths($dueDate);
        if ($monthIndex < 1) {
            $monthIndex = 1;
        }

        return [
            'period_from' => $periodFrom,
            'period_to' => $dueDate,
            'due_date' => $dueDate,
            'payout_month' => (int) $dueDate->month,
            'payout_year' => (int) $dueDate->year,
            'month_index' => $monthIndex,
        ];
    }

    private function payPeriod(FixedDeposit $deposit, array $period): FixedDepositInterestPayout
    {
        return DB::transaction(function () use ($deposit, $period) {
            $deposit = FixedDeposit::where('id', $deposit->id)->lockForUpdate()->first();

            if ($deposit->status !== 'active' || !$deposit->monthly_interest_to_wallet) {
                throw new \RuntimeException('FD is not eligible for monthly interest payout.');
            }

            $exists = FixedDepositInterestPayout::where('fixed_deposit_id', $deposit->id)
                ->where('payout_year', $period['payout_year'])
                ->where('payout_month', $period['payout_month'])
                ->exists();

            if ($exists) {
                throw new \RuntimeException('Interest already paid for this period.');
            }

            $amount = $this->monthlyInterestAmount($deposit, $period['month_index']);
            if ($amount <= 0) {
                throw new \RuntimeException('Calculated interest is zero.');
            }

            $remaining = $this->remainingInterest($deposit);
            $amount = round(min($amount, $remaining), 2);
            if ($amount <= 0) {
                throw new \RuntimeException('No remaining interest to pay.');
            }

            $walletTxn = $this->walletService->credit(
                $deposit->client_id,
                $amount,
                'FD Monthly Interest — ' . $deposit->fd_number,
                'fixed_deposit_interest',
                $deposit->id,
                [
                    'fd_number' => $deposit->fd_number,
                    'period' => $period['period_from']->format('Y-m-d') . ' to ' . $period['period_to']->format('Y-m-d'),
                ]
            );

            $payout = FixedDepositInterestPayout::create([
                'fixed_deposit_id' => $deposit->id,
                'payout_month' => $period['payout_month'],
                'payout_year' => $period['payout_year'],
                'period_from' => $period['period_from']->toDateString(),
                'period_to' => $period['period_to']->toDateString(),
                'interest_amount' => $amount,
                'wallet_transaction_id' => $walletTxn->id,
                'created_by' => Auth::id(),
            ]);

            FixedDepositTransaction::create([
                'fixed_deposit_id' => $deposit->id,
                'transaction_type' => 'monthly_interest',
                'amount' => $amount,
                'interest_amount' => $amount,
                'description' => 'Monthly interest credited to wallet',
                'meta' => [
                    'payout_month' => $period['payout_month'],
                    'payout_year' => $period['payout_year'],
                ],
                'created_by' => Auth::id(),
            ]);

            $deposit->update([
                'interest_paid_to_wallet' => round((float) $deposit->interest_paid_to_wallet + $amount, 2),
                'last_interest_payout_date' => $period['period_to']->toDateString(),
            ]);

            $this->accountingService->postMonthlyInterestCredit($deposit, $amount);
            $this->auditService->log('Wallet Credit', $deposit->id, $deposit->scheme_id, null, [
                'type' => 'monthly_interest',
                'amount' => $amount,
                'period' => $period['period_to']->format('M Y'),
            ]);

            return $payout;
        });
    }
}
