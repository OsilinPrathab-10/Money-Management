<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CardCashBillPayment;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CardCashPaymentSource;
use App\Models\CardCash\CreditCardWallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CardCashBillPaymentService
{
    public function __construct(
        protected CardCashLeadService $leadService,
        protected CardCashNotificationService $notificationService,
        protected CreditCardWalletService $walletService
    ) {}

    /**
     * Process a bill payment for a lead
     */
    public function processBillPayment(
        CardCashLead $lead,
        array $data,
        ?UploadedFile $proofFile = null,
        ?int $userId = null
    ): CardCashBillPayment {
        if ($lead->transaction_type !== 'bill_payment') {
            throw new InvalidArgumentException("Lead #{$lead->lead_number} is not a Bill Payment transaction.");
        }

        if (in_array($lead->status, ['completed', 'cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException("Cannot process Bill Payment for lead in '{$lead->status}' status.");
        }

        return DB::transaction(function () use ($lead, $data, $proofFile, $userId) {
            $amount = round((float) ($data['amount'] ?? $lead->requested_amount), 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException("Bill payment amount must be greater than zero.");
            }

            // Upload proof screenshot if present
            $proofPath = null;
            if ($proofFile && $proofFile->isValid()) {
                $directory = 'card-cash/bill-payments';
                Storage::disk('public')->makeDirectory($directory);
                $proofPath = $proofFile->store($directory, 'public');
            }

            $sourceId = !empty($data['payment_source_id']) ? (int) $data['payment_source_id'] : null;
            $sourceName = 'Default Gateway';
            if ($sourceId) {
                $source = CardCashPaymentSource::find($sourceId);
                if ($source) {
                    $sourceName = $source->source_name;
                }
            }

            // Wallet Debiting: Single or Multiple (Split) Wallets
            $isSplitWallet = !empty($data['is_split_wallet']) && filter_var($data['is_split_wallet'], FILTER_VALIDATE_BOOLEAN);
            $walletId = !empty($data['wallet_id']) ? (int) $data['wallet_id'] : null;
            $walletBreakdown = [];
            $sourceDesc = $sourceName;

            if ($isSplitWallet && !empty($data['split_wallets']) && is_array($data['split_wallets'])) {
                // Filter allocations with positive amounts
                $allocatedTotal = 0;
                foreach ($data['split_wallets'] as $wKey => $wData) {
                    $wId = (int) ($wData['wallet_id'] ?? $wKey);
                    $wAmt = round((float) ($wData['amount'] ?? 0), 2);

                    if ($wAmt > 0) {
                        $w = CreditCardWallet::findOrFail($wId);
                        $allocatedTotal = round($allocatedTotal + $wAmt, 2);

                        // Debit wallet atomically
                        $description = sprintf(
                            "Bill Payment for Lead #%s (%s - %s) [Split Allocation]",
                            $lead->lead_number,
                            $lead->card_name,
                            $lead->csr_bank_name
                        );

                        $this->walletService->debitWallet(
                            wallet: $w,
                            amount: $wAmt,
                            description: $description,
                            refType: 'card_cash_bill_payment',
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
                    throw new InvalidArgumentException("Please allocate payment amounts to at least one wallet.");
                }

                if (abs($allocatedTotal - $amount) > 0.01) {
                    throw new InvalidArgumentException(sprintf(
                        "Total split allocation (₹%s) must match the total bill payment amount (₹%s).",
                        number_format($allocatedTotal, 2),
                        number_format($amount, 2)
                    ));
                }

                $walletId = $walletBreakdown[0]['wallet_id'];
                $parts = [];
                foreach ($walletBreakdown as $wb) {
                    $parts[] = "{$wb['wallet_name']} (₹" . number_format($wb['amount'], 2) . ")";
                }
                $sourceDesc = "Split Wallets [" . implode(' + ', $parts) . "]";
            } elseif ($walletId) {
                // Single wallet debit
                $wallet = CreditCardWallet::findOrFail($walletId);
                $description = sprintf(
                    "Bill Payment for Lead #%s (%s - %s)",
                    $lead->lead_number,
                    $lead->card_name,
                    $lead->csr_bank_name
                );

                $this->walletService->debitWallet(
                    wallet: $wallet,
                    amount: $amount,
                    description: $description,
                    refType: 'card_cash_bill_payment',
                    refId: $lead->id,
                    userId: $userId
                );

                $walletBreakdown = [
                    [
                        'wallet_id' => $wallet->id,
                        'wallet_name' => $wallet->wallet_name,
                        'amount' => $amount,
                    ]
                ];
                $sourceDesc = "Wallet '{$wallet->wallet_name}'";
                if ($sourceId && $sourceName && $sourceName !== 'Default Gateway') {
                    $sourceDesc .= " · {$sourceName}";
                }
            }

            // Create bill payment record
            $payment = CardCashBillPayment::create([
                'lead_id' => $lead->id,
                'customer_id' => $lead->credit_card_customer_id,
                'payment_source_id' => $sourceId,
                'wallet_id' => $walletId,
                'is_split_wallet' => $isSplitWallet,
                'wallet_split_breakdown' => !empty($walletBreakdown) ? $walletBreakdown : null,
                'amount' => $amount,
                'gateway_reference' => $data['gateway_reference'] ?? null,
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_date' => !empty($data['payment_date']) ? $data['payment_date'] : now(),
                'status' => 'payment_success',
                'screenshot_proof' => $proofPath,
                'remarks' => $data['remarks'] ?? null,
                'processed_by' => $userId,
            ]);

            // Transition lead: payment_success -> return_pending
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'payment_success',
                description: sprintf(
                    "Bill Payment of ₹%s processed successfully via %s (Ref: %s)",
                    number_format($amount, 2),
                    $sourceDesc,
                    $payment->transaction_reference ?: 'N/A'
                ),
                userId: $userId,
                metadata: [
                    'bill_payment_id' => $payment->id,
                    'amount' => $amount,
                    'is_split_wallet' => $isSplitWallet,
                    'wallet_breakdown' => $walletBreakdown,
                    'wallet_id' => $walletId,
                    'source' => $sourceDesc,
                    'gateway_reference' => $payment->gateway_reference,
                    'transaction_reference' => $payment->transaction_reference,
                    'proof_path' => $proofPath,
                ]
            );

            // Automatically move to return_pending
            $this->leadService->changeStatus(
                lead: $lead,
                newStatus: 'return_pending',
                description: "Moved to Return Settlement queue. Awaiting customer return processing.",
                userId: $userId
            );

            return $payment;
        });
    }

    /**
     * Upload / replace payment proof screenshot
     */
    public function uploadProof(CardCashBillPayment $payment, UploadedFile $file, ?int $userId = null): string
    {
        $directory = 'card-cash/bill-payments';
        Storage::disk('public')->makeDirectory($directory);
        
        // Remove old proof if exists
        if ($payment->screenshot_proof && Storage::disk('public')->exists($payment->screenshot_proof)) {
            Storage::disk('public')->delete($payment->screenshot_proof);
        }

        $path = $file->store($directory, 'public');
        $payment->screenshot_proof = $path;
        $payment->save();

        $this->leadService->logActivity(
            leadId: $payment->lead_id,
            action: 'screenshot_uploaded',
            description: "Bill payment proof screenshot uploaded",
            userId: $userId,
            metadata: ['path' => $path]
        );

        return $path;
    }
}
