<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CardCashLead extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'card_cash_leads';

    protected $fillable = [
        'lead_number',
        'credit_card_customer_id',
        'customer_card_id',
        'card_name',
        'card_number',
        'csr_bank_name',
        'card_holder_phone',
        'lead_date',
        'due_date',
        'phone_number',
        'transaction_type',
        'requested_amount',
        'status',
        'assigned_user_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public function getLastFourAttribute(): ?string
    {
        return CustomerCreditCard::lastFourDigits($this->card_number);
    }

    public function getMaskedCardNumberAttribute(): ?string
    {
        $last4 = $this->last_four;
        if ($last4) {
            return '•••• •••• •••• ' . $last4;
        }

        return $this->card_number ?: null;
    }

    public function getFormattedCardNumberAttribute(): ?string
    {
        return $this->masked_card_number;
    }

    protected $appends = [
        'last_four',
        'masked_card_number',
    ];

    protected $casts = [
        'lead_date' => 'datetime',
        'due_date' => 'date',
        'requested_amount' => 'decimal:2',
    ];

    /**
     * Labeled settlement fields for screens, WhatsApp, and lead view.
     *
     * @return array{name: string, bank_name: string, card_number: string, due_date: string, amount: string, settlement_details: string}
     */
    public function settlementDisplay(?CardCashReturn $return = null): array
    {
        $return ??= $this->returnSettlement;

        $amount = $return
            ? (float) $return->return_amount
            : (float) $this->requested_amount;

        return [
            'name' => optional($this->customer)->customer_name ?: 'N/A',
            'bank_name' => $this->csr_bank_name ?: '—',
            'card_number' => $this->masked_card_number ?: '—',
            'due_date' => $this->due_date ? $this->due_date->format('d M Y') : '—',
            'amount' => '₹' . number_format($amount, 2),
            'settlement_details' => $return?->settlement_summary ?: 'Pending settlement',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CreditCardCustomer::class, 'credit_card_customer_id');
    }

    public function customerCard(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditCard::class, 'customer_card_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function billPayment(): HasOne
    {
        return $this->hasOne(CardCashBillPayment::class, 'lead_id')->latest('id');
    }

    public function swipeTransaction(): HasOne
    {
        return $this->hasOne(CardCashSwipeTransaction::class, 'lead_id')->latest('id');
    }

    public function returnSettlement(): HasOne
    {
        return $this->hasOne(CardCashReturn::class, 'lead_id')->latest('id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(CardCashActivityLog::class, 'lead_id')->orderBy('created_at', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateLeadNumber(): string
    {
        $year = date('Y');
        $last = self::withTrashed()
            ->where('lead_number', 'LIKE', "CCL-{$year}-%")
            ->latest('id')
            ->first();

        $nextSeq = 1;
        if ($last && preg_match('/CCL-\d{4}-(\d+)/', $last->lead_number, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('CCL-%s-%04d', $year, $nextSeq);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'new' => 'bg-label-primary',
            'processing' => 'bg-label-info',
            'approved' => 'bg-label-success',
            'payment_processing' => 'bg-label-warning',
            'payment_success' => 'bg-label-info',
            'return_pending' => 'bg-label-warning',
            'return_processed' => 'bg-label-success',
            'completed' => 'bg-label-success',
            'rejected' => 'bg-label-danger',
            'cancelled' => 'bg-label-secondary',
            'failed' => 'bg-label-danger',
            default => 'bg-label-primary',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
