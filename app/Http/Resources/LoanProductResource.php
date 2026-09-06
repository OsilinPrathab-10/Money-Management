<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->query('view') === 'summary') {
            return [
                'id' => $this->id,
                'loan_name' => $this->loan_name,
                'credit_limit' => $this->loan_amount_max,
                'loan_type' => $this->loanType->name ?? null,
                'interest_rate' => $this->interest_rate,
                'loan_type_icon' => $this->loan_type_icon_url,
                'loan_type_image' => $this->loan_type_image_url,
                'loan_type_banner' => $this->loan_type_banner_url,
            ];
        }

        // Full detail view
        return [
            'id' => $this->id,
            'loan_name' => $this->loan_name,
            'loan_type' => [
                'name' => $this->loanType->name ?? null,
                'icon' => $this->loan_type_icon_url,   
                'image' => $this->loan_type_image_url,
                'banner' => $this->loan_type_banner_url,
            ],
            'loan_code' => $this->loan_code,
            'credit_limit' => $this->loan_amount_max,
            'interest_rate' => $this->interest_rate,
            'interest_type' => $this->interest_type,
            'term_unit' => $this->term_unit,
            'min_tenture' => $this->min_tenture,
            'max_tenture' => $this->max_tenture,
            'processing_fee' => $this->processing_fee,
            'require_collateral' => (bool) $this->require_collateral,
            'default_term' => $this->default_term,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
