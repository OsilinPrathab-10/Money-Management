<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CollectionNote extends Model
{
    use HasFactory;

    protected $table = 'collection_notes';

    protected $fillable = [
        'type',
        'client_id',
        'account_id',
        'emi_id',
        'group_member_id',
        'installment_id',
        'user_id',
        'agent_id',
        'note',
        'reminder_date',
        'reminder_time',
        'status',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'reminder_date' => 'date:Y-m-d',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function loanAccount(): BelongsTo
    {
        return $this->belongsTo(LoanAccount::class, 'loan_account_id');
    }

    public function emi(): BelongsTo
    {
        return $this->belongsTo(Emi::class, 'emi_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    public function scopePendingReminders($query)
    {
        return $query->where('is_completed', false)->whereNotNull('reminder_date');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('reminder_date', $date);
    }

    public function getFormattedReminderDateAttribute(): ?string
    {
        return $this->reminder_date ? Carbon::parse($this->reminder_date)->format('d-m-Y') : null;
    }

    public function getCreatorNameAttribute(): string
    {
        if ($this->agent) {
            return $this->agent->agent_name . ' (Agent)';
        }
        if ($this->user) {
            return $this->user->name;
        }
        return 'System';
    }
}
