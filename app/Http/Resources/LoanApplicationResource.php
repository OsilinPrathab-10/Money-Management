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
        $rawStatus = (string) ($this->status ?? 'pending');
        $displayStatus = strtolower($rawStatus) === 'applied' ? 'pending' : $rawStatus;

        return [
            'id' => $this->id,
            'loan_code' => $this->loan_code,
            'loan_name' => optional($this->product)->loan_name,
            'application_number' => $this->application_number,
            'status' => $displayStatus,
            'status_label' => $this->status_label,
            'status_badge' => $this->status_badge,
            'remarks' => $this->remarks,
            'loan_amount_min' => $this->loan_amount_min,
            'loan_amount' => $this->loan_amount,
            'interest_rate' => $this->interest_rate,
            'tenure_min' => $this->tenure_min,
            'tenure_max' => $this->tenure_max,
            'terms&condition' => optional($this->product)->description,
            'processing_fee' => optional($this->product)->processing_fee,
            'applied_date' => optional($this->applied_at ?? $this->created_at)?->toDateString(),

            'disbursed' => [
                'date' => optional($this->loanAccount)->disbursed_at,
                'amount' => optional($this->loanAccount)->disbursed_amount,
                'ifsc_code' => optional(optional($this->client)->kycDetail)->ifsc_code,
                'account_number' => optional(optional($this->client)->kycDetail)->account_number,
                'transaction_id' => optional($this->loanAccount)->transaction_id,
                'processing_fee' => optional($this->product)->processing_fee,
                'document_charges' => optional($this->product)->document_charges,
                'other_charges' => optional($this->product)->other_charges,
            ]
        ];
    }
}
