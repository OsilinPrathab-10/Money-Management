<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CardCashBillPayment extends Model
{
    use HasFactory;

    protected $table = 'card_cash_bill_payments';

    protected $fillable = [
        'lead_id',
        'customer_id',
        'payment_source_id',
        'wallet_id',
        'is_split_wallet',
        'wallet_split_breakdown',
        'amount',
        'gateway_reference',
        'transaction_reference',
        'payment_date',
        'status',
        'screenshot_proof',
        'remarks',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'is_split_wallet' => 'boolean',
        'wallet_split_breakdown' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CardCashLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CreditCardCustomer::class, 'customer_id');
    }

    public function paymentSource(): BelongsTo
    {
        return $this->belongsTo(CardCashPaymentSource::class, 'payment_source_id');
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

    public function getWalletSummaryAttribute(): string
    {
        if ($this->is_split_wallet && !empty($this->wallet_split_breakdown)) {
            $parts = [];
            foreach ($this->wallet_split_breakdown as $item) {
                $name = $item['wallet_name'] ?? 'Wallet #' . ($item['wallet_id'] ?? '');
                $amt = isset($item['amount']) ? number_format((float) $item['amount'], 2) : '0.00';
                $parts[] = "{$name} (₹{$amt})";
            }
            return implode(' + ', $parts);
        }

        $walletName = $this->wallet?->wallet_name;
        $sourceName = optional($this->paymentSource)->source_name;

        if ($walletName && $sourceName) {
            return $walletName . ' · ' . $sourceName;
        }

        if ($walletName) {
            return $walletName;
        }

        return $sourceName ?? 'Default Source';
    }
}
