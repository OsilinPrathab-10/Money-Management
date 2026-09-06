<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\LoanAccount;
use App\Models\Agent;

class Recovery extends Model
{
    use HasFactory;

    protected $fillable = [
        'loan_id',
        'agent_id',
        'amount',
        'recovered_at',
        'mode',
        'remarks',
    ];

    /**
     * Loan associated with this recovery
     */
    public function loanAccount()
    {
        return $this->belongsTo(LoanAccount::class);
    }

    /**
     * Agent who collected this recovery
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
