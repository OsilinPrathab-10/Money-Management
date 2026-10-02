<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChitReferralBonus extends Model
{
    protected $table = 'chit_referral_bonuses';

    protected $fillable = [
        'group_member_id',
        'referrer_agent_id',
        'referrer_client_id',
        'bonus_amount',
        'calculated_percentage',
        'status',
        'paid_date'
    ];

    protected $casts = [
        'paid_date' => 'date',
        'bonus_amount' => 'decimal:2',
        'calculated_percentage' => 'decimal:2'
    ];

    public function member()
    {
        return $this->belongsTo(GroupMember::class, 'group_member_id');
    }

    public function referrerAgent()
    {
        return $this->belongsTo(Agent::class, 'referrer_agent_id');
    }

    public function referrerClient()
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }

    public function getReferrerAttribute()
    {
        return $this->referrerAgent ?: $this->referrerClient;
    }

    public static function generateForMember(GroupMember $member)
    {
        if (!$member->referred_by_agent_id && !$member->referred_by_client_id) {
            return null;
        }

        // Check if bonus already exists
        $exists = self::where('group_member_id', $member->id)->exists();
        if ($exists) {
            return null;
        }

        // Get group details
        $group = $member->group;
        if (!$group) {
            return null;
        }

        // Fetch config / Group / Scheme referral percentage
        $refPercent = null;
        if ($group && !is_null($group->referral_commission_pct)) {
            $refPercent = (float) $group->referral_commission_pct;
        } elseif ($group && $group->scheme && !is_null($group->scheme->referral_commission_pct)) {
            $refPercent = (float) $group->scheme->referral_commission_pct;
        } else {
            $refPercent = (float) \App\Models\ChitConfiguration::get('referral_commission_percentage', 10.00);
        }

        // Always base calculations on the scheme amount (chit_value)
        $baseAmount = (float) $group->chit_value;

        $bonusAmount = ($baseAmount * $refPercent) / 100;

        return self::create([
            'group_member_id' => $member->id,
            'referrer_agent_id' => $member->referred_by_agent_id,
            'referrer_client_id' => $member->referred_by_client_id,
            'bonus_amount' => $bonusAmount,
            'calculated_percentage' => $refPercent,
            'status' => 'pending',
        ]);
    }
}
