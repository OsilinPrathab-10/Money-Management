<?php

namespace App\Http\Controllers;

use App\Models\ChitReferralBonus;
use App\Models\Agent;
use Illuminate\Http\Request;

class ChitReferralBonusController extends Controller
{
    public function index(Request $request)
    {
        $query = ChitReferralBonus::with(['member.client', 'member.group', 'referrerAgent', 'referrerClient'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('referrer_id')) {
            $parts = explode(':', $request->referrer_id);
            if (count($parts) === 2) {
                if ($parts[0] === 'agent') {
                    $query->where('referrer_agent_id', $parts[1]);
                } elseif ($parts[0] === 'client') {
                    $query->where('referrer_client_id', $parts[1]);
                }
            }
        }

        $bonuses = $query->paginate(15)->withQueryString();
        $agents = Agent::orderBy('agent_name')->get();
        $clients = \App\Models\Client::orderBy('client_name')->get();

        return view('admin.chit.referral-bonuses.index', compact('bonuses', 'agents', 'clients'));
    }

    public function pay(ChitReferralBonus $bonus)
    {
        if ($bonus->status === 'paid') {
            return back()->with('error', 'Referral bonus is already marked as paid.');
        }

        $bonus->update([
            'status' => 'paid',
            'paid_date' => today(),
        ]);

        return back()->with('success', 'Referral bonus marked as paid successfully.');
    }

    public function getOptions()
    {
        $schemes = \App\Models\ChitScheme::select('id', 'name', 'referral_commission_pct', 'chit_value', 'commission_pct')->get();
        
        $groups = \App\Models\ChitGroup::select('id', 'group_code', 'scheme_id', 'referral_commission_pct', 'chit_value', 'commission_pct')
            ->with(['members' => function($q) {
                $q->select('id', 'group_id', 'client_id', 'member_number')
                  ->with('client:id,client_name,client_phone');
            }])
            ->get();

        $globalPercent = (float) \App\Models\ChitConfiguration::get('referral_commission_percentage', 10.00);

        return response()->json([
            'schemes' => $schemes,
            'groups' => $groups,
            'global_percent' => $globalPercent,
            'calculation_base' => 'chit_value', // Always use the scheme amount
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'referrer_id'           => 'required|string',
            'group_member_id'       => 'required|exists:group_members,id',
            'calculated_percentage' => 'required|numeric|min:0|max:100',
            'bonus_amount'          => 'required|numeric|min:0',
            'status'                => 'required|in:pending,paid',
        ]);

        $exists = ChitReferralBonus::where('group_member_id', $validated['group_member_id'])->exists();
        if ($exists) {
            return back()->with('error', 'A referral bonus for this member enrollment already exists.');
        }

        $referrerAgentId = null;
        $referrerClientId = null;

        $parts = explode(':', $validated['referrer_id']);
        if (count($parts) === 2) {
            if ($parts[0] === 'agent') {
                $referrerAgentId = $parts[1];
            } elseif ($parts[0] === 'client') {
                $referrerClientId = $parts[1];
            }
        }

        if (!$referrerAgentId && !$referrerClientId) {
            return back()->with('error', 'Please select a valid referrer.');
        }

        $member = \App\Models\GroupMember::findOrFail($validated['group_member_id']);
        if (!$member->referred_by_agent_id && !$member->referred_by_client_id) {
            $member->update([
                'referred_by_agent_id'  => $referrerAgentId,
                'referred_by_client_id' => $referrerClientId,
            ]);
        }

        ChitReferralBonus::create([
            'group_member_id'       => $validated['group_member_id'],
            'referrer_agent_id'     => $referrerAgentId,
            'referrer_client_id'    => $referrerClientId,
            'bonus_amount'          => $validated['bonus_amount'],
            'calculated_percentage' => $validated['calculated_percentage'],
            'status'                => $validated['status'],
            'paid_date'             => $validated['status'] === 'paid' ? today() : null,
        ]);

        return back()->with('success', 'Referral bonus added manually successfully.');
    }
}
