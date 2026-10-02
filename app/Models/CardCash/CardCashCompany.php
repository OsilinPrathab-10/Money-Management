<?php

namespace App\Models\CardCash;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardCashCompany extends Model
{
    use HasFactory;

    protected $table = 'card_cash_companies';

    protected $fillable = [
        'company_name',
        'company_code',
        'contact_person',
        'phone',
        'email',
        'status',
        'remarks',
        'created_by',
    ];

    public function gateways(): HasMany
    {
        return $this->hasMany(CardCashWithdrawalGateway::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
