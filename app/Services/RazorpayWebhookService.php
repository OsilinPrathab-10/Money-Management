<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Razorpay\Api\Api;
use Razorpay\Api\Utility;
use Razorpay\Api\Errors\SignatureVerificationError;
use App\Models\Payment;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\EmiAgentAssignment;
use App\Models\PaymentGateway;

class RazorpayWebhookService
{
    protected string $webhookSecret;
    protected array $authorization; // Added based on the provided code edit

    public function __construct()
    {
        $this->webhookSecret = trim(env('RAZORPAY_WEBHOOK_SECRET'));

        if (empty($this->webhookSecret)) {
            Log::critical('[RAZORPAY] Webhook secret missing in .env');
        }
    }

    /**
     * Main webhook handler
     */
    public function handleWebhook(array $payload, ?string $signature, string $rawContent)
    {
        Log::info('================ RAZORPAY WEBHOOK START ================');

        if (empty($signature)) {
            Log::warning('[RAZORPAY] Signature missing');
            return ['status' => 400, 'message' => 'Signature missing'];
        }

        if (empty($rawContent)) {
            Log::warning('[RAZORPAY] Empty payload');
            return ['status' => 400, 'message' => 'Empty payload'];
        }

        // [DEBUG] Log details BEFORE verification to catch the failure cause
        Log::info('[DEBUG] VERIFICATION DETAILS', [
            'signature_header' => substr($signature, 0, 10) . '...',
            'content_length' => strlen($rawContent),
            'secret_length' => strlen($this->webhookSecret),
            'content_preview' => substr($rawContent, 0, 100)
        ]);

        // ✅ Correct SDK signature verification
        try {
            (new Utility())->verifyWebhookSignature(
                $rawContent,
                $signature,
                $this->webhookSecret
            );
        } catch (SignatureVerificationError $e) {
            Log::error('[RAZORPAY] Invalid signature', [
                'error' => $e->getMessage()
            ]);

            return ['status' => 400, 'message' => 'Invalid signature'];
        }

        Log::info('[RAZORPAY] Signature verified');

        $event = $payload['event'] ?? null;

        if (!$event) {
            Log::warning('[RAZORPAY] Event missing');
            return ['status' => 400, 'message' => 'Event missing'];
        }

        Log::info('[RAZORPAY] Event: ' . $event);

        try {
            return match ($event) {
                'payment.captured' => $this->handlePaymentCaptured($payload),
                'payment_link.paid' => $this->handlePaymentLinkPaid($payload),
                'payment.failed' => $this->handlePaymentFailed($payload),
                default => $this->ignoreEvent($event),
            };
        } catch (\Throwable $e) {
            Log::error('[RAZORPAY] Processing error', [
                'event' => $event,
                'error' => $e->getMessage()
            ]);

            return ['status' => 500, 'message' => 'Server error'];
        }
    }

