<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasObfuscatedRouteKey;

class EmiCollection extends Model
{
    use HasFactory, HasObfuscatedRouteKey, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'emi_collections';

    protected $fillable = [
        'emi_id',
        'agent_id',
        'bank_account_id',
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
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Relationships

    /**
     * The bank account this collection was deposited into.
     */
    public function bankAccount()
    {
        return $this->belongsTo(\App\Models\Account\BankAccount::class, 'bank_account_id');
    }

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

    /**
     * Whether this payment was collected by a field agent (vs admin/office).
     */
    public function isAgentCollected(): bool
    {
        return $this->agent_id !== null;
    }

    /**
     * Display info for who collected this payment (logged-in collector).
     *
     * @return array{name: string, sub: string}
     */
    public function getCollectorDisplay(): array
    {
        if ($this->agent_id) {
            $agent = $this->relationLoaded('agent') ? $this->agent : $this->agent()->first();

            return [
                'name' => $agent?->agent_name ?? 'Agent',
                'sub' => $agent?->agent_code ? ('Agent: ' . $agent->agent_code) : 'Agent',
            ];
        }

        $verifier = $this->relationLoaded('verifiedBy') ? $this->verifiedBy : $this->verifiedBy()->first();
        if ($verifier) {
            $role = 'Admin/Staff';
            if (method_exists($verifier, 'hasRole')) {
                if ($verifier->hasRole('Agent')) {
                    $role = 'Agent';
                } elseif ($verifier->hasAnyRole(['Admin', 'Super Admin'])) {
                    $role = 'Admin';
                } elseif ($verifier->hasRole('Staff')) {
                    $role = 'Staff';
                }
            }

            return [
                'name' => $verifier->name ?? 'User',
                'sub' => $role,
            ];
        }

        if (str_contains((string) $this->remarks, '[Admin Created]')) {
            return ['name' => 'Admin/Staff', 'sub' => 'Office'];
        }

        return ['name' => 'System/Admin', 'sub' => ''];
    }

    /**
     * Label for who collected this payment.
     */
    public function getCollectedByLabel(): string
    {
        return $this->getCollectorDisplay()['name'];
    }
}
