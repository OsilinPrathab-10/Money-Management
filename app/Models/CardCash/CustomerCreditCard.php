<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerCreditCard extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'credit_card_customer_cards';

    protected $fillable = [
        'customer_id',
        'card_name',
        'card_number',
        'csr_bank_name',
        'card_holder_phone',
        'card_network',
        'card_type',
        'expiry_month',
        'expiry_year',
        'is_primary',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(CreditCardCustomer::class, 'customer_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CardCashLead::class, 'customer_card_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Last 4 digits from a full or partial card number.
     */
    public static function lastFourDigits(?string $cardNumber): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $cardNumber);
        if (strlen($digits) < 4) {
            return null;
        }

        return substr($digits, -4);
    }

    public function getLastFourAttribute(): ?string
    {
        return self::lastFourDigits($this->card_number);
    }

    /**
     * Masked card number (e.g. •••• •••• •••• 1234)
     */
    public function getMaskedCardNumberAttribute(): string
    {
        $last4 = $this->last_four;
        if ($last4) {
            return '•••• •••• •••• ' . $last4;
        }

        return $this->card_number ?: '••••';
    }

    /**
     * Formatted card number with space grouping (e.g. 4111 2222 3333 4444)
     */
    public function getFormattedCardNumberAttribute(): string
    {
        $num = preg_replace('/\D/', '', (string) $this->card_number);
        return trim(chunk_split($num, 4, ' '));
    }

    /**
     * Friendly display label for dropdowns
     */
    public function getDisplayLabelAttribute(): string
    {
        return "{$this->card_name} ({$this->masked_card_number}) - {$this->csr_bank_name}";
    }

    public static function findForCustomerByLastFour(int $customerId, string $lastFour): ?self
    {
        return self::where('customer_id', $customerId)
            ->get()
            ->first(fn (self $card) => self::lastFourDigits($card->card_number) === $lastFour);
    }
}