    /**
     * payment.captured
     */
    protected function handlePaymentCaptured(array $payload)
    {
        Log::info('===================================================================');
        Log::info('[START] PAYMENT.CAPTURED EVENT - START PROCESSING');

        $entity = $payload['payload']['payment']['entity'] ?? null;
        if (!$entity)
            return ['status' => 200, 'message' => 'No entity'];

        $razorpayOrderId = $entity['order_id'] ?? null;
        $razorpayPaymentId = $entity['id'];
        $amount = ($entity['amount'] ?? 0) / 100;
        $status = $entity['status'] ?? 'unknown';

        Log::info('[DETAILS] PAYMENT.CAPTURED - EVENT DETAILS FROM WEBHOOK', [
            'razorpay_payment_id' => $razorpayPaymentId,
            'razorpay_order_id' => $razorpayOrderId,
            'amount' => $amount,
            'status_from_webhook' => $status,
            'timestamp' => now()->toDateTimeString()
        ]);

        $orderId = $razorpayOrderId;
        $paymentId = $razorpayPaymentId;

        // If this payment came from a payment link (invoice_id is set by Razorpay),
        // skip here — payment_link.paid will handle it exclusively to avoid double processing.
        if (!empty($entity['invoice_id'])) {
            Log::info('[PAYMENT.CAPTURED] Skipping — payment link payment, handled by payment_link.paid', [
                'payment_id' => $paymentId,
                'invoice_id' => $entity['invoice_id'],
            ]);
            return ['status' => 200, 'message' => 'Skipped: payment link handled separately'];
        }

        // Payment link payments without order_id sometimes come here (legacy path)
        if (!$orderId && !empty($entity['notes'])) {
            return $this->processCollectionPayment($entity);
        }

        if (!$orderId) {
            return ['status' => 200, 'message' => 'Order ID missing'];
        }

        // Check if this is an EMI collection payment (direct payment)
        // Fetch order from Razorpay to get notes
        try {
            $credentials = $this->getRazorpayCredentials();
            $api = new \Razorpay\Api\Api(
                $credentials['key_id'],
                $credentials['key_secret']
            );

            $order = $api->order->fetch($orderId);
            $orderNotes = $order['notes'] ?? [];

            Log::info('[ORDER] Fetched Razorpay order details', [
                'order_id' => $orderId,
                'notes' => $orderNotes,
            ]);

            // If order has collection_ids, this is an EMI collection payment
            if (!empty($orderNotes['collection_ids']) || !empty($orderNotes['collection_id'])) {
                Log::info('[COLLECTION] Detected EMI collection payment in order', [
                    'order_id' => $orderId,
                    'collection_ids' => $orderNotes['collection_ids'] ?? $orderNotes['collection_id'] ?? 'none',
                ]);

                // Create a modified entity with notes from order
                $entityWithNotes = array_merge($entity, ['notes' => $orderNotes]);
                return $this->processCollectionPayment($entityWithNotes);
            }

        } catch (\Exception $e) {
            Log::error('[ORDER] Failed to fetch Razorpay order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }

        // Fall back to regular Payment processing (for other payment types)
        $loanAccountId = null;
        DB::transaction(function () use ($orderId, $paymentId, $entity, &$loanAccountId) {

            $payment = Payment::where('order_id', $orderId)
                ->lockForUpdate()
                ->first();

            if (!$payment || $payment->status === 'success')
                return;

            $payment->update([
                'status' => 'success',
                'payment_id' => $paymentId,
            ]);

            $loanAccountId = $payment->loan_account_id;
            $this->applyBusinessLogic($payment, $entity);
        });

        return [
            'status' => 200,
            'message' => 'Payment processed',
            'loan_account_id' => $loanAccountId
        ];
    }

    /**
     * payment_link.paid
     */
    protected function handlePaymentLinkPaid(array $payload)
    {
        Log::info('===================================================================');
        Log::info('[START] PAYMENT_LINK.PAID EVENT - START PROCESSING');

        $entity = $payload['payload']['payment_link']['entity'] ?? null;
        if (!$entity) {
            return ['status' => 200, 'message' => 'No entity'];
        }

        $paymentLinkId = $entity['id'];
        $amount = ($entity['amount'] ?? 0) / 100;
        $status = $entity['status'] ?? 'unknown';

        Log::info('[DETAILS] PAYMENT_LINK.PAID - EVENT DETAILS', [
            'payment_link_id' => $paymentLinkId,
            'amount' => $amount,
            'status' => $status,
            'timestamp' => now()->toDateTimeString()
        ]);

        return $this->processCollectionPayment($entity);
    }

    /**
     * Shared EMI collection logic
     */
    protected function processCollectionPayment(array $entity)
    {
        $notes = $entity['notes'] ?? [];
        $collectionIds = [];

        if (!empty($notes['collection_id'])) {
            $collectionIds[] = $notes['collection_id'];
        }

        if (!empty($notes['collection_ids'])) {
            $collectionIds = array_merge(
                $collectionIds,
                array_map('trim', explode(',', $notes['collection_ids']))
            );
        }

        if (empty($collectionIds)) {
            Log::warning('[COLLECTION] No collection ids found in webhook notes', [
                'entity_id' => $entity['id'] ?? 'unknown',
                'notes' => $notes,
            ]);
            return ['status' => 200, 'message' => 'No collection'];
        }

        Log::info('[COLLECTION] Processing payment for collections', [
            'entity_id' => $entity['id'],
            'collection_ids' => $collectionIds,
            'collection_count' => count($collectionIds),
        ]);

        DB::transaction(function () use ($collectionIds, $entity) {

            foreach ($collectionIds as $id) {

                $collection = EmiCollection::lockForUpdate()->find($id);

                if (!$collection) {
                    Log::warning('[COLLECTION] Collection not found', ['collection_id' => $id]);
                    continue;
                }

                if ($collection->status === 'completed') {
                    Log::info('[COLLECTION] Collection already completed', ['collection_id' => $id]);
                    continue;
                }

                Log::info('[COLLECTION] Updating collection', [
                    'collection_id' => $collection->id,
                    'emi_id' => $collection->emi_id,
                    'amount' => $collection->amount,
                    'old_status' => $collection->status,
                ]);

                $collection->update([
                    'status' => 'completed',
                    'payment_reference' => $entity['id'],
                    'collected_at' => now(),
                ]);

                Log::info('[COLLECTION] Collection updated, now updating EMI', [
                    'collection_id' => $collection->id,
                    'emi_id' => $collection->emi_id,
                ]);

                $this->updateEmiAfterCollection($collection, $entity['id']);
            }
        });

        Log::info('[COLLECTION] All collections processed successfully', [
            'total_processed' => count($collectionIds),
        ]);

        // Get loan_account_id from first collection's EMI
        $loanAccountId = null;
        if (!empty($collectionIds)) {
            $firstCollection = EmiCollection::find($collectionIds[0]);
            if ($firstCollection) {
                $emi = Emi::find($firstCollection->emi_id);
                if ($emi) {
                    $loanAccountId = $emi->loan_account_id;
                }
            }
        }

        return [
            'status' => 200,
            'message' => 'Collections processed',
            'loan_account_id' => $loanAccountId
        ];
    }

    protected function updateEmiAfterCollection(EmiCollection $collection, string $paymentRef)
    {
        $emi = Emi::find($collection->emi_id);
        if (!$emi)
            return;
        Log::info('[EMI UPDATE] Processing collection payment', [
            'emi_id' => $emi->id,
            'collection_amount' => $collection->amount,
            'current_paid_amount' => $emi->paid_amount ?? 0,
            'current_pending_amount' => $emi->pending_amount,
            'total_amount' => $emi->total_amount,
            'penalty_amount' => $emi->penalty_amount ?? 0,
            'previous_balance' => $emi->previous_balance ?? 0,
        ]);

        $emi->paid_amount = ($emi->paid_amount ?? 0) + $collection->amount;

        // IMPORTANT: pending_amount ALREADY includes penalty and previous_balance
        // So we just subtract the payment amount from pending_amount
        $emi->pending_amount = max(0, $emi->pending_amount - $collection->amount);

        if ($emi->pending_amount <= 0) {
            $emi->status = 'paid';
            $emi->paid_date = now();
            Log::info('[EMI UPDATE] EMI fully paid', ['emi_id' => $emi->id]);
        } else {
            $emi->status = 'partial';
            $emi->is_partial_paid = true;
            $emi->partial_paid_amount = $emi->paid_amount;
            $emi->partial_paid_date = now();
            Log::info('[EMI UPDATE] EMI partially paid', [
                'emi_id' => $emi->id,
                'pending_amount' => $emi->pending_amount
            ]);
        }

        $emi->payment_reference = $paymentRef;
        $emi->payment_method = 'online';
        $emi->save();

        Log::info('[EMI UPDATE] EMI updated successfully', [
            'emi_id' => $emi->id,
            'status' => $emi->status,
            'paid_amount' => $emi->paid_amount,
            'pending_amount' => $emi->pending_amount,
        ]);

        // Sync loan account totals and EMI balances
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $paymentService->syncEmiBalances($emi->loan_account_id);
        $paymentService->syncLoanTotals($emi->loan_account_id);

        if ($emi->status === 'paid') {
            EmiAgentAssignment::where('emi_id', $emi->id)
                ->whereIn('status', ['assigned', 'visited'])
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'visited_at' => DB::raw('COALESCE(visited_at, NOW())'),
                    'remarks' => 'Recovered via Online Payment (Link)',
                ]);

            Log::info('[EMI UPDATE] Agent assignments updated to resolved', [
                'emi_id' => $emi->id
            ]);
        }
    }

