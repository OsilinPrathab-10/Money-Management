<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashReturn;
use App\Models\CardCash\CardCashSetting;
use App\Models\CardCash\CreditCardWallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CardCashReturnService
{
    public function __construct(
        protected CardCashLeadService $leadService,
        protected CreditCardWalletService $walletService
    ) {}

    /**
     * Calculate return breakdown based on configurable percentage and split channels
     */
    public function calculateReturn(
        float $grossAmount,
        string $returnMethod = 'card',
        ?float $customPercentage = null,
        bool $isSplit = false,
        ?float $cardPct = null,
        ?float $cashPct = null,
        ?float $cardAmount = null,
        ?float $cashAmount = null
    ): array {
        $percentage = $customPercentage !== null
            ? $customPercentage
            : (float) CardCashSetting::get('card_return_percentage', 99.00);

        if ($percentage <= 0 || $percentage > 100) {
            $percentage = 99.00;
        }

        $returnAmount = round($grossAmount * ($percentage / 100), 2);
        $charges = round($grossAmount - $returnAmount, 2);

        $isSplitActive = $isSplit || $returnMethod === 'split';

        if ($isSplitActive) {
            if ($cardAmount !== null || $cashAmount !== null) {
                $cardAmount = round((float) ($cardAmount ?? 0), 2);
                $cashAmount = $cashAmount !== null
                    ? round((float) $cashAmount, 2)
                    : round($returnAmount - $cardAmount, 2);
            } else {
                $cardShare = $cardPct !== null ? ((float) $cardPct / 100) : 0.5;
                $cardAmount = round($returnAmount * $cardShare, 2);
                $cashAmount = round($returnAmount - $cardAmount, 2);
            }
            $cardPercentage = $returnAmount > 0 ? round(($cardAmount / $returnAmount) * 100, 2) : 0.00;
            $cashPercentage = $returnAmount > 0 ? round(($cashAmount / $returnAmount) * 100, 2) : 0.00;
            $summary = 'Card/Wallet: ₹' . number_format($cardAmount, 2) . ' | Cash: ₹' . number_format($cashAmount, 2);
        } else {
            if ($returnMethod === 'card') {
                $cardPercentage = 100.00;
                $cardAmount = $returnAmount;
                $cashPercentage = 0.00;
                $cashAmount = 0.00;
                $summary = "Card: ₹" . number_format($returnAmount, 2);
            } elseif ($returnMethod === 'wallet') {
                $cardPercentage = 100.00;
                $cardAmount = $returnAmount;
                $cashPercentage = 0.00;
                $cashAmount = 0.00;
                $summary = "Wallet: ₹" . number_format($returnAmount, 2);
            } else {
                $cardPercentage = 0.00;
                $cardAmount = 0.00;
                $cashPercentage = 100.00;
                $cashAmount = $returnAmount;
                $methodLabel = strtoupper($returnMethod === 'other' ? 'Cash' : $returnMethod);
                $summary = "{$methodLabel}: ₹" . number_format($returnAmount, 2);
            }
        }

        return [
            'gross_amount' => $grossAmount,
            'return_percentage' => $percentage,
            'charges' => $charges,
            'return_amount' => $returnAmount,
            'return_method' => $returnMethod,
            'is_split' => $isSplitActive,
            'card_percentage' => $cardPercentage,
            'card_amount' => $cardAmount,
            'cash_percentage' => $cashPercentage,
            'cash_amount' => $cashAmount,
            'settlement_summary' => $summary,
            'split_breakdown' => [
                'card' => ['amount' => $cardAmount],
                'cash' => ['amount' => $cashAmount],
            ],
        ];
    }

    /**
     * Process return / settlement to customer
     */
    public function processReturn(
        CardCashLead $lead,
        array $data,
        ?UploadedFile $proofFile = null,
        ?int $userId = null
    ): CardCashReturn {
        if (!in_array($lead->status, ['return_pending', 'payment_success'], true)) {
            throw new InvalidArgumentException("Lead #{$lead->lead_number} is not ready for Return processing (Current status: {$lead->status}).");
        }

        return DB::transaction(function () use ($lead, $data, $proofFile, $userId) {
            $method = $data['return_method'] ?? 'card';
            $isSplit = !empty($data['is_split']) || $method === 'split';

            // Calculate amounts
            $grossAmount = (float) ($data['gross_amount'] ?? $lead->requested_amount);
            $customPct = isset($data['return_percentage']) ? (float) $data['return_percentage'] : null;
            $cardAmtIn = isset($data['card_amount']) ? (float) $data['card_amount'] : null;
            $cashAmtIn = isset($data['cash_amount']) ? (float) $data['cash_amount'] : null;

            $calc = $this->calculateReturn(
                $grossAmount,
                $method,
                $customPct,
                $isSplit,
                null,
                null,
                $cardAmtIn,
                $cashAmtIn
            );

            $returnAmount = isset($data['return_amount']) ? (float) $data['return_amount'] : $calc['return_amount'];
            $charges = isset($data['charges']) ? (float) $data['charges'] : $calc['charges'];
            $returnPercentage = $calc['return_percentage'];

            // Split breakdown details
            $finalIsSplit = $calc['is_split'];
            $finalCardAmt = $cardAmtIn !== null && $finalIsSplit ? round($cardAmtIn, 2) : $calc['card_amount'];
            $finalCashAmt = $cashAmtIn !== null && $finalIsSplit ? round($cashAmtIn, 2) : $calc['cash_amount'];
            if ($finalIsSplit && abs(($finalCardAmt + $finalCashAmt) - $returnAmount) > 0.01) {
                throw new InvalidArgumentException(sprintf(
                    'Card/Wallet (₹%s) and Cash (₹%s) must add up to the total return of ₹%s.',
                    number_format($finalCardAmt, 2),
                    number_format($finalCashAmt, 2),
                    number_format($returnAmount, 2)
                ));
            }
            $finalCardPct = $returnAmount > 0 ? round(($finalCardAmt / $returnAmount) * 100, 2) : 0.00;
            $finalCashPct = $returnAmount > 0 ? round(($finalCashAmt / $returnAmount) * 100, 2) : 0.00;
            $splitBreakdown = [
                'card' => ['amount' => $finalCardAmt],
                'cash' => ['amount' => $finalCashAmt],
            ];

            // Multi-Wallet Split or Single Wallet processing
            $isSplitWallet = !empty($data['is_split_wallet']) && filter_var($data['is_split_wallet'], FILTER_VALIDATE_BOOLEAN);
            $walletId = !empty($data['wallet_id']) ? (int) $data['wallet_id'] : null;
            $walletBreakdown = null;

            // When return method is 'wallet' or when split wallets is enabled
            if ($isSplitWallet && !empty($data['split_wallets']) && is_array($data['split_wallets'])) {
                $allocatedTotal = 0;
                $walletBreakdown = [];

                foreach ($data['split_wallets'] as $wKey => $wData) {
                    $wId = (int) ($wData['wallet_id'] ?? $wKey);
                    $wAmt = round((float) ($wData['amount'] ?? 0), 2);

                    if ($wAmt > 0) {
                        $w = CreditCardWallet::findOrFail($wId);
                        $allocatedTotal = round($allocatedTotal + $wAmt, 2);

                        // Atomically debit wallet for customer return settlement
                        $description = sprintf(
                            "Return Settlement for Lead #%s (%s - %s) [Split Allocation]",
                            $lead->lead_number,
                            $lead->card_name,
                            $lead->csr_bank_name
                        );

                        $this->walletService->debitWallet(
                            wallet: $w,
                            amount: $wAmt,
                            description: $description,
                            refType: 'card_cash_return',
                            refId: $lead->id,
                            userId: $userId
                        );

                        $walletBreakdown[] = [
                            'wallet_id' => $w->id,
                            'wallet_name' => $w->wallet_name,
                            'amount' => $wAmt,
                        ];
                    }
                }

                if (empty($walletBreakdown)) {
                    throw new InvalidArgumentException("Please allocate settlement amounts to at least one wallet.");
                }

                $targetWalletAmt = $finalIsSplit ? $finalCardAmt : $returnAmount;
                if (abs($allocatedTotal - $targetWalletAmt) > 0.01) {
                    throw new InvalidArgumentException(sprintf(
                        "Total split allocation (₹%s) must match the return settlement amount (₹%s).",
                        number_format($allocatedTotal, 2),
                        number_format($targetWalletAmt, 2)
                    ));
                }

                $walletId = $walletBreakdown[0]['wallet_id'];
            } elseif ($walletId && ($method === 'wallet' || ($finalIsSplit && $finalCardAmt > 0))) {
                // Single wallet debit for return
                $w = CreditCardWallet::findOrFail($walletId);
                $singleDebitAmt = $finalIsSplit ? $finalCardAmt : $returnAmount;

                $description = sprintf(
                    "Return Settlement for Lead #%s (%s - %s)",
                    $lead->lead_number,
                    $lead->card_name,
                    $lead->csr_bank_name
                );

                $this->walletService->debitWallet(
                    wallet: $w,
                    amount: $singleDebitAmt,
                    description: $description,
                    refType: 'card_cash_return',
                    refId: $lead->id,
                    userId: $userId
                );

                $walletBreakdown = [
                    [
                        'wallet_id' => $w->id,
                        'wallet_name' => $w->wallet_name,
                        'amount' => $singleDebitAmt,
                    ]
                ];
            }

            // Store proof if provided
            $proofPath = null;
            if ($proofFile && $proofFile->isValid()) {
                $directory = 'card-cash/returns';
                Storage::disk('public')->makeDirectory($directory);
                $proofPath = $proofFile->store($directory, 'public');
            }

            // Create return record
            $return = CardCashReturn::create([
                'lead_id' => $lead->id,
                'customer_id' => $lead->credit_card_customer_id,
                'return_method' => $method === 'split' ? ($walletId ? 'wallet' : 'card') : $method,
                'wallet_id' => $walletId,
                'is_split_wallet' => $isSplitWallet,
                'wallet_split_breakdown' => $walletBreakdown,
                'is_split' => $finalIsSplit,
                'card_percentage' => $finalCardPct,
                'card_amount' => $finalCardAmt,
                'cash_percentage' => $finalCashPct,
                'cash_amount' => $finalCashAmt,
                'split_breakdown' => $splitBreakdown,
                'return_percentage' => $returnPercentage,
                'gross_amount' => $grossAmount,
                'charges' => $charges,
                'return_amount' => $returnAmount,
                'payment_reference' => $data['payment_reference'] ?? null,
                'upi_id' => $data['upi_id'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'account_holder_name' => $data['account_holder_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'ifsc_code' => $data['ifsc_code'] ?? null,
                'status' => 'success',
                'payment_proof' => $proofPath,
                'processed_by' => $userId,
                'processed_at' => now(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            $summaryDesc = $return->settlement_summary;

            // Transition lead: return_processed -> completed
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'return_processed',
                description: sprintf(
                    "Customer return of ₹%s (%s%%) settled [%s] (Ref: %s)",
                    number_format($returnAmount, 2),
                    $returnPercentage,
                    $summaryDesc,
                    $return->payment_reference ?: 'N/A'
                ),
                userId: $userId,
                metadata: [
                    'return_id' => $return->id,
                    'is_split' => $finalIsSplit,
                    'is_split_wallet' => $isSplitWallet,
                    'wallet_id' => $walletId,
                    'wallet_breakdown' => $walletBreakdown,
                    'summary' => $summaryDesc,
                    'return_amount' => $returnAmount,
                    'charges' => $charges,
                ]
            );

            // Complete lead
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'completed',
                description: "Card to Cash cycle successfully completed.",
                userId: $userId
            );

            return $return;
        });
    }
}
