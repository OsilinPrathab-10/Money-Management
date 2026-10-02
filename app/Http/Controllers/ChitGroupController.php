<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\AuctionBid;
use App\Models\ChitGroup;
use App\Models\ChitScheme;
use App\Models\Client;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\Branch;
use App\Models\ChitCollection;
use App\Models\Dividend;
use App\Models\ChitDividendPoolEntry;
use App\Models\Payout;
use App\Models\ChitMemberTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChitGroupController extends Controller
{
    public function index(Request $request)
    {
        $isTrash = $request->trash === 'true';

        if ($isTrash) {
            $query = ChitGroup::onlyTrashed()->with(['scheme' => fn ($q) => $q->withTrashed(), 'branch', 'groupLeader']);
        } else {
            $query = ChitGroup::with(['scheme', 'branch', 'groupLeader']);
        }

        if ($request->filled('client_id')) {
            $clientId = (int) $request->client_id;
            $query->whereHas('members', fn ($m) => $m->involvingClient($clientId));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('group_code', 'like', "%{$s}%")
                    ->orWhereHas('members', function ($m) use ($s) {
                        $m->where(function ($mq) use ($s) {
                            $mq->whereHas('client', function ($c) use ($s) {
                                $c->where(function ($cq) use ($s) {
                                    $cq->where('client_name', 'like', "%{$s}%")
                                        ->orWhere('client_phone', 'like', "%{$s}%");
                                });
                            })->orWhereHas('shares.client', function ($c) use ($s) {
                                $c->where(function ($cq) use ($s) {
                                    $cq->where('client_name', 'like', "%{$s}%")
                                        ->orWhere('client_phone', 'like', "%{$s}%");
                                });
                            });
                        });
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('scheme_type')) {
            $query->where('scheme_type', $request->scheme_type);
        }

        $allowedSorts = [
            'id'                   => 'id',
            'group_code'           => 'group_code',
            'scheme'               => 'scheme_id',
            'scheme_type'          => 'scheme_type',
            'chit_value'           => 'chit_value',
            'total_members'        => 'total_members',
            'installment_frequency'=> 'installment_frequency',
            'start_date'           => 'start_date',
            'end_date'             => 'end_date',
            'current_month'        => 'current_month',
            'status'               => 'status',
        ];

        $sortField = $request->input('sort', 'id');
        $sortDir   = strtolower($request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (array_key_exists($sortField, $allowedSorts)) {
            $query->orderBy($allowedSorts[$sortField], $sortDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $groups   = $query->paginate(15)->withQueryString();
        foreach ($groups as $g) {
            $g->syncEndDate();
            $g->repairSkippedYearInstallmentDates();
        }
        $branches = Branch::all();
        $schemes  = ChitScheme::where('status', 'active')->get();
        $mode     = $isTrash ? 'trash' : 'index';
        $trashedGroupsCount = ChitGroup::onlyTrashed()->count();
        $filterClients = Client::query()
            ->where(function ($q) {
                $q->whereHas('groupMembers')
                    ->orWhereHas('chitMembershipShares');
            })
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'client_phone']);
        $selectedClient = $request->filled('client_id')
            ? $filterClients->firstWhere('id', (int) $request->client_id)
            : null;

        return view('admin.chit.groups.index', compact(
            'groups',
            'branches',
            'schemes',
            'mode',
            'sortField',
            'sortDir',
            'trashedGroupsCount',
            'filterClients',
            'selectedClient'
        ));
    }

    public function create(Request $request)
    {
        $schemes   = ChitScheme::where('status', 'active')->get();
        $branches  = Branch::all();
        $clients   = Client::orderBy('client_name')->get();
        $mode      = 'create';
        $preselectedSchemeId = old('scheme_id', $request->query('scheme_id'));
        $lockedScheme = null;
        if ($preselectedSchemeId) {
            $lockedScheme = $schemes->firstWhere('id', (int) $preselectedSchemeId)
                ?? ChitScheme::find($preselectedSchemeId);
        }

        return view('admin.chit.groups.index', compact(
            'schemes',
            'branches',
            'clients',
            'mode',
            'preselectedSchemeId',
            'lockedScheme'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'scheme_id'       => 'required|exists:chit_schemes,id',
            'start_date'      => 'required|date',
            'branch_id'       => 'nullable|exists:branches,id',
            'auction_type'    => 'required|in:open,closed',
            'group_leader_id' => 'nullable|exists:clients,id',
            'remarks'         => 'nullable|string',
            'referral_commission_pct' => 'nullable|numeric|min:0|max:100',
            'registration_type'              => 'required|in:non_registered,registered',
            'registration_number'             => 'nullable|string|max:100',
            'registration_date'               => 'nullable|date',
            'registering_authority'           => 'nullable|string|max:150',
            'registration_office'             => 'nullable|string|max:150',
            'registration_certificate_number' => 'nullable|string|max:100',
            'registration_valid_from'         => 'nullable|date',
            'registration_valid_until'        => 'nullable|date',
        ]);

        // Clear detail fields if non-registered
        if (($validated['registration_type'] ?? 'non_registered') !== 'registered') {
            foreach (['registration_number','registration_date','registering_authority','registration_office','registration_certificate_number','registration_valid_from','registration_valid_until'] as $field) {
                $validated[$field] = null;
            }
        }

        $scheme = ChitScheme::findOrFail($validated['scheme_id']);

        DB::transaction(function () use ($validated, $scheme) {
            $startDate = \Carbon\Carbon::parse($validated['start_date'])->startOfDay();
            $totalMonths = max(1, (int) $scheme->duration_months);
            $frequency = $scheme->installment_frequency ?? 'monthly';

            $group = ChitGroup::create([
                'group_code'          => ChitGroup::generateCode(),
                'scheme_id'           => $scheme->id,
                'branch_id'           => $validated['branch_id'],
                'start_date'          => $startDate->toDateString(),
                // Inclusive of start month: N months starting on start_date ends at start + (N-1).
                'end_date'            => ChitGroup::calculateEndDate($startDate, $totalMonths, $frequency)->toDateString(),
                'current_month'       => 0,
                'total_months'        => $totalMonths,
                'chit_value'          => $scheme->chit_value,
                'total_members'       => $scheme->total_members,
                'installment_amount'  => $scheme->installment_amount,
                'commission_pct'      => $scheme->commission_pct,
                'referral_commission_pct' => $validated['referral_commission_pct'] ?? $scheme->referral_commission_pct,
                'auction_type'        => $validated['auction_type'],
                'scheme_type'             => $scheme->scheme_type,
                'registration_type'       => $validated['registration_type'] ?? 'non_registered',
                'registration_number'     => $validated['registration_number'] ?? null,
                'registration_date'       => $validated['registration_date'] ?? null,
                'registering_authority'   => $validated['registering_authority'] ?? null,
                'registration_office'     => $validated['registration_office'] ?? null,
                'registration_certificate_number' => $validated['registration_certificate_number'] ?? null,
                'registration_valid_from' => $validated['registration_valid_from'] ?? null,
                'registration_valid_until'=> $validated['registration_valid_until'] ?? null,
                'installment_frequency'   => $frequency,
                'fixed_return_amount'     => $scheme->fixed_return_amount,
                'is_private'              => $scheme->is_private,
                'group_leader_id'         => $validated['group_leader_id'] ?? null,
                'status'              => 'forming',
                'remarks'             => $validated['remarks'] ?? null,
                'created_by'          => Auth::id(),
            ]);

            try {
                if (class_exists('\App\Services\PushNotificationService')) {
                    $notifService = app(\App\Services\PushNotificationService::class);
                    $users = \App\Models\User::where('role', 'customer')->whereNotNull('fcm_token')->get();
                    
                    foreach ($users as $user) {
                        $notifService->sendToUser(
                            $user->id,
                            'New Chit Group Available!',
                            "A new chit group {$group->group_code} ({$scheme->name}) has been created. Join now to start saving!",
                            'new_group',
                            ['group_id' => $group->id, 'notifiable_type' => 'chit_group', 'notifiable_id' => $group->id]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('New Group Notification Failed: ' . $e->getMessage());
            }
        });

        return redirect()->route('chit.groups.index')->with('success', 'Chit group created successfully!');
    }

    public function show(Request $request, ChitGroup $group)
    {
        $group->load([
            'scheme', 'branch',
            'members.client.agent', 'members.shares.client.agent',
            'members.transferredTo.client', 'members.transferredFrom.client',
            'members.outgoingTransfer', 'members.incomingTransfer',
            'members.installments' => fn ($q) => $q->where('group_id', $group->id)
                ->with('sharePayments')
                ->orderBy('month_number'),
            'members.referrerAgent', 'members.referrerClient',
            'auctions', 'payouts.winner.client', 'dividends', 'groupLeader',
        ]);

        // Shared seats expand to one row per co-owner (e.g. #4 and #4.1) with separate payments.
        // Sequence by applied / settlement month (highest month first).
        $sortOption = $request->get('sort', 'default');

        $memberDisplayRows = $group->members
            ->whereNotIn('status', ['transferred', 'rejected', 'withdrawn', 'cancelled'])
            ->values()
            ->flatMap(fn ($member) => $member->ownerDisplayRows());

        if ($sortOption === 'sl_asc') {
            $memberDisplayRows = $memberDisplayRows->sortBy([
                [fn ($row) => (int) ($row->member->member_number ?? 0), 'asc'],
                [fn ($row) => (string) ($row->row_key ?? ''), 'asc'],
            ]);
        } elseif ($sortOption === 'sl_desc') {
            $memberDisplayRows = $memberDisplayRows->sortBy([
                [fn ($row) => (int) ($row->member->member_number ?? 0), 'desc'],
                [fn ($row) => (string) ($row->row_key ?? ''), 'asc'],
            ]);
        } else {
            // Default sorting: active applied settlement month first, then preferred need month
            $memberDisplayRows = $memberDisplayRows->sortBy([
                [fn ($row) => $row->member->displaySettlementMonthNumber() ?? $row->member->preferredChitNeedPeriod() ?? -1, 'desc'],
                [fn ($row) => (int) ($row->member->member_number ?? 0), 'asc'],
                [fn ($row) => (string) ($row->row_key ?? ''), 'asc'],
            ]);
        }

        $memberDisplayRows = $memberDisplayRows->values();

        $settlementService = app(\App\Services\ChitPayoutService::class);

        // Align operating month with start_date calendar; mark past unpaid as overdue;
        // advance when the period is fully collected.
        $group->syncEndDate();
        $group->repairSkippedYearInstallmentDates();
        $operatingMonth = $group->syncOperatingMonth();
        $calendarMonth = $group->resolveCalendarPeriodNumber();
        $periodOptions = $group->periodFilterOptions();

        $nextSettlementMonth = $group->getNextSettlementMonth();
        $currentMonthSettlement = $group->getCurrentMonthSettlement();
        $canInitiateSettlement = $settlementService->canInitiateSettlement($group);
        $allowsAdvancePayouts = $group->allowsAdvancePayouts();
        $advancePeriod = (int) $group->current_month;
        $paidOriginalForAdvance = $advancePeriod > 0
            ? $settlementService->originalSettlementForMonth($group, $advancePeriod)
            : null;
        $advanceEligibleMembers = ($allowsAdvancePayouts && $paidOriginalForAdvance?->status === 'paid')
            ? $settlementService->getAdvanceEligibleMembers($group, $advancePeriod)
            : collect();
        $advanceSettlements = $advancePeriod > 0
            ? $settlementService->advanceSettlementsForMonth($group, $advancePeriod)
            : collect();
        $installmentSummary = [
            'total'     => $group->installments()->count(),
            'paid'      => $group->installments()->where('status', 'paid')->count(),
            'partial'   => $group->installments()->where('status', 'partial')->count(),
            'overdue'   => $group->installments()->where('status', 'overdue')->count(),
            'pending'   => $group->installments()->where('status', 'pending')->count(),
            'collected' => $group->installments()->where('status', 'paid')->sum('paid_amount'),
        ];

        // Collected = client installment payments only (enrolled members with a client).
        $groupCollections = (float) $group->installments()
            ->whereIn('status', ['paid', 'partial'])
            ->whereHas('member', function ($mq) {
                $mq->whereNotIn('status', GroupMember::INACTIVE_STATUSES)
                    ->whereNotNull('client_id');
            })
            ->sum('paid_amount');
        $groupSettlements = (float) $group->payouts()->where('status', 'paid')->sum('payout_amount');
        $foremanCommissionSummary = $group->foremanCommissionSummary();
        $dividendPool = (float) ($group->dividend_pool_balance ?? 0);

        $progressMonth = (int) $request->input('progress_month', $operatingMonth);
        if ($progressMonth < 1 || $progressMonth > (int) $group->total_months) {
            $progressMonth = $operatingMonth;
        }

        $progressInstallments = $group->installments()
            ->forEnrolledMembers()
            ->where('month_number', $progressMonth)
            ->get();

        $progressExpected = (float) $progressInstallments->sum(fn ($inst) => (float) $inst->total_due);
        $progressCollected = (float) $progressInstallments->sum(fn ($inst) => (float) $inst->paid_amount);
        $progressPending = (float) $progressInstallments->sum(fn ($inst) => max(0, (float) $inst->balance));
        $progressPct = $progressExpected > 0
            ? round(($progressCollected / $progressExpected) * 100, 1)
            : 0;

        $currentMonthSummary = [
            'month'         => $progressMonth,
            'month_label'   => $group->periodCalendarLabel($progressMonth),
            'month_title'   => 'Month ' . $progressMonth . ' — ' . $group->periodCalendarLabel($progressMonth),
            'is_operating'  => $progressMonth === $operatingMonth,
            'is_calendar'   => $progressMonth === $calendarMonth,
            'is_overdue_period' => $progressMonth < $calendarMonth && $progressPending > 0.01,
            'expected'      => round($progressExpected, 2),
            'collected'     => round($progressCollected, 2),
            'pending'       => round($progressPending, 2),
            'progress_pct'  => min(100, $progressPct),
            'paid_count'    => $progressInstallments->filter(fn ($inst) => (float) $inst->balance <= 0.01)->count(),
            'total_count'   => $progressInstallments->count(),
            'overdue_count' => $progressInstallments->where('status', 'overdue')->count(),
        ];

        $outstandingInstallments = $group->installments()
            ->forEnrolledMembers()
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->get();

        // Uncollected = overdue (past periods) + current calendar month pending balances.
        $overdueUncollectedAmount = round((float) $outstandingInstallments
            ->filter(fn ($inst) => (int) $inst->month_number < $calendarMonth)
            ->sum(fn ($inst) => max(0, (float) $inst->balance)), 2);

        $currentMonthUncollectedAmount = round((float) $outstandingInstallments
            ->filter(fn ($inst) => (int) $inst->month_number === (int) $calendarMonth)
            ->sum(fn ($inst) => max(0, (float) $inst->balance)), 2);

        $uncollectedAmount = round($overdueUncollectedAmount + $currentMonthUncollectedAmount, 2);
        $totalUncollectedAmount = round((float) $outstandingInstallments
            ->sum(fn ($inst) => max(0, (float) $inst->balance)), 2);
        $cumulativePendingNetValue = (float) $outstandingInstallments->sum(fn ($inst) => (float) $inst->amount);

        // Group Balance = Collected − Settled − recognised Foreman Commission
        // (client-wise month 0: only after the extra is collected).
        $foremanCommissionAmount = (float) ($foremanCommissionSummary['amount'] ?? $group->recognizedForemanCommissionAmount());
        $groupBalance = round($groupCollections - $groupSettlements - $foremanCommissionAmount, 2);
        $availableGroupFunds = round($groupBalance + $dividendPool, 2);

        $clients = Client::orderBy('client_name')->get();
        $availableClients = Client::where('status', 'active')
            ->orderBy('client_name')
            ->get();
        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $groupIsFull = $group->is_full;

        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)
            ->orderBy('account_name')
            ->get();
        $partialPaymentConfig = app(\App\Services\PartialPaymentConfigService::class)->getGlobalSettings();

        $mode = 'show';
        return view('admin.chit.groups.index', compact(
            'group',
            'installmentSummary',
            'groupCollections',
            'groupSettlements',
            'groupBalance',
            'dividendPool',
            'availableGroupFunds',
            'foremanCommissionSummary',
            'uncollectedAmount',
            'overdueUncollectedAmount',
            'currentMonthUncollectedAmount',
            'totalUncollectedAmount',
            'cumulativePendingNetValue',
            'currentMonthSummary',
            'operatingMonth',
            'calendarMonth',
            'periodOptions',
            'clients',
            'availableClients',
            'agents',
            'groupIsFull',
            'nextSettlementMonth',
            'currentMonthSettlement',
            'canInitiateSettlement',
            'allowsAdvancePayouts',
            'advancePeriod',
            'paidOriginalForAdvance',
            'advanceEligibleMembers',
            'advanceSettlements',
            'bankAccounts',
            'partialPaymentConfig',
            'memberDisplayRows',
            'mode'
        ));
    }

    public function edit(ChitGroup $group)
    {
        $schemes   = ChitScheme::where('status', 'active')->get();
        $branches  = Branch::all();
        $clients   = Client::orderBy('client_name')->get();
        $mode      = 'edit';
        return view('admin.chit.groups.index', compact('group', 'schemes', 'branches', 'clients', 'mode'));
    }

    public function update(Request $request, ChitGroup $group)
    {
        $validated = $request->validate([
            'status'          => 'required|in:forming,active,completed,terminated',
            'group_leader_id' => 'nullable|exists:clients,id',
            'remarks'         => 'nullable|string',
            'referral_commission_pct' => 'nullable|numeric|min:0|max:100',
            'registration_type'              => 'sometimes|in:non_registered,registered',
            'registration_number'             => 'nullable|string|max:100',
            'registration_date'               => 'nullable|date',
            'registering_authority'           => 'nullable|string|max:150',
            'registration_office'             => 'nullable|string|max:150',
            'registration_certificate_number' => 'nullable|string|max:100',
            'registration_valid_from'         => 'nullable|date',
            'registration_valid_until'        => 'nullable|date',
        ]);

        // Clear registration details if switching to non-registered
        if (isset($validated['registration_type']) && $validated['registration_type'] !== 'registered') {
            foreach (['registration_number','registration_date','registering_authority','registration_office','registration_certificate_number','registration_valid_from','registration_valid_until'] as $field) {
                $validated[$field] = null;
            }
        }

        $oldStatus = $group->status;
        $newStatus = $validated['status'];

        $group->update($validated);

        if ($oldStatus !== 'active' && $newStatus === 'active') {
            $startDate = $group->start_date
                ? \Carbon\Carbon::parse($group->start_date)
                : today();

            $members = $group->members()
                ->whereIn('status', ['approved', 'active', 'applied', 'frozen'])
                ->get();

            foreach ($members as $member) {
                $member->update(['status' => 'active']);
                $group->generateInstallmentsForMember($member, $startDate);
            }

            $group->syncEndDate();
        }

        if ($oldStatus !== 'terminated' && $newStatus === 'terminated') {
            // Group status changed to terminated -> freeze member accounts
            $group->members()
                ->whereIn('status', ['active', 'approved', 'applied'])
                ->update(['status' => 'frozen']);
        } elseif ($oldStatus === 'terminated' && $newStatus !== 'terminated') {
            // Termination cancelled / reactivated -> un-freeze member accounts
            $group->members()
                ->where('status', 'frozen')
                ->update(['status' => 'active']);
        }

        return redirect()->route('chit.groups.show', $group)->with('success', 'Group updated!');
    }

    public function cancelTermination(ChitGroup $group)
    {
        if ($group->status !== 'terminated') {
            return $this->respondGroupAjax(request(), 'Group is not terminated.', false);
        }

        DB::transaction(function () use ($group) {
            $group->update(['status' => 'active']);
            $group->members()
                ->where('status', 'frozen')
                ->update(['status' => 'active']);
        });

        return $this->respondGroupAjax(request(), 'Group termination cancelled successfully. Member accounts have been un-frozen!');
    }

    public function activate(ChitGroup $group)
    {
        $eligibleMembersCount = $group->members()
            ->whereIn('status', ['active', 'approved', 'applied', 'frozen'])
            ->count();

        if ($eligibleMembersCount < 1) {
            return $this->respondGroupAjax(
                request(),
                "Cannot activate: group needs at least 1 member (currently has {$eligibleMembersCount}).",
                false
            );
        }

        DB::transaction(function () use ($group) {
            $group->update(['status' => 'active', 'current_month' => 0]);
            
            $members = $group->members()
                ->whereIn('status', ['approved', 'active', 'applied', 'frozen'])
                ->get();
                
            $startDate = Carbon::parse($group->start_date);

            foreach ($members as $member) {
                $member->update(['status' => 'active']);
                $group->generateInstallmentsForMember($member, $startDate);
            }

            $group->syncEndDate();

            $this->generateAuctionSchedule($group, $startDate);

            try {
                if (class_exists('\App\Services\PushNotificationService')) {
                    $notifService = app(\App\Services\PushNotificationService::class);
                    foreach ($members as $member) {
                        if ($member->client && $member->client->user) {
                            $notifService->sendToUser(
                                $member->client->user->id,
                                'Your Chit Group has Started!',
                                "Your chit group {$group->group_code} is now active. First installment due date: " . $startDate->format('d-m-Y'),
                                'group_started',
                                ['group_id' => $group->id, 'notifiable_type' => 'chit_group', 'notifiable_id' => $group->id]
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Group Activation Notification Failed: ' . $e->getMessage());
            }
        });

        return $this->respondGroupAjax(request(), 'Group activated, installments and auction schedule generated!');
    }

    /**
     * Close an active chit group at any time while it is running.
     * Fully settled groups become completed; early closes are terminated.
     */
    public function close(Request $request, ChitGroup $group)
    {
        $blockReason = $group->closeBlockReason();
        if ($blockReason) {
            return $this->respondGroupAjax($request, $blockReason, false);
        }

        $fullySettled = $group->isFullySettled();
        $newStatus = $fullySettled ? 'completed' : 'terminated';

        DB::transaction(function () use ($group, $request, $fullySettled, $newStatus) {
            $note = $request->filled('remarks')
                ? $request->input('remarks')
                : ($fullySettled
                    ? 'Closed / completed on ' . now()->format('d M Y')
                    : 'Closed early on ' . now()->format('d M Y') . ' (' . $group->closeProgressLabel() . ')');

            $updates = [
                'status' => $newStatus,
                'end_date' => $group->end_date ?? now()->toDateString(),
                'remarks' => trim(($group->remarks ? $group->remarks . "\n" : '') . $note),
            ];

            if ($fullySettled) {
                $updates['current_month'] = max((int) $group->current_month, (int) $group->total_months);
            }

            $group->update($updates);

            if ($fullySettled) {
                $group->markActiveMembersCompleted();
            } else {
                $group->members()
                    ->whereIn('status', ['active', 'approved', 'applied'])
                    ->update(['status' => 'frozen']);
            }
        });

        $message = $fullySettled
            ? "Chit group {$group->group_code} has been closed / completed successfully."
            : "Chit group {$group->group_code} has been closed early (terminated) and member accounts are frozen.";

        return $this->respondGroupAjax($request, $message);
    }

    public function reopen(Request $request, ChitGroup $group)
    {
        if (!in_array($group->status, ['completed', 'terminated', 'closed'])) {
            return $this->respondGroupAjax($request, 'Only closed or terminated groups can be reopened.', false);
        }

        DB::transaction(function () use ($group, $request) {
            $note = $request->filled('remarks') ? $request->input('remarks') : 'Reopened on ' . now()->format('d M Y');
            
            $group->update([
                'status' => 'active',
                'remarks' => trim(($group->remarks ? $group->remarks . "\n" : '') . $note),
            ]);

            $group->members()
                ->whereIn('status', ['completed', 'frozen'])
                ->update(['status' => 'active']);
        });

        return $this->respondGroupAjax($request, "Chit group {$group->group_code} has been reopened successfully.");
    }

    protected function respondGroupAjax(Request $request, string $message, bool $success = true)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message], $success ? 200 : 422);
        }

        return $success
            ? back()->with('success', $message)
            : back()->with('error', $message);
    }

    private function generateAuctionSchedule(ChitGroup $group, Carbon $startDate): void
    {
        for ($month = 1; $month <= $group->total_months; $month++) {
            $auctionDate = $startDate->copy()->addMonthsNoOverflow($month - 1);
            $auctionTime = '10:00';
            $minBid      = $group->getInstallmentAmountForMonth($month);

            Auction::firstOrCreate(
                ['group_id' => $group->id, 'month_number' => $month],
                [
                    'auction_date' => $auctionDate,
                    'auction_time' => $auctionTime,
                    'status'       => 'scheduled',
                    'bid_type'     => $group->auction_type,
                    'min_bid'      => $minBid,
                    'max_bid'      => $group->chit_value,
                ]
            );
        }
    }

    public function destroy(ChitGroup $group)
    {
        DB::transaction(function () use ($group) {
            $now = now();

            // 1. Remove settlement applications / payouts (auction winners) first due to FK constraints
            Payout::where('group_id', $group->id)->delete();

            // 2. Remove dividends and dividend pool entries
            Dividend::where('group_id', $group->id)->delete();
            ChitDividendPoolEntry::where('group_id', $group->id)->delete();

            // 3. Remove auction bids and auctions belonging to this group
            $auctionIds = Auction::where('group_id', $group->id)->pluck('id');
            if ($auctionIds->isNotEmpty()) {
                AuctionBid::whereIn('auction_id', $auctionIds)->delete();
            }
            Auction::where('group_id', $group->id)->delete();

            // Soft-delete all installments belonging to this group
            Installment::where('group_id', $group->id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            // Soft-delete all group members belonging to this group
            GroupMember::where('group_id', $group->id)->whereNull('deleted_at')->update(['deleted_at' => $now]);

            // Soft-delete the group itself
            $group->delete();
        });

        return redirect()->route('chit.groups.index')->with('success', 'Group moved to recycle bin. Members, installments, auctions and settlement applications have also been cleaned up.');
    }

    public function restore($id)
    {
        $group = ChitGroup::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($group) {
            $deletedAt = $group->deleted_at;

            // Restore the group first
            $group->restore();

            // Restore members that were soft-deleted with the group
            GroupMember::onlyTrashed()
                ->where('group_id', $group->id)
                ->where('deleted_at', $deletedAt)
                ->restore();

            // Restore installments that were soft-deleted with the group
            Installment::onlyTrashed()
                ->where('group_id', $group->id)
                ->where('deleted_at', $deletedAt)
                ->restore();
        });

        return redirect()->route('chit.groups.index', ['trash' => 'true'])->with('success', 'Group, members and installments restored successfully.');
    }

    public function forceDelete($id)
    {
        $group = ChitGroup::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($group) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            try {
                $memberIds = GroupMember::where('group_id', $group->id)->withTrashed()->pluck('id');

                // Unlink group and member references from collections if present
                ChitCollection::where('group_id', $group->id)->update(['group_id' => null]);
                if ($memberIds->isNotEmpty()) {
                    ChitCollection::whereIn('member_id', $memberIds)->update(['member_id' => null]);
                }

                // Delete member transfers associated with this group or its members
                ChitMemberTransfer::where('group_id', $group->id)
                    ->orWhere('destination_group_id', $group->id)
                    ->when($memberIds->isNotEmpty(), function ($q) use ($memberIds) {
                        $q->orWhereIn('outgoing_member_id', $memberIds)
                          ->orWhereIn('incoming_member_id', $memberIds);
                    })
                    ->delete();

                // Delete referral bonuses linked to group members if model exists
                if ($memberIds->isNotEmpty() && class_exists(\App\Models\ChitReferralBonus::class)) {
                    \App\Models\ChitReferralBonus::whereIn('group_member_id', $memberIds)->delete();
                }

                // Delete related payouts, dividends, dividend pool entries, and auctions
                Payout::where('group_id', $group->id)->delete();
                Dividend::where('group_id', $group->id)->delete();
                \App\Models\ChitDividendPoolEntry::where('group_id', $group->id)->delete();
                Auction::where('group_id', $group->id)->delete();

                // Permanently delete installments (including any that were soft-deleted)
                Installment::where('group_id', $group->id)->withTrashed()->forceDelete();

                // Permanently delete group members (including any that were soft-deleted)
                GroupMember::where('group_id', $group->id)->withTrashed()->forceDelete();

                // Now permanently delete the group
                $group->forceDelete();
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }
        });

        return redirect()->route('chit.groups.index', ['trash' => 'true'])->with('success', 'Group, members and installments permanently deleted.');
    }
}
