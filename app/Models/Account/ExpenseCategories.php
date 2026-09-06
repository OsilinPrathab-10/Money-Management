<?php

namespace App\Models\Account;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Account\ChartOfAccount;

class ExpenseCategories extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'category_name',
        'category_code',
        'description',
        'is_active',
        'gl_account_id',
        'creator_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function gl_account()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}