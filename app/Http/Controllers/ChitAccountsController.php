<?php

namespace App\Http\Controllers;

use App\Models\GroupMember;
use App\Models\Installment;
use Illuminate\Http\Request;

class ChitAccountsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $isAgent = $user && $user->hasRole('Agent') && ! $user->hasRole('Admin');
        $agentId = $isAgent ? optional($user->agent)->id : null;

        $activeQuery = GroupMember::accounts()->where('status', 'active');
        $completedQuery = GroupMember::accounts()->where('status', 'completed');

        if ($isAgent && $agentId) {
            $assignedClientIds = \App\Models\Client::where(function ($q) use ($agentId, $user) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('user_id', $user->id);
            })->pluck('id')->toArray();

            $activeQuery->where(function ($q) use ($agentId, $assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds)
                  ->orWhere('referred_by_agent_id', $agentId)
                  ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
            });

            $completedQuery->where(function ($q) use ($agentId, $assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds)
                  ->orWhere('referred_by_agent_id', $agentId)
                  ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
            });
        }

        $activeAccounts = $activeQuery->count();
        $completedAccounts = $completedQuery->count();

        return view('admin.chit.accounts.chit-accounts', compact('activeAccounts', 'completedAccounts'));
    }

    public function data(Request $request)
    {
        $columns = [
            1 => 'id',
            6 => 'status',
        ];

        $user = auth()->user();
        $isAgent = $user && $user->hasRole('Agent') && ! $user->hasRole('Admin');
        $agentId = $isAgent ? optional($user->agent)->id : null;

        $query = GroupMember::accounts()
            ->with(['client.location', 'group.scheme', 'installments']);

        if ($isAgent && $agentId) {
            $assignedClientIds = \App\Models\Client::where(function ($q) use ($agentId, $user) {
                $q->where('assigned_to', $agentId)
                  ->orWhere('added_by', $agentId)
                  ->orWhere('user_id', $user->id);
            })->pluck('id')->toArray();

            $query->where(function ($q) use ($agentId, $assignedClientIds) {
                $q->whereIn('client_id', $assignedClientIds)
                  ->orWhere('referred_by_agent_id', $agentId)
                  ->orWhereHas('shares', fn ($sq) => $sq->whereIn('client_id', $assignedClientIds));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('account_number')) {
            $search = $request->account_number;
            $query->where(function ($q) use ($search) {
                $q->whereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('joined_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('joined_date', '<=', $request->to_date);
        }

        $totalData = (clone $query)->count();
        $totalFiltered = $totalData;

        $limit = $request->input('length', 15);
        $start = $request->input('start', 0);
        $orderCol = $columns[$request->input('order.0.column')] ?? 'id';
        $dir = $request->input('order.0.dir', 'desc');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->whereHas('group', fn ($g) => $g->where('group_code', 'like', "%{$search}%"))
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', "%{$search}%")
                        ->orWhere('client_phone', 'like', "%{$search}%"))
                    ->orWhereHas('group.scheme', fn ($s) => $s->where('name', 'like', "%{$search}%"));
            });
            $totalFiltered = $query->count();
        }

        $members = $query->offset($start)
            ->limit($limit)
            ->orderBy($orderCol, $dir)
            ->get();

        $data = $members->map(function (GroupMember $member) {
            $group = $member->group;
            $scheme = $group?->scheme;
            $installmentAmount = (float) $member->seatInstallmentAmount($group, $group?->current_month ?? 1);
            $outstanding = $member->outstanding_balance;
            $totalInstallments = $member->installments->count();
            $paidCount = $member->paid_installments_count;

            return [
                'id' => $member->id,
                'account_number' => $member->account_number,
                'client_name' => $member->client->client_name ?? 'N/A',
                'client_phone' => $member->client->client_phone ?? 'N/A',
                'client_id' => $member->client_id,
                'zone' => $member->client->location->name ?? 'N/A',
                'group_code' => $group->group_code ?? 'N/A',
                'scheme_name' => $scheme->name ?? 'N/A',
                'chit_value' => (float) ($group->chit_value ?? 0),
                'chit_value_formatted' => '₹' . number_format((float) ($group->chit_value ?? 0), 0),
                'installment_amount_formatted' => '₹' . number_format($installmentAmount, 2),
                'tenure_formatted' => ($group->total_months ?? $totalInstallments) . ' months',
                'progress_formatted' => $paidCount . '/' . max($totalInstallments, 1),
                'outstanding_balance' => $outstanding,
                'outstanding_formatted' => '₹' . number_format($outstanding, 2),
                'status' => $member->status,
                'status_label' => ucfirst($member->status),
                'joined_date' => optional($member->joined_date)->format('d-m-Y') ?? 'N/A',
                'member_number' => $member->member_number,
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function view(GroupMember $member)
    {
        if (!in_array($member->status, ['active', 'completed', 'defaulted'], true)) {
            return redirect()->route('chit.applications.index')
                ->with('error', 'This enrollment is not an active chit account yet.');
        }

        if (!$member->group) {
            return redirect()->route('chit.accounts.index')
                ->with('error', 'Chit group for this account no longer exists.');
        }

        Installment::applyAutomatedPenalties();

        $member->load([
            'client',
            'group.scheme',
            'shares.client',
            'installments' => fn ($q) => $q->with('collections')->orderBy('month_number'),
            'referrerAgent',
            'referrerClient',
        ]);

        $installments = $member->installments;
        $stats = [
            'total_due' => round((float) $installments->sum(fn ($i) => (float) $i->amount + (float) $i->penalty_amount), 2),
            'total_paid' => round((float) $installments->sum('paid_amount'), 2),
            'outstanding' => $member->outstanding_balance,
            'paid_count' => $installments->where('status', 'paid')->count(),
            'pending_count' => $installments->whereIn('status', ['pending', 'partial', 'overdue'])->count(),
            'overdue_count' => $installments->where('status', 'overdue')->count(),
        ];

        return view('admin.chit.accounts.view-chit-account', compact('member', 'installments', 'stats'));
    }
}
