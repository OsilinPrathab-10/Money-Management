<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    protected $fillable = [
        'template_name',
        'event_type',
        'provider',
        'provider_template_name',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get human-readable event type label
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match($this->event_type) {
            'kyc_approved' => 'KYC Approved',
            'kyc_verified' => 'KYC Verified',
            'kyc_rejected' => 'KYC Rejected',
            'loan_approved' => 'Loan Approved',
            'loan_rejected' => 'Loan Rejected',
            'loan_disbursed' => 'Loan Disbursed',
            'emi_due_today' => 'EMI Due Today',
            'emi_overdue' => 'EMI Overdue',
            'emi_paid' => 'EMI Paid',
            'payment_received' => 'Payment Received',
            default => ucwords(str_replace('_', ' ', $this->event_type)),
        };
    }
}
