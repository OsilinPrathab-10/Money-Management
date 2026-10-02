<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CardCashReturn extends Model
{
    use HasFactory;

    protected $table = 'card_cash_returns';

    protected $fillable = [
        'lead_id',
        'customer_id',
        'return_method',
        'wallet_id',
        'is_split_wallet',
        'wallet_split_breakdown',
        'is_split',
        'card_percentage',
        'card_amount',
        'cash_percentage',
        'cash_amount',
        'split_breakdown',
        'return_percentage',
        'gross_amount',
        'charges',
        'return_amount',
        'payment_reference',
        'upi_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'status',
        'payment_proof',
        'processed_by',
        'processed_at',
        'remarks',
    ];

    protected $appends = [
        'settlement_summary',
        'proof_url',
    ];

    protected $casts = [
        'is_split' => 'boolean',
        'is_split_wallet' => 'boolean',
        'wallet_split_breakdown' => 'array',
        'card_percentage' => 'decimal:2',
        'card_amount' => 'decimal:2',
        'cash_percentage' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'split_breakdown' => 'array',
        'return_percentage' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'charges' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function getSettlementSummaryAttribute(): string
    {
        if ($this->is_split_wallet && !empty($this->wallet_split_breakdown)) {
            $parts = [];
            foreach ($this->wallet_split_breakdown as $split) {
                $wName = $split['wallet_name'] ?? 'Wallet';
                $wAmt = number_format((float) ($split['amount'] ?? 0), 2);
                $parts[] = "{$wName}: ₹{$wAmt}";
            }
            return "Split Wallets [" . implode(' + ', $parts) . "]";
        }

        if ($this->return_method === 'wallet' && $this->wallet) {
            return "Wallet ({$this->wallet->wallet_name}): ₹" . number_format($this->return_amount, 2);
        }

        if ($this->is_split) {
            $cardAmt = number_format((float) ($this->card_amount ?? 0), 2);
            $cashAmt = number_format((float) ($this->cash_amount ?? 0), 2);
            return "Card/Wallet: ₹{$cardAmt} | Cash: ₹{$cashAmt}";
        }

        $methodLabel = match($this->return_method) {
            'card' => 'Card',
            'wallet' => 'Wallet',
            'upi' => 'UPI',
            'imps' => 'IMPS',
            default => 'Cash / Other'
        };

        return "{$methodLabel}: ₹" . number_format($this->return_amount, 2);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CardCashLead::class, 'lead_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditCardWallet::class, 'wallet_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CreditCardCustomer::class, 'customer_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->payment_proof ? Storage::disk('public')->url($this->payment_proof) : null;
    }

    public function getMaskedAccountNumberAttribute(): ?string
    {
        if (!$this->account_number) return null;
        $len = strlen($this->account_number);
        if ($len <= 4) return $this->account_number;
        return str_repeat('X', $len - 4) . substr($this->account_number, -4);
    }
}
