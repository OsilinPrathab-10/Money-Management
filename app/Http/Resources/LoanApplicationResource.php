<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_code' => $this->loan_code,
            'loan_name' => $this->product->loan_name,
            'application_number' => $this->application_number,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'loan_amount_min' => $this->loan_amount_min,
            'loan_amount' => $this->loan_amount,
            'interest_rate' => $this->interest_rate,
            'tenure_min' => $this->tenure_min,
            'tenure_max' => $this->tenure_max,
            'terms&condition' => $this->product->description,
            'processing_fee' => $this->product->processing_fee,
            'applied_date' => $this->created_at->toDateString(),

            'disbursed' => [
                'date' => $this->loanAccount->disbursed_at ?? null,
                'amount' => $this->loanAccount->disbursed_amount ?? null,
                'ifsc_code' => $this->client->kycDetail->ifsc_code ?? null,
                'account_number' => $this->client->kycDetail->account_number ?? null,
                'transaction_id' => $this->loanAccount->transaction_id ?? null,
                'processing_fee' => $this->product->processing_fee ?? null,
                'document_charges' => $this->product->document_charges ?? null,
                'other_charges' => $this->product->other_charges ?? null,
            ]
        ];
    }
}
