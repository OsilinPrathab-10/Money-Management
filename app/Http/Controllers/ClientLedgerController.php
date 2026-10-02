<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Client;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\DividendDistribution;
use App\Models\Payout;
use App\Models\Location;
use App\Models\ChitCollection;
use App\Support\ClientLedgerEntries;
use App\Support\HashId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\View;

class ClientLedgerController extends Controller
{
    /**
     * Display the client ledger index page.
     */
    public function index(): View
    {
        $locations = Location::orderBy('name')->get();
        
        // Calculate ledger stats
        $totalClients = Client::count();
        
        $totalOutstandingLoans = LoanAccount::where('status', 'active')
            ->sum('outstanding_amount');
            
        $activeLoanAccountsCount = LoanAccount::where('status', 'active')->count();
        
        $activeChitMembersCount = GroupMember::where('status', 'active')->count();

        return view('admin.clients.ledgers-index', compact(
            'locations', 
            'totalClients', 
            'totalOutstandingLoans', 
            'activeLoanAccountsCount', 
            'activeChitMembersCount'
        ));
    }

    /**
     * Get Datatable JSON data for clients ledger.
     */
    public function getData(Request $request): JsonResponse
    {
        $columns = [
            1 => 'id',
            2 => 'id',
            3 => 'client_name',
            4 => 'client_phone',
            5 => 'location_id',
            6 => 'active_loans_count',
            7 => 'active_chits_count',
            8 => 'total_outstanding',
        ];

        $query = Client::with(['location'])
            ->withCount([
                'loanAccounts as active_loans_count' => function ($q) {
                    $q->where('status', 'active');
                },
                'groupMembers as active_chits_count' => function ($q) {
                    $q->where('status', 'active');
                }
            ])
            ->withSum([
                'loanAccounts as total_outstanding' => function ($q) {
                    $q->where('status', 'active');
                }
            ], 'outstanding_amount');

        $totalData = $query->count();
        $totalFiltered = $totalData;

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'id';
        $dir = $request->input('order.0.dir') ?? 'desc';

        // Location Filter
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        // Search
        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('customer_id', 'LIKE', "%{$search}%")
                  ->orWhere('client_name', 'LIKE', "%{$search}%")
                  ->orWhere('client_phone', 'LIKE', "%{$search}%")
                  ->orWhere('client_email', 'LIKE', "%{$search}%");
            });

            $totalFiltered = $query->count();
        }

        // Apply pagination and ordering
        // Since we are using generated relations/aggregations, let's handle ordering
        if (in_array($order, ['active_loans_count', 'active_chits_count', 'total_outstanding'])) {
            $clients = $query->orderBy($order, $dir)
                ->offset($start)
                ->limit($limit)
                ->get();
        } else {
            $clients = $query->orderBy($order, $dir)
                ->offset($start)
                ->limit($limit)
                ->get();
        }

        $data = [];
        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->getRouteKey(),
                'fake_id' => (string) $client->id,
                'customer_id' => $client->displayCustomerId(),
                'name' => $client->client_name,
                'email' => $client->client_email ?? 'N/A',
                'mobile' => $client->client_phone ?? 'N/A',
                'zone' => $client->location ? $client->location->name : 'N/A',
                'active_loans' => $client->active_loans_count ?? 0,
                'active_chits' => $client->active_chits_count ?? 0,
                'total_outstanding' => number_format($client->total_outstanding ?? 0, 2),
                'action' => '',
            ];
        }

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    /**
     * Display a specific client's consolidated financial ledger.
     */
    public function show($id): View
    {
        $decodedId = HashId::decode($id);
        $realId = is_array($decodedId) ? ($decodedId[0] ?? $id) : ($decodedId ?? $id);
        
        $client = Client::with(['location', 'kycDetail', 'agent'])->findOrFail($realId);

        // 1. Fetch Loan Accounts & compute summaries
        $loanAccounts = LoanAccount::with(['emis.collections', 'loanApplication'])
            ->where('client_id', $realId)
            ->get();

        $loanStats = [
            'total_loans' => $loanAccounts->where('status', '!=', 'closed')->count(),
            'active_loans' => $loanAccounts->where('status', 'active')->count(),
            'closed_loans' => $loanAccounts->where('status', 'closed')->count(),
            'total_amount' => $loanAccounts->where('status', '!=', 'closed')->sum('loan_amount'),
            'total_payable' => $loanAccounts->where('status', '!=', 'closed')->sum('total_payable'),
            'total_paid' => $loanAccounts->sum('paid_amount'),
            'outstanding' => $loanAccounts->where('status', 'active')->sum('outstanding_amount'),
        ];

        $closedLoanAccounts = $loanAccounts->where('status', 'closed')->values();

        // 2. Fetch Chit Fund subscriptions (Group memberships) & compute summaries
        $groupMemberships = GroupMember::with(['group.scheme', 'installments', 'shares'])
            ->whereHas('group')
            ->involvingClient((int) $realId)
            ->get();

        $chitStats = [
            'total_subscriptions' => $groupMemberships
                ->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                ->filter(fn ($m) => ($m->group->status ?? '') !== 'completed')
                ->count(),
            'active_subscriptions' => $groupMemberships->where('status', 'active')->count(),
            'completed_subscriptions' => $groupMemberships
                ->filter(fn ($m) => ($m->group->status ?? '') === 'completed' || $m->status === 'completed')
                ->count(),
            'total_paid_installments' => 0,
            'total_pending_installments' => 0,
            'total_penalty_paid' => 0,
            'total_dividend_received' => 0,
            'total_payout_received' => 0,
        ];

        $closedChitMemberships = $groupMemberships
            ->filter(fn ($m) => ($m->group->status ?? '') === 'completed'
                || in_array($m->status, ['completed', 'withdrawn', 'cancelled', 'transferred'], true))
            ->values();

        $memberIds = $groupMemberships->pluck('id')->toArray();
        $membershipById = $groupMemberships->keyBy('id');
        $clientId = (int) $realId;

        if (!empty($memberIds)) {
            // Installment sums (ownership-weighted for shared seats)
            $installments = Installment::with(['member.shares', 'sharePayments'])->whereIn('member_id', $memberIds)->get();
            $chitStats['total_paid_installments'] = $installments->where('status', 'paid')
                ->sum(fn ($inst) => $inst->clientPaidShare($clientId));
            $chitStats['total_pending_installments'] = $installments->whereIn('status', ['pending', 'overdue', 'partial'])
                ->sum(fn ($inst) => $inst->clientBalanceShare($clientId));
            $chitStats['total_penalty_paid'] = $installments->sum(function ($inst) use ($membershipById, $clientId) {
                $member = $membershipById->get($inst->member_id);
                return $member ? $member->amountForClient((float) $inst->penalty_amount, $clientId) : (float) $inst->penalty_amount;
            });

            // Dividends
            $dividends = DividendDistribution::with('member.shares')->whereIn('member_id', $memberIds)
                ->where('status', 'paid')
                ->get();
            $chitStats['total_dividend_received'] = $dividends->sum(function ($div) use ($clientId) {
                return $div->member
                    ? $div->member->amountForClient((float) $div->amount, $clientId)
                    : (float) $div->amount;
            });

            // Payouts
            $payouts = Payout::with('winner.shares')->whereIn('winner_member_id', $memberIds)
                ->where('status', 'paid')
                ->get();
            $chitStats['total_payout_received'] = $payouts->sum(function ($payout) use ($clientId) {
                return $payout->winner
                    ? $payout->winner->amountForClient((float) $payout->payout_amount, $clientId)
                    : (float) $payout->payout_amount;
            });
        }

        // 3. Build Unified Chronological Transaction History (Ledger Entries)
        $ledgerEntries = [];

        // A. Loan Disbursements & Foreclosures
        foreach ($loanAccounts as $loan) {
            if ($loan->disbursed_at) {
                $ledgerEntries[] = [
                    'date' => $loan->disbursed_at,
                    'type' => 'Loan Disbursement',
                    'reference' => $loan->account_number,
                    'details' => 'Principal Disbursed via ' . ucfirst(str_replace('_', ' ', $loan->payment_method ?? 'Bank')),
                    'method' => ucfirst(str_replace('_', ' ', $loan->payment_method ?? 'Bank')),
                    'flow' => 'OUT', // system paid out to client
                    'amount' => $loan->disbursed_amount ?: $loan->loan_amount,
                    'badge_color' => 'danger'
                ];
            }
            if ($loan->is_foreclosed && (float)$loan->foreclosure_amount > 0) {
                $ledgerEntries[] = [
                    'date' => $loan->closed_at ?: $loan->updated_at,
                    'type' => 'Loan Foreclosure',
                    'reference' => $loan->account_number,
                    'details' => 'Loan Foreclosure Settlement Received' . ($loan->foreclosure_payment_method ? ' via ' . ucfirst(str_replace('_', ' ', $loan->foreclosure_payment_method)) : ''),
                    'method' => ucfirst(str_replace('_', ' ', $loan->foreclosure_payment_method ?? 'Bank')),
                    'flow' => 'IN', // client paid in to system
                    'amount' => round((float) $loan->foreclosure_amount, 2),
                    'badge_color' => 'dark'
                ];
            }
        }

        // B + C. EMI / Open Loan / Chit incoming receipts (original amount, splits inside)
        $loanIds = $loanAccounts->pluck('id')->toArray();
        $emiCollections = collect();
        if (! empty($loanIds)) {
            $emiCollections = EmiCollection::with(['emi.loanAccount.loanApplication'])
                ->whereIn('emi_id', function ($query) use ($loanIds) {
                    $query->select('id')->from('emis')->whereIn('loan_account_id', $loanIds);
                })
                ->whereIn('status', ClientLedgerEntries::postedCollectionStatuses())
                ->get();
        }

        $chitCollections = collect();
        $legacyInstallments = collect();
        if (! empty($memberIds)) {
            $chitCollections = ChitCollection::with(['installment.group', 'group', 'member.shares'])
                ->where(function ($q) use ($clientId, $memberIds) {
                    $q->where('client_id', $clientId)
                        ->orWhereIn('member_id', $memberIds);
                })
                ->whereIn('status', ClientLedgerEntries::postedCollectionStatuses())
                ->get()
                ->filter(function (ChitCollection $col) use ($clientId) {
                    if ($col->client_id) {
                        return (int) $col->client_id === $clientId;
                    }

                    return $col->member?->involvesClient($clientId) ?? false;
                })
                ->values();

            $legacyInstallments = Installment::with(['group', 'member.shares', 'sharePayments', 'collections'])
                ->whereIn('member_id', $memberIds)
                ->where('paid_amount', '>', 0)
                ->get();
        }

        foreach (ClientLedgerEntries::incomingPaymentRows(
            $emiCollections,
            $chitCollections,
            $legacyInstallments,
            $clientId
        ) as $paymentRow) {
            $ledgerEntries[] = $paymentRow;
        }

        if (! empty($memberIds)) {

            // D. Chit Dividends Received
            $dividendsReceived = DividendDistribution::with(['dividend.group', 'member.shares'])
                ->whereIn('member_id', $memberIds)
                ->where('status', 'paid')
                ->get();

            foreach ($dividendsReceived as $div) {
                $shareDiv = $div->member
                    ? $div->member->amountForClient((float) $div->amount, $clientId)
                    : (float) $div->amount;
                $ledgerEntries[] = [
                    'date' => $div->paid_at ?: $div->created_at,
                    'type' => 'Chit Dividend',
                    'reference' => $div->dividend->group->group_code,
                    'details' => 'Dividend Distributed (Ref: ' . ($div->reference_no ?? 'N/A') . ')',
                    'method' => $div->payment_mode ?? 'Wallet/Adjusted',
                    'flow' => 'OUT', // System distributed/paid out to client
                    'amount' => $shareDiv,
                    'badge_color' => 'primary'
                ];
            }

            // E. Chit Payouts (Auction Winnings)
            $payoutsReceived = Payout::with(['group', 'winner.shares'])
                ->whereIn('winner_member_id', $memberIds)
                ->where('status', 'paid')
                ->get();

            foreach ($payoutsReceived as $payout) {
                $sharePayout = $payout->winner
                    ? $payout->winner->amountForClient((float) $payout->payout_amount, $clientId)
                    : (float) $payout->payout_amount;
                $ledgerEntries[] = [
                    'date' => $payout->paid_date ?: $payout->created_at,
                    'type' => 'Chit Payout',
                    'reference' => $payout->group->group_code,
                    'details' => 'Auction Prize Payout (' . $payout->payout_code . ')',
                    'method' => $payout->payment_mode ?? 'Bank Transfer',
                    'flow' => 'OUT', // System paid out to client
                    'amount' => $sharePayout,
                    'badge_color' => 'secondary'
                ];
            }
        }

        // Sort entries by date descending, then by type to ensure stable sorting
        usort($ledgerEntries, function ($a, $b) {
            $t1 = strtotime((string)$a['date']);
            $t2 = strtotime((string)$b['date']);
            if ($t1 === $t2) {
                return strcmp($a['type'], $b['type']);
            }
            return $t2 - $t1; // descending order
        });

        return view('admin.clients.ledger-show', compact(
            'client',
            'loanAccounts',
            'loanStats',
            'closedLoanAccounts',
            'groupMemberships',
            'chitStats',
            'closedChitMemberships',
            'ledgerEntries'
        ));
    }
}
