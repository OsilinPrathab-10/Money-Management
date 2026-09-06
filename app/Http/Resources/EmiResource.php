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

            'loan_account' => [
                'id'           => $this->loanAccount->id,
                'payment_gateway' => $this->loanAccount?->loanApplication?->payment_gateway,
            ],
        ];
    }
}
