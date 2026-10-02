<?php

namespace App\Http\Controllers;

use App\Models\ChitGroup;
use App\Models\GroupMember;
use App\Models\Payout;
use App\Services\ChitPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChitSettlementApplicationController extends Controller
{
    public function __construct(
        protected ChitPayoutService $payoutService
    ) {}

    protected function checkRole(): void
    {
        if (!Auth::check() || !Auth::user()->hasAnyRole(['Admin', 'Staff', 'admin', 'staff'])) {
            abort(403, 'Only administrators and staff members are authorized to access the Chit Settlement Applications module.');
        }
    }

    public function index(Request $request)
    {
        $this->checkRole();

        // Group dropdown / listing can include non-active groups for reviewing applications,
        // but Apply for Settlement only lists members from ACTIVE groups.
        $activeGroups = ChitGroup::with([
                'scheme',
                'members' => fn ($q) => $q->withTrashed()
                    ->whereIn('status', ['active', 'approved', 'transferred', 'withdrawn', 'cancelled'])
                    ->where(function ($inner) {
                        $inner->whereNull('deleted_at')
                            ->orWhere('status', 'transferred');
                    })
                    ->orderBy('member_number'),
                'members.client.kycDetail',
                'members.shares.client.kycDetail',
                'members.installments' => fn ($q) => $q->withTrashed(),
            ])
            ->whereIn('status', ['active', 'forming', 'completed'])
            ->orderBy('group_code')
            ->get();

        $applyGroups = $activeGroups->where('status', 'active')->values();

        // Collect all member IDs to bulk-fetch existing active payouts (avoids N+1 query)
        $allMemberIds = $applyGroups->flatMap(fn ($g) => $g->members->pluck('id'))->unique()->values()->all();

        // Key: member_id => payout (pending, processing, or paid — these block re-application)
        $activePayouts = Payout::whereIn('winner_member_id', $allMemberIds)
            ->whereIn('status', ['pending', 'processing', 'paid'])
            ->get()
            ->keyBy('winner_member_id');

        $groupMembers = $applyGroups->flatMap(function (ChitGroup $group) use ($activePayouts) {
            return $group->members
                ->filter(function (GroupMember $member) use ($group) {
                    $member->setRelation('group', $group);

                    if (in_array($member->status, ['transferred', 'withdrawn', 'cancelled'], true)) {
                        // Contribution settlement only while unpaid refund remains.
                        return $this->payoutService->contributionSettlementAmount($member) > 0.009
                            && ! $member->has_won_auction
                            && (
                                $member->status !== 'transferred'
                                || ! $this->payoutService->outgoingTransferBuyoutAlreadyPaid($member)
                            );
                    }

                    return true;
                })
                ->map(function (GroupMember $member) use ($group, $activePayouts) {
                $member->setRelation('group', $group);

                $enrollmentSuffix = $member->is_shared ? null : $member->enrollmentSuffix();
                $clientName = $member->is_shared
                    ? ($member->owners_display ?? 'Shared Members')
                    : $member->displayClientName();

                $clientKyc = $member->client?->kycDetail ?? $member->shares->first()?->client?->kycDetail;

                $needMonthLabel = $member->chit_need_month_label;
                $needPeriods = $member->chit_need_periods;
                $needPeriodsLabel = ! empty($needPeriods)
                    ? 'Periods: ' . implode(', ', $needPeriods)
                    : '';

                $goingMonth = max(1, (int) ($group->current_month ?: 1));
                $totalMonths = (int) ($group->total_months ?: 0);
                $usesContribution = $this->payoutService->usesContributionSettlement($member);
                $mustSettleLast = $this->payoutService->memberMustSettleLast($member);
                $isOutgoingTransferred = $this->payoutService->isOutgoingTransferredMember($member);
                $isCancelledWithdrawn = $this->payoutService->isCancelledWithdrawnMember($member);
                $nextSettlementMonth = ($isOutgoingTransferred || $isCancelledWithdrawn)
                    ? $this->payoutService->lastPaidNonForemanMonth($member)
                    : ($mustSettleLast
                        ? $this->payoutService->lastEligibleSettlementMonth($group)
                        : $this->payoutService->getNextSettlementMonth($group));

                $chitValue = (float) $group->chit_value;
                $sharePct = (float) ($member->effective_share_percentage ?? 100);
                $shareRatio = max(0, $sharePct) / 100.0;
                $foremanCommPct = (float) ($group->foreman_commission_percentage ?? 5);
                $estDiscount = (float) ($group->estimated_discount_amount ?? ($group->installment_amount * 0.15));
                $estSettlement = max(0, $chitValue - ($chitValue * ($foremanCommPct / 100)) - $estDiscount);
                $estSettlement = round($estSettlement * $shareRatio, 2);

                $monthPayouts = [];
                $totalM = $totalMonths > 0 ? $totalMonths : 20;
                $contributionAmount = $usesContribution
                    ? $this->payoutService->contributionSettlementAmount($member)
                    : null;
                $memberInstallment = $member->share_installment !== null
                    ? (float) $member->share_installment
                    : round((float) $group->installment_amount * $shareRatio, 2);

                for ($m = 1; $m <= $totalM; $m++) {
                    if ($group->isForemanCommissionMonth($m)) {
                        continue;
                    }

                    if ($usesContribution) {
                        $pamt = (float) $contributionAmount;
                    } else {
                        // Must include independent share % (same rule as settle/pay).
                        $pamt = (float) $this->payoutService->calculateAmounts($group, null, $m, $member)['payout_amount'];
                    }
                    $mDate = $group->periodCalendarLabel($m);
                    $monthPayouts[$m] = [
                        'month' => $m,
                        'month_name' => $mDate,
                        'amount' => $pamt,
                        'formatted' => '₹' . number_format($pamt, 2),
                        'display_label' => sprintf('Month %d — %s (₹%s)', $m, $mDate, number_format($pamt, 0)),
                    ];
                }

                $allowsAdvance = method_exists($group, 'allowsAdvancePayouts')
                    ? $group->allowsAdvancePayouts()
                    : false;
                $advancePeriod = (int) $group->current_month;
                $canApplyAdvance = $allowsAdvance
                    && ! $mustSettleLast
                    && ! $isOutgoingTransferred
                    && ! $isCancelledWithdrawn
                    && $this->payoutService->canInitiateAdvanceSettlement($group, $member, $advancePeriod ?: null);

                $labelParts = [
                    sprintf('%s (#%s)', $clientName, $member->display_member_number),
                    $group->group_code,
                ];
                if ($needMonthLabel && $needMonthLabel !== '—') {
                    $labelParts[] = 'Need: ' . $needMonthLabel;
                }
                if ($isOutgoingTransferred) {
                    $labelParts[] = 'Outgoing transfer';
                } elseif ($isCancelledWithdrawn) {
                    $labelParts[] = 'Cancelled — paid months − foreman';
                } elseif ($mustSettleLast) {
                    $labelParts[] = 'Incoming transfer';
                }

                // Check if this member already has an active (pending/processing) application
                $existingPayout = $activePayouts->get($member->id);

                $estimatedAmount = $usesContribution
                    ? (float) $contributionAmount
                    : ($monthPayouts[$nextSettlementMonth]['amount']
                        ?? (float) $this->payoutService->calculateAmounts($group, null, $nextSettlementMonth, $member)['payout_amount']
                        ?? $estSettlement);

                return [
                    'id' => $member->id,
                    'group_id' => $group->id,
                    'client_id' => $member->client_id,
                    'is_shared' => (bool) $member->is_shared,
                    'enrollment_suffix' => $enrollmentSuffix,
                    'member_number' => $member->member_number,
                    'display_member_number' => $member->display_member_number,
                    'client_name' => $clientName,
                    'group_code' => $group->group_code,
                    'scheme_name' => $group->scheme->name ?? '—',
                    'chit_value' => $chitValue,
                    'chit_value_formatted' => '₹' . number_format($chitValue, 2),
                    'share_percentage' => $sharePct,
                    'share_percentage_label' => rtrim(rtrim(number_format($sharePct, 2), '0'), '.') . '%',
                    'installment_amount' => $memberInstallment,
                    'installment_formatted' => '₹' . number_format($memberInstallment, 2),
                    'frequency' => ucfirst($group->installment_frequency ?? 'monthly'),
                    'going_month' => $goingMonth,
                    'total_months' => $totalMonths,
                    'next_settlement_month' => $nextSettlementMonth,
                    'must_settle_last' => $mustSettleLast,
                    'is_transferred_member' => $usesContribution,
                    'is_outgoing_transferred' => $isOutgoingTransferred,
                    'is_cancelled_withdrawn' => $isCancelledWithdrawn,
                    'uses_contribution_settlement' => $usesContribution,
                    'estimated_settlement_amount' => $estimatedAmount,
                    'estimated_settlement_formatted' => '₹' . number_format($estimatedAmount, 2),
                    'month_payouts' => $monthPayouts,
                    'allows_advance' => $allowsAdvance && ! $mustSettleLast && ! $isOutgoingTransferred && ! $isCancelledWithdrawn,
                    'can_apply_advance' => $canApplyAdvance,
                    'advance_period' => $advancePeriod,
                    'advance_period_label' => $advancePeriod > 0
                        ? ($monthPayouts[$advancePeriod]['display_label'] ?? ('Month ' . $advancePeriod))
                        : '',
                    'chit_need_month' => $member->chit_need_month,
                    'chit_need_month_label' => $needMonthLabel ?: 'Not set',
                    'chit_need_periods' => $needPeriods,
                    'chit_need_periods_label' => $needPeriodsLabel,
                    'preferred_chit_need_period' => $member->preferredChitNeedPeriod(),
                    'label' => implode(' — ', $labelParts),
                    'bank_name' => $clientKyc?->bank_name ?? '',
                    'account_number' => $clientKyc?->account_number ?? '',
                    'ifsc_code' => $clientKyc?->ifsc_code ?? '',
                    'account_holder_name' => $clientKyc?->account_holder_name ?? '',
                    // Existing active application info (null if none / cancelled = re-apply allowed)
                    'existing_application_status' => $existingPayout?->status,
                    'existing_application_id'     => $existingPayout?->id,
                    'existing_payout_code'         => $existingPayout?->payout_code,
                ];
            });
        })->values();

        $groupId = $request->filled('group_id') ? (int) $request->query('group_id') : null;

        $countsQuery = Payout::query()->whereHas('group');
        if ($groupId) {
            $countsQuery->where('group_id', $groupId);
        }

        $counts = [
            'pending' => (clone $countsQuery)->whereIn('status', ['pending', 'applied', 'requested'])->count(),
            'processing' => (clone $countsQuery)->where('status', 'processing')->count(),
            'paid' => (clone $countsQuery)->where('status', 'paid')->count(),
            'approved' => (clone $countsQuery)->whereIn('status', ['paid', 'processing'])->count(),
            'rejected' => (clone $countsQuery)->whereIn('status', ['cancelled', 'failed'])->count(),
            'total' => (clone $countsQuery)->count(),
        ];

        return view('admin.chit.settlement-applications.index', compact(
            'activeGroups',
            'groupMembers',
            'counts',
            'groupId'
        ));
    }

    public function data(Request $request): JsonResponse
    {
        $this->checkRole();

        $query = Payout::with(['group.scheme', 'winner.client', 'initiatedBy', 'processedBy'])
            ->whereHas('group')
            ->latest();

        if ($request->filled('status')) {
            $status = $request->status;
            if (in_array($status, ['applied', 'pending', 'requested'], true)) {
                $query->whereIn('status', ['pending', 'applied', 'requested']);
            } elseif (in_array($status, ['approved', 'paid', 'processing'], true)) {
                $query->whereIn('status', ['paid', 'processing']);
            } elseif (in_array($status, ['rejected', 'cancelled', 'failed'], true)) {
                $query->whereIn('status', ['cancelled', 'failed']);
            } else {
                $query->where('status', $status);
            }
        } else {
            // Default tab: Applied
            $query->whereIn('status', ['pending', 'applied', 'requested']);
        }

        if ($request->filled('source') && $request->source !== 'all') {
            if ($request->source === 'customer') {
                $query->where('applied_source', 'customer');
            } elseif ($request->source === 'agent') {
                $query->where('applied_source', 'agent');
            } elseif ($request->source === 'admin') {
                $query->where(function ($q) {
                    $q->where('applied_source', 'admin')
                      ->orWhereNull('applied_source');
                });
            }
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->group_id);
        }

        if ($request->filled('payout_kind')) {
            $query->where('payout_kind', $request->payout_kind);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('payout_code', 'like', "%{$s}%")
                    ->orWhere('reference_no', 'like', "%{$s}%")
                    ->orWhereHas('winner.client', fn ($c) => $c
                        ->where('client_name', 'like', "%{$s}%")
                        ->orWhere('client_phone', 'like', "%{$s}%"))
                    ->orWhereHas('group', fn ($g) => $g
                        ->where('group_code', 'like', "%{$s}%"));
            });
        }

        $total = $query->count();
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 15);

        $applications = $query->skip($start)->take($length)->get();

        $data = $applications->map(function (Payout $payout) {
            $member = $payout->winner;
            $clientName = $member
                ? ($member->is_shared
                    ? ($member->owners_display ?? 'Shared Members')
                    : $member->displayClientName())
                : '—';
            $clientPhone = $member?->client?->client_phone ?? '—';
            $group = $payout->group;
            $applicant = $payout->initiatedBy;
            $applicantLogin = $applicant?->name
                ?: ($applicant?->phone ?: ($applicant?->email ?: null));
            $applicantPhone = $applicant?->phone ?: $clientPhone;

            $mNum = $payout->month_number ?? $payout->auction?->month_number;
            $monthLabel = null;
            if ($mNum && $group) {
                $monthLabel = 'Month ' . $mNum . ' — ' . $group->periodCalendarLabel((int) $mNum);
            }

            return [
                'id' => $payout->id,
                'payout_code' => $payout->payout_code,
                'client_name' => $clientName,
                'client_phone' => $clientPhone,
                'group_code' => $group->group_code ?? '—',
                'scheme_name' => $group->scheme->name ?? '—',
                'chit_value' => number_format((float) ($payout->chit_value ?? $group->chit_value ?? 0), 0),
                'settlement_amount' => number_format((float) $payout->payout_amount, 0),
                'month_number' => $mNum,
                'month_label' => $monthLabel,
                'payout_kind_label' => $payout->payout_kind_label,
                'payout_kind_badge' => $payout->payout_kind_badge,
                'source' => $payout->source,
                'source_label' => $payout->source_label,
                'source_badge' => $payout->source_badge,
                'applicant_login' => $applicantLogin,
                'applicant_phone' => $applicantPhone,
                'applicant_user_id' => $payout->initiated_by,
                'status' => $payout->status,
                'status_label' => $payout->status_label,
                'status_badge' => $payout->status_badge,
                'applied_at' => $payout->created_at->format('d M Y'),
                'member_number' => $member->member_number ?? '—',
                'group_id' => $group->id ?? 0,
                'member_id' => $member->id ?? 0,
            ];
        });

        $countsQuery = Payout::query()->whereHas('group');
        if ($request->filled('source') && $request->source !== 'all') {
            if ($request->source === 'customer') {
                $countsQuery->where('applied_source', 'customer');
            } elseif ($request->source === 'agent') {
                $countsQuery->where('applied_source', 'agent');
            } elseif ($request->source === 'admin') {
                $countsQuery->where(function ($q) {
                    $q->where('applied_source', 'admin')
                      ->orWhereNull('applied_source');
                });
            }
        }
        if ($request->filled('group_id')) {
            $countsQuery->where('group_id', $request->group_id);
        }
        if ($request->filled('payout_kind')) {
            $countsQuery->where('payout_kind', $request->payout_kind);
        }
        if ($request->filled('from_date')) {
            $countsQuery->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $countsQuery->whereDate('created_at', '<=', $request->to_date);
        }

        $counts = [
            'pending' => (clone $countsQuery)->whereIn('status', ['pending', 'applied', 'requested'])->count(),
            'processing' => (clone $countsQuery)->where('status', 'processing')->count(),
            'paid' => (clone $countsQuery)->where('status', 'paid')->count(),
            'approved' => (clone $countsQuery)->whereIn('status', ['paid', 'processing'])->count(),
            'rejected' => (clone $countsQuery)->whereIn('status', ['cancelled', 'failed'])->count(),
            'total' => (clone $countsQuery)->count(),
        ];

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
            'counts' => $counts,
        ]);
    }

    public function show(Payout $payout)
    {
        $this->checkRole();

        $payout->load(['group.scheme', 'winner.client.kycDetail', 'winner.shares.client.kycDetail', 'initiatedBy', 'processedBy', 'internalBankAccount']);

        $group = $payout->group;
        $member = $payout->winner;

        if (!$group || !$member) {
            return redirect()->route('chit.settlement-applications.index')
                ->with('error', 'Associated group or member for this settlement request no longer exists.');
        }

        $clientKyc = $member->client?->kycDetail ?? $member->shares->first()?->client?->kycDetail;
        $defaultFees = $this->payoutService->defaultSettlementFees();
        $isTransferredMember = $this->payoutService->usesContributionSettlement($member);
        $canEditSettlementMonth = ! $isTransferredMember || Auth::user()->hasAnyRole(['Admin', 'admin']);
        if ($this->payoutService->isOutgoingTransferredMember($member)
            || $this->payoutService->isCancelledWithdrawnMember($member)
        ) {
            $canEditSettlementMonth = Auth::user()->hasAnyRole(['Admin', 'admin']);
        }

        return view('admin.chit.settlement-applications.show', compact(
            'payout',
            'group',
            'member',
            'defaultFees',
            'clientKyc',
            'isTransferredMember',
            'canEditSettlementMonth'
        ));
    }

    public function updateMonth(Payout $payout, Request $request)
    {
        $this->checkRole();

        if (!in_array($payout->status, ['pending', 'processing'], true)) {
            $msg = 'Applied settlement month can only be updated for pending or processing applications.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->with('error', $msg);
        }

        $payout->loadMissing('winner');
        $winner = $payout->winner;
        $isTransferredMember = $winner && $this->payoutService->memberMustSettleLast($winner);
        $usesContribution = $winner && $this->payoutService->usesContributionSettlement($winner);
        $isAdmin = Auth::user()->hasAnyRole(['Admin', 'admin']);

        if ($isTransferredMember && ! $isAdmin) {
            $msg = 'Only Admin can edit the settlement month for transferred members.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            abort(403, $msg);
        }

        if ($winner && $this->payoutService->isOutgoingTransferredMember($winner) && ! $isAdmin) {
            $msg = 'Settlement month cannot be changed for outgoing transfer contribution settlements.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            abort(403, $msg);
        }

        if ($winner && $this->payoutService->isCancelledWithdrawnMember($winner) && ! $isAdmin) {
            $msg = 'Settlement month cannot be changed for cancelled-client contribution settlements.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 403);
            }
            abort(403, $msg);
        }

        $validated = $request->validate([
            'month_number' => 'required|integer|min:1',
        ]);

        $monthNumber = (int) $validated['month_number'];
        $group = $payout->group;

        if ($group && $group->isForemanCommissionMonth($monthNumber)) {
            $msg = 'Month ' . $monthNumber . ' is a Foreman Commission month and cannot be selected for member settlement.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withInput()->with('error', $msg);
        }

        // Always recalculate via payout service so Independent Sharing % is applied
        // (resolvePayoutAmountForMonth alone returns the full-month payout).
        if ($winner && $group) {
            $amounts = $this->payoutService->calculateAmounts($group, null, $monthNumber, $winner);
            $newPayoutAmount = (float) $amounts['payout_amount'];
            $commissionAmount = (float) $amounts['commission'];
            $winningBid = (float) $amounts['winning_bid'];
            $sharePct = (float) ($winner->effective_share_percentage ?? $payout->share_percentage ?? 100);
        } else {
            $newPayoutAmount = $group
                ? (float) $group->resolvePayoutAmountForMonth($monthNumber)
                : (float) $payout->payout_amount;
            $commissionAmount = $usesContribution ? 0.0 : (float) $payout->commission_amount;
            $winningBid = (float) $payout->winning_bid;
            $sharePct = (float) ($payout->share_percentage ?? 100);
        }

        $payout->update([
            'month_number' => $monthNumber,
            'payout_amount' => $newPayoutAmount,
            'commission_amount' => $commissionAmount,
            'winning_bid' => $winningBid,
            'share_percentage' => $sharePct,
        ]);

        $msg = 'Applied settlement month updated successfully to Month ' . $monthNumber . ' (Payout Amount: ₹' . number_format($newPayoutAmount, 2) . ').';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $msg,
                'month_number' => $monthNumber,
                'payout_amount' => $newPayoutAmount,
                'payout_amount_formatted' => '₹' . number_format($newPayoutAmount, 2),
            ]);
        }

        return back()->with('success', $msg);
    }
}
