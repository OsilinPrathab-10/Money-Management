<?php

namespace App\Http\Resources;

use App\Support\CollectedDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class LoanHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

      $emis = $this->emis;

        $totalEmis = $emis->count();
        $paidEmis = $emis->where('status', 'paid')->count();
        $totalEmiPaidAmount = $emis->sum('paid_amount');
        $nextEmi = $emis
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('instalment_number')
            ->first();

        $nextDueDate = optional($nextEmi)->due_date;
        $totalPayable = (float) $this->total_payable;
        $totalPaid = (float) $this->paid_amount;

        $percentageComplete = $totalPayable > 0
            ? round(($totalPaid / $totalPayable) * 100, 2)
            : 0;
        $emiAmount = (float) ($this->emi_amount ?? optional($emis->first())->total_amount ?? 0);

        // Calculate only min and max prepayment amounts for frontend input
        $outstandingAmount = round($this->outstanding_amount, 2);

        $lastPaidEmi = $emis->where('status', 'paid')->sortByDesc('paid_date')->first();
        $fromDate = $lastPaidEmi ? Carbon::parse($lastPaidEmi->paid_date) : Carbon::parse($this->disbursed_at);
        $days = $fromDate->diffInDays(now());

        $annualRate = (float) $this->interest_rate;
        $dailyRate = $annualRate / 100 / 365;
        $interestOutstanding = round($outstandingAmount * $dailyRate * $days, 2);

        $prepaymentChargesPercentage = $this->getPrepaymentChargesPercentage();
        $prepaymentCharges = round(($outstandingAmount * $prepaymentChargesPercentage) / 100, 2);

        $prepaymentTotal = round($outstandingAmount + $interestOutstanding + $prepaymentCharges, 2);

        $minPrepaymentAmount = round((float) ($this->emi_amount ?? $emiAmount ?? 0), 2);
        $maxPrepaymentAmount = $prepaymentTotal;

        $encodedAccountId = \App\Support\HashId::encode($this->id);
        $statementUrl = url('/loan/statement/' . ($encodedAccountId ?: $this->id));
        $adminStatementUrl = url('/emi/statement/print/' . ($encodedAccountId ?: $this->id));

        $application = $this->loanApplication;
        $disbursement = $application?->disbursementDetail;
        $collectedDocuments = CollectedDocuments::forLoanDisbursement($disbursement, $application);
        $generatedDocuments = $this->relationLoaded('clientLoanDocuments')
            ? $this->clientLoanDocuments
            : collect();
        $generatedDocuments = $generatedDocuments
            ->filter(fn ($doc) => $doc->isVisible())
            ->map(function ($doc) {
                $url = $doc->file_url;
                return [
                    'type' => $doc->document_type,
                    'title' => $doc->document_title ?: ucfirst(str_replace('_', ' ', (string) $doc->document_type)),
                    'file_name' => $doc->file_name,
                    'file_path' => $doc->file_path,
                    'file_url' => $url,
                    'url' => $url,
                ];
            })
            ->values()
            ->all();
        $collateralDoc = collect($collectedDocuments)->firstWhere('type', 'collateral_document');
        $otherDoc = collect($collectedDocuments)->firstWhere('type', 'other_document');
        $paymentMode = $disbursement
            ? ((strtoupper((string) $disbursement->bank_name) === 'CASH' || strtoupper((string) $disbursement->bank_account_number) === 'OFFLINE')
                ? 'cash'
                : 'bank_transfer')
            : null;

        return [
            'id' => $this->id,
            'account_number' => $this->account_number,
            'application_number' => $this->application_number,
            'loan_code' => $this->loan_code,
            'loan_type' => $this->loanApplication?->product?->loan_name,
            'loan_amount' => number_format($this->loan_amount, 0),
            'interest_rate' => number_format($this->interest_rate, 2) . '%',
            'tenure' => $this->tenure . ' months',
            'emi_day' => $this->emi_day,
            'payment_method' => ucfirst($this->payment_method),
            'payment_gateway' => ucfirst($this->loanApplication?->payment_gateway),
            'total_payable' => number_format($this->total_payable, 2),
            'paid_amount' => number_format($this->paid_amount, 2),
            'outstanding_amount' => number_format($this->outstanding_amount, 2),
            'status' => ucfirst($this->status),
            'disbursed_at' => optional($this->disbursed_at)->format('d-m-Y'),
            'closed_at' => optional($this->closed_at)->format('d-m-Y'),
            'statement_url' => $statementUrl,
            'statement_view_url' => $statementUrl,
            'statement_download_url' => $statementUrl,
            'admin_statement_url' => $adminStatementUrl,
            'disbursement_details' => [
                'disbursement_amount' => (float) ($disbursement?->disbursement_amount ?? $this->disbursed_amount),
                'loan_amount' => (float) $this->loan_amount,
                'disbursed_at' => optional($disbursement?->disburse_at ?? $this->disbursed_at)?->format('Y-m-d'),
                'transaction_id' => $disbursement?->transaction_id ?? $this->transaction_id,
                'utr_number' => $disbursement?->utr_number ?? $this->utr_number,
                'payment_mode' => $paymentMode,
                'payment_mode_label' => $paymentMode === 'cash' ? 'Cash' : ($paymentMode === 'bank_transfer' ? 'Bank Transfer' : null),
                'bank_name' => $disbursement?->bank_name,
                'account_number' => $disbursement?->bank_account_number,
                'ifsc_code' => $disbursement?->ifsc_code,
                'holder_name' => $disbursement?->holder_name,
                'account_type' => $disbursement?->account_type,
                'live_photo_url' => CollectedDocuments::url($application?->live_photo),
                'cash_photo_url' => CollectedDocuments::url($application?->cash_photo),
                'collateral_document_url' => $collateralDoc['file_url'] ?? null,
                'other_document_url' => $otherDoc['file_url'] ?? null,
                'documents' => $collectedDocuments,
            ],
            'documents' => array_values(array_merge($collectedDocuments, $generatedDocuments)),
            'collateral_documents' => $collectedDocuments,
            'emis' => EmiResource::collection($this->whenLoaded('emis')),
            "summary" => [
                "percentage_complete" => $percentageComplete ?? null,
                "total_emi_paid" => (float) $totalEmiPaidAmount ?? null,
                "emi_paid_count" => $paidEmis ?? null,
                "total_emis" => $totalEmis ?? null,
                "emi_amount" => $emiAmount ?? null,
                "next_due_date" => $nextDueDate ?? null,
                "statement_url" => $statementUrl,
                "statement_view_url" => $statementUrl,
                "statement_download_url" => $statementUrl,
                "admin_statement_url" => $adminStatementUrl,
                "prepayment" => [
                    "min_amount" => $minPrepaymentAmount,
                    "max_amount" => $maxPrepaymentAmount,
                ],
            ],
        ];
    }
}