    protected function handlePaymentFailed(array $payload)
    {
        Log::info('===================================================================');
        Log::info('[FAIL] PAYMENT.FAILED EVENT - START PROCESSING');

        $entity = $payload['payload']['payment']['entity'] ?? null;
        if (!$entity)
            return ['status' => 200, 'message' => 'No entity'];

        $orderId = $entity['order_id'] ?? null;
        $reason = $entity['error_description'] ?? 'Unknown reason';

        Log::info('[DETAILS] PAYMENT.FAILED - DETAILS', [
            'order_id' => $orderId,
            'reason' => $reason,
            'timestamp' => now()->toDateTimeString()
        ]);

        if (!empty($orderId)) {
            Payment::where('order_id', $orderId)
                ->update(['status' => 'failed']);
            Log::info('[WARN] Payment status updated to FAILED for Order ID: ' . $orderId);
        }

        return ['status' => 200, 'message' => 'Marked failed'];
    }

    protected function ignoreEvent(string $event)
    {
        Log::info('[RAZORPAY] Ignored event: ' . $event);
        return ['status' => 200, 'message' => 'Ignored'];
    }

    protected function applyBusinessLogic(Payment $payment, array $entity)
    {
        $amount = round($entity['amount'] / 100, 2);
        $method = $entity['method'] ?? 'online';

        Log::info('[BUSINESS] Applying business logic', [
            'payment_type' => $payment->payment_type,
            'amount' => $amount,
            'method' => $method,
        ]);

        switch ($payment->payment_type) {

            case 'EMI':
                $this->handleEmiPayment($payment, $amount, $method);
                break;

            case 'PARTIAL':
                Log::info('[BUSINESS] Processing PARTIAL payment', [
                    'loan_account_id' => $payment->loan_account_id,
                    'amount' => $amount,
                ]);
                app(\App\Services\LoanPaymentService::class)
                    ->processPartialPayment(
                        $payment->loan_account_id,
                        $amount,
                        now(),
                        $method,
                        $payment->payment_id,
                        'Partial payment via Razorpay'
                    );
                break;

            case 'PREPAYMENT':
                Log::info('[BUSINESS] Processing PREPAYMENT', [
                    'loan_account_id' => $payment->loan_account_id,
                    'amount' => $amount,
                ]);
                app(\App\Services\LoanPaymentService::class)
                    ->processPrepayment(
                        $payment->loan_account_id,
                        $amount,
                        now(),
                        $method,
                        $payment->payment_id,
                        'Prepayment via Razorpay'
                    );
                break;

            case 'FORECLOSURE':
                Log::info('[BUSINESS] Processing FORECLOSURE', [
                    'loan_account_id' => $payment->loan_account_id,
                ]);
                app(\App\Services\LoanPaymentService::class)
                    ->foreclose($payment->loan_account_id);
                break;

            default:
                Log::warning('[BUSINESS] Unknown payment type', [
                    'payment_type' => $payment->payment_type,
                    'payment_id' => $payment->id,
                ]);
                break;
        }
    }

