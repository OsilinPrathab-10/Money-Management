<?php

namespace App\Http\Controllers;

use App\Models\Installment;
use App\Models\InstallmentSharePayment;
use App\Models\ChitGroup;
use App\Models\Account\BankAccount;
use App\Services\PartialPaymentConfigService;
use App\Services\ChitPaymentService;
use App\Support\BulkPaymentGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ChitInstallmentController extends Controller
{
    public function __construct(
        protected PartialPaymentConfigService $partialPaymentConfig,
        protected ChitPaymentService $paymentService,
        protected \App\Services\FixedDeposit\WalletService $walletService
    ) {}

    public function index(Request $request)
    {
        Installment::applyAutomatedPenalties();

        $baseQuery = $this->buildFilteredQuery($request);
        $stats = $this->computeStats(clone $baseQuery);

        // Offer every group the listing can actually show, otherwise groups that are
        // no longer active (terminated, closed) appear in the table with no way to
        // filter by them.
        $groups = ChitGroup::whereHas('installments')
            ->with('scheme')
            ->orderBy('id', 'desc')
            ->get();
        $families = \App\Models\ChitFamily::orderBy('name')->get();

        $partialPaymentConfig = $this->partialPaymentConfig->getGlobalSettings();
        $bankAccounts = $this->getBankAccounts();

        $mode = 'index';
        return view('admin.chit.installments.index', compact(
            'stats',
            'groups',
            'families',
            'mode',
            'partialPaymentConfig',
            'bankAccounts'
        ));
    }

    /**
     * DataTables AJAX: client-grouped installments (all chits) by status tab.
     */
    public function getData(Request $request): JsonResponse
    {
        Installment::applyAutomatedPenalties();

        $today = Carbon::now()->startOfDay();
        $status = $request->input('status', 'overdue');
        $partialPaymentConfig = $this->partialPaymentConfig->getGlobalSettings();

        $query = $this->buildFilteredQuery($request)
            ->join('group_members', function ($j) {
                $j->on('installments.member_id', '=', 'group_members.id')
                  ->whereNull('group_members.deleted_at')
                  ->whereNotIn('group_members.status', \App\Models\GroupMember::INACTIVE_STATUSES);
            })
            ->leftJoin('group_member_shares', 'group_member_shares.group_member_id', '=', 'group_members.id');

        $baseCountQuery = $this->buildFilteredQuery($request);
        $stats = $this->computeStats(clone $baseCountQuery);

        // One row per client (primary or share holder), balances weighted by ownership %
        $clientIdExpr = 'COALESCE(group_member_shares.client_id, group_members.client_id)';
        $ownershipExpr = 'COALESCE(group_member_shares.ownership_percentage, 100)';

        // An owner owes their slice of the total due minus what they personally paid.
        // Slicing the shrinking balance instead would make each owner's dues collapse
        // every time the other owner pays.
        $ownerPaidExpr = "(SELECT COALESCE(SUM(isp.amount), 0) FROM installment_share_payments isp"
            . " WHERE isp.installment_id = installments.id AND isp.client_id = {$clientIdExpr})";
        $trackedPaidExpr = "(SELECT COALESCE(SUM(isp2.amount), 0) FROM installment_share_payments isp2"
            . " WHERE isp2.installment_id = installments.id)";
        $dueShareExpr = "((installments.amount + COALESCE(installments.penalty_amount, 0)) * ({$ownershipExpr} / 100))";
        $paidShareExpr = "({$ownerPaidExpr} + GREATEST(0, COALESCE(installments.paid_amount, 0) - {$trackedPaidExpr}) * ({$ownershipExpr} / 100))";
        $ownerBalanceExpr = "GREATEST(0, {$dueShareExpr} - {$paidShareExpr})";

        // Status tabs must use per-owner share status (not seat-level installment.status),
        // otherwise unpaid co-owners appear under Partial when the other owner paid.
        $this->applyStatusFilter($query, $status, $today, $paidShareExpr, $ownerBalanceExpr);

        $this->applyRowOwnerFilters($query, $request, $clientIdExpr);

        $clientGroupsQuery = (clone $query)
            ->select(
                DB::raw("{$clientIdExpr} as client_id"),
                DB::raw('MIN(installments.due_date) as earliest_due'),
                DB::raw("SUM(GREATEST(0, {$dueShareExpr} - {$paidShareExpr})) as total_balance")
            )
            ->groupBy(DB::raw($clientIdExpr));

        $totalFiltered = DB::query()
            ->fromSub($clientGroupsQuery, 'client_groups')
            ->count();

        $limit = (int) $request->input('length', 20);
        $start = (int) $request->input('start', 0);
        $dir = $request->input('order.0.dir') === 'desc' ? 'desc' : 'asc';

        // Column indexes come from the DataTable definition in chit-installments.js.
        $sortable = [
            2 => 'clients.client_name',
            3 => 'clients.client_phone',
            5 => 'locations.name',
            7 => 'client_groups.total_balance',
        ];
        $orderBy = $sortable[(int) $request->input('order.0.column', 7)] ?? 'client_groups.earliest_due';

        $pagedGroups = DB::query()
            ->fromSub($clientGroupsQuery, 'client_groups')
            ->leftJoin('clients', 'clients.id', '=', 'client_groups.client_id')
            ->leftJoin('locations', 'locations.id', '=', 'clients.location_id')
            ->select('client_groups.*')
            ->orderBy($orderBy, $dir)
            ->offset($start)
            ->limit($limit > 0 ? $limit : 20)
            ->get();

        $clientIds = $pagedGroups->pluck('client_id')->filter()->values();

        $clients = \App\Models\Client::with('location')
            ->whereIn('id', $clientIds)
            ->get()
            ->keyBy('id');

        $allMemberships = \App\Models\GroupMember::with(['group', 'shares.client'])
            ->whereHas('group')
            ->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
            ->where(function ($q) use ($clientIds) {
                $q->whereIn('client_id', $clientIds)
                    ->orWhereHas('shares', fn ($s) => $s->whereIn('client_id', $clientIds));
            })
            ->orderBy('group_id')
            ->orderBy('id')
            ->get();

        $membershipsByClient = collect();
        foreach ($clientIds as $cid) {
            $membershipsByClient[(int) $cid] = $allMemberships
                ->filter(fn ($m) => $m->involvesClient((int) $cid))
                ->values();
        }

        $seatLetterMap = $this->buildSeatLetterMap($allMemberships);
        $memberIdsForClients = $allMemberships->pluck('id');

        $allInstallments = Installment::with(['member.client.location', 'member.shares.client', 'group.scheme', 'sharePayments'])
            ->whereHas('group')
            ->whereHas('member')
            ->whereIn('member_id', $memberIdsForClients)
            ->orderBy('group_id')
            ->orderBy('member_id')
            ->orderBy('month_number')
            ->orderBy('due_date')
            ->get();

        $installmentsByClient = collect();
        foreach ($clientIds as $cid) {
            $installmentsByClient[(int) $cid] = $allInstallments
                ->filter(fn ($inst) => $inst->member && $inst->member->involvesClient((int) $cid))
                ->values();
        }

        $company = \App\Models\CompanyDetail::first();
        $companyMobile = $company?->company_mobile ?? '';
        $companySlogan = $company?->company_slogan ?? $company?->company_name ?? 'Codepluse Gen PVT Ltd';

        $data = $pagedGroups->values()->map(function ($group, $index) use (
            $start,
            $clients,
            $membershipsByClient,
            $installmentsByClient,
            $seatLetterMap,
            $partialPaymentConfig,
            $today,
            $status,
            $companyMobile,
            $companySlogan
        ) {
            $clientId = (int) $group->client_id;
            $client = $clients->get($clientId) ?? $clients->get($group->client_id);
            $clientInstallments = $installmentsByClient->get($clientId, collect());
            $memberships = $membershipsByClient->get($clientId, collect());
            $baseClientName = $client->client_name ?? 'N/A';

            $formatted = $clientInstallments->map(
                fn ($inst) => $this->formatInstallmentRow(
                    $inst,
                    $partialPaymentConfig,
                    $today,
                    $companyMobile,
                    $companySlogan,
                    $seatLetterMap[$inst->member_id] ?? null,
                    $clientId
                )
            );

            $grouped = [
                'overdue' => $formatted->where('status', 'overdue')->values()->all(),
                'pending' => $formatted->where('status', 'pending')->values()->all(),
                'upcoming' => $formatted->where('status', 'upcoming')->values()->all(),
                'partial' => $formatted->where('status', 'partial')->values()->all(),
                'paid' => $formatted->where('status', 'paid')->values()->all(),
            ];

            // One block per seat/membership so multi-seat clients (demo A, B, C) stay separate.
            $byChitGroup = $formatted->groupBy(function ($item) {
                return 'group_' . ($item['group_id'] ?? '0') . '_member_' . ($item['member_id'] ?? '0');
            })->map(function ($groupItems) use ($baseClientName, $clientId) {
                // Keep each month row on its own seat — do not merge A+B+C into one line.
                $consolidatedGroupItems = $groupItems->groupBy(function ($item) {
                    return ($item['month_number'] ?? 0) . '_' . ($item['member_id'] ?? 0) . '_' . ($item['id'] ?? 0);
                })->map(function ($monthItems) use ($baseClientName) {
                    $item = $monthItems->first();
                    $item['is_consolidated'] = false;
                    $item['single_amount'] = (float) ($item['share_amount'] ?? $item['amount']);
                    $item['cumulative_amount'] = (float) ($item['share_amount'] ?? $item['amount']);
                    $item['member_numbers'] = $item['member_number'] ?? '';
                    if (empty($item['client_name'])) {
                        $seat = $item['seat_letter'] ?? null;
                        $item['client_name'] = $seat ? trim($baseClientName . ' ' . $seat) : $baseClientName;
                    }

                    return $item;
                })->sortBy('month_number')->values();

                // Determine is_undoable dynamically
                $maxPaidMonth = $consolidatedGroupItems
                    ->filter(fn ($i) => in_array($i['status'], ['paid', 'partial']))
                    ->max('month_number');

                $consolidatedGroupItems = $consolidatedGroupItems->map(function ($inst) use ($maxPaidMonth) {
                    $inst['is_undoable'] = in_array($inst['status'], ['paid', 'partial']) && (int) $inst['month_number'] === (int) $maxPaidMonth;
                    return $inst;
                });

                $first = $groupItems->first();
                $seatLetter = $first['seat_letter'] ?? null;
                $memberNumber = $first['member_number'] ?? null;
                $displayName = $first['client_name']
                    ?? ($seatLetter ? trim($baseClientName . ' ' . $seatLetter) : $baseClientName);

                return [
                    'group_code' => $first['group_code'] ?? 'N/A',
                    'member_id' => $first['member_id'] ?? null,
                    'member_number' => $memberNumber,
                    'seat_letter' => $seatLetter,
                    'is_shared' => $first['is_shared'] ?? false,
                    'ownership_percentage' => $first['ownership_percentage'] ?? 100,
                    'client_display_name' => $displayName,
                    'installments' => $consolidatedGroupItems->all(),
                    'overdue' => $consolidatedGroupItems->where('status', 'overdue')->values()->all(),
                    'pending' => $consolidatedGroupItems->where('status', 'pending')->values()->all(),
                    'upcoming' => $consolidatedGroupItems->where('status', 'upcoming')->values()->all(),
                    'partial' => $consolidatedGroupItems->where('status', 'partial')->values()->all(),
                    'paid' => $consolidatedGroupItems->where('status', 'paid')->values()->all(),
                ];
            })->values()->all();


            $overdueCount = count($grouped['overdue']);
            $pendingCount = count($grouped['pending']);
            $upcomingCount = count($grouped['upcoming']);
            $partialCount = count($grouped['partial']);
            $paidCount = count($grouped['paid']);

            $totalDue = $formatted
                ->filter(fn ($i) => $i['status'] === $status)
                ->sum(function ($i) use ($status) {
                    if ($status === 'paid') {
                        return $i['paid_amount'] ?? 0;
                    }
                    return $i['share_balance'] ?? $i['balance'];
                });

            $groupEntries = $memberships->map(function ($m) use ($seatLetterMap, $baseClientName, $clientId) {
                $seat = $seatLetterMap[$m->id] ?? null;
                $ownPct = $m->ownershipPercentageFor($clientId);
                $label = $this->clientDisplayName($baseClientName, $seat);
                if ($m->is_shared) {
                    $label .= ' (' . rtrim(rtrim(number_format($ownPct, 2), '0'), '.') . '% share)';
                }

                return [
                    'group_code' => $m->group->group_code ?? 'N/A',
                    'member_number' => $m->is_shared ? $m->memberNumberForClient($clientId) : ($m->display_member_number ?? '—'),
                    'group_id' => $m->group_id,
                    'member_id' => $m->id,
                    'seat_letter' => $seat,
                    'is_shared' => (bool) $m->is_shared,
                    'ownership_percentage' => $ownPct,
                    'client_display_name' => $label,
                ];
            })->values();

            $groupsCount = $groupEntries->pluck('group_code')->unique()->count();
            $hasMultipleSeats = $groupEntries->contains(fn ($e) => !empty($e['seat_letter']));
            $isSingleGroup = $groupsCount <= 1;
            $isSharedInAnyGroup = $memberships->contains(fn ($m) => (bool) $m->is_shared)
                || $groupEntries->contains(fn ($e) => !empty($e['is_shared']));
            $sharedGroupCodes = $groupEntries
                ->filter(fn ($e) => !empty($e['is_shared']))
                ->pluck('group_code')
                ->filter()
                ->unique()
                ->values();
            $sharedGroupsLabel = $sharedGroupCodes->isNotEmpty()
                ? $sharedGroupCodes->implode(', ')
                : '';
            $groupsLabel = $isSingleGroup ? 'Same group' : 'More groups';

            if ($isSingleGroup) {
                $code = $groupEntries->first()['group_code'] ?? 'N/A';
                if ($hasMultipleSeats) {
                    $seatBadges = $groupEntries->map(function ($entry) {
                        $letter = $entry['seat_letter'] ?? '';
                        return '<span class="badge bg-label-warning me-1">'
                            . e($entry['client_display_name'])
                            . ' <span class="opacity-75">#' . e((string) $entry['member_number']) . '</span>'
                            . '</span>';
                    })->implode('');
                    $groupsBadge = '<div class="d-flex flex-column gap-1">'
                        . '<small class="text-muted fw-medium">Same group · ' . e($code) . ' (multiple seats)</small>'
                        . '<div>' . $seatBadges . '</div>'
                        . '</div>';
                    $groupsSubtitle = 'Same group · ' . $code . ' · '
                        . $groupEntries->pluck('seat_letter')->filter()->implode(', ');
                } else {
                    $entry = $groupEntries->first();
                    $memberNo = $entry['member_number'] ?? '—';
                    $groupsBadge = '<div class="d-flex flex-column gap-1">'
                        . '<small class="text-muted fw-medium">Same group</small>'
                        . '<span><span class="badge bg-label-primary">' . e($code) . '</span>'
                        . ' <small class="text-muted">Member #' . e((string) $memberNo) . '</small></span>'
                        . '</div>';
                    $groupsSubtitle = 'Same group · ' . $code;
                }
            } else {
                $badges = $groupEntries->map(function ($entry) {
                    $label = $entry['client_display_name'] ?? $entry['group_code'];
                    return '<span class="badge bg-label-primary me-1 mb-1">'
                        . e($entry['group_code'])
                        . ($entry['seat_letter'] ? ' · ' . e($entry['seat_letter']) : '')
                        . ' <span class="opacity-75">#' . e((string) $entry['member_number']) . '</span>'
                        . '</span>';
                })->implode('');
                $groupsBadge = '<div class="d-flex flex-column gap-1">'
                    . '<small class="text-muted fw-medium">More groups (' . $groupsCount . ')</small>'
                    . '<div>' . $badges . '</div>'
                    . '</div>';
                $groupsSubtitle = 'More groups · ' . $groupsCount . ' chit groups';
            }

            $firstCollectible = $formatted->firstWhere('can_collect', true);
            $latestPaidOrPartial = $formatted
                ->filter(fn ($i) => in_array($i['status'], ['paid', 'partial'], true) && $i['paid_amount'] > 0)
                ->sortByDesc('month_number')
                ->first();

            $monthlyInstallmentAmount = $firstCollectible
                ? ($firstCollectible['share_amount'] ?? $firstCollectible['amount'])
                : ($formatted->first()['share_amount'] ?? $formatted->first()['amount'] ?? 0);

            $publicToken = $client ? \App\Support\HashId::encode($client->id) : null;
            $publicLink = $publicToken ? route('public.view-chit-schedule', $publicToken) : null;

            return [
                'id' => $group->client_id,
                'sno' => $start + $index + 1,
                'client_id' => $client ? $client->getRouteKey() : null,
                'client_name' => $baseClientName,
                'client_phone' => $client->client_phone ?? 'N/A',
                'zone' => optional($client)->location ? $client->location->name : 'N/A',
                'group_code' => $groupEntries->pluck('group_code')->unique()->implode(', ') ?: 'N/A',
                'groups_count' => $groupsCount,
                'is_single_group' => $isSingleGroup,
                'has_multiple_seats' => $hasMultipleSeats,
                'is_shared_in_any_group' => $isSharedInAnyGroup,
                'shared_group_codes' => $sharedGroupCodes->all(),
                'shared_groups_label' => $sharedGroupsLabel,
                'groups_label' => $groupsLabel,
                'groups_subtitle' => $groupsSubtitle,
                'groups_badge' => $groupsBadge,
                'member_number' => $groupEntries->pluck('member_number')->filter()->unique()->implode(', ') ?: '—',
                'installment_amount' => (float) $monthlyInstallmentAmount,
                'installment_amount_formatted' => '₹' . number_format((float) $monthlyInstallmentAmount, 2),
                'overdue_count' => $overdueCount,
                'pending_count' => $pendingCount,
                'upcoming_count' => $upcomingCount,
                'partial_count' => $partialCount,
                'paid_count' => $paidCount,
                'total_due' => $totalDue,
                'total_due_formatted' => '₹' . number_format((float) $totalDue, 2),
                'public_token' => $publicToken,
                'public_link' => $publicLink,
                'total_due_formatted' => '₹' . number_format($totalDue, 2),
                'status_summary' => $this->buildStatusSummary($overdueCount, $pendingCount, $upcomingCount, $partialCount, $paidCount, $status),
                'company_phone' => $companyMobile,
                'company_slogan' => $companySlogan,
                'first_collectible' => $firstCollectible,
                'latest_paid' => $latestPaidOrPartial,
                'installments' => $formatted->values()->all(),
                'installments_grouped' => $grouped,
                'installments_by_group' => $byChitGroup,
            ];
        });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalFiltered),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
            'stats' => $stats,
        ]);
    }

    /**
     * Full installment schedule for a single client (all chit groups).
     */
    public function clientShow($client)
    {
        return app(\App\Http\Controllers\ClientViewChitsController::class)->index($client);
    }

    public function show(ChitGroup $group, int $month)
    {
        Installment::applyAutomatedPenalties();

        $group->load('scheme');
        $allInstallments = Installment::with(['member.client', 'member.shares.client', 'group.scheme', 'sharePayments'])
            ->where('group_id', $group->id)
            ->where('month_number', $month)
            ->orderBy('id')
            ->get();

        // Seat letters must consider every enrollment in the group, not only this month's rows.
        $membershipsInGroup = \App\Models\GroupMember::query()
            ->with('client')
            ->where('group_id', $group->id)
            ->whereNotIn('status', ['transferred', 'rejected', 'withdrawn', 'cancelled'])
            ->orderBy('id')
            ->get();
        $seatLetterMap = $this->buildSeatLetterMap($membershipsInGroup);

        $installments = collect();

        foreach ($allInstallments as $inst) {
            if ($inst->member?->is_shared) {
                $shares = $inst->member->resolveOwnershipShares();
                foreach ($shares as $share) {
                    $shareClientId = (int) $share->client_id;
                    $shareClient = $share->client;
                    $pct = rtrim(rtrim(number_format($share->ownership_percentage, 2), '0'), '.');
                    $subAmount = $inst->member->displayAmountForInstallment($inst, $shareClientId);
                    $subPaid = round($inst->clientPaidShare($shareClientId), 2);
                    $subBalance = round($inst->clientBalanceShare($shareClientId), 2);
                    $subPenalty = $inst->member->penaltyAmountForClient((float) $inst->penalty_amount, $shareClientId);
                    if ((float) $inst->paid_amount < 0.01 && abs((float) $inst->amount - $subAmount) > 0.05) {
                        $subBalance = max(0, round($subAmount + $subPenalty - $subPaid, 2));
                    }

                    if ($subBalance <= 0.009 && $subPaid > 0.009) {
                        $ownerStatus = 'paid';
                    } elseif ($subPaid > 0.009 && $subBalance > 0.009) {
                        $ownerStatus = 'partial';
                    } else {
                        $ownerStatus = $inst->status;
                    }

                    $rowMemberNo = $inst->member->memberNumberForClient($shareClientId);
                    $rowClientName = ($shareClient?->client_name ?? 'Shared Member') . " ({$pct}% share)";

                    $shareInst = clone $inst;
                    $shareInst->is_share_row = true;
                    $shareInst->is_consolidated = false;
                    $shareInst->viewing_client_id = $shareClientId;
                    $shareInst->client_share_amount = $subAmount;
                    $shareInst->client_share_paid = $subPaid;
                    $shareInst->client_share_balance = $subBalance;
                    $shareInst->amount = $subAmount;
                    $shareInst->paid_amount = $subPaid;
                    $shareInst->balance = $subBalance;
                    $shareInst->penalty_amount = $subPenalty;
                    $shareInst->status = $ownerStatus;
                    $shareInst->client_display_name = $rowClientName;
                    $shareInst->display_member_number = $rowMemberNo;

                    if ($shareInst->member) {
                        $shareInst->member = clone $shareInst->member;
                        $shareInst->member->display_member_number = $rowMemberNo;
                        $shareInst->member->client_id = $shareClientId;
                        $shareInst->member->client = $shareClient;
                    }

                    $installments->push($shareInst);
                }
            } else {
                $seat = $seatLetterMap[$inst->member_id] ?? null;
                $inst->is_share_row = false;
                $inst->is_consolidated = false;
                $inst->client_display_name = $this->clientDisplayName($this->rawClientName($inst->member), $seat);
                $inst->display_member_number = $inst->member->display_member_number ?? '—';
                $inst->seat_letter = $seat;
                $installments->push($inst);
            }
        }

        $summary = [
            'total'     => $installments->count(),
            'paid'      => $installments->where('status', 'paid')->count(),
            'partial'   => $installments->where('status', 'partial')->count(),
            'pending'   => $installments->where('status', 'pending')->count(),
            'overdue'   => $installments->where('status', 'overdue')->count(),
            'collected' => $installments->sum('paid_amount'),
        ];

        $partialPaymentConfig = $this->partialPaymentConfig->getGlobalSettings();
        $bankAccounts = $this->getBankAccounts();

        $mode = 'show';
        return view('admin.chit.installments.index', compact(
            'group',
            'month',
            'installments',
            'summary',
            'mode',
            'partialPaymentConfig',
            'bankAccounts'
        ));
    }

    public function partialPaymentRules(Request $request, Installment $installment)
    {
        $installment->loadMissing(['member.shares', 'sharePayments']);

        $clientId = (int) $request->input('client_id', 0);
        if ($clientId > 0 && !$installment->member?->involvesClient($clientId)) {
            $clientId = 0;
        }

        return response()->json(
            $this->partialPaymentConfig->rulesForChitInstallment($installment, $clientId ?: null)
        );
    }

    public function collect(Request $request, Installment $installment)
    {
        $request->validate([
            'paid_amount'    => 'required|numeric|min:0.01',
            'payment_mode'   => 'required|in:cash,in_hand,bank_transfer,upi,wallet',
            'payment_type'   => 'required|in:full,partial',
            'paid_date'      => 'required|date|before_or_equal:today',
            'reference_no'   => 'nullable|string|max:100',
            'remarks'        => 'nullable|string|max:500',
            'client_id'      => 'nullable|integer|exists:clients,id',
            'single_seat_only' => 'nullable',
            'internal_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
            // Daily/weekly day-part collections are partials toward the monthly installment.
            'force_frequency_partial' => 'nullable|in:0,1,true,false',
        ]);

        $paymentMode = $request->input('payment_mode');
        if (in_array($paymentMode, ['upi', 'bank_transfer'], true) && ! $request->filled('internal_bank_account_id')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a collection bank account for UPI / Bank Transfer payments.',
                ], 422);
            }

            return back()->withInput()->with('error', 'Please select a collection bank account for UPI / Bank Transfer payments.');
        }

        if ($installment->group?->status === 'terminated' || $installment->member?->status === 'frozen') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Cannot collect payment: Chit Group is terminated / Member account is frozen.'], 422);
            }
            return back()->with('error', 'Cannot collect payment: Chit Group is terminated / Member account is frozen.');
        }

        $user = Auth::user();
        $collectedBy = ($user && $user->hasRole('Agent')) ? optional($user->agent)->id : ($user ? $user->id : null);

        $payload = $request->only([
            'paid_amount', 'payment_mode', 'payment_type', 'paid_date', 'reference_no', 'remarks', 'client_id', 'single_seat_only', 'internal_bank_account_id'
        ]);

        // Day/week slices credit the monthly installment as a partial — skip global % / timing mins.
        $forceFrequencyPartial = filter_var($request->input('force_frequency_partial'), FILTER_VALIDATE_BOOLEAN);
        $memberFreq = $installment->member?->collection_frequency ?? 'monthly';
        if ($forceFrequencyPartial || in_array($memberFreq, ['daily', 'weekly'], true)) {
            if (($payload['payment_type'] ?? '') === 'partial') {
                $payload['bypass_min_validation'] = true;
            }
        }

        $result = $this->paymentService->collectInstallment($installment, $payload, $collectedBy);

        if ($request->expectsJson()) {
            $payingClientId = (int) ($request->input('client_id') ?: ($installment->member?->client_id ?? 0));
            $payingClient = $payingClientId
                ? \App\Models\Client::find($payingClientId)
                : $installment->member?->client;
            $client = $payingClient ?: $installment->member?->client;

            $mobileNo = '';
            if ($client) {
                $mobileNo = $client->client_phone ?? $client->alternate_phone ?? '';
            }
            $cleanMobile = preg_replace('/[^0-9]/', '', $mobileNo);
            if (strlen($cleanMobile) === 10) {
                $cleanMobile = '91' . $cleanMobile;
            }

            $clientId = $payingClientId ?: null;
            $publicToken = $clientId ? \App\Support\HashId::encode($clientId) : null;
            $publicLink = $publicToken ? route('public.view-chit-schedule', $publicToken) : '';

            $smsData = [
                'client_name' => $client ? $client->client_name : 'Client',
                'mobile_no' => $cleanMobile,
                'group_name' => $installment->group ? ($installment->group->group_code ?? $installment->group->name) : '',
                'month_number' => $installment->month_number,
                'amount_paid' => $request->paid_amount,
                'remaining_balance' => $clientId
                    ? $installment->fresh(['member.shares', 'sharePayments'])->clientBalanceShare($clientId)
                    : $installment->balance,
                'client_id' => $clientId,
                'public_link' => $publicLink,
            ];

            $smsData = array_merge($smsData, \App\Helpers\NotificationTemplateHelper::getChitRepaymentMessages($smsData));

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'status'  => $result['status'],
                'sms_data' => $smsData,
            ]);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * Bulk-collect multiple monthly installments (suggested day/week partial or full remaining).
     */
    public function bulkCollect(Request $request)
    {
        Installment::applyAutomatedPenalties();

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.installment_id' => 'required|integer|exists:installments,id',
            'items.*.client_id' => 'nullable|integer|exists:clients,id',
            'items.*.amount' => 'nullable|numeric|min:0.01',
            'items.*.period_index' => 'nullable|integer|min:1',
            'items.*.period_label' => 'nullable|string|max:50',
            'payment_mode' => 'required|in:cash,in_hand,bank_transfer,upi,wallet',
            'paid_date' => 'required|date|before_or_equal:today',
            'reference_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
            'collection_type' => 'nullable|in:suggested,full,period',
            'internal_bank_account_id' => 'nullable|integer|exists:bank_accounts,id',
        ]);

        $paymentMode = $request->input('payment_mode');
        if (in_array($paymentMode, ['upi', 'bank_transfer'], true) && ! $request->filled('internal_bank_account_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a collection bank account for UPI / Bank Transfer payments.',
            ], 422);
        }

        $user = Auth::user();
        $collectedBy = ($user && $user->hasRole('Agent')) ? optional($user->agent)->id : ($user ? $user->id : null);

        $ids = collect($request->input('items'))->pluck('installment_id')->unique()->values();
        $installments = Installment::with(['member.shares', 'member.client', 'group', 'sharePayments'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $collected = 0;
        $totalAmount = 0.0;
        $errors = [];
        $batchKey = BulkPaymentGroup::generateKey();
        $reference = $request->reference_no ?: $batchKey;
        $remarks = BulkPaymentGroup::appendRemarks(
            $request->remarks,
            $batchKey,
            BulkPaymentGroup::CHIT_ADMIN_MARKER
        );
        $collectionType = $request->input('collection_type', 'period');
        $cashbookLines = [];

        // Process Day 1 before Day 2, etc. when multiple periods share one monthly installment.
        $items = collect($request->input('items'))
            ->sortBy(fn ($item) => sprintf(
                '%010d-%05d',
                (int) ($item['installment_id'] ?? 0),
                (int) ($item['period_index'] ?? 0)
            ))
            ->values()
            ->all();

        // "Full remaining" pays once per installment (ignore duplicate period rows).
        if ($collectionType === 'full') {
            $seen = [];
            $items = array_values(array_filter($items, function ($item) use (&$seen) {
                $id = (int) ($item['installment_id'] ?? 0);
                if (isset($seen[$id])) {
                    return false;
                }
                $seen[$id] = true;

                return true;
            }));
        }

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $installmentId = (int) ($item['installment_id'] ?? 0);
                $installment = $installments->get($installmentId);
                if (! $installment) {
                    $errors[] = "Installment #{$installmentId} not found.";
                    continue;
                }

                // Keep balances accurate when paying Day 1 then Day 2 on the same row.
                $installment->refresh();
                $installment->load(['member.shares', 'member.client', 'group', 'sharePayments']);

                if ($installment->group?->status === 'terminated' || $installment->member?->status === 'frozen') {
                    $errors[] = ($installment->member?->client?->client_name ?? 'Member') . ': frozen/terminated.';
                    continue;
                }

                $memberFreq = $installment->member?->collection_frequency ?? 'monthly';
                $groupFreq = $installment->group?->installment_frequency ?? 'monthly';
                $isFreq = in_array($memberFreq, ['daily', 'weekly'], true)
                    && ($groupFreq === 'monthly' || $groupFreq === '');
                if (! $isFreq) {
                    $errors[] = ($installment->member?->client?->client_name ?? 'Member')
                        . ': bulk pay is only for daily/weekly collection.';
                    continue;
                }

                $clientId = (int) ($item['client_id'] ?? 0);
                if ($clientId <= 0) {
                    $clientId = (int) ($installment->member?->client_id ?? 0);
                }
                if ($clientId > 0 && $installment->member && ! $installment->member->involvesClient($clientId)) {
                    $errors[] = ($installment->member->client?->client_name ?? 'Member') . ': client does not own this seat.';
                    continue;
                }

                $balance = $clientId
                    ? round($installment->clientBalanceShare($clientId), 2)
                    : round((float) $installment->balance, 2);

                if ($balance <= 0.009) {
                    continue;
                }

                $amountToPay = $balance;
                $paymentType = 'full';
                $periodLabel = trim((string) ($item['period_label'] ?? ''));

                if ($collectionType !== 'full') {
                    $requested = isset($item['amount']) ? (float) $item['amount'] : 0.0;

                    if ($requested <= 0 && $installment->member) {
                        $shareAmount = $clientId
                            ? $installment->member->displayAmountForInstallment($installment, $clientId)
                            : (float) $installment->amount;
                        $paidShare = $clientId
                            ? round($installment->clientPaidShare($clientId), 2)
                            : (float) $installment->paid_amount;
                        $sched = $installment->member->collectionPeriodSchedule(
                            $installment->due_date,
                            (float) $shareAmount,
                            (float) $paidShare
                        );
                        $periodIndex = (int) ($item['period_index'] ?? 0);
                        $match = $periodIndex > 0
                            ? collect($sched)->firstWhere('index', $periodIndex)
                            : collect($sched)->firstWhere('is_next', true);
                        $requested = $match
                            ? (float) $match['balance']
                            : $installment->member->suggestedCollectionAmount(
                                (float) $shareAmount,
                                $balance,
                                $installment->due_date
                            );
                        if ($match && $periodLabel === '') {
                            $periodLabel = (string) ($match['label'] ?? '');
                        }
                    }

                    $amountToPay = round(min(max(0.01, $requested), $balance), 2);
                    $paymentType = abs($amountToPay - $balance) < 0.01 ? 'full' : 'partial';
                }

                try {
                    $itemRemarks = $remarks;
                    if ($periodLabel !== '') {
                        $itemRemarks .= ' — ' . $periodLabel;
                    }

                    $payload = [
                        'paid_amount' => $amountToPay,
                        'payment_mode' => $paymentMode,
                        'payment_type' => $paymentType,
                        'paid_date' => $request->paid_date,
                        'reference_no' => $reference,
                        'remarks' => $itemRemarks,
                        'client_id' => $clientId ?: null,
                        'single_seat_only' => 1,
                        'internal_bank_account_id' => $request->input('internal_bank_account_id'),
                        'skip_cashbook' => true,
                    ];
                    if ($paymentType === 'partial') {
                        $payload['bypass_min_validation'] = true;
                    }

                    $result = $this->paymentService->collectInstallment($installment, $payload, $collectedBy);
                    $collected++;
                    $totalAmount = round($totalAmount + $amountToPay, 2);

                    $lineKey = ($result['group_code'] ?? 'GRP') . '|' . ($result['client_name'] ?? 'Member');
                    if (! isset($cashbookLines[$lineKey])) {
                        $cashbookLines[$lineKey] = [
                            'group_code' => $result['group_code'] ?? ($installment->group?->group_code ?? 'GRP'),
                            'client_name' => $result['client_name']
                                ?? ($installment->member?->client?->client_name ?? 'Member'),
                            'month' => [],
                        ];
                    }
                    foreach (($result['months'] ?? [(int) $installment->month_number]) as $monthNo) {
                        $cashbookLines[$lineKey]['month'][] = (int) $monthNo;
                    }
                } catch (ValidationException $e) {
                    $name = $installment->member?->client?->client_name ?? 'Member';
                    $errors[] = $name . ': ' . collect($e->errors())->flatten()->first();
                }
            }

            if ($collected === 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => $errors[0] ?? 'No installments could be collected.',
                    'errors' => $errors,
                ], 422);
            }

            if ($totalAmount > 0.009 && ! in_array($paymentMode, ['wallet'], true)) {
                foreach ($cashbookLines as &$line) {
                    $line['month'] = array_values(array_unique($line['month']));
                }
                unset($line);

                app(\App\Services\Account\ChitAccountingService::class)->recordBulkInstallmentCollection(
                    $totalAmount,
                    [
                        'payment_mode' => $paymentMode,
                        'paid_date' => $request->paid_date,
                        'reference_no' => $reference,
                        'internal_bank_account_id' => $request->input('internal_bank_account_id'),
                        'collected_by' => $collectedBy ?? Auth::id(),
                        'lines' => array_values($cashbookLines),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $message = "Collected {$collected} installment(s) totalling ₹" . number_format($totalAmount, 2) . '.';
        if (! empty($errors)) {
            $message .= ' Skipped: ' . implode('; ', $errors);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'collected' => $collected,
            'total_amount' => $totalAmount,
            'errors' => $errors,
        ]);
    }

    public function undo(Request $request, Installment $installment)
    {
        $member = $installment->member;
        if (!$member) {
            return $this->respondUndo($request, 'Unable to resolve the chit membership.', false);
        }

        $requestedClientId = (int) $request->input('client_id', 0);
        if ($requestedClientId > 0 && !$member->involvesClient($requestedClientId)) {
            return $this->respondUndo($request, 'The selected customer is not an owner of this chit membership.', false);
        }

        $clientId = $requestedClientId ?: (int) ($member->client_id ?? 0);
        if (!$clientId) {
            return $this->respondUndo($request, 'Unable to resolve client.', false);
        }

        // Only a named owner's own money is reversed on a shared seat, so undoing one
        // co-owner's payment never wipes out what the other co-owner already paid.
        $perOwner = (bool) $member->is_shared && $requestedClientId > 0;

        $membershipIds = \App\Models\GroupMember::where('group_id', $installment->group_id)
            ->involvingClient($clientId)
            ->pluck('id');

        $installmentsToUndo = Installment::with(['member.shares', 'sharePayments'])
            ->whereIn('member_id', $membershipIds)
            ->where('group_id', $installment->group_id)
            ->where('month_number', $installment->month_number)
            ->get();

        foreach ($installmentsToUndo as $inst) {
            if (!$inst->isUndoable()) {
                return $this->respondUndo($request, 'Cannot undo payment. Please undo succeeding paid installments first.', false);
            }
        }

        DB::transaction(function () use ($installmentsToUndo, $installment, $clientId, $perOwner) {
            $walletRefunds = [];

            foreach ($installmentsToUndo as $inst) {
                $rows = $perOwner
                    ? $inst->sharePayments->where('client_id', $clientId)
                    : $inst->sharePayments;

                $tracked = round((float) $rows->sum('amount'), 2);
                $reversal = $perOwner ? $tracked : round((float) $inst->paid_amount, 2);

                if ($reversal <= 0.009) {
                    continue;
                }

                foreach ($rows as $row) {
                    if ($row->payment_mode === 'wallet') {
                        $walletRefunds[(int) $row->client_id] = round(
                            ($walletRefunds[(int) $row->client_id] ?? 0) + (float) $row->amount,
                            2
                        );
                    }
                }

                // Payments made before per-owner tracking existed carry no share rows,
                // so refund them back across the owners by ownership percentage.
                $untracked = round($reversal - $tracked, 2);
                if ($untracked > 0.009 && $inst->payment_mode === 'wallet') {
                    foreach ($inst->member->allocateAmount($untracked) as $ownerId => $ownerAmount) {
                        if ($ownerAmount > 0.009) {
                            $walletRefunds[(int) $ownerId] = round(($walletRefunds[(int) $ownerId] ?? 0) + $ownerAmount, 2);
                        }
                    }
                }

                // Reverse non-wallet collections in the company cashbook.
                $cashbookReversal = 0.0;
                foreach ($rows as $row) {
                    $mode = strtolower((string) ($row->payment_mode ?? ''));
                    if ($mode && $mode !== 'wallet') {
                        $cashbookReversal = round($cashbookReversal + (float) $row->amount, 2);
                    }
                }
                if ($cashbookReversal <= 0.009 && $untracked > 0.009) {
                    $mode = strtolower((string) ($inst->payment_mode ?? ''));
                    if ($mode && $mode !== 'wallet') {
                        $cashbookReversal = $untracked;
                    }
                }
                if ($cashbookReversal > 0.009) {
                    $revMode = strtolower((string) (
                        $rows->firstWhere(fn ($r) => strtolower((string) $r->payment_mode) !== 'wallet')?->payment_mode
                        ?? $inst->payment_mode
                        ?? 'cash'
                    ));
                    app(\App\Services\Account\ChitAccountingService::class)->reverseInstallmentCollection(
                        $inst,
                        $cashbookReversal,
                        [
                            'payment_mode' => $revMode,
                            'reference_no' => $inst->reference_no,
                        ]
                    );
                }

                InstallmentSharePayment::whereIn('id', $rows->pluck('id'))->delete();

                $newPaid = max(0, round((float) $inst->paid_amount - $reversal, 2));
                $due = round((float) $inst->amount + (float) $inst->penalty_amount, 2);

                if ($newPaid <= 0.009) {
                    $inst->update([
                        'paid_amount'  => 0,
                        'payment_mode' => null,
                        'reference_no' => null,
                        'remarks'      => null,
                        'status'       => $inst->due_date < today() ? 'overdue' : 'pending',
                        'paid_date'    => null,
                        'collected_by' => null,
                    ]);
                } else {
                    $inst->update([
                        'paid_amount' => $newPaid,
                        'status'      => $newPaid >= ($due - 0.01) ? 'paid' : 'partial',
                    ]);
                }
            }

            foreach ($walletRefunds as $refundClientId => $amount) {
                if ($amount > 0.009) {
                    $this->walletService->credit(
                        $refundClientId,
                        $amount,
                        'Chit Installment Payment Reversed',
                        'chit_installment',
                        $installment->id,
                        ['group_id' => $installment->group_id]
                    );
                }
            }
        });

        return $this->respondUndo($request, 'Installment payment undone successfully.');
    }

    protected function respondUndo(Request $request, string $message, bool $success = true)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return $success
            ? back()->with('success', $message)
            : back()->with('error', $message);
    }

    protected function buildFilteredQuery(Request $request)
    {
        $query = Installment::query()
            ->whereHas('group')
            ->whereHas('member');

        $user = Auth::user();
        if ($user && $user->hasRole('Agent')) {
            $agentId = optional($user->agent)->id;
            if ($agentId) {
                $query->whereHas('member', function ($mq) use ($agentId) {
                    $mq->where('referred_by_agent_id', $agentId)
                      ->orWhereHas('client', function ($cq) use ($agentId) {
                          $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                      })
                      ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                          $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                      });
                });
            }
        } elseif ($request->filled('agent_id')) {
            $agentId = $request->input('agent_id');
            $query->whereHas('member', function ($mq) use ($agentId) {
                $mq->where('referred_by_agent_id', $agentId)
                  ->orWhereHas('client', function ($cq) use ($agentId) {
                      $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                  })
                  ->orWhereHas('shares.client', function ($cq) use ($agentId) {
                      $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                  });
            });
        }

        if ($request->filled('group_id')) {
            $query->where('installments.group_id', $request->group_id);
        }
        if ($request->filled('month')) {
            $query->where('installments.month_number', $request->month);
        }
        if ($request->filled('family_id')) {
            $familyId = $request->family_id;
            $this->whereSeatOwner($query, function ($cq) use ($familyId) {
                $cq->whereIn('clients.id', function ($sub) use ($familyId) {
                    $sub->select('client_id')
                        ->from('chit_family_members')
                        ->where('family_id', $familyId);
                });
            });
        }
        if ($request->filled('from_date')) {
            $query->where('installments.due_date', '>=', Carbon::parse($request->from_date)->startOfDay());
        }
        if ($request->filled('to_date')) {
            $query->where('installments.due_date', '<=', Carbon::parse($request->to_date)->endOfDay());
        }
        if ($request->filled('search.value') || $request->filled('client_search')) {
            $search = $request->input('search.value') ?: $request->input('client_search');
            $query->where(function ($q) use ($search) {
                $this->whereSeatOwner($q, function ($cq) use ($search) {
                    $cq->where('clients.client_name', 'LIKE', "%{$search}%")
                        ->orWhere('clients.client_phone', 'LIKE', "%{$search}%");
                });

                $q->orWhereHas('group', function ($gq) use ($search) {
                    $gq->where('group_code', 'LIKE', "%{$search}%")
                        ->orWhereHas('scheme', function ($sq) use ($search) {
                            $sq->where('name', 'LIKE', "%{$search}%");
                        });
                });
            });
        }

        return $query;
    }

    /**
     * The listing shows one row per owner, so a shared seat surfaces under each
     * co-owner. Client filters must therefore match the seat's primary client or
     * any of its share holders, otherwise co-owners silently disappear.
     */
    protected function whereSeatOwner($query, callable $clientConstraint): void
    {
        $query->whereHas('member', function ($mq) use ($clientConstraint) {
            $mq->whereHas('client', $clientConstraint)
                ->orWhereHas('shares.client', $clientConstraint);
        });
    }

    /**
     * whereSeatOwner() keeps every installment of a matching seat, which would also
     * surface the co-owners of that seat as their own rows. Restrict the listing to
     * the owner the filter was actually about.
     *
     * @param string $clientIdExpr SQL expression resolving each row to its owner.
     */
    protected function applyRowOwnerFilters($query, Request $request, string $clientIdExpr): void
    {
        if ($request->filled('family_id')) {
            $familyId = $request->family_id;
            $query->whereIn(DB::raw($clientIdExpr), function ($sub) use ($familyId) {
                $sub->select('client_id')
                    ->from('chit_family_members')
                    ->where('family_id', $familyId);
            });
        }

        $search = $request->filled('search.value')
            ? $request->input('search.value')
            : $request->input('client_search');

        if ($search === null || trim((string) $search) === '') {
            return;
        }

        // A group or scheme match is not about one owner, so it must not be narrowed.
        $query->where(function ($q) use ($search, $clientIdExpr) {
            $q->whereIn(DB::raw($clientIdExpr), function ($sub) use ($search) {
                $sub->select('id')
                    ->from('clients')
                    ->whereNull('deleted_at')
                    ->where(function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%");
                    });
            })->orWhereHas('group', function ($gq) use ($search) {
                $gq->where('group_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('scheme', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });
    }

    /**
     * Tab filters use each owner's share paid/balance so co-owners are not
     * forced into Partial/Paid together when only one of them paid.
     *
     * @param  string|null  $paidShareExpr  SQL for this row-owner's paid share
     * @param  string|null  $ownerBalanceExpr  SQL for this row-owner's remaining share
     */
    protected function applyStatusFilter(
        $query,
        ?string $status,
        Carbon $today,
        ?string $paidShareExpr = null,
        ?string $ownerBalanceExpr = null
    ): void {
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $todayDate = $today->toDateString();

        if (empty($status) || $status === 'all') {
            return;
        }

        $useOwnerShare = $paidShareExpr && $ownerBalanceExpr;

        if ($status === 'paid') {
            if ($useOwnerShare) {
                $query->where(function ($q) use ($paidShareExpr, $ownerBalanceExpr) {
                    $q->where(function ($q2) use ($paidShareExpr, $ownerBalanceExpr) {
                        // This owner's share is fully covered.
                        $q2->whereRaw("{$ownerBalanceExpr} <= 0.009")
                            ->whereRaw("{$paidShareExpr} > 0.009");
                    })->orWhere(function ($q2) use ($ownerBalanceExpr) {
                        // Seat marked paid/waived and this owner has nothing left.
                        $q2->whereIn('installments.status', ['paid', 'waived'])
                            ->whereRaw("{$ownerBalanceExpr} <= 0.009");
                    });
                });
            } else {
                $query->where('installments.status', 'paid');
            }
            return;
        }

        if ($status === 'partial') {
            if ($useOwnerShare) {
                $query->whereRaw("{$paidShareExpr} > 0.009")
                    ->whereRaw("{$ownerBalanceExpr} > 0.009");
            } else {
                $query->where('installments.status', 'partial');
            }
            return;
        }

        if ($status === 'overdue') {
            $query->whereDate('installments.due_date', '<', $todayDate);
            if ($useOwnerShare) {
                // Still owes, and has not started paying their own share.
                $query->whereRaw("{$ownerBalanceExpr} > 0.009")
                    ->whereRaw("{$paidShareExpr} <= 0.009")
                    ->whereNotIn('installments.status', ['paid', 'waived']);
            } else {
                $query->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            }
            return;
        }

        if ($status === 'pending') {
            $query->whereDate('installments.due_date', '>=', $todayDate)
                ->whereDate('installments.due_date', '<=', $monthEnd);
            if ($useOwnerShare) {
                $query->whereRaw("{$ownerBalanceExpr} > 0.009")
                    ->whereRaw("{$paidShareExpr} <= 0.009")
                    ->whereNotIn('installments.status', ['paid', 'waived']);
            } else {
                $query->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            }
            return;
        }

        if ($status === 'upcoming') {
            $query->whereDate('installments.due_date', '>', $monthEnd);
            if ($useOwnerShare) {
                $query->whereRaw("{$ownerBalanceExpr} > 0.009")
                    ->whereRaw("{$paidShareExpr} <= 0.009")
                    ->whereNotIn('installments.status', ['paid', 'waived']);
            } else {
                $query->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            }
        }
    }

    protected function computeStats($baseQuery): array
    {
        $today = Carbon::now()->startOfDay();
        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $todayDate = $today->toDateString();

        $paidCount = (clone $baseQuery)->where('installments.status', 'paid')->count();

        $overdueCount = (clone $baseQuery)
            ->whereDate('installments.due_date', '<', $todayDate)
            ->whereIn('installments.status', ['pending', 'overdue', 'partial'])
            ->count();

        $pendingCount = (clone $baseQuery)
            ->whereDate('installments.due_date', '>=', $todayDate)
            ->whereDate('installments.due_date', '<=', $monthEnd)
            ->whereIn('installments.status', ['pending', 'overdue', 'partial'])
            ->count();

        $upcomingCount = (clone $baseQuery)
            ->whereDate('installments.due_date', '>', $monthEnd)
            ->whereIn('installments.status', ['pending', 'overdue', 'partial'])
            ->count();

        $partialCount = (clone $baseQuery)
            ->where('installments.status', 'partial')
            ->count();

        $totalCollected = (clone $baseQuery)->where('installments.status', 'paid')->sum('installments.paid_amount');

        $pendingDueQuery = (clone $baseQuery)
            ->whereIn('installments.status', ['pending', 'overdue', 'partial']);
        $pendingBase = $pendingDueQuery->toBase();
        $pendingBase->columns = null;
        $pendingBase->bindings['select'] = [];
        $totalPending = (float) $pendingBase
            ->selectRaw('COALESCE(SUM(GREATEST(0, (amount + COALESCE(penalty_amount, 0) - COALESCE(paid_amount, 0)))), 0) as aggregate')
            ->value('aggregate');

        return [
            'total_installments' => $overdueCount + $pendingCount + $upcomingCount + $partialCount + $paidCount,
            'paid_installments' => $paidCount,
            'pending_installments' => $pendingCount,
            'upcoming_installments' => $upcomingCount,
            'partial_installments' => $partialCount,
            'overdue_installments' => $overdueCount,
            'total_collected' => (float) $totalCollected,
            'total_pending' => $totalPending,
        ];
    }

    /**
     * Map member_id => seat letter (A, B, C...) when client has multiple seats in same group.
     */
    protected function buildSeatLetterMap($memberships): array
    {
        $map = [];
        $memberships->groupBy(fn ($m) => $m->group_id . ':' . $m->client_id)->each(function ($siblings) use (&$map) {
            if ($siblings->count() <= 1) {
                return;
            }
            $siblings->sortBy('id')->values()->each(function ($member, $index) use (&$map) {
                $map[$member->id] = chr(65 + $index);
            });
        });

        return $map;
    }

    protected function rawClientName(?\App\Models\GroupMember $member): string
    {
        $client = $member?->getRelationValue('client');

        return (string) (
            ($client ? ($client->getAttributes()['client_name'] ?? null) : null)
            ?? '—'
        );
    }

    protected function clientDisplayName(?string $baseName, ?string $seatLetter): string
    {
        $name = $baseName ?: '—';
        return $seatLetter ? trim($name) . ' ' . $seatLetter : $name;
    }

    protected function formatInstallmentRow(
        Installment $inst,
        array $partialPaymentConfig,
        Carbon $today,
        ?string $companyMobile = null,
        ?string $companySlogan = null,
        ?string $seatLetter = null,
        ?int $viewingClientId = null
    ): array {
        $displayStatus = $this->resolveDisplayStatus($inst, $today);

        $freq = $inst->group->installment_frequency ?? 'monthly';
        $periodLabel = match ($freq) {
            'daily' => 'Day ' . $inst->month_number,
            'weekly' => 'Week ' . $inst->month_number,
            default => 'Month ' . $inst->month_number,
        };

        $balance = round((float) $inst->balance, 2);
        $canCollect = $inst->isCollectible();
        $isSettled = in_array($inst->status, ['paid', 'waived'], true);
        $isForeman = $inst->isForemanCommission();
        $primaryClient = $inst->member?->getRelationValue('client');

        // For shared seats, each owner row must show THAT owner's name — not the primary member's.
        $viewingClient = null;
        if ($viewingClientId && $inst->member) {
            $share = $inst->member->relationLoaded('shares')
                ? $inst->member->shares->firstWhere('client_id', (int) $viewingClientId)
                : $inst->member->shares()->with('client')->where('client_id', (int) $viewingClientId)->first();
            $viewingClient = $share?->getRelationValue('client')
                ?? $share?->client
                ?? ((int) ($inst->member->client_id) === (int) $viewingClientId ? $primaryClient : null);

            if (! $viewingClient) {
                $viewingClient = \App\Models\Client::find((int) $viewingClientId);
            }
        }

        $baseClientName = (string) (
            ($viewingClient ? ($viewingClient->getAttributes()['client_name'] ?? $viewingClient->client_name ?? null) : null)
            ?? ($primaryClient ? ($primaryClient->getAttributes()['client_name'] ?? $primaryClient->client_name ?? null) : null)
            ?? '—'
        );
        $clientPhone = $viewingClient?->client_phone ?? $primaryClient?->client_phone ?? null;

        // Strip accidental trailing seat letter from accessor if present
        if ($seatLetter && str_ends_with(trim((string) $baseClientName), ' ' . $seatLetter)) {
            $baseClientName = trim(substr(trim($baseClientName), 0, -2));
        }

        $displayName = $this->clientDisplayName($baseClientName, $seatLetter);
        $isShared = (bool) ($inst->member?->is_shared);
        $ownershipPct = 100.0;
        if ($viewingClientId && $inst->member) {
            $ownershipPct = $inst->member->ownershipPercentageFor($viewingClientId);
            if ($isShared) {
                $displayName .= ' (' . rtrim(rtrim(number_format($ownershipPct, 2), '0'), '.') . '%)';
            }
        } elseif ($isShared && $inst->member && ! $viewingClientId) {
            $displayName = $inst->member->owners_display;
        }

        // Consolidated rows carry summed amounts across seats, so their per-owner split
        // has to stay proportional; only real rows can use the share-payment ledger.
        $shareBalance = $viewingClientId && $inst->member
            ? ($inst->is_consolidated
                ? $inst->member->amountForClient($balance, $viewingClientId)
                : $inst->clientBalanceShare($viewingClientId))
            : $balance;
        $shareAmount = $inst->member
            ? $inst->member->displayAmountForInstallment($inst, $viewingClientId ?: null)
            : (float) $inst->amount;
        $sharePenalty = $inst->member
            ? $inst->member->penaltyAmountForClient((float) $inst->penalty_amount, $viewingClientId ?: null)
            : (float) $inst->penalty_amount;
        $sharePaid = $viewingClientId && $inst->member && !$inst->is_consolidated
            ? $inst->clientPaidShare($viewingClientId)
            : (float) $inst->paid_amount;

        if (
            $viewingClientId
            && $inst->member
            && ! $inst->is_consolidated
            && (float) $inst->paid_amount < 0.01
            && abs((float) $inst->amount - $shareAmount) > 0.05
        ) {
            $shareBalance = max(0, round($shareAmount + $sharePenalty - $sharePaid, 2));
        }

        $amountFormatted = '₹' . number_format($shareAmount, 2);
        $shareAmountFormatted = '₹' . number_format($shareAmount, 2);
        $displayBalance = $viewingClientId ? $shareBalance : (
            // Non-client list: still show independent-share-scaled seat amount when needed
            abs((float) $inst->amount - $shareAmount) > 0.05 ? $shareAmount : $balance
        );
        $displayStatusForClient = $displayStatus;
        if ($viewingClientId && ! $inst->is_consolidated) {
            if ($shareBalance <= 0.009 && $sharePaid > 0.009) {
                $displayStatusForClient = 'paid';
            } elseif ($sharePaid > 0.009 && $shareBalance > 0.009) {
                $displayStatusForClient = 'partial';
            } elseif ($shareBalance > 0.009) {
                // Unpaid co-owner: ignore seat-level partial/paid — bucket by due date only.
                $displayStatusForClient = $this->resolveDueDateBucket($inst, $today);
            } elseif (in_array($inst->status, ['waived'], true)) {
                $displayStatusForClient = 'waived';
            } else {
                $displayStatusForClient = 'paid';
            }
        }

        $partialRulesUrl = route('chit.installments.partial-rules', $inst);
        if ($viewingClientId) {
            $partialRulesUrl .= '?client_id=' . $viewingClientId;
        }

        $isFreqMember = in_array($inst->member?->collection_frequency ?? 'monthly', ['daily', 'weekly'], true);
        $periods = $this->formatCollectionPeriods(
            $inst,
            $shareAmount,
            $sharePaid,
            $displayBalance,
            $displayStatusForClient
        );
        $nextPeriod = collect($periods)->firstWhere('is_next', true);

        return [
            'id' => $inst->id,
            'member_id' => $inst->member_id,
            'group_id' => $inst->group_id,
            'group_code' => $inst->group->group_code ?? 'N/A',
            'seat_letter' => $seatLetter,
            'is_shared' => $isShared,
            'ownership_percentage' => $ownershipPct,
            'month_number' => $inst->month_number,
            'period_label' => $periodLabel,
            'due_date' => $inst->due_date ? $inst->due_date->format('d-m-Y') : '—',
            'due_month_year' => $inst->due_date ? $inst->due_date->format('M Y') : '',
            'amount' => $shareAmount,
            'amount_formatted' => $amountFormatted,
            'is_foreman_commission' => $isForeman,
            'is_client_wise_foreman' => $inst->group?->usesClientWiseForemanCommission()
                && (int) $inst->month_number === (int) ($inst->group->scheme?->clientWiseForemanCollectionMonth() ?? 0),
            'client_wise_foreman_amount' => $inst->group
                ? $inst->group->clientWiseForemanExtraForShare(
                    (int) $inst->month_number,
                    (float) ($inst->share_percentage ?? $inst->member?->effective_share_percentage ?? 100)
                )
                : 0.0,
            'share_amount' => $shareAmount,
            'share_amount_formatted' => $shareAmountFormatted,
            'penalty_amount' => $sharePenalty,
            'penalty_formatted' => $sharePenalty > 0 ? '₹' . number_format($sharePenalty, 2) : '—',
            'paid_amount' => $sharePaid,
            'paid_formatted' => $sharePaid > 0 ? '₹' . number_format($sharePaid, 2) : '—',
            'balance' => $displayBalance,
            'balance_formatted' => $displayBalance > 0.009 ? '₹' . number_format($displayBalance, 2) : '—',
            'share_balance' => $shareBalance,
            'share_balance_formatted' => $shareBalance > 0.009 ? '₹' . number_format($shareBalance, 2) : '—',
            'paid_date' => $inst->paid_date ? $inst->paid_date->format('d M Y') : null,
            'paid_date_formatted' => $inst->paid_date ? $inst->paid_date->format('d-m-Y') : null,
            'status' => $displayStatusForClient,
            'status_badge' => $this->getStatusBadge($displayStatusForClient),
            'can_collect' => $canCollect && $displayBalance > 0.009,
            'is_settled' => in_array($displayStatusForClient, ['paid', 'waived'], true),
            'client_name' => $displayName,
            'client_base_name' => $baseClientName,
            'client_phone' => $clientPhone,
            'member_number' => $inst->member_numbers ?? ($inst->member->display_member_number ?? '—'),
            'member_numbers' => $inst->member_numbers ?? ($inst->member->display_member_number ?? '—'),
            'is_consolidated' => !empty($inst->is_consolidated),
            'single_amount' => (float) ($inst->single_amount ?? $shareAmount),
            'cumulative_amount' => (float) ($inst->cumulative_amount ?? $shareAmount),
            'client_id' => $viewingClientId ?: ($inst->member ? $inst->member->client_id : null),
            'public_token' => ($viewingClientId ?: ($inst->member ? $inst->member->client_id : null)) ? \App\Support\HashId::encode($viewingClientId ?: $inst->member->client_id) : null,
            'public_link' => ($viewingClientId ?: ($inst->member ? $inst->member->client_id : null)) ? route('public.view-chit-schedule', \App\Support\HashId::encode($viewingClientId ?: $inst->member->client_id)) : null,
            'company_phone' => $companyMobile,
            'company_slogan' => $companySlogan,
            'collect_url' => route('chit.installments.collect', $inst),
            'partial_rules_url' => $partialRulesUrl,
            'undo_url' => route('chit.installments.undo', $inst),
            'is_undoable' => $inst->isUndoable($viewingClientId) && $sharePaid > 0.009,
            'partial_enabled' => (bool) ($partialPaymentConfig['is_active'] ?? false) || $isFreqMember,
            'min_percentage' => $isFreqMember ? 1 : ($partialPaymentConfig['minimum_partial_percentage'] ?? 10),
            'penalty_method' => $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount',
            'collection_frequency' => $inst->member?->collection_frequency ?? 'monthly',
            'collection_frequency_label' => $inst->member?->collection_frequency_label ?? 'Monthly',
            'collection_split_count' => $inst->member
                ? $inst->member->collectionSplitCount($inst->due_date)
                : 1,
            'collection_split_amount' => $inst->member
                ? $inst->member->collectionSplitAmount($shareAmount, $inst->due_date)
                : $shareAmount,
            'suggested_collection_amount' => $nextPeriod
                ? (float) $nextPeriod['balance']
                : ($inst->member
                    ? $inst->member->suggestedCollectionAmount($shareAmount, (float) $displayBalance, $inst->due_date)
                    : (float) $displayBalance),
            'collection_periods' => $periods,
            'next_period' => $nextPeriod,
            'force_frequency_partial' => ! empty($periods),
        ];
    }

    /**
     * Serialize daily/weekly collection slices for installment UI.
     */
    protected function formatCollectionPeriods(
        Installment $inst,
        float $shareAmount,
        float $sharePaid,
        float $displayBalance,
        string $displayStatus
    ): array {
        $member = $inst->member;
        if (! $member) {
            return [];
        }

        $groupFreq = $inst->group->installment_frequency ?? 'monthly';
        $memberFreq = $member->collection_frequency ?? 'monthly';
        if (! in_array($memberFreq, ['daily', 'weekly'], true)) {
            return [];
        }
        if ($groupFreq !== 'monthly' && $groupFreq !== '') {
            return [];
        }

        $periods = $member->collectionPeriodSchedule($inst->due_date, $shareAmount, $sharePaid);

        return array_map(static function (array $p) {
            return [
                'index' => $p['index'],
                'label' => $p['label'],
                'due_date' => $p['due_date'] ? $p['due_date']->format('d M Y') : '—',
                'period_end' => ! empty($p['period_end']) ? $p['period_end']->format('d M Y') : '—',
                'amount' => $p['amount'],
                'amount_formatted' => '₹' . number_format($p['amount'], 2),
                'paid' => $p['paid'],
                'paid_formatted' => '₹' . number_format($p['paid'], 2),
                'balance' => $p['balance'],
                'balance_formatted' => $p['balance'] > 0.009 ? '₹' . number_format($p['balance'], 2) : '—',
                'status' => $p['status'],
                'is_next' => $p['is_next'],
                'is_current' => (bool) ($p['is_current'] ?? false),
            ];
        }, $periods);
    }

    /**
     * Map installment to UI bucket: overdue | pending (this month) | upcoming | partial | paid.
     */
    protected function resolveDisplayStatus(Installment $inst, Carbon $today): string
    {
        if ($inst->status === 'paid') {
            return 'paid';
        }

        if ($inst->status === 'waived') {
            return 'waived';
        }

        $dueDate = $inst->due_date ? $inst->due_date->copy()->startOfDay() : null;
        if (! $dueDate) {
            return $inst->status === 'partial' ? 'partial' : ($inst->status ?: 'pending');
        }

        // Past-due open installments stay in Overdue even if partially paid at seat level.
        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        if ($inst->status === 'partial') {
            return 'partial';
        }

        return $this->resolveDueDateBucket($inst, $today);
    }

    /**
     * Due-date bucket only (overdue / pending / upcoming). Used for unpaid co-owners
     * so they are not pulled into Partial when another share owner has paid.
     */
    protected function resolveDueDateBucket(Installment $inst, Carbon $today): string
    {
        $dueDate = $inst->due_date ? $inst->due_date->copy()->startOfDay() : null;
        if (! $dueDate) {
            return 'pending';
        }

        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        $monthEnd = $today->copy()->endOfMonth()->startOfDay();
        if ($dueDate->lte($monthEnd)) {
            return 'pending';
        }

        return 'upcoming';
    }

    protected function buildStatusSummary(int $overdue, int $pending, int $upcoming, int $partial, int $paid, ?string $activeStatus = null): string
    {
        $parts = [];
        $showAll = empty($activeStatus) || $activeStatus === 'all';

        if (($showAll || $activeStatus === 'overdue') && $overdue > 0) {
            $parts[] = '<span class="badge bg-label-danger me-1">' . $overdue . ' Overdue</span>';
        }
        if (($showAll || $activeStatus === 'pending') && $pending > 0) {
            $parts[] = '<span class="badge bg-label-warning me-1">' . $pending . ' Pending</span>';
        }
        if (($showAll || $activeStatus === 'upcoming') && $upcoming > 0) {
            $parts[] = '<span class="badge bg-label-secondary me-1">' . $upcoming . ' Upcoming</span>';
        }
        if (($showAll || $activeStatus === 'partial') && $partial > 0) {
            $parts[] = '<span class="badge bg-label-info me-1">' . $partial . ' Partial</span>';
        }
        if (($showAll || $activeStatus === 'paid') && $paid > 0) {
            $parts[] = '<span class="badge bg-label-success me-1">' . $paid . ' Paid</span>';
        }

        return $parts ? implode('', $parts) : '<span class="text-muted">No installments</span>';
    }

    protected function getStatusBadge(string $status): string
    {
        $map = [
            'paid' => ['label' => 'Paid', 'color' => 'success'],
            'partial' => ['label' => 'Partial', 'color' => 'info'],
            'pending' => ['label' => 'Pending', 'color' => 'warning'],
            'upcoming' => ['label' => 'Upcoming', 'color' => 'secondary'],
            'overdue' => ['label' => 'Overdue', 'color' => 'danger'],
            'waived' => ['label' => 'Waived', 'color' => 'secondary'],
        ];
        $meta = $map[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary'];

        return sprintf('<span class="badge bg-label-%s">%s</span>', $meta['color'], $meta['label']);
    }

    protected function getBankAccounts()
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();
    }
}
