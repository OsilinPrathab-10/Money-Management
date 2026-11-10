<?php

namespace App\Services;

use App\Models\FixedDeposit;
use App\Models\LoanAccount;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class RevenueReportService
{
    /**
     * @return array{items: LengthAwarePaginator, totals: array<string, float>, overall_total: float}
     */
    public function loanReport(array $filters): array
    {
        $query = LoanAccount::with(['client.user', 'loanApplication.applicationDetail', 'emis']);

        if (! empty($filters['from_date'])) {
            $query->whereDate('disbursed_at', '>=', Carbon::parse($filters['from_date']));
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('disbursed_at', '<=', Carbon::parse($filters['to_date']));
        }
        if (($filters['loan_mode'] ?? 'all') !== 'all') {
            $query->where('loan_mode', $filters['loan_mode']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('loan_code', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('client_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $allFiltered = (clone $query)->get();
        $totals = $this->emptyLoanTotals();

        foreach ($allFiltered as $loan) {
            $row = $this->mapLoanRevenue($loan);
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }
        }

        $items = $query->orderByDesc('disbursed_at')
            ->paginate(15)
            ->withQueryString();

        $items->getCollection()->transform(function ($loan) {
            $row = $this->mapLoanRevenue($loan);
            foreach ($row as $key => $value) {
                $loan->{$key} = $value;
            }

            return $loan;
        });

        return [
            'items' => $items,
            'totals' => $totals,
            'overall_total' => max(0.00, array_sum($totals)),
        ];
    }


    /**
     * @return array{items: LengthAwarePaginator, totals: array<string, float>, overall_total: float}
     */
    public function fdReport(array $filters): array
    {
        $query = FixedDeposit::with(['client.user', 'scheme', 'transactions']);

        if (! empty($filters['from_date'])) {
            $query->whereDate('deposit_date', '>=', Carbon::parse($filters['from_date']));
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('deposit_date', '<=', Carbon::parse($filters['to_date']));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fd_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('client_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        $allFiltered = (clone $query)->get();
        $totals = $this->emptyFdTotals();

        foreach ($allFiltered as $fd) {
            $row = $this->mapFdRevenue($fd);
            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }
        }

        $items = $query->orderByDesc('deposit_date')
            ->paginate(15)
            ->withQueryString();

        $items->getCollection()->transform(function ($fd) {
            $row = $this->mapFdRevenue($fd);
            foreach ($row as $key => $value) {
                $fd->{$key} = $value;
            }

            return $fd;
        });

        return [
            'items' => $items,
            'totals' => $totals,
            'overall_total' => max(0.00, array_sum($totals)),
        ];
    }

    public function loanExportRows(array $filters): \Illuminate\Support\Collection
    {
        $query = LoanAccount::with(['client.user', 'loanApplication.applicationDetail', 'emis']);

        $this->applyLoanFilters($query, $filters);

        return $query->orderByDesc('disbursed_at')->get()->map(function ($loan, $index) {
            $row = $this->mapLoanRevenue($loan);

            return [
                'S.No' => $index + 1,
                'Customer Name' => $loan->client->user->name ?? $loan->client->client_name ?? 'N/A',
                'Account Number' => $loan->account_number ?? $loan->id,
                'Loan Code' => $loan->loan_code,
                'Loan Type' => $loan->loan_mode === 'interest_only' ? 'Open Loan' : 'EMI',
                'EMI / Cycle Amount (₹)' => number_format((float) $loan->emi_amount, 2),
                'Processing Fee (₹)' => number_format($row['processing_fee'], 2),
                'Document Charges (₹)' => number_format($row['document_charges'], 2),
                'Other Charges (₹)' => number_format($row['other_charges'], 2),
                'Interest Collected (₹)' => number_format($row['interest_collected'], 2),
                'Foreclose Revenue (₹)' => number_format($row['foreclosure_revenue'], 2),
                'Penalty Amount (₹)' => number_format($row['penalty_collected'], 2),
                'Total Revenue (₹)' => number_format($row['total_revenue'], 2),
            ];
        });
    }


    public function fdExportRows(array $filters): \Illuminate\Support\Collection
    {
        $query = FixedDeposit::with(['client.user', 'scheme', 'transactions']);
        $this->applyFdFilters($query, $filters);

        return $query->orderByDesc('deposit_date')->get()->map(function ($fd, $index) {
            $row = $this->mapFdRevenue($fd);

            return [
                'S.No' => $index + 1,
                'Customer Name' => $fd->client->user->name ?? $fd->client->client_name ?? 'N/A',
                'FD Number' => $fd->fd_number,
                'Scheme' => $fd->scheme->name ?? 'N/A',
                'Deposit Amount (₹)' => number_format((float) $fd->deposit_amount, 2),
                'Processing Fee (₹)' => number_format($row['processing_fee'], 2),
                'Document Charges (₹)' => number_format($row['document_charges'], 2),
                'Other Charges (₹)' => number_format($row['other_charges'], 2),
                'Premature Penalty (₹)' => number_format($row['penalty_collected'], 2),
                'Total Revenue (₹)' => number_format($row['total_revenue'], 2),
            ];
        });
    }

    private function applyLoanFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from_date'])) {
            $query->whereDate('disbursed_at', '>=', Carbon::parse($filters['from_date']));
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('disbursed_at', '<=', Carbon::parse($filters['to_date']));
        }
        if (($filters['loan_mode'] ?? 'all') !== 'all') {
            $query->where('loan_mode', $filters['loan_mode']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('loan_code', 'like', "%{$search}%")
                    ->orWhere('account_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('client_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                    });
            });
        }
    }


    private function applyFdFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['from_date'])) {
            $query->whereDate('deposit_date', '>=', Carbon::parse($filters['from_date']));
        }
        if (! empty($filters['to_date'])) {
            $query->whereDate('deposit_date', '<=', Carbon::parse($filters['to_date']));
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('fd_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('client_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                    });
            });
        }
    }

    /**
     * @return array<string, float>
     */
    private function mapLoanRevenue(LoanAccount $loan): array
    {
        $details = $loan->loanApplication->applicationDetail->details ?? [];
        $processingFee = max(0.00, (float) ($details['applied_processing_fee'] ?? 0));
        $documentCharges = max(0.00, (float) ($details['applied_document_charges'] ?? 0));
        $otherCharges = max(0.00, (float) ($details['applied_other_charges'] ?? 0));

        $interestCollected = max(0.00, (float) $loan->emis->sum(function ($emi) use ($loan) {
            if ($loan->loan_mode === 'interest_only') {
                return max(0.00, (float) $emi->paid_amount - (float) $emi->principal_amount);
            }

            return max(0.00, min((float) $emi->paid_amount, (float) $emi->interest_amount));
        }));

        $foreclosureRevenue = 0.0;
        if ($loan->is_foreclosed) {
            if ($loan->foreclosure_charges_amount !== null) {
                $foreclosureRevenue = max(0.00, (float) $loan->foreclosure_charges_amount);
            } elseif ($loan->foreclosure_amount > 0) {
                $chargesPercentage = $loan->foreclosure_charges_percentage ?? $loan->getForeclosureChargesPercentage();
                $multiplier = 1 + ($chargesPercentage / 100);
                $outstanding = $loan->foreclosure_amount / $multiplier;
                $foreclosureRevenue = max(0.00, (float) $loan->foreclosure_amount - $outstanding);
            }
        }

        if ($loan->is_foreclosed && (float) ($loan->foreclosure_interest_amount ?? 0) > 0) {
            $emiAlreadyHasForeclosureInterest = $loan->emis->contains(function ($emi) use ($loan) {
                return $emi->paid_date
                    && $loan->closed_at
                    && $emi->paid_date->isSameDay($loan->closed_at)
                    && (float) $emi->interest_amount > 0;
            });
            if (! $emiAlreadyHasForeclosureInterest) {
                $interestCollected += max(0.00, (float) $loan->foreclosure_interest_amount);
            }
        }

        $penaltyCollected = max(0.00, (float) $loan->emis->sum('penalty_amount'));

        $totalRevenue = max(0.00, $processingFee + $documentCharges + $otherCharges + $interestCollected + $foreclosureRevenue + $penaltyCollected);

        return [
            'processing_fee' => $processingFee,
            'document_charges' => $documentCharges,
            'other_charges' => $otherCharges,
            'interest_collected' => $interestCollected,
            'foreclosure_revenue' => $foreclosureRevenue,
            'penalty_collected' => $penaltyCollected,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * @return array<string, float>
     */

    /**
     * @return array<string, float>
     */
    private function mapFdRevenue(FixedDeposit $fd): array
    {
        $processingFee = max(0.00, (float) ($fd->processing_fee ?? 0));
        $documentCharges = max(0.00, (float) ($fd->document_charges ?? 0));
        $otherCharges = max(0.00, (float) ($fd->other_charges ?? 0));

        $penaltyCollected = max(0.00, (float) $fd->transactions
            ->where('transaction_type', 'premature')
            ->sum('penalty_amount'));

        $totalRevenue = max(0.00, $processingFee + $documentCharges + $otherCharges + $penaltyCollected);

        return [
            'processing_fee' => $processingFee,
            'document_charges' => $documentCharges,
            'other_charges' => $otherCharges,
            'penalty_collected' => $penaltyCollected,
            'total_revenue' => $totalRevenue,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function emptyLoanTotals(): array
    {
        return [
            'processing_fee' => 0.0,
            'document_charges' => 0.0,
            'other_charges' => 0.0,
            'interest_collected' => 0.0,
            'foreclosure_revenue' => 0.0,
            'penalty_collected' => 0.0,
        ];
    }

    /**
     * @return array<string, float>
     */

    /**
     * @return array<string, float>
     */
    private function emptyFdTotals(): array
    {
        return [
            'processing_fee' => 0.0,
            'document_charges' => 0.0,
            'other_charges' => 0.0,
            'penalty_collected' => 0.0,
        ];
    }
}
