<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\LoanConfiguration;

class EmiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $encodedEmiId = \App\Support\HashId::encode($this->id);
        $receiptUrl = url('/emi/receipt/' . ($encodedEmiId ?: $this->id));
        $adminReceiptUrl = url('/emi/receipts/view/' . ($encodedEmiId ?: $this->id));
        $receiptPrintUrl = url('/emi/receipts/print/' . ($encodedEmiId ?: $this->id));
        $receiptApiUrl = url('/api/loans/emi/' . $this->id . '/receipt');

        $encodedAccountId = $this->loanAccount ? \App\Support\HashId::encode($this->loanAccount->id) : null;
        $statementUrl = $encodedAccountId ? url('/loan/statement/' . $encodedAccountId) : ($this->loan_account_id ? url('/loan/statement/' . $this->loan_account_id) : null);
        $adminStatementUrl = $encodedAccountId ? url('/emi/statement/print/' . $encodedAccountId) : ($this->loan_account_id ? url('/emi/statement/print/' . $this->loan_account_id) : null);

        return [
            'id' => $this->id,
            'instalment_number' => $this->instalment_number,
            'principal_amount' => number_format($this->principal_amount, 2),
            'interest_amount' => number_format($this->interest_amount, 2),
            'total_amount' => number_format($this->total_amount, 2),
            'due_date' => $this->due_date ? $this->due_date->format('d-m-Y') : null,
            'paid_date' => $this->paid_date ? $this->paid_date->format('d-m-Y') : null,
            'penalty_amount' => number_format($this->penalty_amount, 2),
            'partial_paid_amount' => $this->partial_paid_amount ? number_format($this->partial_paid_amount, 2) : null,
            'partial_paid_date' => $this->partial_paid_date ? $this->partial_paid_date->format('d-m-Y') : null,
            'is_partial_payment_active' => LoanConfiguration::getPartialPaymentConfig()?->is_active ?? false,
            'status' => ucfirst($this->status),
            'payment_id' => $this->payment_reference,
            'receipt_url' => $receiptUrl,
            'receipt_view_url' => $receiptUrl,
            'admin_receipt_url' => $adminReceiptUrl,
            'receipt_print_url' => $receiptPrintUrl,
            'receipt_api_url' => $receiptApiUrl,

            'loan_account' => [
                'id'           => $this->loanAccount?->id,
                'payment_gateway' => $this->loanAccount?->loanApplication?->payment_gateway,
                'statement_url' => $statementUrl,
                'statement_view_url' => $statementUrl,
                'statement_download_url' => $statementUrl,
                'admin_statement_url' => $adminStatementUrl,
            ],
        ];
    }
}
