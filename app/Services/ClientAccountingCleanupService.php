<?php

namespace App\Services;

use App\Models\Account\BankTransaction;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentAuditLog;
use App\Services\Account\BankTransactionsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClientAccountingCleanupService
{
    public function __construct(
        protected BankTransactionsService $bankTransactionsService
    ) {}

    /**
     * Remove accounting records linked to a single loan account.
     */
    public function purgeForLoanAccount(\App\Models\LoanAccount $loanAccount): void
    {
        DB::transaction(function () use ($loanAccount) {
            // Load necessary relations if not loaded
            $loanAccount->loadMissing([
                'loanApplication' => fn ($q) => $q->withTrashed()->with('disbursementDetail'),
                'emis' => fn ($q) => $q->withTrashed()->with(['collections' => fn ($q2) => $q2->withTrashed()]),
            ]);

            $this->purgeForLoanAccounts(collect([$loanAccount]));
        });
    }

    /**
     * Remove accounting records linked to a client before the client row is deleted.
     */
    public function purgeForClient(Client $client): void
    {
        DB::transaction(function () use ($client) {
            $loanAccounts = $client->loanAccounts()
                ->withTrashed()
                ->with([
                    'loanApplication' => fn ($q) => $q->withTrashed()->with('disbursementDetail'),
                    'emis' => fn ($q) => $q->withTrashed()->with(['collections' => fn ($q2) => $q2->withTrashed()]),
                ])
                ->get();

            if ($loanAccounts->isEmpty()) {
                Payment::where('client_id', $client->id)->delete();

                return;
            }

            $this->purgeForLoanAccounts($loanAccounts);

            Payment::where('client_id', $client->id)->delete();
        });
    }

    /**
     * Common logic to purge accounting records for a collection of loan accounts.
     */
    protected function purgeForLoanAccounts(Collection $loanAccounts): void
    {
        $loanAccountIds = $loanAccounts->pluck('id');
        $accountNumbers = $this->collectLoanAccountNumbers($loanAccounts);
        $referenceNumbers = $this->collectReferenceNumbers($loanAccounts);

        $transactions = $this->findClientBankTransactions($accountNumbers, $referenceNumbers);

        $affectedBankAccountIds = $transactions->pluck('bank_account_id')->unique()->filter();

        if ($transactions->isNotEmpty()) {
            BankTransaction::whereIn('id', $transactions->pluck('id'))->delete();

            foreach ($affectedBankAccountIds as $bankAccountId) {
                $this->bankTransactionsService->recalculateRunningBalances((int) $bankAccountId);
            }
        }

        PaymentAuditLog::whereIn('loan_account_id', $loanAccountIds)->delete();
        Payment::whereIn('loan_account_id', $loanAccountIds)->delete();
    }

    protected function collectLoanAccountNumbers(Collection $loanAccounts): Collection
    {
        return $loanAccounts
            ->flatMap(fn ($account) => [
                $account->customer_loan_account_number,
                $account->account_number,
            ])
            ->filter(fn ($number) => is_string($number) && $number !== '' && ! str_starts_with($number, 'TEMP_'))
            ->unique()
            ->values();
    }

    protected function collectReferenceNumbers(Collection $loanAccounts): Collection
    {
        $references = collect();

        foreach ($loanAccounts as $account) {
            if ($account->transaction_id) {
                $references->push($account->transaction_id);
            }

            if ($account->utr_number) {
                $references->push($account->utr_number);
            }

            $disbursement = $account->loanApplication?->disbursementDetail;
            if ($disbursement?->transaction_id) {
                $references->push($disbursement->transaction_id);
            }
            if ($disbursement?->utr_number) {
                $references->push($disbursement->utr_number);
            }

            foreach ($account->emis as $emi) {
                foreach ($emi->collections as $collection) {
                    if ($collection->payment_reference) {
                        $references->push($collection->payment_reference);
                    }
                }
            }
        }

        return $references->filter()->unique()->values();
    }

    protected function findClientBankTransactions(Collection $accountNumbers, Collection $referenceNumbers): Collection
    {
        if ($accountNumbers->isEmpty() && $referenceNumbers->isEmpty()) {
            return collect();
        }

        $query = BankTransaction::query();

        $query->where(function ($outer) use ($accountNumbers, $referenceNumbers) {
            if ($referenceNumbers->isNotEmpty()) {
                $outer->whereIn('reference_number', $referenceNumbers->all());
            }

            foreach ($accountNumbers as $accountNumber) {
                $outer->orWhere('description', 'like', '%' . $accountNumber . '%');
            }
        });

        return $query->get();
    }
}
