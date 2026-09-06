<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Models\Emi;
use App\Models\Agent;

class EmiAgentAssignment extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $table = 'emi_agent_assignments';

    protected $fillable = [
        'emi_id',
        'agent_id',
        'status',
        'assigned_at',
        'visited_at',
        'resolved_at',
        'remarks',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'visited_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * EMI linked to this assignment
     */
    public function emi()
    {
        return $this->belongsTo(Emi::class);
    }

    /**
     * Agent handling this EMI
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Scope: Active assignments
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['assigned', 'visited'])
            ->whereHas('emi', function ($q) {
                $q->where('emis.status', '!=', 'paid')
                    ->whereDoesntHave('collections', function ($sq) {
                        $sq->whereIn('status', ['verified', 'in_progress']);
                    });
            });
    }

    /**
     * Exclude assignments for closed or foreclosed loans.
     */
    public function scopeOnActiveLoan($query)
    {
        return $query->whereHas('emi.loanAccount', function ($q) {
            $q->activeForCollection();
        });
    }

    /**
     * Mark EMI as visited
     */
    public function markVisited()
    {
        $this->update([
            'status'     => 'visited',
            'visited_at' => now(),
        ]);
    }

    /**
     * Mark EMI as resolved
     */
    public function markResolved()
    {
        $this->update([
            'status'      => 'resolved',
            'resolved_at' => now(),
        ]);
    }
}
