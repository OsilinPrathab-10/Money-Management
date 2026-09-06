<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmiReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_account_id',
        'emi_id',
        'reminder_type',
        'sent_at',
        'channel',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * Get the loan account for this reminder log.
     */
    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class);
    }

    /**
     * Get the EMI for this reminder log.
     */
    public function emi(): BelongsTo
    {
        return $this->belongsTo(Emi::class);
    }
}
