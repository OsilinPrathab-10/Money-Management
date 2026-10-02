<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashSwipeTransaction;
use App\Models\CardCash\CardCashWithdrawalGateway;
use App\Models\CardCash\CreditCardWallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CardCashSwipeService
{
    public function __construct(
        protected CardCashLeadService $leadService,
        protected CreditCardWalletService $walletService
    ) {}

    /**
     * Process a card swipe transaction
     */
    public function processSwipe(
        CardCashLead $lead,
        array $data,
        ?UploadedFile $proofFile = null,
        ?int $userId = null
    ): CardCashSwipeTransaction {
        if ($lead->transaction_type !== 'swipe') {
            throw new InvalidArgumentException("Lead #{$lead->lead_number} is not a Swipe transaction.");
        }

        if (in_array($lead->status, ['completed', 'cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException("Cannot process Swipe for lead in '{$lead->status}' status.");
        }

        $swipeAmount = round((float) ($data['swipe_amount'] ?? $lead->requested_amount), 2);

        $chargesPct = isset($data['charges_percentage']) && $data['charges_percentage'] !== '' && $data['charges_percentage'] !== null
            ? round((float) $data['charges_percentage'], 2)
            : null;

        if (isset($data['charges']) && $data['charges'] !== '' && $data['charges'] !== null) {
            $charges = round((float) $data['charges'], 2);
            if (($chargesPct === null || $chargesPct == 0) && $charges > 0 && $swipeAmount > 0) {
                $chargesPct = round(($charges / $swipeAmount) * 100, 2);
            }
        } elseif ($chargesPct !== null) {
            $charges = round($swipeAmount * ($chargesPct / 100), 2);
        } else {
            $charges = 0.00;
            $chargesPct = 0.00;
        }

        $netAmount = max(0, round($swipeAmount - $charges, 2));

        if ($swipeAmount <= 0) {
            throw new InvalidArgumentException("Swipe amount must be greater than zero.");
        }

        $gatewayId = (int) ($data['withdrawal_gateway_id'] ?? 0);
        $gateway = CardCashWithdrawalGateway::findOrFail($gatewayId);

        $isSplitWallet = !empty($data['is_split_wallet']) && filter_var($data['is_split_wallet'], FILTER_VALIDATE_BOOLEAN);

        return DB::transaction(function () use ($lead, $data, $swipeAmount, $charges, $chargesPct, $netAmount, $gateway, $isSplitWallet, $proofFile, $userId) {
            $walletBreakdown = null;
            $walletId = null;
            $walletDesc = '';

            if ($isSplitWallet && !empty($data['split_wallets']) && is_array($data['split_wallets'])) {
                $allocatedTotal = 0;
                $walletBreakdown = [];

                foreach ($data['split_wallets'] as $wKey => $wData) {
                    $wId = (int) ($wData['wallet_id'] ?? $wKey);
                    $wAmt = round((float) ($wData['amount'] ?? 0), 2);

                    if ($wAmt > 0) {
                        $w = CreditCardWallet::findOrFail($wId);
                        $allocatedTotal = round($allocatedTotal + $wAmt, 2);

                        $description = sprintf(
                            "Swipe debited for Lead #%s (%s - %s) [Split Allocation]",
                            $lead->lead_number,
                            $lead->card_name,
                            $lead->csr_bank_name
                        );

                        $this->walletService->debitWallet(
                            wallet: $w,
                            amount: $wAmt,
                            description: $description,
                            refType: 'card_cash_swipe',
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
                    throw new InvalidArgumentException("Please allocate swipe debit amounts to at least one wallet.");
                }

                if (abs($allocatedTotal - $swipeAmount) > 0.01) {
                    throw new InvalidArgumentException(sprintf(
                        "Total split allocation (₹%s) must match the swipe amount (₹%s).",
                        number_format($allocatedTotal, 2),
                        number_format($swipeAmount, 2)
                    ));
                }

                $walletId = $walletBreakdown[0]['wallet_id'];
                $parts = [];
                foreach ($walletBreakdown as $wb) {
                    $parts[] = "{$wb['wallet_name']} (₹" . number_format($wb['amount'], 2) . ")";
                }
                $walletDesc = "Split Wallets [" . implode(' + ', $parts) . "]";
            } else {
                $walletId = (int) ($data['wallet_id'] ?? 0);
                $wallet = CreditCardWallet::findOrFail($walletId);

                $description = sprintf(
                    "Swipe debited for Lead #%s (%s - %s)",
                    $lead->lead_number,
                    $lead->card_name,
                    $lead->csr_bank_name
                );

                $this->walletService->debitWallet(
                    wallet: $wallet,
                    amount: $swipeAmount,
                    description: $description,
                    refType: 'card_cash_swipe',
                    refId: $lead->id,
                    userId: $userId
                );

                $walletDesc = "wallet '{$wallet->wallet_name}'";
                $walletBreakdown = [
                    [
                        'wallet_id' => $wallet->id,
                        'wallet_name' => $wallet->wallet_name,
                        'amount' => $swipeAmount,
                    ]
                ];
            }

            // Upload screenshot proof if present
            $proofPath = null;
            if ($proofFile && $proofFile->isValid()) {
                $directory = 'card-cash/swipes';
                Storage::disk('public')->makeDirectory($directory);
                $proofPath = $proofFile->store($directory, 'public');
            }

            // Create swipe transaction record
            $swipe = CardCashSwipeTransaction::create([
                'lead_id' => $lead->id,
                'customer_id' => $lead->credit_card_customer_id,
                'withdrawal_gateway_id' => $gateway->id,
                'wallet_id' => $walletId,
                'is_split_wallet' => $isSplitWallet,
                'wallet_split_breakdown' => $walletBreakdown,
                'swipe_amount' => $swipeAmount,
                'charges' => $charges,
                'charges_percentage' => $chargesPct ?: 0.00,
                'net_amount' => $netAmount,
                'gateway_reference' => $data['gateway_reference'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'status' => 'success',
                'screenshot_proof' => $proofPath,
                'processed_by' => $userId,
                'processed_at' => now(),
                'remarks' => $data['remarks'] ?? null,
            ]);

            // Update lead status
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'payment_success',
                description: sprintf(
                    "Swipe of ₹%s (Fee: ₹%s%s) processed via %s with %s (Ref: %s)",
                    number_format($swipeAmount, 2),
                    number_format($charges, 2),
                    $chargesPct > 0 ? " [{$chargesPct}%]" : "",
                    $gateway->gateway_name,
                    $walletDesc,
                    $swipe->transaction_reference ?: 'N/A'
                ),
                userId: $userId,
                metadata: [
                    'swipe_transaction_id' => $swipe->id,
                    'gateway' => $gateway->gateway_name,
                    'wallet_id' => $walletId,
                    'is_split_wallet' => $isSplitWallet,
                    'wallet_breakdown' => $walletBreakdown,
                    'swipe_amount' => $swipeAmount,
                    'charges' => $charges,
                    'charges_percentage' => $chargesPct,
                    'net_amount' => $netAmount,
                ]
            );

            // Move to return_pending
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'return_pending',
                description: "Moved to Return Settlement queue. Awaiting customer return processing.",
                userId: $userId
            );

            return $swipe;
        });
    }
}
