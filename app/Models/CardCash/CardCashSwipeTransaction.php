<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CardCashSwipeTransaction extends Model
{
    use HasFactory;

    protected $table = 'card_cash_swipe_transactions';

    protected $fillable = [
        'lead_id',
        'customer_id',
        'withdrawal_gateway_id',
        'wallet_id',
        'is_split_wallet',
        'wallet_split_breakdown',
        'swipe_amount',
        'charges',
        'charges_percentage',
        'net_amount',
        'gateway_reference',
        'transaction_reference',
        'status',
        'screenshot_proof',
        'processed_by',
        'processed_at',
        'remarks',
    ];

    protected $casts = [
        'is_split_wallet' => 'boolean',
        'wallet_split_breakdown' => 'array',
        'swipe_amount' => 'decimal:2',
        'charges' => 'decimal:2',
        'charges_percentage' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function getWalletSummaryAttribute(): string
    {
        if ($this->is_split_wallet && !empty($this->wallet_split_breakdown)) {
            $parts = [];
            foreach ($this->wallet_split_breakdown as $split) {
                $parts[] = ($split['wallet_name'] ?? 'Wallet') . " (₹" . number_format($split['amount'] ?? 0, 2) . ")";
            }
            return "Split Wallets [" . implode(' + ', $parts) . "]";
        }

        return $this->wallet ? $this->wallet->wallet_name : 'N/A';
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CardCashLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CreditCardCustomer::class, 'customer_id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(CardCashWithdrawalGateway::class, 'withdrawal_gateway_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CreditCardWallet::class, 'wallet_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function getProofUrlAttribute(): ?string
    {
        return $this->screenshot_proof ? Storage::disk('public')->url($this->screenshot_proof) : null;
    }
}
