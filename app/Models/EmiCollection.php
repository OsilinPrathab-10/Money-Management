<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;

class EmiCollection extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $table = 'emi_collections';

    protected $fillable = [
        'emi_id',
        'agent_id',
        'amount',
        'payment_method',
        'payment_type',
        'status',
        'payment_reference',
        'proof_image_path',
        'verified_by',
        'verified_at',
        'collected_at',
        'remarks',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Relationships

    /**
     * The EMI this collection belongs to.
     */
    public function emi()
    {
        return $this->belongsTo(Emi::class, 'emi_id');
    }

    /**
     * The agent who collected this payment.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'agent_id');
    }

    /**
     * The admin who verified this collection.
     */
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
