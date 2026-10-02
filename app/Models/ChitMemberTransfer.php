<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChitMemberTransfer extends Model
{
    protected $fillable = [
        'transfer_code',
        'transfer_type',
        'group_id',
        'destination_group_id',
        'outgoing_member_id',
        'incoming_member_id',
        'ticket_number',
        'transfer_date',
        'completed_rounds',
        'remaining_installments',
        'paid_installments_total',
        'outstanding_at_transfer',
        'takeover_amount',
        'outgoing_settlement_amount',
        'transfer_fee',
        'takeover_payment_mode',
        'takeover_reference_no',
        'outgoing_settlement_mode',
        'outgoing_settlement_reference_no',
        'outgoing_settlement_status',
        'outgoing_settlement_paid_at',
        'remarks',
        'processed_by',
        'status',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'paid_installments_total' => 'decimal:2',
        'outstanding_at_transfer' => 'decimal:2',
        'takeover_amount' => 'decimal:2',
        'outgoing_settlement_amount' => 'decimal:2',
        'transfer_fee' => 'decimal:2',
        'outgoing_settlement_paid_at' => 'datetime',
    ];

    public function isOutgoingSettlementPending(): bool
    {
        return ($this->outgoing_settlement_status ?? 'pending') === 'pending';
    }

    public function isOutgoingSettlementPaid(): bool
    {
        return ($this->outgoing_settlement_status ?? 'pending') === 'paid';
    }

    public function usesTransferBuyoutSettlement(): bool
    {
        return $this->isSameGroup()
            && in_array($this->outgoing_settlement_status ?? 'pending', ['pending', 'paid'], true);
    }

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function destinationGroup()
    {
        return $this->belongsTo(ChitGroup::class, 'destination_group_id');
    }

    public function isCrossGroup(): bool
    {
        return ($this->transfer_type ?? 'same_group') === 'cross_group';
    }

    public function isSameGroup(): bool
    {
        return ! $this->isCrossGroup();
    }

    public function outgoingMember()
    {
        return $this->belongsTo(GroupMember::class, 'outgoing_member_id')->withTrashed();
    }

    public function incomingMember()
    {
        return $this->belongsTo(GroupMember::class, 'incoming_member_id')->withTrashed();
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public static function generateCode(): string
    {
        $last = self::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;

        return 'TRF' . date('Ym') . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function getTakeoverPaymentModeLabelAttribute(): string
    {
        return match ($this->takeover_payment_mode) {
            'bank_transfer' => 'Bank Transfer',
            'upi' => 'UPI',
            'wallet' => 'Wallet',
            'cheque' => 'Cheque',
            'cash' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $this->takeover_payment_mode ?? '')),
        };
    }

    public function getOutgoingSettlementModeLabelAttribute(): string
    {
        return match ($this->outgoing_settlement_mode) {
            'bank_transfer' => 'Bank Transfer',
            'upi' => 'UPI',
            'wallet' => 'Wallet',
            'cheque' => 'Cheque',
            'cash' => 'Cash',
            default => ucfirst(str_replace('_', ' ', $this->outgoing_settlement_mode ?? '')),
        };
    }
}
