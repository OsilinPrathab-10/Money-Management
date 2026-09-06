<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Models\LoanAccount;
use App\Models\EmiAgentAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\EmiCollection;
use App\Models\EmiFollowup;

class Emi extends Model
{
    use HasObfuscatedRouteKey;
    protected $fillable = [
        'loan_account_id',
        'instalment_number',
        'principal_amount',
        'interest_amount',
        'total_amount',
        'due_date',
        'penalty_amount',
        'last_penalty_date',
        'paid_amount',
        'paid_date',
        'partial_paid_amount',
        'is_partial_paid',
        'partial_paid_date',
        'previous_balance',
        'total_due',
        'balance_forward',
        'pending_amount',
        'status',
        'payment_method',
        'payment_reference',
        'receipt_url',
        'remarks',
        'opening_balance',
        'closing_balance',
    ];

    protected $appends = ['risk_level', 'dpd_days', 'risk_level_label'];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'partial_paid_amount' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'total_due' => 'decimal:2',
        'balance_forward' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'is_partial_paid' => 'boolean',
        'due_date' => 'date',
        'paid_date' => 'date',
        'partial_paid_date' => 'date',
        'last_penalty_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    /**
     * Get the loan account for this EMI.
     */
    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    public function collections()
    {
        return $this->hasMany(EmiCollection::class);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter overdue EMIs (past due with an outstanding balance).
     */
    public function scopeOverdue($query)
    {
        return $query->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->where('pending_amount', '>', 0);
    }

    public function followups()
    {
        return $this->hasMany(EmiFollowup::class);
    }

    public function activities()
    {
        return $this->hasMany(AgentActivity::class);
    }

    public function visitLogs()
    {
        return $this->hasMany(\App\Models\AgentVisitLog::class);
    }

    public function assignments()
    {
        return $this->hasOne(EmiAgentAssignment::class);
    }

    public function activeAssignment()
    {
        return $this->hasOne(EmiAgentAssignment::class)
            ->whereIn('status', ['assigned', 'visited']);
    }

    // App\Models\Emi.php
    public function getRiskLevelAttribute()
    {
        if ($this->pending_amount <= 0) {
            return 'closed';
        }

        $dpd = $this->dpd_days;

        if ($dpd > 15) {
            return 'high_risk';
        }

        if ($dpd > 7) {
            return 'at_risk';
        }

        return 'normal';
    }

    public function getDpdDaysAttribute()
    {
        if (!$this->due_date || $this->due_date->isFuture()) {
            return 0;
        }

        return (int) floor($this->due_date->diffInDays(now(), false));
    }

    public function getRiskLevelLabelAttribute()
    {
        $level = $this->risk_level;
        return match ($level) {
            'high_risk' => 'High Risk',
            'at_risk' => 'At Risk',
            'closed' => 'Closed',
            default => 'Normal',
        };
    }
}
