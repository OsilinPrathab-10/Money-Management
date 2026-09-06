<?php

namespace App\Http\Resources;

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
            'emis' => EmiResource::collection($this->whenLoaded('emis')),
            "summary" => [
                "percentage_complete" => $percentageComplete ?? null,
                "total_emi_paid" => (float) $totalEmiPaidAmount ?? null,
                "emi_paid_count" => $paidEmis ?? null,
                "total_emis" => $totalEmis ?? null,
                "emi_amount" => $emiAmount ?? null,
                    "next_due_date" => $nextDueDate ?? null,
                    "prepayment" => [
                        "min_amount" => $minPrepaymentAmount,
                        "max_amount" => $maxPrepaymentAmount,
                    ],
            ],
        ];
    }
}
