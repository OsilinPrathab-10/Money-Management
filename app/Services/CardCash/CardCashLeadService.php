<?php

namespace App\Services\CardCash;

use App\Models\CardCash\CardCashActivityLog;
use App\Models\CardCash\CardCashLead;
use App\Models\CardCash\CreditCardCustomer;
use App\Models\CardCash\CustomerCreditCard;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CardCashLeadService
{
    /**
     * Allowed status transitions
     */
    protected array $allowedTransitions = [
        'new' => ['processing', 'approved', 'payment_processing', 'payment_success', 'rejected', 'cancelled'],
        'processing' => ['approved', 'rejected', 'payment_processing', 'payment_success', 'return_pending', 'cancelled', 'failed'],
        'approved' => ['payment_processing', 'payment_success', 'return_pending', 'cancelled', 'failed'],
        'payment_processing' => ['payment_success', 'failed', 'cancelled'],
        'payment_success' => ['return_pending', 'return_processed', 'completed', 'failed'],
        'return_pending' => ['return_processed', 'completed', 'failed', 'cancelled'],
        'return_processed' => ['completed', 'failed'],
        'completed' => [],
        'rejected' => ['new', 'processing'],
        'cancelled' => ['new'],
        'failed' => ['new', 'processing', 'payment_processing', 'return_pending'],
    ];

    /**
     * Find existing credit card customer by phone or create a new one
     */
    public function findOrCreateCustomer(array $data, ?int $userId = null): CreditCardCustomer
    {
        $phone = trim($data['phone_number']);
        
        $customer = CreditCardCustomer::where('phone_number', $phone)->first();
        if ($customer) {
            // Update name / email if provided
            $updates = [];
            if (!empty($data['customer_name']) && empty($customer->customer_name)) {
                $updates['customer_name'] = $data['customer_name'];
            }
            if (!empty($data['email']) && empty($customer->email)) {
                $updates['email'] = $data['email'];
            }
            if (!empty($data['address']) && empty($customer->address)) {
                $updates['address'] = $data['address'];
            }
            if (!empty($updates)) {
                $updates['updated_by'] = $userId;
                $customer->update($updates);
            }
            return $customer;
        }

        return CreditCardCustomer::create([
            'customer_number' => CreditCardCustomer::generateCustomerNumber(),
            'customer_name' => $data['customer_name'],
            'phone_number' => $phone,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
            'remarks' => $data['customer_remarks'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * Create a new Card to Cash lead
     */
    public function createLead(array $data, ?int $userId = null): CardCashLead
    {
        return DB::transaction(function () use ($data, $userId) {
            // 1. Resolve or create customer
            if (!empty($data['credit_card_customer_id'])) {
                $customer = CreditCardCustomer::findOrFail($data['credit_card_customer_id']);
            } else {
                $customer = $this->findOrCreateCustomer([
                    'customer_name' => $data['customer_name'],
                    'phone_number' => $data['phone_number'],
                    'email' => $data['email'] ?? null,
                    'address' => $data['address'] ?? null,
                ], $userId);
            }

            // 2. Resolve or create customer credit card in separate table
            $customerCard = null;
            if (!empty($data['customer_card_id'])) {
                $customerCard = CustomerCreditCard::where('customer_id', $customer->id)
                    ->where('id', $data['customer_card_id'])
                    ->first();
            }

            $lastFour = CustomerCreditCard::lastFourDigits($data['card_number'] ?? null);
            $cleanedCardHolderPhone = !empty($data['card_holder_phone']) ? preg_replace('/\D/', '', (string) $data['card_holder_phone']) : null;
            if ($cleanedCardHolderPhone && strlen($cleanedCardHolderPhone) === 12 && str_starts_with($cleanedCardHolderPhone, '91')) {
                $cleanedCardHolderPhone = substr($cleanedCardHolderPhone, 2);
            }

            if (!$customerCard && $lastFour) {
                $customerCard = CustomerCreditCard::findForCustomerByLastFour($customer->id, $lastFour);
                if (!$customerCard) {
                    $customerCard = CustomerCreditCard::create([
                        'customer_id' => $customer->id,
                        'card_number' => $lastFour,
                        'card_name' => $data['card_name'] ?? 'Credit Card',
                        'csr_bank_name' => $data['csr_bank_name'] ?? 'Bank',
                        'card_holder_phone' => $cleanedCardHolderPhone,
                        'card_network' => $data['card_network'] ?? null,
                        'card_type' => 'credit',
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);
                }

                if ($customerCard && (!empty($data['card_name']) || !empty($data['csr_bank_name']) || !empty($cleanedCardHolderPhone))) {
                    $cardUpdates = [];
                    if (!empty($data['card_name']) && $customerCard->card_name !== $data['card_name']) {
                        $cardUpdates['card_name'] = $data['card_name'];
                    }
                    if (!empty($data['csr_bank_name']) && $customerCard->csr_bank_name !== $data['csr_bank_name']) {
                        $cardUpdates['csr_bank_name'] = $data['csr_bank_name'];
                    }
                    if (!empty($cleanedCardHolderPhone) && $customerCard->card_holder_phone !== $cleanedCardHolderPhone) {
                        $cardUpdates['card_holder_phone'] = $cleanedCardHolderPhone;
                    }
                    if (!empty($cardUpdates)) {
                        $cardUpdates['updated_by'] = $userId;
                        $customerCard->update($cardUpdates);
                    }
                }
            } elseif ($customerCard && !empty($cleanedCardHolderPhone) && $customerCard->card_holder_phone !== $cleanedCardHolderPhone) {
                $customerCard->update([
                    'card_holder_phone' => $cleanedCardHolderPhone,
                    'updated_by' => $userId,
                ]);
            }

            $cardName = $data['card_name'] ?? ($customerCard ? $customerCard->card_name : 'Credit Card');
            $cardNumber = $lastFour ?: ($customerCard ? $customerCard->last_four : null);
            $csrBankName = $data['csr_bank_name'] ?? ($customerCard ? $customerCard->csr_bank_name : 'Bank');
            $cardHolderPhone = $cleanedCardHolderPhone ?: ($customerCard ? $customerCard->card_holder_phone : null);

            // 3. Create lead
            $lead = CardCashLead::create([
                'lead_number' => CardCashLead::generateLeadNumber(),
                'credit_card_customer_id' => $customer->id,
                'customer_card_id' => $customerCard ? $customerCard->id : null,
                'card_name' => $cardName,
                'card_number' => $cardNumber,
                'csr_bank_name' => $csrBankName,
                'card_holder_phone' => $cardHolderPhone,
                'lead_date' => now(), // System date/time, non-editable
                'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
                'phone_number' => $customer->phone_number,
                'transaction_type' => $data['transaction_type'],
                'requested_amount' => number_format((float) $data['requested_amount'], 2, '.', ''),
                'status' => 'new',
                'assigned_user_id' => $data['assigned_user_id'] ?? $userId,
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // 3. Log initial activity
            $this->logActivity(
                leadId: $lead->id,
                action: 'lead_created',
                description: "Lead created for {$customer->customer_name} ({$lead->transaction_type}) for ₹" . number_format($lead->requested_amount, 2),
                oldStatus: null,
                newStatus: 'new',
                userId: $userId,
                metadata: [
                    'customer_number' => $customer->customer_number,
                    'requested_amount' => $lead->requested_amount,
                    'transaction_type' => $lead->transaction_type,
                ]
            );

            if (!empty($lead->assigned_user_id) && $lead->assigned_user_id !== $userId) {
                $this->logActivity(
                    leadId: $lead->id,
                    action: 'lead_assigned',
                    description: "Lead assigned to staff ID: {$lead->assigned_user_id}",
                    oldStatus: 'new',
                    newStatus: 'new',
                    userId: $userId
                );
            }

            return $lead;
        });
    }

    /**
     * Change lead status with validation
     */
    public function changeStatus(
        CardCashLead $lead,
        string $newStatus,
        string $description,
        ?int $userId = null,
        ?array $metadata = null
    ): bool {
        $oldStatus = $lead->status;
        if ($oldStatus === $newStatus) {
            return true;
        }

        $allowed = $this->allowedTransitions[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowed, true) && !auth()->user()?->hasRole('Admin')) {
            throw new InvalidArgumentException("Status transition from '{$oldStatus}' to '{$newStatus}' is not allowed.");
        }

        $lead->status = $newStatus;
        $lead->updated_by = $userId;
        $lead->save();

        $this->logActivity(
            leadId: $lead->id,
            action: 'status_changed',
            description: $description ?: "Status changed from {$oldStatus} to {$newStatus}",
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            userId: $userId,
            metadata: $metadata
        );

        return true;
    }

    /**
     * Reassign lead to another staff
     */
    public function assignLead(CardCashLead $lead, int $assignedUserId, ?int $byUserId = null): bool
    {
        $oldAssigned = $lead->assigned_user_id;
        $lead->assigned_user_id = $assignedUserId;
        $lead->updated_by = $byUserId;
        $lead->save();

        $this->logActivity(
            leadId: $lead->id,
            action: 'lead_assigned',
            description: "Lead reassigned to user ID: {$assignedUserId}",
            oldStatus: $lead->status,
            newStatus: $lead->status,
            userId: $byUserId,
            metadata: ['old_user_id' => $oldAssigned, 'new_user_id' => $assignedUserId]
        );

        return true;
    }

    /**
     * Log activity in timeline
     */
    public function logActivity(
        int $leadId,
        string $action,
        string $description,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?int $userId = null,
        ?array $metadata = null
    ): CardCashActivityLog {
        return CardCashActivityLog::create([
            'lead_id' => $leadId,
            'user_id' => $userId,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
