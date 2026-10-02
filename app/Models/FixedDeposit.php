<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedDeposit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fd_number',
        'client_id',
        'scheme_id',
        'deposit_amount',
        'deposit_date',
        'start_date',
        'maturity_date',
        'tenure',
        'tenure_type',
        'interest_rate',
        'interest_type',
        'interest_frequency',
        'interest_amount',
        'maturity_amount',
        'interest_paid_to_wallet',
        'last_interest_payout_date',
        'monthly_interest_to_wallet',
        'nominee_name',
        'nominee_relation',
        'payout_option',
        'auto_renewal',
        'renewal_type',
        'status',
        'remarks',
        'bank_name',
        'account_number',
        'ifsc_code',
        'utr_reference',
        'payment_date',
        'closure_date',
        'closure_amount',
        'closure_payment_mode',
        'closure_transaction_ref',
        'closed_by',
        'closure_remarks',
        'renewed_from_id',
        'maturity_processed_at',
        'created_by',
        'processing_fee',
        'document_charges',
        'other_charges',
        'banking_charges',
        'internal_bank_account_id',
        'payment_proof',
        'customer_bank_name',
        'customer_account_number',
        'customer_ifsc_code',
        'customer_branch_name',
        'customer_holder_name',
    ];

    protected $casts = [
        'deposit_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'interest_amount' => 'decimal:2',
        'maturity_amount' => 'decimal:2',
        'interest_paid_to_wallet' => 'decimal:2',
        'closure_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'document_charges' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'banking_charges' => 'decimal:2',
        'deposit_date' => 'date',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'payment_date' => 'date',
        'closure_date' => 'date',
        'last_interest_payout_date' => 'date',
        'auto_renewal' => 'boolean',
        'monthly_interest_to_wallet' => 'boolean',
        'maturity_processed_at' => 'datetime',
    ];

    protected $appends = [
        'remaining_days',
        'total_days',
        'completed_days',
        'tenure_progress_percentage',
        'tenure_progress',
        'status_badge',
        'status_label',
        'interest_type_label',
        'payout_option_label',
        'payment_proof_url',
    ];

    public function getPaymentProofUrlAttribute(): ?string
    {
        return \App\Models\LoanType::formatImageUrl($this->payment_proof);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function scheme()
    {
        return $this->belongsTo(FixedDepositScheme::class, 'scheme_id');
    }

    public function application()
    {
        return $this->hasOne(FixedDepositApplication::class, 'fixed_deposit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewalsAsOld()
    {
        return $this->hasMany(FixedDepositRenewal::class, 'old_fd_id');
    }

    public function renewalsAsNew()
    {
        return $this->hasMany(FixedDepositRenewal::class, 'new_fd_id');
    }

    public function transactions()
    {
        return $this->hasMany(FixedDepositTransaction::class);
    }

    public function chitAllocations()
    {
        return $this->hasMany(FixedDepositChitAllocation::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(FixedDepositAuditLog::class);
    }

    public function interestPayouts()
    {
        return $this->hasMany(FixedDepositInterestPayout::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isReadOnly(): bool
    {
        return in_array($this->status, ['closed', 'cancelled', 'premature_closed', 'renewed'], true);
    }

    public function canEdit(): bool
    {
        return $this->status === 'active' && $this->maturity_processed_at === null;
    }

    public function canDelete(): bool
    {
        if (!$this->canEdit()) {
            return false;
        }

        if (array_key_exists('processed_transactions_count', $this->attributes)) {
            if ((int) $this->processed_transactions_count > 0) {
                return false;
            }
        } elseif ($this->transactions()->where('transaction_type', '!=', 'creation')->exists()) {
            return false;
        }

        if (array_key_exists('interest_payouts_count', $this->attributes)) {
            if ((int) $this->interest_payouts_count > 0) {
                return false;
            }
        } elseif ($this->interestPayouts()->exists()) {
            return false;
        }

        return true;
    }

    public function canChangeFinancials(): bool
    {
        return $this->canDelete();
    }

    public function canProcessMaturity(): bool
    {
        return $this->status === 'active'
            && $this->maturity_processed_at === null
            && $this->maturity_date->lte(Carbon::today());
    }

    public function getTotalDaysAttribute(): int
    {
        $start = $this->start_date ?? $this->deposit_date;
        if (!$start || !$this->maturity_date) {
            return 0;
        }

        $s = $start->copy()->startOfDay();
        $m = $this->maturity_date->copy()->startOfDay();

        return max(1, (int) $s->diffInDays($m));
    }

    public function getCompletedDaysAttribute(): int
    {
        $start = $this->start_date ?? $this->deposit_date;
        if (!$start) {
            return 0;
        }

        $totalDays = $this->total_days;
        if ($totalDays <= 0) {
            return 0;
        }

        if (in_array($this->status, ['matured', 'closed', 'renewed', 'premature_closed'], true)) {
            return $totalDays;
        }

        $s = $start->copy()->startOfDay();
        $today = Carbon::today()->startOfDay();

        if ($today->lt($s)) {
            return 0;
        }

        $elapsed = (int) $s->diffInDays($today);

        return min($totalDays, max(0, $elapsed));
    }

    public function getRemainingDaysAttribute(): int
    {
        if (!$this->maturity_date) {
            return 0;
        }

        if (in_array($this->status, ['matured', 'closed', 'renewed', 'premature_closed'], true)) {
            return 0;
        }

        $days = (int) floor(Carbon::today()->startOfDay()->diffInDays($this->maturity_date->copy()->startOfDay(), false));

        return max(0, $days);
    }

    public function getTenureProgressPercentageAttribute(): float
    {
        $total = $this->total_days;
        if ($total <= 0) {
            return 0.0;
        }

        if (in_array($this->status, ['matured', 'closed', 'renewed'], true)) {
            return 100.0;
        }

        $completed = $this->completed_days;
        $percentage = round(($completed / $total) * 100, 2);

        return min(100.0, max(0.0, $percentage));
    }

    public function getTenureProgressAttribute(): array
    {
        return [
            'total_days' => $this->total_days,
            'completed_days' => $this->completed_days,
            'remaining_days' => $this->remaining_days,
            'progress_percentage' => $this->tenure_progress_percentage,
        ];
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'matured' => 'info',
            'closed' => 'secondary',
            'premature_closed' => 'warning',
            'renewed' => 'primary',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'matured' => 'Matured',
            'closed' => 'Closed',
            'premature_closed' => 'Premature Closed',
            'renewed' => 'Renewed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getInterestTypeLabelAttribute(): string
    {
        return match ($this->interest_type) {
            'simple_interest' => 'Simple Interest',
            'compound_interest' => 'Compound Interest',
            default => ucfirst(str_replace('_', ' ', (string) $this->interest_type)),
        };
    }

    public function getPayoutOptionLabelAttribute(): string
    {
        return match ($this->payout_option) {
            'wallet' => 'Wallet',
            'chit' => 'Chit',
            'bank_transfer' => 'Bank Transfer',
            default => ucfirst(str_replace('_', ' ', (string) $this->payout_option)),
        };
    }

    public static function generateFdNumber(): string
    {
        $year = date('Y');
        $prefix = 'FD-' . $year . '-';

        $last = self::withTrashed()
            ->where('fd_number', 'like', $prefix . '%')
            ->orderByDesc('fd_number')
            ->value('fd_number');

        $next = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    public function internalBankAccount()
    {
        return $this->belongsTo(Account\BankAccount::class, 'internal_bank_account_id');
    }
}
