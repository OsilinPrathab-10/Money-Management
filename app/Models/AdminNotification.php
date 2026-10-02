<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'message',
        'link',
        'icon',
        'is_read',
        'read_at',
        'related_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Get icon class based on notification type
     */
    public function getIconClassAttribute()
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match($this->type) {
            'emi_overdue' => 'ri-alarm-warning-line',
            'new_loan_application' => 'ri-file-list-3-line',
            'new_chit_application' => 'ri-group-line',
            'new_fd_application' => 'ri-safe-2-line',
            'new_settlement_application' => 'ri-hand-coin-line',
            'new_user_registration' => 'ri-user-add-line',
            'payment_received' => 'ri-money-rupee-circle-line',
            'chit_installment_collected' => 'ri-money-rupee-circle-line',
            'chit_settlement_paid' => 'ri-hand-coin-line',
            'loan_disbursed' => 'ri-bank-card-line',
            'fd_booked' => 'ri-safe-2-line',
            'client_assigned' => 'ri-user-shared-line',
            'kyc_approved' => 'ri-shield-check-line',
            'kyc_rejected' => 'ri-shield-cross-line',
            'broadcast', 'interest_update', 'loan_product', 'offer', 'disbursement', 'general' => 'ri-megaphone-line',
            default => 'ri-notification-3-line',
        };
    }

    /**
     * Get badge color based on notification type
     */
    public function getBadgeColorAttribute()
    {
        return match($this->type) {
            'emi_overdue', 'kyc_rejected', 'loan_application_rejected', 'chit_application_rejected', 'fd_application_rejected' => 'danger',
            'new_loan_application', 'new_chit_application', 'new_fd_application', 'new_settlement_application' => 'primary',
            'new_user_registration', 'payment_received', 'chit_installment_collected', 'chit_settlement_paid', 'loan_disbursed', 'fd_booked', 'kyc_approved' => 'success',
            'client_assigned' => 'info',
            'broadcast', 'interest_update', 'loan_product', 'offer', 'disbursement', 'general' => 'primary',
            default => 'secondary',
        };
    }
}
