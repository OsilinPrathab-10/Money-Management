<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditCardCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'credit_card_customers';

    protected $fillable = [
        'customer_number',
        'customer_name',
        'phone_number',
        'email',
        'address',
        'status',
        'remarks',
        'created_by',
        'updated_by',
    ];

    public function cards(): HasMany
    {
        return $this->hasMany(CustomerCreditCard::class, 'customer_id');
    }

    public function activeCards(): HasMany
    {
        return $this->hasMany(CustomerCreditCard::class, 'customer_id')->where('status', 'active');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CardCashLead::class, 'credit_card_customer_id');
    }

    public function billPayments(): HasMany
    {
        return $this->hasMany(CardCashBillPayment::class, 'customer_id');
    }

    public function swipeTransactions(): HasMany
    {
        return $this->hasMany(CardCashSwipeTransaction::class, 'customer_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(CardCashReturn::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function generateCustomerNumber(): string
    {
        $last = self::withTrashed()->latest('id')->first();
        $nextId = $last ? ($last->id + 1) : 1;
        return 'CCC-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
    }
}