    /**
     * Handle EMI payment type
     */
    /**
     * Get Razorpay credentials from database first, fallback to environment
     */
    private function getRazorpayCredentials()
    {
        $razorpay = PaymentGateway::where('gateway', 'razorpay')
            ->where('enabled', true)
            ->first();

        if ($razorpay && $razorpay->api_key && $razorpay->api_secret) {
            return [
                'key_id' => $razorpay->api_key,
                'key_secret' => $razorpay->api_secret
            ];
        }

        // Fallback to environment variables
        $keyId = trim(env('RAZORPAY_KEY_ID'));
        $keySecret = trim(env('RAZORPAY_KEY_SECRET'));

        if (!$keyId || !$keySecret) {
            throw new \Exception('Razorpay credentials not found in database or environment');
        }

        return [
            'key_id' => $keyId,
            'key_secret' => $keySecret
        ];
    }

    protected function handleEmiPayment(Payment $payment, float $amount, string $method)
    {
        Log::info('[EMI] Processing EMI payment', [
            'payment_id' => $payment->id,
            'emi_id' => $payment->emi_id,
            'amount' => $amount,
        ]);

        $emi = Emi::find($payment->emi_id);
        if (!$emi) {
            Log::error('[EMI] EMI not found', ['emi_id' => $payment->emi_id]);
            return;
        }

        $emi->update([
            'paid_amount' => $amount,
            'pending_amount' => 0,
            'status' => 'paid',
            'payment_method' => $method,
            'payment_reference' => $payment->payment_id,
            'paid_date' => now(),
        ]);

        Log::info('[EMI] EMI updated to paid', [
            'emi_id' => $emi->id,
            'paid_amount' => $amount,
        ]);

        // Sync loan account totals and EMI balances
        $paymentService = app(\App\Services\LoanPaymentService::class);
        $paymentService->syncEmiBalances($emi->loan_account_id);
        $paymentService->syncLoanTotals($emi->loan_account_id);

        // Resolve any active agent assignments
        EmiAgentAssignment::where('emi_id', $emi->id)
            ->whereIn('status', ['assigned', 'visited'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'visited_at' => DB::raw('COALESCE(visited_at, NOW())'),
                'remarks' => 'Recovered via Online Payment (Direct)',
            ]);

        Log::info('[EMI] Agent assignments resolved', ['emi_id' => $emi->id]);
    }
}
