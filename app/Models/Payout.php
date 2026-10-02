<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    public const KIND_ORIGINAL = 'original';

    public const KIND_ADVANCE = 'advance';

    protected $fillable = [
        'payout_code', 'group_id', 'auction_id', 'winner_member_id', 'month_number',
        'payout_kind', 'original_payout_id',
        'chit_value', 'winning_bid', 'commission_amount', 'payout_amount', 'share_percentage',
        'processing_fee', 'document_charges', 'other_charges', 'banking_charges', 'net_payout_amount',
        'payment_mode', 'internal_bank_account_id', 'bank_name', 'account_number', 'ifsc_code', 'upi_id',
        'reference_no', 'status', 'paid_date', 'remarks', 'settlement_document', 'collateral_document', 'other_document', 'additional_documents', 'processed_by', 'initiated_by', 'applied_source',
    ];

    protected $casts = [
        'paid_date' => 'date',
        'chit_value' => 'decimal:2',
        'winning_bid' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'share_percentage' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'document_charges' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'banking_charges' => 'decimal:2',
        'net_payout_amount' => 'decimal:2',
        'additional_documents' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(ChitGroup::class, 'group_id');
    }

    public function auction()
    {
        return $this->belongsTo(Auction::class);
    }

    public function winner()
    {
        return $this->belongsTo(GroupMember::class, 'winner_member_id')->withTrashed();
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function originalPayout()
    {
        return $this->belongsTo(self::class, 'original_payout_id');
    }

    public function advancePayouts()
    {
        return $this->hasMany(self::class, 'original_payout_id');
    }

    public function scopeOriginal($query)
    {
        return $query->where('payout_kind', self::KIND_ORIGINAL);
    }

    public function scopeAdvance($query)
    {
        return $query->where('payout_kind', self::KIND_ADVANCE);
    }

    public function isAdvance(): bool
    {
        return $this->payout_kind === self::KIND_ADVANCE;
    }

    public function isOriginal(): bool
    {
        return $this->payout_kind === self::KIND_ORIGINAL;
    }

    /**
     * Outgoing transfer / cancel buyout (paid months − foreman) — not a scheme original prize.
     */
    public function isContributionSettlement(): bool
    {
        $remarks = strtolower((string) ($this->remarks ?? ''));
        if (
            str_contains($remarks, 'outgoing transfer settlement')
            || str_contains($remarks, 'cancel settlement')
            || str_contains($remarks, 'outgoing release')
        ) {
            return true;
        }

        $winner = $this->relationLoaded('winner')
            ? $this->winner
            : $this->winner()->withTrashed()->first();

        if (! $winner) {
            return false;
        }

        return in_array($winner->status, ['transferred', 'withdrawn', 'cancelled'], true)
            || method_exists($winner, 'trashed') && $winner->trashed();
    }

    public function isOutgoingRelease(): bool
    {
        if (! $this->isContributionSettlement()) {
            return false;
        }

        $winner = $this->relationLoaded('winner')
            ? $this->winner
            : $this->winner()->withTrashed()->first();

        if ($winner && in_array($winner->status, ['withdrawn', 'cancelled'], true)) {
            return false;
        }

        $remarks = strtolower((string) ($this->remarks ?? ''));

        return ! str_contains($remarks, 'cancel settlement');
    }

    public function getPayoutKindLabelAttribute(): string
    {
        if ($this->isAdvance()) {
            return 'Advance Amount';
        }

        if ($this->isContributionSettlement()) {
            return $this->isOutgoingRelease() ? 'Outgoing Release' : 'Cancel Settlement';
        }

        return 'Original Payout';
    }

    public function getPayoutKindBadgeAttribute(): string
    {
        if ($this->isAdvance()) {
            return 'info';
        }

        if ($this->isContributionSettlement()) {
            return $this->isOutgoingRelease() ? 'warning' : 'secondary';
        }

        return 'primary';
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'paid'       => 'success',
            'pending'    => 'warning',
            'processing' => 'info',
            'cancelled'  => 'danger',
            'failed'     => 'danger',
            default      => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'paid'       => 'Approved',
            'pending'    => 'Applied',
            'processing' => 'Approved',
            'cancelled'  => 'Rejected',
            'failed'     => 'Failed',
            default      => ucfirst($this->status ?? 'unknown'),
        };
    }

    public static function activeApplicationStatuses(): array
    {
        return ['pending', 'processing', 'paid'];
    }

    public function isActiveApplication(): bool
    {
        return in_array($this->status, self::activeApplicationStatuses(), true);
    }

    public function isRejectedApplication(): bool
    {
        return in_array($this->status, ['cancelled', 'failed'], true);
    }

    /** Customer app status: applied | approved | rejected. */
    public function customerFacingStatus(): string
    {
        return match ($this->status) {
            'pending' => 'applied',
            'processing', 'paid' => 'approved',
            'cancelled', 'failed' => 'rejected',
            default => (string) ($this->status ?: 'applied'),
        };
    }

    public function customerFacingStatusLabel(): string
    {
        return match ($this->customerFacingStatus()) {
            'applied' => 'Applied',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => $this->status_label,
        };
    }

    public function customerFacingStatusBadge(): string
    {
        return match ($this->customerFacingStatus()) {
            'applied' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => $this->status_badge,
        };
    }

    public function getSourceAttribute(): string
    {
        return $this->applied_source ?: 'admin';
    }

    public function getSourceLabelAttribute(): string
    {
        return match (strtolower($this->applied_source ?: 'admin')) {
            'customer' => 'Customer App',
            'agent'    => 'Agent App',
            default    => 'Admin',
        };
    }

    public function getSourceBadgeAttribute(): string
    {
        return match (strtolower($this->applied_source ?: 'admin')) {
            'customer' => 'info',
            'agent'    => 'warning',
            default    => 'primary',
        };
    }

    public function internalBankAccount()
    {
        return $this->belongsTo(Account\BankAccount::class, 'internal_bank_account_id');
    }

    public static function generateCode(): string
    {
        $last = self::latest('id')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'PAY' . date('Ym') . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
