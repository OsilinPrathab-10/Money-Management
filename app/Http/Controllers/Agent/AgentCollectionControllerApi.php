<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\EmiCollection;
use App\Models\ChitCollection;
use App\Models\Agent;
use App\Models\Emi;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\PartialPaymentConfigService;
use App\Support\BulkPaymentGroup;
use App\Support\RupeeRound;
use App\Services\PaymentReceiptService;
use Illuminate\Pagination\LengthAwarePaginator;

class AgentCollectionControllerApi extends Controller
{
    /**
     * Chit installments for clients assigned/referred to this agent.
     */
    private function applyChitAgentScope($query, int $agentId, ?int $userId = null): void
    {
        $query->whereHas('member', function ($mq) use ($agentId, $userId) {
            $mq->where(function ($memberQ) use ($agentId, $userId) {
                $memberQ->whereHas('client', function ($cq) use ($agentId, $userId) {
                    $cq->where('assigned_to', $agentId)
                       ->orWhere('added_by', $agentId);
                    if ($userId) {
                        $cq->orWhere('assigned_to', $userId)
                           ->orWhere('added_by', $userId);
                    }
                })
                ->orWhere('referred_by_agent_id', $agentId)
                ->orWhereHas('shares', function ($sq) use ($agentId, $userId) {
                    $sq->whereHas('client', function ($cq) use ($agentId, $userId) {
                        $cq->where('assigned_to', $agentId)
                           ->orWhere('added_by', $agentId);
                        if ($userId) {
                            $cq->orWhere('assigned_to', $userId)
                               ->orWhere('added_by', $userId);
                        }
                    });
                });

                if ($userId) {
                    $memberQ->orWhere('referred_by_agent_id', $userId);
                }
            });
        });
    }

    private function agentCanAccessLoanEmi(Emi $emi, int $agentId, ?int $userId = null): bool
    {
        $client = $emi->loanAccount?->client;
        if ($client) {
            $assigned = (int) $client->assigned_to;
            $added = (int) $client->added_by;
            if ($assigned === $agentId || $added === $agentId || ($userId && ($assigned === $userId || $added === $userId))) {
                return true;
            }
        }

        return $emi->activeAssignment()
            ->where(function ($q) use ($agentId, $userId) {
                $q->where('agent_id', $agentId);
                if ($userId) {
                    $q->orWhere('agent_id', $userId);
                }
            })
            ->exists();
    }

    private function agentCanAccessChitInstallment(Installment $installment, int $agentId, ?int $userId = null): bool
    {
        return Installment::query()
            ->whereKey($installment->id)
            ->where(function ($q) use ($agentId, $userId) {
                $this->applyChitAgentScope($q, $agentId, $userId);
            })
            ->exists();
    }

    private function resolveChitCollectionClientId(Installment $installment, ?int $preferredClientId = null): ?int
    {
        $installment->loadMissing(['member.shares', 'sharePayments']);
        $member = $installment->member;
        if (!$member) {
            return $preferredClientId ?: null;
        }

        $ownerIds = $member->resolveOwnershipShares()
            ->pluck('client_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($preferredClientId && $preferredClientId > 0) {
            array_unshift($ownerIds, $preferredClientId);
            $ownerIds = array_values(array_unique($ownerIds));
        } elseif ($member->client_id) {
            array_unshift($ownerIds, (int) $member->client_id);
            $ownerIds = array_values(array_unique($ownerIds));
        }

        foreach ($ownerIds as $clientId) {
            if ($installment->clientBalanceShare($clientId) > 0.009) {
                return $clientId;
            }
        }

        return $preferredClientId ?: ((int) ($member->client_id ?? 0) ?: ($ownerIds[0] ?? null));
    }

    private function loanHasUnpaidPriorEmi(Emi $emi): ?Emi
    {
        $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
            ->orderByDesc('instalment_number')
            ->first();
        $isLoanMatured = $lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now());
        if ($isLoanMatured) {
            return null;
        }

        return Emi::where('loan_account_id', $emi->loan_account_id)
            ->where('instalment_number', '<', $emi->instalment_number)
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
            ->orderBy('instalment_number', 'asc')
            ->first();
    }

    private function getOrdinal($number)
    {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return 'th';
        }
        return $ends[$number % 10];
    }

    /**
     * Resolves an Installment model and optional period index from various ID formats
     * that might be sent by mobile clients or web (e.g. "chit_1848_p_1", "chit_18481", 18481, "1848").
     *
     * @param string|int|array $target
     * @param int|null $explicitPeriod
     * @return array{0: Installment|null, 1: ?int}
     */
    private function resolveChitInstallmentAndPeriod(string|int|array $target, ?int $explicitPeriod = null): array
    {
        $rawId = '';
        $explicitInstId = null;

        if (is_array($target)) {
            $rawId = (string) ($target['id'] ?? $target['chit_installment_id'] ?? $target['due_id'] ?? '');
            $explicitInstId = $target['chit_installment_id'] ?? $target['installment_id'] ?? $target['due_id'] ?? null;
            if ($explicitPeriod === null) {
                $explicitPeriod = isset($target['period_index']) && $target['period_index'] !== ''
                    ? (int) $target['period_index']
                    : (isset($target['period_number']) && $target['period_number'] !== '' ? (int) $target['period_number'] : null);
            }
        } else {
            $rawId = (string) $target;
        }

        // 1. Check if rawId matches pattern "chit_{id}_p_{period}" or "{id}_p_{period}"
        if (preg_match('/^(?:chit_)?(\d+)_p_?(\d+)$/', $rawId, $m)) {
            $instId = (int) $m[1];
            $period = (int) $m[2];
            $inst = Installment::with(['member.client', 'group'])->find($instId);
            if ($inst) {
                return [$inst, $period];
            }
        }

        // 2. Direct lookup: check if clean numeric ID exists directly in database
        $cleanId = preg_replace('/^chit_/', '', $rawId);
        if (is_numeric($cleanId)) {
            $inst = Installment::with(['member.client', 'group'])->find((int) $cleanId);
            if ($inst) {
                return [$inst, $explicitPeriod];
            }
        }

        // 3. Check explicit installment ID if different from cleanId
        if ($explicitInstId && is_numeric($explicitInstId) && (int) $explicitInstId !== (int) $cleanId) {
            $inst = Installment::with(['member.client', 'group'])->find((int) $explicitInstId);
            if ($inst) {
                return [$inst, $explicitPeriod];
            }
        }

        // 4. Handle concatenated digits from mobile clients (e.g. "chit_18481" or "18481" where 1848 is installment and 1 is period)
        $digitsOnly = preg_replace('/\D/', '', $rawId);
        if (strlen($digitsOnly) >= 2) {
            // Check 1-digit period suffix (periods 1 to 9): e.g. 18481 -> id 1848, period 1
            $candidateId1 = (int) substr($digitsOnly, 0, -1);
            $candidatePeriod1 = (int) substr($digitsOnly, -1);
            $inst1 = Installment::with(['member.client', 'group'])->find($candidateId1);
            if ($inst1 && $candidatePeriod1 >= 1) {
                $freq = $inst1->member?->collection_frequency ?? 'monthly';
                if ($freq === 'weekly' && $candidatePeriod1 <= 5) {
                    return [$inst1, $candidatePeriod1];
                }
                if ($freq === 'daily' && $candidatePeriod1 <= 9) {
                    return [$inst1, $candidatePeriod1];
                }
                return [$inst1, $candidatePeriod1];
            }

            // Check 2-digit period suffix (periods 10 to 31 for daily): e.g. 184825 -> id 1848, period 25
            if (strlen($digitsOnly) >= 3) {
                $candidateId2 = (int) substr($digitsOnly, 0, -2);
                $candidatePeriod2 = (int) substr($digitsOnly, -2);
                if ($candidatePeriod2 >= 10 && $candidatePeriod2 <= 31) {
                    $inst2 = Installment::with(['member.client', 'group'])->find($candidateId2);
                    if ($inst2 && ($inst2->member?->collection_frequency ?? '') === 'daily') {
                        return [$inst2, $candidatePeriod2];
                    }
                }
            }
        }

        // 5. Also check if $explicitInstId had concatenated digits
        if ($explicitInstId && is_numeric($explicitInstId)) {
            $digitsOnlyExp = (string) $explicitInstId;
            if (strlen($digitsOnlyExp) >= 2 && $digitsOnlyExp !== $digitsOnly) {
                $candidateId1 = (int) substr($digitsOnlyExp, 0, -1);
                $candidatePeriod1 = (int) substr($digitsOnlyExp, -1);
                $inst1 = Installment::with(['member.client', 'group'])->find($candidateId1);
                if ($inst1 && $candidatePeriod1 >= 1) {
                    return [$inst1, $candidatePeriod1];
                }
                if (strlen($digitsOnlyExp) >= 3) {
                    $candidateId2 = (int) substr($digitsOnlyExp, 0, -2);
                    $candidatePeriod2 = (int) substr($digitsOnlyExp, -2);
                    if ($candidatePeriod2 >= 10 && $candidatePeriod2 <= 31) {
                        $inst2 = Installment::with(['member.client', 'group'])->find($candidateId2);
                        if ($inst2) {
                            return [$inst2, $candidatePeriod2];
                        }
                    }
                }
            }
        }

        return [null, null];
    }


    /**
     * Get active collection bank accounts.
     */
    public function collectionBankAccounts()
    {
        $bankAccounts = \App\Models\Account\BankAccount::where('is_active', true)
            ->whereRaw("LOWER(TRIM(account_name)) != ?", ['cash in hand'])
            ->get([
                'id', 'bank_name', 'account_name', 'account_number', 
                'branch_name', 'account_type', 'ifsc_code', 'routing_number', 'iban',
                'upi_id', 'qr_code', 'current_balance'
            ]);

        $filteredAccounts = $bankAccounts->reject(function ($account) {
            return strcasecmp(trim((string) $account->account_name), 'Cash in Hand') === 0;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $filteredAccounts->map(function ($account) {
                return [
                    'id' => $account->id,
                    'name' => trim($account->bank_name . ' - ' . $account->account_name . ' (' . substr($account->account_number, -4) . ')', ' -'),
                    'bank_name' => $account->bank_name,
                    'account_name' => $account->account_name,
                    'account_number' => $account->account_number,
                    'branch_name' => $account->branch_name,
                    'account_type' => $account->account_type,
                    'ifsc_code' => $account->effective_ifsc,
                    'upi_id' => $account->upi_id,
                    'qr_code_url' => $account->qr_code ? asset('storage/' . $account->qr_code) : null,
                    'current_balance' => (float) $account->current_balance,
                ];
            })
        ]);
    }

    /**
     * Return loan and chit dues available to the logged-in agent for collection.
     */
    public function assignedDues(Request $request)
    {
        $agent = Auth::user();
        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $agent instanceof \App\Models\Agent ? $agent->id : (optional($agent->agent)->id ?: $agent->id);
        $userId = $agent instanceof \App\Models\Agent ? $agent->user_id : $agent->id;

        $validated = $request->validate([
            'type' => 'nullable|in:loan,chit,all',
            'search' => 'nullable|string|max:100',
        ]);

        $type = $validated['type'] ?? 'all';
        $search = trim($validated['search'] ?? '');
        $rows = collect();

        if ($type !== 'chit') {
            $loanQuery = Emi::with(['loanAccount.client', 'collections'])
                ->whereIn('status', ['pending', 'overdue', 'partial']);
            $loanQuery->where(function ($q) use ($agentId, $userId) {
                $q->whereHas('loanAccount.client', function ($clientQ) use ($agentId, $userId) {
                    $clientQ->where('assigned_to', $agentId)
                            ->orWhere('assigned_to', $userId)
                            ->orWhere('added_by', $agentId)
                            ->orWhere('added_by', $userId);
                })->orWhereHas('activeAssignment', fn ($assignmentQ) => $assignmentQ->where('agent_id', $agentId)->orWhere('agent_id', $userId));
            });

            if ($search !== '') {
                $loanQuery->where(function ($q) use ($search) {
                    $q->whereHas('loanAccount.client', function ($clientQ) use ($search) {
                        $clientQ->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%")
                            ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })->orWhereHas('loanAccount', fn ($accountQ) => $accountQ->where('account_number', 'LIKE', "%{$search}%"));
                });
            }

            $partialService = app(PartialPaymentConfigService::class);
            $loanEmis = $loanQuery->orderBy('due_date')->get();
            $loanEmis->groupBy(fn (Emi $emi) => (int) $emi->loan_account_id)->each(function ($group) {
                $account = $group->first()?->loanAccount;
                if ($account) {
                    RupeeRound::persistOpenLoanInterest($account, $group);
                }
            });

            $loanAccountIds = $loanEmis->pluck('loan_account_id')->filter()->unique()->values()->all();
            $maturedLoanAccountIds = [];
            $minUnpaidInstMap = [];

            if (!empty($loanAccountIds)) {
                $maturedLoanAccountIds = Emi::whereIn('loan_account_id', $loanAccountIds)
                    ->select('loan_account_id', DB::raw('MAX(due_date) as max_due_date'))
                    ->groupBy('loan_account_id')
                    ->get()
                    ->filter(fn ($item) => $item->max_due_date && \Carbon\Carbon::parse($item->max_due_date)->lt(now()))
                    ->pluck('loan_account_id')
                    ->flip()
                    ->all();

                $nonMaturedIds = array_diff($loanAccountIds, array_keys($maturedLoanAccountIds));
                if (!empty($nonMaturedIds)) {
                    $minUnpaidInstMap = Emi::whereIn('loan_account_id', $nonMaturedIds)
                        ->whereIn('status', ['pending', 'overdue', 'partial'])
                        ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")')
                        ->select('loan_account_id', DB::raw('MIN(instalment_number) as min_inst'))
                        ->groupBy('loan_account_id')
                        ->pluck('min_inst', 'loan_account_id')
                        ->all();
                }
            }

            $loanRows = $loanEmis->flatMap(function (Emi $emi) use ($partialService, $maturedLoanAccountIds, $minUnpaidInstMap) {
                $loanAccount = $emi->loanAccount;
                $loanAccId = (int) $emi->loan_account_id;
                if (isset($maturedLoanAccountIds[$loanAccId])) {
                    $unpaidPrior = false;
                } else {
                    $minInst = $minUnpaidInstMap[$loanAccId] ?? null;
                    $unpaidPrior = ($minInst !== null && (int) $emi->instalment_number > (int) $minInst);
                }
                $amount = max(0, (float) $partialService->getOutstandingDueAmount($emi, $loanAccount));
                $isOpenLoan = ($loanAccount->loan_mode ?? '') === 'interest_only';
                $principalBalance = $isOpenLoan
                    ? (float) RupeeRound::one(max(0, (float) $loanAccount->remaining_principal_balance))
                    : 0.0;

                $status = $emi->status;
                $isDueToday = $emi->due_date && \Carbon\Carbon::parse($emi->due_date)->startOfDay()->equalTo(now()->startOfDay());

                if ($status === 'pending' && $emi->due_date && \Carbon\Carbon::parse($emi->due_date)->startOfDay()->lt(now()->startOfDay())) {
                    $status = 'overdue';
                }

                $forceEnable = $status === 'overdue' || ($status === 'pending' && $isDueToday);
                $disabled = $forceEnable ? false : ($amount <= 0 || $unpaidPrior);

                $row = [
                    'id' => 'loan_' . $emi->id,
                    'type' => 'loan',
                    'emi_id' => $emi->id,
                    'client_id' => $loanAccount?->client?->id,
                    'client_name' => $loanAccount?->client?->client_name ?? 'N/A',
                    'client_phone' => $loanAccount?->client?->client_phone,
                    'label' => $isOpenLoan
                        ? ('Open Loan Interest #' . $emi->instalment_number)
                        : ('EMI #' . $emi->instalment_number),
                    'account_or_group' => $loanAccount?->account_number ?? 'N/A',
                    'due_date' => optional($emi->due_date)->format('Y-m-d'),
                    'amount' => RupeeRound::one($amount),
                    'balance' => RupeeRound::one($amount),
                    'total_amount' => RupeeRound::one((float) ($emi->total_amount ?? $emi->total_due ?? 0)),
                    'status' => $status,
                    'disabled' => $disabled,
                    'disabled_reason' => $forceEnable ? null : ($unpaidPrior ? 'Previous EMI is still pending.' : ($amount <= 0 ? 'No amount is due.' : null)),
                    'is_open_loan' => $isOpenLoan,
                    'loan_mode' => $isOpenLoan ? 'interest_only' : 'emi',
                    'loan_mode_label' => $isOpenLoan ? 'Open Loan' : 'EMI',
                    'payment_type' => $isOpenLoan ? 'interest' : 'emi',
                    'principal_amount' => $principalBalance,
                    'principal_balance' => $principalBalance,
                    'can_pay_principal' => $isOpenLoan && $principalBalance > 0.01,
                ];

                return $amount > 0 ? [$row] : [];
            });
            $loanRows = $this->applyRupeeRoundingToLoanDueRows($loanRows)
                ->reject(fn ($row) => $this->isOpenLoanPrincipalDueRow($row))
                ->values();
            $rows = $rows->concat($loanRows);
        }

        if ($type !== 'loan') {
            $chitQuery = Installment::with(['member.client', 'member.shares', 'group', 'collections', 'sharePayments'])
                ->whereIn('status', ['pending', 'overdue', 'partial']);
            $this->applyChitAgentScope($chitQuery, $agentId, $userId);

            if ($search !== '') {
                $chitQuery->where(function ($q) use ($search) {
                    $q->whereHas('member.client', function ($clientQ) use ($search) {
                        $clientQ->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%")
                            ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })->orWhereHas('group', fn ($groupQ) => $groupQ->where('group_code', 'LIKE', "%{$search}%"));
                });
            }

            $chitInstallments = $chitQuery->orderBy('due_date')->get();
            $memberIds = $chitInstallments->pluck('member_id')->filter()->unique()->values()->all();

            $minUnpaidChitMonthMap = collect();
            if (!empty($memberIds)) {
                $minUnpaidChitMonthMap = Installment::whereIn('member_id', $memberIds)
                    ->whereNotIn('status', ['paid', 'waived'])
                    ->select('member_id', 'group_id', DB::raw('MIN(month_number) as min_month'))
                    ->groupBy('member_id', 'group_id')
                    ->get()
                    ->keyBy(fn ($item) => $item->member_id . '_' . $item->group_id);
            }

            $isInstallmentCollectibleFast = function (Installment $installment) use ($minUnpaidChitMonthMap) {
                if ($installment->is_consolidated) {
                    return (bool) $installment->consolidated_collectible;
                }

                if (in_array($installment->status, ['paid', 'waived'], true)) {
                    return false;
                }

                if ($installment->status === 'overdue') {
                    return true;
                }

                if ($installment->due_date && \Carbon\Carbon::parse($installment->due_date)->startOfDay()->lt(now()->startOfDay())) {
                    return true;
                }

                $key = $installment->member_id . '_' . $installment->group_id;
                $minMonthRow = $minUnpaidChitMonthMap->get($key);
                if (!$minMonthRow) {
                    return true;
                }

                return (int) $installment->month_number <= (int) $minMonthRow->min_month;
            };

            $chitRows = $chitInstallments->flatMap(function (Installment $installment) use ($isInstallmentCollectibleFast) {
                $member = $installment->member;
                $memberFreq = $member?->collection_frequency ?? 'monthly';
                $clientId = (int) ($member?->client_id ?? 0);
                $isCollectible = $isInstallmentCollectibleFast($installment);
                $clientName = $member?->client?->client_name ?? 'N/A';
                $clientPhone = $member?->client?->client_phone;
                $groupCode = $installment->group?->group_code ?? 'N/A';
                $inProgressAmount = $installment->collections ? $installment->collections->where('status', 'in_progress')->sum('amount') : 0;

                if (in_array($memberFreq, ['weekly', 'daily'], true)) {
                    $instAmount = (float) $member->displayAmountForInstallment($installment, $clientId);
                    $paidAmt = (float) $installment->clientPaidShare($clientId);
                    $effectivePaid = $paidAmt + (float) $inProgressAmount;
                    $periods = $member->collectionPeriodSchedule($installment->due_date, $instAmount, $effectivePaid);

                    $unpaidPeriods = collect($periods)->filter(fn ($p) => (float) $p['balance'] > 0.009);
                    if ($unpaidPeriods->isNotEmpty()) {
                        return $unpaidPeriods->map(function ($p) use ($installment, $clientName, $clientPhone, $groupCode, $isCollectible, $memberFreq, $clientId) {
                            $pBalance = round((float) $p['balance'], 2);
                            $pTotal = round((float) $p['amount'], 2);
                            $pDueDate = $p['due_date'] ? \Carbon\Carbon::parse($p['due_date'])->startOfDay() : null;
                            $isDueToday = $pDueDate && $pDueDate->equalTo(now()->startOfDay());
                            $pStatus = $p['status'];
                            if ($pStatus === 'pending' && $pDueDate && $pDueDate->lt(now()->startOfDay())) {
                                $pStatus = 'overdue';
                            }
                            $isNext = (bool) ($p['is_next'] ?? false);
                            $disabled = !$isCollectible || !$isNext;
                            $disabledReason = !$isCollectible
                                ? 'Previous installment must be paid first.'
                                : (!$isNext ? 'Previous ' . ($memberFreq === 'weekly' ? 'week' : 'day') . ' must be paid first.' : null);

                            return [
                                'id' => 'chit_' . $installment->id . '_p_' . $p['index'],
                                'type' => 'chit',
                                'chit_installment_id' => $installment->id,
                                'installment_id' => $installment->id,
                                'period_index' => (int) $p['index'],
                                'period_number' => (int) $p['index'],
                                'period_label' => (string) $p['label'],
                                'month_number' => (int) $installment->month_number,
                                'frequency' => $memberFreq,
                                'frequency_label' => ucfirst($memberFreq),
                                'client_id' => $clientId,
                                'client_name' => $clientName,
                                'client_phone' => $clientPhone,
                                'label' => 'Month ' . $installment->month_number . ' - ' . $p['label'],
                                'account_or_group' => $groupCode,
                                'due_date' => $pDueDate ? $pDueDate->format('Y-m-d') : optional($installment->due_date)->format('Y-m-d'),
                                'period_end' => ! empty($p['period_end']) ? \Carbon\Carbon::parse($p['period_end'])->format('Y-m-d') : null,
                                'is_current' => (bool) ($p['is_current'] ?? false),
                                'amount' => $pBalance,
                                'balance' => $pBalance,
                                'total_amount' => $pTotal,
                                'status' => $pStatus,
                                'disabled' => $disabled,
                                'disabled_reason' => $disabledReason,
                            ];
                        });
                    }
                }

                $amount = max(0, (float) $installment->balance - (float) $inProgressAmount);
                $status = $installment->status;
                $isDueToday = $installment->due_date && \Carbon\Carbon::parse($installment->due_date)->startOfDay()->equalTo(now()->startOfDay());

                if ($status === 'pending' && $installment->due_date && \Carbon\Carbon::parse($installment->due_date)->startOfDay()->lt(now()->startOfDay())) {
                    $status = 'overdue';
                }

                $forceEnable = $status === 'overdue' || ($status === 'pending' && $isDueToday);

                return [[
                    'id' => 'chit_' . $installment->id,
                    'type' => 'chit',
                    'chit_installment_id' => $installment->id,
                    'installment_id' => $installment->id,
                    'period_index' => null,
                    'period_number' => null,
                    'period_label' => null,
                    'month_number' => (int) $installment->month_number,
                    'frequency' => 'monthly',
                    'frequency_label' => 'Monthly',
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'client_phone' => $clientPhone,
                    'label' => 'Installment #' . $installment->month_number,
                    'account_or_group' => $groupCode,
                    'due_date' => optional($installment->due_date)->format('Y-m-d'),
                    'amount' => round($amount, 2),
                    'balance' => round($amount, 2),
                    'total_amount' => round((float) $installment->total_due, 2),
                    'status' => $status,
                    'disabled' => $forceEnable ? false : ($amount <= 0 || !$isCollectible),
                    'disabled_reason' => $forceEnable ? null : (!$isCollectible
                        ? 'Previous installment must be paid first.'
                        : ($amount <= 0 ? 'No amount is due.' : null)),
                ]];
            })->filter(fn ($row) => $row['amount'] > 0);
            $rows = $rows->concat($chitRows);
        }

        $rows = $rows->sortBy(fn ($row) => $row['due_date'] ?? '9999-12-31')->values();

        $seenAccounts = [];
        $rows->transform(function ($row) use (&$seenAccounts) {
            $account = ($row['client_id'] ?? '') . '_' . $row['account_or_group'];
            if (!isset($seenAccounts[$account])) {
                $seenAccounts[$account] = ['prev_status' => $row['status']];
                $row['disabled'] = false;
                $row['disabled_reason'] = null;
            } else {
                if ($seenAccounts[$account]['prev_status'] === 'in_progress') {
                    $row['disabled'] = false;
                    $row['disabled_reason'] = null;
                }
                $seenAccounts[$account]['prev_status'] = $row['status'];
            }
            return $row;
        });

        $groupedData = [];

        foreach ($rows as $row) {
            $clientId = $row['client_id'] ?? 0;
            $clientName = $row['client_name'];
            $clientPhone = $row['client_phone'] ?? 'N/A';
            
            $groupKey = $clientId ? $clientId : $clientName;
            
            if (!isset($groupedData[$groupKey])) {
                $groupedData[$groupKey] = [
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'client_phone' => $clientPhone,
                    'loans' => [],
                    'chits' => [],
                ];
            }
            
            unset($row['client_id'], $row['client_name'], $row['client_phone']);

            if ($this->isOpenLoanPrincipalDueRow($row)) {
                continue;
            }
            
            if ($row['type'] === 'loan') {
                $groupedData[$groupKey]['loans'][] = $row;
            } else if ($row['type'] === 'chit') {
                $groupedData[$groupKey]['chits'][] = $row;
            }
        }
        
        $groupedData = array_values($groupedData);

        return response()->json([
            'success' => true,
            'data' => $groupedData,
            'total' => count($groupedData),
        ]);
    }

    /**
     * Whole-rupee dues; leftover paise go on the last EMI of that loan.
     */
    private function applyRupeeRoundingToLoanDueRows($rows)
    {
        return collect($rows)->groupBy(function ($row) {
            return ($row['client_id'] ?? '') . '|' . ($row['account_or_group'] ?? '');
        })->flatMap(function ($group) {
            $interest = $group->reject(fn ($row) => $this->isOpenLoanPrincipalDueRow($row))->values();

            $rounded = RupeeRound::distribute($interest->map(fn ($row) => (float) $row['amount'])->all());
            $interest = $interest->map(function ($row, $index) use ($rounded) {
                $wasFull = abs((float) $row['amount'] - (float) $row['total_amount']) < 0.05;
                $amount = $rounded[$index] ?? RupeeRound::one((float) $row['amount']);
                $row['amount'] = $amount;
                $row['balance'] = $amount;
                $row['total_amount'] = $wasFull ? $amount : RupeeRound::one((float) $row['total_amount']);
                if (isset($row['principal_amount'])) {
                    $row['principal_amount'] = RupeeRound::one((float) $row['principal_amount']);
                    $row['principal_balance'] = $row['principal_amount'];
                }

                return $row;
            });

            return $interest->values();
        })->values();
    }

    private function isOpenLoanPrincipalDueRow(array $row): bool
    {
        $id = (string) ($row['id'] ?? '');
        $paymentType = (string) ($row['payment_type'] ?? '');

        return $paymentType === 'principal' || str_ends_with($id, '_principal');
    }

    public function pendingFollowUps(Request $request)
    {
        $agent = Auth::user();
        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $agent->id;

        $validated = $request->validate([
            'type' => 'nullable|in:loan,chit,all',
            'search' => 'nullable|string|max:100',
        ]);

        $type = $validated['type'] ?? 'all';
        $search = trim($validated['search'] ?? '');
        $rows = collect();

        if ($type !== 'chit') {
            $loanQuery = \App\Models\EmiAgentAssignment::with(['emi.loanAccount.client', 'emi.collections'])
                ->where('agent_id', $agentId)
                ->active()
                ->onActiveLoan()
                ->whereHas('emi', function ($q) {
                    $q->whereIn('status', ['pending', 'overdue', 'partial'])
                        ->whereDate('due_date', '=', now());
                });

            if ($search !== '') {
                $loanQuery->whereHas('emi', function ($emiQ) use ($search) {
                    $emiQ->where(function ($q) use ($search) {
                        $q->whereHas('loanAccount.client', function ($clientQ) use ($search) {
                            $clientQ->where('client_name', 'LIKE', "%{$search}%")
                                ->orWhere('client_phone', 'LIKE', "%{$search}%")
                                ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                        })->orWhereHas('loanAccount', fn ($accountQ) => $accountQ->where('account_number', 'LIKE', "%{$search}%"));
                    });
                });
            }

            if ($request->has('start_date') && !empty($request->start_date)) {
                $loanQuery->whereHas('emi', fn($q) => $q->whereDate('due_date', '>=', $request->start_date));
            }
            if ($request->has('end_date') && !empty($request->end_date)) {
                $loanQuery->whereHas('emi', fn($q) => $q->whereDate('due_date', '<=', $request->end_date));
            }
            if ($request->has('date') && !empty($request->date)) {
                $loanQuery->whereHas('emi', fn($q) => $q->whereDate('due_date', '=', $request->date));
            }

            $partialService = app(PartialPaymentConfigService::class);
            $loanRows = $loanQuery->get()
                ->sortBy(fn ($assignment) => $assignment->emi->due_date)
                ->map(function ($assignment) use ($partialService) {
                $emi = $assignment->emi;
                $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                
                if ($emi->status !== 'paid' && $inProgressSum > 0) {
                    $emi->status = 'in_progress';
                }

                $unpaidPrior = $this->loanHasUnpaidPriorEmi($emi) !== null;
                $amount = max(0, (float) $partialService->getOutstandingDueAmount($emi, $emi->loanAccount));

                return [
                    'id' => 'loan_' . $emi->id,
                    'type' => 'loan',
                    'client_name' => $emi->loanAccount?->client?->client_name ?? 'N/A',
                    'label' => 'EMI #' . $emi->instalment_number,
                    'account_or_group' => $emi->loanAccount?->account_number ?? 'N/A',
                    'due_date' => optional($emi->due_date)->format('Y-m-d'),
                    'amount' => round($amount, 2),
                    'balance' => round($amount, 2),
                    'total_amount' => round((float) ($emi->total_amount ?? $emi->total_due ?? 0), 2),
                    'status' => $emi->status,
                    'disabled' => $amount <= 0 || $unpaidPrior,
                    'disabled_reason' => $unpaidPrior ? 'Previous EMI is still pending.' : ($amount <= 0 ? 'No amount is due.' : null),
                ];
            })->filter(fn ($row) => $row['amount'] > 0);
            $rows = $rows->concat($loanRows);
        }

        if ($type !== 'loan') {
            $chitQuery = Installment::with(['member.client', 'group', 'collections'])
                ->whereDate('due_date', '=', now())
                ->whereIn('status', ['pending', 'overdue', 'partial']);
            $this->applyChitAgentScope($chitQuery, $agentId);

            if ($search !== '') {
                $chitQuery->where(function ($q) use ($search) {
                    $q->whereHas('member.client', function ($clientQ) use ($search) {
                        $clientQ->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%")
                            ->orWhere('alternate_phone', 'LIKE', "%{$search}%");
                    })->orWhereHas('group', fn ($groupQ) => $groupQ->where('group_code', 'LIKE', "%{$search}%"));
                });
            }

            if ($request->has('start_date') && !empty($request->start_date)) {
                $chitQuery->whereDate('due_date', '>=', $request->start_date);
            }
            if ($request->has('end_date') && !empty($request->end_date)) {
                $chitQuery->whereDate('due_date', '<=', $request->end_date);
            }
            if ($request->has('date') && !empty($request->date)) {
                $chitQuery->whereDate('due_date', '=', $request->date);
            }

            $chitRows = $chitQuery->orderBy('due_date')->get()->map(function (Installment $installment) {
                $inProgressSum = $installment->collections ? $installment->collections->where('status', 'in_progress')->sum('amount') : 0;
                
                if ($installment->status !== 'paid' && $inProgressSum > 0) {
                    $installment->status = 'in_progress';
                }

                $amount = max(0, (float) $installment->balance - (float) $inProgressSum);
                $isCollectible = $installment->isCollectible();

                return [
                    'id' => 'chit_' . $installment->id,
                    'type' => 'chit',
                    'client_name' => $installment->member?->client?->client_name ?? 'N/A',
                    'label' => 'Installment #' . $installment->month_number,
                    'account_or_group' => $installment->group?->group_code ?? 'N/A',
                    'due_date' => optional($installment->due_date)->format('Y-m-d'),
                    'amount' => round($amount, 2),
                    'balance' => round($amount, 2),
                    'total_amount' => round((float) $installment->total_due, 2),
                    'status' => $installment->status,
                    'disabled' => $amount <= 0 || !$isCollectible,
                    'disabled_reason' => !$isCollectible
                        ? 'Previous installment must be paid first.'
                        : ($amount <= 0 ? 'No amount is due.' : null),
                ];
            })->filter(fn ($row) => $row['amount'] > 0);
            $rows = $rows->concat($chitRows);
        }

        $rows = $rows->sortBy(fn ($row) => $row['due_date'] ?? '9999-12-31')->values();

        $seenAccounts = [];
        $rows->transform(function ($row) use (&$seenAccounts) {
            $account = $row['account_or_group'];
            if (!isset($seenAccounts[$account])) {
                $seenAccounts[$account] = ['prev_status' => $row['status']];
                $row['disabled'] = false;
                $row['disabled_reason'] = null;
            } else {
                if ($seenAccounts[$account]['prev_status'] === 'in_progress') {
                    $row['disabled'] = false;
                    $row['disabled_reason'] = null;
                }
                $seenAccounts[$account]['prev_status'] = $row['status'];
            }
            return $row;
        });

        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $total = $rows->count();
        
        $paginatedRows = $rows->forPage($page, $perPage)->values();

        return response()->json([
            'success' => true,
            'data' => $paginatedRows,
            'meta' => [
                'current_page' => (int) $page,
                'last_page' => ceil($total / $perPage),
                'per_page' => (int) $perPage,
                'total' => $total,
            ]
        ]);
    }

    private function isOpenLoanAccount($loanAccount): bool
    {
        if (!$loanAccount) {
            return false;
        }

        return ($loanAccount->loan_mode ?? '') === 'interest_only'
            || (int) ($loanAccount->tenure ?? 0) === 0;
    }

    private function parseLoanCollectionId(string $rawId): array
    {
        $isPrincipal = str_contains($rawId, '_principal');
        $emiId = (int) preg_replace('/\D+/', '', str_replace(['loan_', '_principal'], ['', ''], $rawId));

        return [$emiId, $isPrincipal];
    }

    /**
     * Admin-style split: loan_amount / interest_amount = interest, principal_amount = principal.
     */
    private function resolveLoanPaymentAmounts(Request $request, bool $isPrincipalId): array
    {
        $requestedType = (string) ($request->input('payment_type') ?? '');
        $interestKey = (float) ($request->input('interest_amount') ?? 0);
        $principalKey = (float) ($request->input('principal_amount') ?? 0);
        $loanAmount = (float) ($request->input('loan_amount') ?? $request->input('amount') ?? 0);
        $isPrincipalOnly = $isPrincipalId || $requestedType === 'principal';

        if ($isPrincipalOnly) {
            $interestAmount = $interestKey;
            $principalAmount = $principalKey > 0.01 ? $principalKey : $loanAmount;
        } else {
            $interestAmount = $interestKey > 0.01 ? $interestKey : $loanAmount;
            $principalAmount = $principalKey;
            if ($principalAmount > 0.01 && $interestAmount <= 0.01) {
                $interestAmount = 0;
            }
        }

        return [round($interestAmount, 2), round($principalAmount, 2)];
    }

    private function validateLoanInterestAmount(
        Emi $emi,
        $loanAccount,
        float $interestAmount,
        float $pendingAmount,
        bool $isOpenLoan,
        PartialPaymentConfigService $partialService
    ): ?array {
        if ($isOpenLoan) {
            $isPartialPayment = $interestAmount < ($pendingAmount - 0.01);
            if ($isPartialPayment) {
                if ($partialService->isActive()) {
                    if ($validationError = $partialService->validatePartialAmount($emi, $interestAmount, $loanAccount)) {
                        return ['message' => $validationError, 'status' => 422];
                    }
                }
            } elseif ($interestAmount > ($pendingAmount + 0.01)) {
                return [
                    'message' => 'Interest collection cannot exceed pending interest (₹' . number_format($pendingAmount, 2) . '). Use principal_amount for principal.',
                    'status' => 400,
                ];
            }

            return null;
        }

        $isPartialPayment = $interestAmount < ($pendingAmount - 0.01);
        if ($isPartialPayment) {
            if ($interestAmount <= 0 || $interestAmount > ($pendingAmount + 0.01)) {
                return [
                    'message' => 'Partial payment amount must be between ₹1 and total pending EMI amount (₹' . number_format($pendingAmount, 2) . ').',
                    'status' => 422,
                ];
            }
            if ($partialService->isActive()) {
                if ($validationError = $partialService->validatePartialAmount($emi, $interestAmount, $loanAccount)) {
                    return ['message' => $validationError, 'status' => 422];
                }
            }
        } elseif ($interestAmount > ($pendingAmount + 1)) {
            return [
                'message' => 'Collection amount cannot exceed the pending EMI amount (₹' . number_format($pendingAmount, 0) . ').',
                'status' => 400,
            ];
        }

        return null;
    }

    private function upsertAgentLoanCollection(
        Emi $emi,
        float $amount,
        string $paymentType,
        int $collectorAgentId,
        array $payment,
        float $pendingAmount
    ): EmiCollection {
        $isPrincipal = $paymentType === 'principal';

        $collectionQuery = EmiCollection::where('emi_id', $emi->id)->where('status', 'in_progress');
        if ($isPrincipal) {
            $collectionQuery->where('payment_type', 'principal');
        } else {
            $collectionQuery->where(function ($q) {
                $q->whereNull('payment_type')->orWhere('payment_type', '!=', 'principal');
            });
        }
        $collection = $collectionQuery->first();

        if ($collection) {
            $newTotalAmount = (float) $collection->amount + $amount;
            $isNowFull = ! $isPrincipal && ($newTotalAmount >= ($pendingAmount - 0.01));
            $mergedType = $isPrincipal
                ? 'principal'
                : ($isNowFull ? ($paymentType === 'interest' ? 'interest' : 'full') : 'partial');

            $updateData = [
                'amount' => $newTotalAmount,
                'payment_type' => $mergedType,
                'payment_method' => $payment['payment_method'] ?? $collection->payment_method,
                'collected_at' => $payment['collected_at'] ?? $collection->collected_at,
                'remarks' => trim(($collection->remarks ?? '') . "\n" . ($payment['remarks'] ?? 'Additional collection')),
            ];

            if (array_key_exists('bank_account_id', $payment)) {
                $updateData['bank_account_id'] = $payment['bank_account_id'];
            }
            if (array_key_exists('payment_reference', $payment) && $payment['payment_reference'] !== null) {
                $updateData['payment_reference'] = $payment['payment_reference'];
            }
            if (!$collection->agent_id) {
                $updateData['agent_id'] = $collectorAgentId;
            }

            $collection->update($updateData);

            return $collection->fresh();
        }

        return EmiCollection::create([
            'agent_id' => $collectorAgentId,
            'emi_id' => $emi->id,
            'amount' => $amount,
            'payment_method' => $payment['payment_method'] ?? 'cash',
            'bank_account_id' => $payment['bank_account_id'] ?? null,
            'payment_type' => $paymentType,
            'payment_reference' => $payment['payment_reference'] ?? null,
            'status' => 'in_progress',
            'collected_at' => $payment['collected_at'] ?? now()->toDateString(),
            'remarks' => $payment['remarks'] ?? null,
        ]);
    }

    /**
     * Store a manually created collection
     */
    public function store(Request $request)
    {
        $agent = Auth::user();
        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $collectorAgentId = $agent instanceof Agent ? $agent->id : (optional($agent->agent)->id ?: $agent->id);
        $userId = $agent instanceof Agent ? $agent->user_id : $agent->id;

        $hasLoan = $request->filled('loan_emi_id') || 
                   ($request->filled('emi_id') && !str_starts_with((string)$request->emi_id, 'chit_'));
                   
        $hasChit = $request->filled('chit_installment_id') || 
                   ($request->filled('emi_id') && str_starts_with((string)$request->emi_id, 'chit_'));

        if (!$hasLoan && !$hasChit) {
            return response()->json(['success' => false, 'message' => 'Please provide either Loan EMI or Chit Installment details.'], 422);
        }

        $rules = [
            'payment_method' => 'required|in:in_hand,upi,bank_transfer,cash,direct,payment_link',
            'payment_type' => 'nullable|in:full,partial,principal,interest,emi',
            'payment_reference' => 'nullable|string|max:100',
            'collected_at' => 'required|date',
            'remarks' => 'nullable|string',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
            'principal_amount' => 'nullable|numeric|min:0',
            'interest_amount' => 'nullable|numeric|min:0',
        ];

        if ($hasLoan) {
            $rules['loan_emi_id'] = 'required_without:emi_id';
            $rules['loan_amount'] = 'required_without_all:amount,principal_amount,interest_amount|nullable|numeric|min:0';
            $rules['amount'] = 'nullable|numeric|min:0';
        }

        if ($hasChit) {
            $rules['chit_installment_id'] = 'required_without:emi_id';
            $rules['chit_amount'] = 'required_without:amount|numeric|min:0.01';
        }

        $request->validate($rules);

        DB::beginTransaction();
        try {
            $messages = [];

            // 1. Process Chit Installment
            if ($hasChit) {
                [$installment, $resolvedPeriod] = $this->resolveChitInstallmentAndPeriod([
                    'id' => $request->input('chit_installment_id') ?? $request->input('emi_id'),
                    'chit_installment_id' => $request->input('chit_installment_id'),
                    'due_id' => $request->input('due_id'),
                    'period_index' => $request->input('period_index'),
                    'period_number' => $request->input('period_number'),
                ]);
                $periodIndex = $resolvedPeriod ?: ($request->filled('period_index') ? (int) $request->period_index : null);
                $chitAmount = (float) ($request->input('chit_amount') ?? $request->input('amount'));

                if (!$installment) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Invalid Chit Installment ID.'], 404);
                }

                if (!$this->agentCanAccessChitInstallment($installment, $collectorAgentId, $userId)) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'You can only collect installments for your assigned clients.'], 403);
                }

                if (!$installment->isCollectible()) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Please pay the previous installment(s) before collecting this installment.'], 422);
                }

                $outstanding = round((float) $installment->balance, 2);
                $inProgressSum = \App\Models\ChitCollection::where('installment_id', $installment->id)
                    ->where('status', 'in_progress')
                    ->sum('amount');

                if (($inProgressSum + $chitAmount) > ($outstanding + 0.01)) {
                    DB::rollBack();
                    $remaining = max(0, $outstanding - $inProgressSum);
                    return response()->json(['success' => false, 'message' => 'Collection amount exceeds remaining collectible balance (₹' . number_format($remaining, 2) . ' remaining).'], 422);
                }

                $clientId = $this->resolveChitCollectionClientId(
                    $installment,
                    $request->filled('client_id') ? (int) $request->client_id : null
                );

                $remarks = (string) ($request->remarks ?? '');
                if ($periodIndex && $installment->member && in_array($installment->member->collection_frequency, ['weekly', 'daily'], true)) {
                    $periodUnit = $installment->member->collection_frequency === 'weekly' ? 'Week' : 'Day';
                    $periodTag = "[{$periodUnit} {$periodIndex}]";
                    if (!str_contains($remarks, $periodTag)) {
                        $remarks = trim($remarks . ' ' . $periodTag);
                    }
                }
                $remarks = trim($remarks . ' [Agent Collected]');

                \App\Models\ChitCollection::create([
                    'installment_id'    => $installment->id,
                    'group_id'          => $installment->group_id,
                    'member_id'         => $installment->member_id,
                    'client_id'         => $clientId,
                    'agent_id'          => $collectorAgentId,
                    'amount'            => $chitAmount,
                    'share_percentage'  => $installment->member?->effective_share_percentage,
                    'payment_method'    => $request->payment_method === 'in_hand' ? 'cash' : $request->payment_method,
                    'bank_account_id'   => $request->internal_bank_account_id,
                    'payment_type'      => $request->payment_type ?? 'full',
                    'payment_reference' => $request->payment_reference,
                    'status'            => 'in_progress',
                    'collected_at'      => $request->collected_at,
                    'remarks'           => $remarks,
                ]);

                $messages[] = 'Chit collection recorded';
            }

            // 2. Process Loan EMI (Open Loan: interest and/or principal, same as admin Pay Now)
            if ($hasLoan) {
                $rawLoanId = (string) ($request->input('loan_emi_id') ?? $request->input('emi_id'));
                [$realLoanId, $isPrincipalId] = $this->parseLoanCollectionId($rawLoanId);
                [$interestAmount, $principalAmount] = $this->resolveLoanPaymentAmounts($request, $isPrincipalId);

                if ($interestAmount <= 0.01 && $principalAmount <= 0.01) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Provide loan_amount / interest_amount and/or principal_amount.',
                    ], 422);
                }

                $emi = Emi::with(['loanAccount.client', 'activeAssignment'])->find($realLoanId);

                if (!$emi) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'Invalid Loan EMI ID.'], 404);
                }

                if (!$this->agentCanAccessLoanEmi($emi, $collectorAgentId, $userId)) {
                    DB::rollBack();
                    return response()->json(['success' => false, 'message' => 'You can only collect EMIs for your assigned clients.'], 403);
                }

                $loanAccount = $emi->loanAccount;
                $isOpenLoan = $this->isOpenLoanAccount($loanAccount);
                $partialService = app(PartialPaymentConfigService::class);
                $pendingAmount = $partialService->getOutstandingDueAmount($emi, $loanAccount);
                $maxPrincipal = $isOpenLoan
                    ? (float) RupeeRound::one(max(0, (float) $loanAccount->remaining_principal_balance))
                    : max(0, (float) ($loanAccount->outstanding_amount ?? 0));

                if ($principalAmount > 0.01 && ! $isOpenLoan) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Principal collection is only allowed for Open Loan accounts.',
                    ], 422);
                }

                if ($interestAmount > 0.01) {
                    $lastEmi = Emi::where('loan_account_id', $emi->loan_account_id)
                        ->orderByDesc('instalment_number')
                        ->first();
                    $isLoanMatured = ($lastEmi && $lastEmi->due_date && $lastEmi->due_date->lt(now()));

                    $unpaidPrior = false;
                    if (!$isLoanMatured) {
                        $unpaidPrior = Emi::where('loan_account_id', $emi->loan_account_id)
                            ->where('instalment_number', '<', $emi->instalment_number)
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->where(function ($q) {
                                $q->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');
                            })
                            ->exists();
                    }

                    if ($unpaidPrior) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => 'Please clear previous pending EMIs before paying for this instalment.'], 400);
                    }

                    $interestError = $this->validateLoanInterestAmount(
                        $emi,
                        $loanAccount,
                        $interestAmount,
                        $pendingAmount,
                        $isOpenLoan,
                        $partialService
                    );
                    if ($interestError) {
                        DB::rollBack();
                        return response()->json(['success' => false, 'message' => $interestError['message']], $interestError['status']);
                    }
                }

                if ($principalAmount > 0.01 && $principalAmount > ($maxPrincipal + 0.01)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Principal collection cannot exceed outstanding principal (₹' . number_format($maxPrincipal, 2) . ').',
                    ], 400);
                }

                $requestedType = (string) ($request->input('payment_type') ?? '');
                $paymentDetails = [
                    'payment_method' => $request->payment_method,
                    'bank_account_id' => $request->internal_bank_account_id,
                    'payment_reference' => $request->payment_reference,
                    'collected_at' => $request->collected_at,
                    'remarks' => trim(($request->remarks ?? '') . ' [Agent Collected]'),
                ];

                if ($interestAmount > 0.01) {
                    $interestType = in_array($requestedType, ['partial', 'full', 'interest', 'emi'], true)
                        ? $requestedType
                        : ($interestAmount < ($pendingAmount - 0.01)
                            ? 'partial'
                            : ($isOpenLoan ? 'interest' : 'full'));
                    $this->upsertAgentLoanCollection($emi, $interestAmount, $interestType, $collectorAgentId, $paymentDetails, $pendingAmount);
                }

                if ($principalAmount > 0.01) {
                    $this->upsertAgentLoanCollection($emi, $principalAmount, 'principal', $collectorAgentId, $paymentDetails, $pendingAmount);
                }

                $activityParts = [];
                if ($interestAmount > 0.01) {
                    $activityParts[] = '₹' . number_format($interestAmount, 2) . ($isOpenLoan ? ' interest' : '');
                }
                if ($principalAmount > 0.01) {
                    $activityParts[] = '₹' . number_format($principalAmount, 2) . ' principal';
                }

                \App\Models\AgentActivity::create([
                    'emi_id' => $emi->id,
                    'agent_id' => $collectorAgentId,
                    'type' => 'payment',
                    'description' => implode(' + ', $activityParts),
                    'method' => strtoupper(str_replace('_', ' ', $request->payment_method)),
                    'reference' => $request->payment_reference,
                    'remarks' => $request->remarks,
                    'action_at' => $request->collected_at,
                ]);

                \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                    ->whereIn('status', ['assigned'])
                    ->update([
                        'status' => 'visited',
                        'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' [Collection pending verification]')")
                    ]);

                if ($interestAmount > 0.01 && $principalAmount > 0.01) {
                    $messages[] = 'Open Loan interest and principal recorded';
                } elseif ($principalAmount > 0.01) {
                    $messages[] = 'Open Loan principal recorded';
                } else {
                    $messages[] = 'Loan collection recorded';
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($messages) > 1 
                    ? 'Both Loan and Chit collections recorded and sent for admin verification.' 
                    : ($messages[0] . ' and sent for admin verification.'),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Agent API collection failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Collection failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store multiple agent collections as pending in one transaction.
     */
    public function bulkStore(Request $request)
    {
        $agent = Auth::user();
        if (!$agent) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $agent instanceof Agent ? $agent->id : (optional($agent->agent)->id ?: $agent->id);
        $userId = $agent instanceof Agent ? $agent->user_id : $agent->id;

        // Normalize collection_date / payment_date if collected_at is missing
        if (!$request->filled('collected_at') && ($request->filled('collection_date') || $request->filled('payment_date'))) {
            $request->merge(['collected_at' => $request->input('collection_date') ?? $request->input('payment_date')]);
        }

        // Normalize item IDs if sent as bare numeric or with period fields
        if ($request->has('items') && is_array($request->input('items'))) {
            $rawItems = $request->input('items');
            $itemsModified = false;
            foreach ($rawItems as $idx => $it) {
                if (is_array($it)) {
                    $rawId = (string) ($it['id'] ?? $it['chit_installment_id'] ?? $it['due_id'] ?? $it['emi_id'] ?? '');
                    $type = $it['type'] ?? (str_starts_with($rawId, 'loan') ? 'loan' : 'chit');
                    if (!preg_match('/^(loan|chit)_/', $rawId) && $rawId !== '') {
                        $newId = ($type === 'loan' ? 'loan_' : 'chit_') . $rawId;
                        if (isset($it['period_index']) && $it['period_index'] !== '' && !str_contains($newId, '_p_')) {
                            $newId .= '_p_' . $it['period_index'];
                        }
                        $rawItems[$idx]['id'] = $newId;
                        $itemsModified = true;
                    }
                }
            }
            if ($itemsModified) {
                $request->merge(['items' => $rawItems]);
            }
        }

        $validated = $request->validate([
            'collected_at' => 'required|date',
            'payment_method' => 'required|string|in:in_hand,cash,bank_transfer,upi,online,gpay,phonepe,paytm,cheque,direct',
            'internal_bank_account_id' => 'required_if:payment_method,upi,bank_transfer|nullable|exists:bank_accounts,id',
            'payment_reference' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
            'client_id' => 'nullable|integer',
            'items' => 'required|array|min:1|max:100',
            'items.*.id' => ['required', 'string', 'distinct', 'regex:/^(loan|chit)_[A-Za-z0-9_\-]+$/'],
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.payment_type' => 'nullable|in:full,partial,principal,interest,emi',
            'items.*.principal_amount' => 'nullable|numeric|min:0',
            'items.*.interest_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $agentId, $userId, $request) {
                $created = 0;
                $skipped = 0;
                $total = 0.0;
                $batchKey = BulkPaymentGroup::generateKey();
                $bulkRemarks = BulkPaymentGroup::appendRemarks(
                    $validated['remarks'] ?? null,
                    $batchKey,
                    BulkPaymentGroup::AGENT_MARKER
                );
                $paymentReference = ($validated['payment_reference'] ?? null) ?: $batchKey;
                $priorInProgressChit = [];
                $batchChitCollected = [];
                $preferredRootClientId = $request->filled('client_id') ? (int) $request->input('client_id') : null;

                foreach ($validated['items'] as $item) {
                    $itemId = (string) $item['id'];
                    $type = str_starts_with($itemId, 'loan') ? 'loan' : 'chit';
                    $amount = round((float) $item['amount'], 2);
                    $paymentType = $item['payment_type'] ?? 'full';

                    if ($type === 'loan') {
                        [$emiId, $isPrincipalId] = $this->parseLoanCollectionId($itemId);
                        $requestedType = (string) ($item['payment_type'] ?? '');
                        $isPrincipalItem = $isPrincipalId || $requestedType === 'principal';
                        $principalExtra = round((float) ($item['principal_amount'] ?? 0), 2);
                        $interestExtra = round((float) ($item['interest_amount'] ?? 0), 2);

                        if ($isPrincipalItem) {
                            $interestAmount = $interestExtra;
                            $principalAmount = $amount;
                        } else {
                            $interestAmount = $interestExtra > 0.01 ? $interestExtra : $amount;
                            $principalAmount = $principalExtra;
                        }

                        $emi = Emi::with(['loanAccount.client', 'activeAssignment'])
                            ->lockForUpdate()
                            ->findOrFail($emiId);

                        if (!$this->agentCanAccessLoanEmi($emi, $agentId, $userId)) {
                            throw new AuthorizationException('You can only collect EMIs for your assigned clients.');
                        }

                        $loanAccount = $emi->loanAccount;
                        $isOpenLoan = $this->isOpenLoanAccount($loanAccount);
                        $partialService = app(PartialPaymentConfigService::class);
                        $outstanding = round((float) $partialService->getOutstandingDueAmount($emi, $loanAccount), 2);
                        $maxPrincipal = $isOpenLoan
                            ? (float) RupeeRound::one(max(0, (float) $loanAccount->remaining_principal_balance))
                            : round(max(0, (float) ($loanAccount->outstanding_amount ?? 0)), 2);

                        if ($principalAmount > 0.01 && ! $isOpenLoan) {
                            throw ValidationException::withMessages(['items' => 'Principal collection is only allowed for Open Loan accounts.']);
                        }

                        $paymentDetails = [
                            'payment_method' => $validated['payment_method'],
                            'bank_account_id' => $validated['internal_bank_account_id'] ?? null,
                            'payment_reference' => $paymentReference,
                            'collected_at' => $validated['collected_at'],
                            'remarks' => $bulkRemarks,
                        ];

                        $itemCreated = 0;
                        $itemTotal = 0.0;
                        $recordedInterest = 0.0;
                        $recordedPrincipal = 0.0;

                        if ($interestAmount > 0.01) {
                            if (!in_array($emi->status, ['pending', 'overdue', 'partial'], true)) {
                                // Interest already settled — skip only the interest part
                            } elseif (
                                EmiCollection::where('emi_id', $emi->id)
                                    ->where('status', 'in_progress')
                                    ->where(function ($q) {
                                        $q->whereNull('payment_type')->orWhere('payment_type', '!=', 'principal');
                                    })
                                    ->lockForUpdate()
                                    ->exists()
                            ) {
                                // Interest already pending verification
                            } else {
                                $unpaidPriorEmi = $this->loanHasUnpaidPriorEmi($emi);
                                if ($unpaidPriorEmi) {
                                    $ordinal = $this->getOrdinal($unpaidPriorEmi->instalment_number);
                                    throw ValidationException::withMessages(['items' => "Your previous {$unpaidPriorEmi->instalment_number}{$ordinal} #EMI is pending."]);
                                }

                                $interestError = $this->validateLoanInterestAmount(
                                    $emi,
                                    $loanAccount,
                                    $interestAmount,
                                    $outstanding,
                                    $isOpenLoan,
                                    $partialService
                                );
                                if ($interestError) {
                                    throw ValidationException::withMessages(['items' => $interestError['message']]);
                                }

                                $interestType = in_array($requestedType, ['partial', 'full', 'interest', 'emi'], true)
                                    ? $requestedType
                                    : ($interestAmount < ($outstanding - 0.01)
                                        ? 'partial'
                                        : ($isOpenLoan ? 'interest' : 'full'));

                                $this->upsertAgentLoanCollection($emi, $interestAmount, $interestType, $agentId, $paymentDetails, $outstanding);
                                $itemCreated++;
                                $itemTotal += $interestAmount;
                                $recordedInterest = $interestAmount;
                            }
                        }

                        if ($principalAmount > 0.01) {
                            if ($principalAmount > ($maxPrincipal + 0.01)) {
                                throw ValidationException::withMessages([
                                    'items' => "Open Loan principal cannot exceed ₹" . number_format($maxPrincipal, 2) . '.',
                                ]);
                            }

                            $principalInProgress = EmiCollection::where('emi_id', $emi->id)
                                ->where('status', 'in_progress')
                                ->where('payment_type', 'principal')
                                ->lockForUpdate()
                                ->exists();

                            if ($principalInProgress || $maxPrincipal <= 0.01) {
                                // Already submitted or no principal left
                            } else {
                                $this->upsertAgentLoanCollection($emi, $principalAmount, 'principal', $agentId, $paymentDetails, $outstanding);
                                $itemCreated++;
                                $itemTotal += $principalAmount;
                                $recordedPrincipal = $principalAmount;
                            }
                        }

                        if ($itemCreated === 0) {
                            $skipped++;
                            continue;
                        }

                        $activityParts = [];
                        if ($recordedInterest > 0.01) {
                            $activityParts[] = '₹' . number_format($recordedInterest, 2) . ($isOpenLoan ? ' interest' : '');
                        }
                        if ($recordedPrincipal > 0.01) {
                            $activityParts[] = '₹' . number_format($recordedPrincipal, 2) . ' principal';
                        }

                        \App\Models\AgentActivity::create([
                            'emi_id' => $emi->id,
                            'agent_id' => $agentId,
                            'type' => 'payment',
                            'description' => implode(' + ', $activityParts) ?: ('₹' . number_format($itemTotal, 2)),
                            'method' => strtoupper(str_replace('_', ' ', $validated['payment_method'])),
                            'reference' => $paymentReference,
                            'remarks' => $validated['remarks'] ?? null,
                            'action_at' => $validated['collected_at'],
                        ]);

                        \App\Models\EmiAgentAssignment::where('emi_id', $emi->id)
                            ->where('status', 'assigned')
                            ->update([
                                'status' => 'visited',
                                'remarks' => DB::raw("CONCAT(COALESCE(remarks, ''), ' [Collection pending verification]')"),
                            ]);

                        $created += $itemCreated;
                        $total += $itemTotal;
                        continue;
                    } else {
                        [$installment, $periodIndex] = $this->resolveChitInstallmentAndPeriod($item);

                        if (!$installment) {
                            throw ValidationException::withMessages(['items' => "Chit Installment #{$item['id']} not found."]);
                        }

                        // Re-fetch with row lock
                        $installment = Installment::with(['member.client', 'group'])
                            ->lockForUpdate()
                            ->find($installment->id);

                        if (!$installment) {
                            throw ValidationException::withMessages(['items' => "Chit Installment not found."]);
                        }

                        if (!$this->agentCanAccessChitInstallment($installment, $agentId, $userId)) {
                            throw new AuthorizationException('You can only collect installments for your assigned clients.');
                        }
                        if (!in_array($installment->status, ['pending', 'overdue', 'partial'], true)) {
                            // Already paid or cancelled — skip silently
                            $skipped++;
                            continue;
                        }

                        $outstanding = round((float) $installment->balance, 2);

                        // Snapshot in-progress sum from DB ONLY ONCE per installment ID so that
                        // newly created collections within this batch loop are NOT double counted!
                        if (!isset($priorInProgressChit[$installment->id])) {
                            $priorInProgressChit[$installment->id] = round(
                                (float) ChitCollection::where('installment_id', $installment->id)
                                    ->where('status', 'in_progress')
                                    ->sum('amount'),
                                2
                            );
                        }

                        $priorInProgress = $priorInProgressChit[$installment->id];
                        $batchAlreadyCollected = round((float) ($batchChitCollected[$installment->id] ?? 0), 2);
                        $currentTotalForInstallment = round($batchAlreadyCollected + $amount, 2);

                        // True remaining collectible balance for this installment before current item
                        $remaining = max(0, round($outstanding - $priorInProgress - $batchAlreadyCollected, 2));

                        // If this installment is already fully covered by prior in-progress collections
                        // or by earlier items in this batch, or the balance is already 0, skip it gracefully.
                        if (($priorInProgress + $batchAlreadyCollected) >= ($outstanding - 0.01) || $outstanding <= 0.01) {
                            $skipped++;
                            continue;
                        }

                        // If adding this amount would exceed what remains collectible, throw error
                        if (($priorInProgress + $currentTotalForInstallment) > ($outstanding + 0.01)) {
                            throw ValidationException::withMessages([
                                'items' => "Collection amount for Installment #{$installment->month_number} exceeds remaining balance (₹" . number_format($remaining, 2) . " remaining)."
                            ]);
                        }
                        $batchChitCollected[$installment->id] = $currentTotalForInstallment;

                        $paymentType = $amount < ($remaining - 0.01) ? 'partial' : 'full';
                        $itemClientId = isset($item['client_id']) ? (int) $item['client_id'] : $preferredRootClientId;
                        $clientId = $this->resolveChitCollectionClientId($installment, $itemClientId);

                        $chitRemarks = $bulkRemarks;
                        if ($periodIndex && $installment->member && in_array($installment->member->collection_frequency, ['weekly', 'daily'], true)) {
                            $periodUnit = $installment->member->collection_frequency === 'weekly' ? 'Week' : 'Day';
                            $chitRemarks .= " [{$periodUnit} {$periodIndex}]";
                        }

                        ChitCollection::create([
                            'installment_id' => $installment->id,
                            'group_id' => $installment->group_id,
                            'member_id' => $installment->member_id,
                            'client_id' => $clientId,
                            'agent_id' => $agentId,
                            'amount' => $amount,
                            'share_percentage' => $installment->member?->effective_share_percentage,
                            'payment_method' => $validated['payment_method'] === 'in_hand' ? 'cash' : $validated['payment_method'],
                            'bank_account_id' => $validated['internal_bank_account_id'] ?? null,
                            'payment_type' => $paymentType,
                            'payment_reference' => $paymentReference,
                            'status' => 'in_progress',
                            'collected_at' => $validated['collected_at'],
                            'remarks' => $chitRemarks,
                        ]);
                    }

                    $created++;
                    $total += $amount;
                }

                return [
                    'created_count' => $created,
                    'skipped_count' => $skipped,
                    'total_amount'  => round($total, 2),
                ];
            });
        } catch (AuthorizationException | ValidationException $e) {
            $errors = method_exists($e, 'errors') ? $e->errors() : [];
            $firstError = collect($errors)->flatten()->first();
            return response()->json(['success' => false, 'message' => $firstError ?: $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Agent bulk collection failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Bulk collection failed. Please try again.'], 500);
        }

        $createdCount = $result['created_count'];
        $skippedCount = $result['skipped_count'];
        $totalAmount  = $result['total_amount'];

        if ($createdCount === 0 && $skippedCount > 0) {
            // All items were already submitted and are pending verification
            return response()->json([
                'success'       => true,
                'message'       => 'These collections are already submitted and pending admin verification.',
                'created_count' => 0,
                'skipped_count' => $skippedCount,
                'total_amount'  => 0.0,
            ]);
        }

        $message = $createdCount . ' collection(s) sent for admin verification.';
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} item(s) were already collected and skipped.";
        }

        return response()->json([
            'success'       => true,
            'message'       => $message,
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'total_amount'  => $totalAmount,
        ]);
    }

    /**
     * Loan collections list for the authenticated agent
     */
    public function list(Request $request)
    {
        $query = EmiCollection::with(['agent', 'emi.loanAccount.client', 'verifiedBy']);
        
        $currentUser = Auth::user();
        $agentId = optional($currentUser->agent)->id;
        
        if ($agentId) {
            $this->applyLoanAgentScope($query, $agentId);
            $query->where(function ($q) use ($agentId) {
                $q->whereNull('agent_id')->orWhere('agent_id', $agentId);
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->has('start_date') && !empty($request->start_date)) {
            $query->whereDate('collected_at', '>=', $request->start_date);
        }
        if ($request->has('end_date') && !empty($request->end_date)) {
            $query->whereDate('collected_at', '<=', $request->end_date);
        }

        if ($request->has('status') && !empty($request->status)) {
            $statusVal = $request->status;
            if ($statusVal === 'pending') {
                $query->where('status', 'in_progress');
            } else {
                $query->where('status', $statusVal);
            }
        }

        if (!empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                  ->orWhere('amount', 'LIKE', "%{$search}%")
                  ->orWhereHas('agent', function ($q) use ($search) {
                      $q->where('agent_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('emi.loanAccount.client', function ($q) use ($search) {
                      $q->where('client_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $query->orderBy('collected_at', 'desc');

        $allCollections = $query->get();
        $grouped = BulkPaymentGroup::groupCollections($allCollections);

        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $pageItems = $grouped->forPage($page, $perPage)->values();

        $mapped = $pageItems->map(function (array $group) {
            $collection = $group['lead'];
            $items = $group['items'];
            $isBulk = $group['is_bulk'];

            $clientName = $collection->emi && $collection->emi->loanAccount && $collection->emi->loanAccount->client
                ? $collection->emi->loanAccount->client->client_name
                : 'N/A';

            $emiLabel = $isBulk
                ? BulkPaymentGroup::emiSplitLabel($items)
                : ($collection->emi
                    ? ($collection->emi->instalment_number ? 'EMI #' . $collection->emi->instalment_number : '#' . $collection->emi_id)
                    : 'N/A');

            return [
                'id' => $collection->id,
                'client_name' => $clientName,
                'emi_id_label' => $emiLabel,
                'emi_id' => $collection->emi_id,
                'amount' => round((float) $items->sum('amount'), 2),
                'payment_method' => $collection->payment_method,
                'payment_type' => $isBulk ? BulkPaymentGroup::combinedPaymentType($items) : $collection->payment_type,
                'status' => $collection->status === 'paid' ? 'verified' : ($isBulk ? BulkPaymentGroup::combinedStatus($items) : $collection->status),
                'collected_at' => $collection->collected_at ? $collection->collected_at->format('Y-m-d H:i:s') : null,
                'remarks' => $collection->remarks,
                'is_bulk' => $isBulk,
                'emi_split' => $emiLabel,
                'emi_count' => $items->count(),
            ];
        });

        $paginator = new LengthAwarePaginator(
            $mapped,
            $grouped->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    /**
     * Chit collections list for the authenticated agent
     */
    public function chitList(Request $request)
    {
        $query = Installment::with([
            'member.client',
            'group',
            'collectedBy.agent',
            'pendingCollections.agent',
            'pendingCollections.client',
            'collections',
        ]);

        $currentUser = Auth::user();
        $agentId = optional($currentUser->agent)->id;

        if ($agentId) {
            $this->applyChitAgentScope($query, $agentId);
            $query->where(function ($q) use ($agentId) {
                $q->where('paid_amount', '>', 0)
                  ->orWhereHas('pendingCollections', function ($pq) use ($agentId) {
                      $pq->where('agent_id', $agentId);
                  });
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        if ($request->has('start_date') || $request->has('end_date')) {
            $startDate = $request->start_date;
            $endDate = $request->end_date;

            $query->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($paid) use ($startDate, $endDate) {
                    if ($startDate) {
                        $paid->whereDate('paid_date', '>=', $startDate);
                    }
                    if ($endDate) {
                        $paid->whereDate('paid_date', '<=', $endDate);
                    }
                })->orWhereHas('pendingCollections', function ($pq) use ($startDate, $endDate) {
                    if ($startDate) {
                        $pq->whereDate('collected_at', '>=', $startDate);
                    }
                    if ($endDate) {
                        $pq->whereDate('collected_at', '<=', $endDate);
                    }
                });
            });
        }

        if ($request->filled('status')) {
            $statusVal = $request->status;
            if ($statusVal === 'pending') {
                $query->where(function ($q) {
                    $q->whereHas('pendingCollections')
                        ->orWhere('status', 'partial');
                });
            } elseif ($statusVal === 'verified') {
                $query->where('status', 'paid')->whereDoesntHave('pendingCollections');
            } elseif ($statusVal === 'rejected') {
                $query->whereHas('collections', fn ($cq) => $cq->where('status', 'rejected'));
            }
        }

        if (!empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('paid_amount', 'LIKE', "%{$search}%")
                    ->orWhereHas('member.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('group', function ($gq) use ($search) {
                        $gq->where('group_code', 'LIKE', "%{$search}%");
                    });
            });
        }

        $query->orderBy('paid_date', 'desc');

        $allInstallments = $query->get();
        $grouped = BulkPaymentGroup::groupInstallments($allInstallments);

        $perPage = max(1, (int) $request->input('per_page', 15));
        $page = max(1, (int) $request->input('page', 1));
        $pageItems = $grouped->forPage($page, $perPage)->values();

        $mapped = $pageItems->map(function (array $group) {
            $lead = $group['lead'];
            $items = $group['items'];
            $row = $this->formatAgentChitListRow($lead);

            if ($group['is_bulk']) {
                $itemRows = $items->map(fn (Installment $installment) => $this->formatAgentChitListRow($installment));
                $row['amount'] = BulkPaymentGroup::chitInstallmentTotal($items);
                $row['is_bulk'] = true;
                $row['emi_count'] = $items->count();
                $row['emi_id_label'] = BulkPaymentGroup::chitInstallmentSplitLabel($items);
                $row['emi_split'] = $row['emi_id_label'];
                $types = $itemRows->pluck('payment_type');
                $statuses = $itemRows->pluck('status');
                $row['payment_type'] = $types->contains('partial') ? 'partial' : ($types->first() ?? 'full');
                if ($statuses->contains('in_progress') || $statuses->contains('pending')) {
                    $row['status'] = 'in_progress';
                }
            }

            return $row;
        });

        $paginator = new LengthAwarePaginator(
            $mapped,
            $grouped->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    private function formatAgentChitListRow(Installment $installment): array
    {
        $groupCode = $installment->group?->group_code ?? 'N/A';
        $paymentMode = $installment->payment_mode === 'cash' ? 'in_hand' : ($installment->payment_mode ?? 'cash');
        $pending = $installment->pendingCollections->sortByDesc('collected_at')->first();

        $row = [
            'id' => $installment->id,
            'client_name' => $installment->member?->client?->client_name ?? 'N/A',
            'emi_id_label' => "{$groupCode} - Inst #{$installment->month_number}",
            'amount' => BulkPaymentGroup::installmentBulkAmount($installment),
            'payment_method' => $paymentMode,
            'payment_type' => $installment->status === 'partial' ? 'partial' : 'full',
            'status' => $installment->status === 'paid' ? 'verified' : $installment->status,
            'collected_at' => $installment->paid_date ? $installment->paid_date->format('Y-m-d H:i:s') : null,
            'is_bulk' => false,
            'emi_split' => "{$groupCode} - Inst #{$installment->month_number}",
            'emi_count' => 1,
        ];

        if ($pending) {
            $mode = $pending->payment_method === 'cash' ? 'in_hand' : $pending->payment_method;
            $row['amount'] = (float) $pending->amount;
            $row['payment_method'] = $mode;
            $row['payment_type'] = $pending->payment_type ?: 'full';
            $row['status'] = 'in_progress';
            $row['collected_at'] = $pending->collected_at ? $pending->collected_at->format('Y-m-d H:i:s') : null;

            if ($pending->client_id && $pending->relationLoaded('client') === false) {
                $pending->load('client');
            }
            if ($pending->client?->client_name) {
                $row['client_name'] = $pending->client->client_name;
            }
        }

        return $row;
    }

    /**
     * Unified Payment Receipt API for Agent Mobile/Web.
     * Supports all 6 scenarios:
     * 1. Single Loan
     * 2. Single Chit
     * 3. Bulk Loan
     * 4. Bulk Chit
     * 5. Single Loan & Chit
     * 6. Bulk Loan & Chit
     * Also supports partial payments for all scenarios.
     */
    public function unifiedReceipt(Request $request, $id = null): JsonResponse
    {
        $agentUser = Auth::user();
        if (!$agentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $rawId = $id ?? $request->input('id') ?? $request->input('receipt_id') ?? $request->input('group_id') ?? $request->input('bulk_key');
        $loanIdsInput = $request->input('loan_id') ?? $request->input('loan_ids') ?? $request->input('emi_id') ?? $request->input('emi_ids');
        $chitIdsInput = $request->input('chit_id') ?? $request->input('chit_ids') ?? $request->input('installment_id') ?? $request->input('installment_ids');
        $typeInput = strtolower(trim((string) $request->input('type', '')));

        $loanIds = [];
        $chitIds = [];
        $groupId = null;

        $parseIds = function ($input) {
            if (is_array($input)) {
                return $input;
            }
            if (is_numeric($input)) {
                return [(int) $input];
            }
            if (is_string($input)) {
                return array_filter(array_map('trim', explode(',', $input)));
            }
            return [];
        };

        foreach ($parseIds($loanIdsInput) as $lItem) {
            $clean = preg_replace('/^(?:loan_|emi_)/i', '', (string) $lItem);
            if (is_numeric($clean)) {
                $loanIds[] = (int) $clean;
            }
        }

        foreach ($parseIds($chitIdsInput) as $cItem) {
            $clean = preg_replace('/^(?:chit_|inst_|installment_)/i', '', (string) $cItem);
            if (is_numeric($clean)) {
                $chitIds[] = (int) $clean;
            }
        }

        if (!empty($rawId)) {
            $rawItems = $parseIds($rawId);
            foreach ($rawItems as $rItem) {
                $rItemStr = (string) $rItem;
                if (str_starts_with(strtolower($rItemStr), 'loan_') || str_starts_with(strtolower($rItemStr), 'emi_')) {
                    $clean = preg_replace('/^(?:loan_|emi_)/i', '', $rItemStr);
                    if (is_numeric($clean)) {
                        $loanIds[] = (int) $clean;
                    }
                } elseif (str_starts_with(strtolower($rItemStr), 'chit_') || str_starts_with(strtolower($rItemStr), 'inst_')) {
                    $clean = preg_replace('/^(?:chit_|inst_|installment_)/i', '', $rItemStr);
                    if (is_numeric($clean)) {
                        $chitIds[] = (int) $clean;
                    }
                } elseif (is_numeric($rItemStr)) {
                    $numVal = (int) $rItemStr;
                    $foundInColl = false;

                    // 1. Check EmiCollection first (Loan collection record ID)
                    $emiColl = EmiCollection::find($numVal);
                    if ($emiColl) {
                        $foundInColl = true;
                        $siblings = BulkPaymentGroup::findSiblings($emiColl);
                        foreach ($siblings as $sib) {
                            if ($sib->emi_id) {
                                $loanIds[] = (int) $sib->emi_id;
                            }
                        }
                        $explicit = BulkPaymentGroup::extractExplicitKey($emiColl->remarks, $emiColl->payment_reference);
                        if ($explicit) {
                            $chitSibs = ChitCollection::where(function ($q) use ($explicit) {
                                $q->where('remarks', 'like', '%Group: ' . $explicit . '%')
                                    ->orWhere('payment_reference', $explicit);
                            })->pluck('installment_id')->toArray();
                            $chitIds = array_merge($chitIds, array_filter($chitSibs));
                        }
                    }

                    // 2. Check ChitCollection (Chit collection record ID)
                    $chitColl = ChitCollection::find($numVal);
                    if ($chitColl) {
                        $foundInColl = true;
                        $siblings = BulkPaymentGroup::findChitSiblings($chitColl);
                        foreach ($siblings as $sib) {
                            if ($sib->installment_id) {
                                $chitIds[] = (int) $sib->installment_id;
                            }
                        }
                        $explicit = BulkPaymentGroup::extractExplicitKey($chitColl->remarks, $chitColl->payment_reference);
                        if ($explicit) {
                            $loanSibs = EmiCollection::where(function ($q) use ($explicit) {
                                $q->where('remarks', 'like', '%Group: ' . $explicit . '%')
                                    ->orWhere('payment_reference', $explicit);
                            })->pluck('emi_id')->toArray();
                            $loanIds = array_merge($loanIds, array_filter($loanSibs));
                        }
                    }

                    // 3. If not matched to a collection record ID, check EMI/Installment tables
                    if (!$foundInColl) {
                        if ($typeInput === 'chit') {
                            $chitIds[] = $numVal;
                        } elseif ($typeInput === 'loan') {
                            $loanIds[] = $numVal;
                        } else {
                            if (Emi::where('id', $numVal)->exists()) {
                                $emi = Emi::find($numVal);
                                $coll = $emi?->collections?->first();
                                if ($coll && BulkPaymentGroup::isBulk($coll)) {
                                    $siblings = BulkPaymentGroup::findSiblings($coll);
                                    foreach ($siblings as $sib) {
                                        if ($sib->emi_id) {
                                            $loanIds[] = (int) $sib->emi_id;
                                        }
                                    }
                                } else {
                                    $loanIds[] = $numVal;
                                }
                            } elseif (Installment::where('id', $numVal)->exists()) {
                                $inst = Installment::find($numVal);
                                $coll = $inst?->collections?->first();
                                if ($coll && BulkPaymentGroup::isBulkChit($coll)) {
                                    $siblings = BulkPaymentGroup::findChitSiblings($coll);
                                    foreach ($siblings as $sib) {
                                        if ($sib->installment_id) {
                                            $chitIds[] = (int) $sib->installment_id;
                                        }
                                    }
                                } else {
                                    $chitIds[] = $numVal;
                                }
                            }
                        }
                    }
                } else {
                    $groupId = $rItemStr;
                }
            }
        }

        if ($groupId) {
            $groupLoanColl = EmiCollection::where('remarks', 'LIKE', "%{$groupId}%")
                ->orWhere('payment_reference', $groupId)
                ->pluck('emi_id')
                ->toArray();
            $loanIds = array_unique(array_merge($loanIds, $groupLoanColl));

            $groupChitColl = ChitCollection::where('remarks', 'LIKE', "%{$groupId}%")
                ->orWhere('payment_reference', $groupId)
                ->pluck('installment_id')
                ->toArray();
            $chitIds = array_unique(array_merge($chitIds, $groupChitColl));
        }

        $loanIds = array_values(array_unique(array_filter($loanIds)));
        $chitIds = array_values(array_unique(array_filter($chitIds)));

        if (empty($loanIds) && empty($chitIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid loan or chit collection identifiers provided for receipt generation.'
            ], 404);
        }

        $loanEmis = collect();
        if (!empty($loanIds)) {
            $loanEmis = Emi::with([
                'loanAccount.client',
                'loanAccount.loanApplication.product',
                'collections' => function ($q) {
                    $q->whereIn('status', ['paid', 'in_progress', 'verified', 'approved'])->orderBy('id', 'desc');
                }
            ])->whereIn('id', $loanIds)->get();
        }

        $chitInstallments = collect();
        if (!empty($chitIds)) {
            $chitInstallments = Installment::with([
                'member.client',
                'group',
                'pendingCollections',
                'collections' => function ($q) {
                    $q->whereIn('status', ['paid', 'in_progress', 'verified', 'approved'])->orderBy('id', 'desc');
                }
            ])->whereIn('id', $chitIds)->get();
        }

        if ($loanEmis->isEmpty() && $chitInstallments->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt data not found for the provided identifiers.'
            ], 404);
        }

        $firstLoanClient = $loanEmis->first()?->loanAccount?->client;
        $firstChitClient = $chitInstallments->first()?->member?->client;
        $client = $firstLoanClient ?? $firstChitClient;

        $clientName = $client->client_name ?? 'N/A';
        $customerId = $client->customer_id ?? 'N/A';
        $clientPhone = $client->client_phone ?? 'N/A';

        $loanItems = [];
        $totalLoanPaid = 0;
        $totalLoanAmount = 0;
        $totalLoanPenalty = 0;

        foreach ($loanEmis as $emi) {
            $loanAccount = $emi->loanAccount;
            $product = $loanAccount?->loanApplication?->product;
            $coll = $emi->collections?->first();

            $paid = (float) ($coll ? $coll->amount : ($emi->paid_amount ?? 0));
            $total = (float) ($emi->total_amount ?? $emi->pending_amount ?? $paid);
            $penalty = (float) ($emi->penalty_amount ?? 0);
            $status = $coll ? ($coll->status === 'paid' ? 'verified' : $coll->status) : ($emi->status ?? 'verified');
            $isPartial = ($coll?->payment_type === 'partial') || ($emi->status === 'partial') || ($paid > 0 && $paid < $total);

            $totalLoanPaid += $paid;
            $totalLoanAmount += $total;
            $totalLoanPenalty += $penalty;

            $dueDate = $emi->due_date;
            $dueDateStr = $dueDate ? ($dueDate instanceof \DateTimeInterface ? $dueDate->format('Y-m-d') : (string) $dueDate) : null;

            $collDate = $coll?->collected_at ?? $emi->paid_date ?? null;
            $collectedAtStr = $collDate ? ($collDate instanceof \DateTimeInterface ? $collDate->format('Y-m-d H:i:s') : (string) $collDate) : null;

            $loanItems[] = [
                'type' => 'loan',
                'id' => $emi->id,
                'collection_id' => $coll?->id,
                'item_label' => 'EMI #' . ($emi->instalment_number ?? 1) . ' (' . ($loanAccount?->account_number ?? 'Loan') . ')',
                'account_number' => $loanAccount?->account_number ?? 'N/A',
                'client_name' => $loanAccount?->client?->client_name ?? $clientName,
                'loan_name' => $product?->loan_name ?? 'N/A',
                'instalment_no' => 'EMI #' . ($emi->instalment_number ?? 1),
                'instalment_number' => $emi->instalment_number,
                'due_amount' => round($total, 2),
                'amount' => round($total, 2),
                'paid_amount' => round($paid, 2),
                'penalty_amount' => round($penalty, 2),
                'due_date' => $dueDateStr,
                'status' => $status,
                'is_partial' => $isPartial,
                'payment_method' => $coll?->payment_method ?? $emi->payment_method ?? 'Cash',
                'payment_reference' => $coll?->payment_reference ?? $emi->payment_reference ?? 'N/A',
                'collected_at' => $collectedAtStr,
            ];
        }

        $chitItems = [];
        $totalChitPaid = 0;
        $totalChitAmount = 0;
        $totalChitPenalty = 0;

        foreach ($chitInstallments as $installment) {
            $group = $installment->group;
            $pending = $installment->pendingCollections?->sortByDesc('collected_at')?->first();
            $coll = $pending ?? $installment->collections?->first();

            $paid = (float) ($coll ? $coll->amount : ($installment->paid_amount ?? 0));
            $total = (float) ($installment->amount ?? $paid);
            $penalty = (float) ($installment->penalty_amount ?? 0);
            $status = $pending ? 'in_progress' : ($installment->status === 'paid' ? 'verified' : ($installment->status ?? 'verified'));
            $isPartial = ($coll?->payment_type === 'partial') || ($installment->status === 'partial') || ($paid > 0 && $paid < $total);

            $totalChitPaid += $paid;
            $totalChitAmount += $total;
            $totalChitPenalty += $penalty;

            $dueDate = $installment->due_date;
            $dueDateStr = $dueDate ? ($dueDate instanceof \DateTimeInterface ? $dueDate->format('Y-m-d H:i:s') : (string) $dueDate) : null;

            $collDate = $coll?->collected_at ?? $installment->paid_date ?? null;
            $collectedAtStr = $collDate ? ($collDate instanceof \DateTimeInterface ? $collDate->format('Y-m-d H:i:s') : (string) $collDate) : null;

            $chitItems[] = [
                'type' => 'chit',
                'id' => $installment->id,
                'collection_id' => $coll?->id,
                'item_label' => ($group?->group_code ?? 'Chit') . ' - Inst #' . ($installment->month_number ?? 1),
                'account_number' => $group?->group_code ?? 'N/A',
                'client_name' => $installment->member?->client?->client_name ?? $clientName,
                'group_code' => $group?->group_code ?? 'N/A',
                'instalment_no' => 'Inst #' . ($installment->month_number ?? 1),
                'month_number' => $installment->month_number,
                'due_amount' => round($total, 2),
                'amount' => round($total, 2),
                'paid_amount' => round($paid, 2),
                'penalty_amount' => round($penalty, 2),
                'due_date' => $dueDateStr,
                'status' => $status,
                'is_partial' => $isPartial,
                'payment_method' => $coll?->payment_method ?? $installment->payment_mode ?? 'Cash',
                'payment_reference' => $coll?->payment_reference ?? $installment->reference_no ?? 'N/A',
                'collected_at' => $collectedAtStr,
            ];
        }

        $hasLoan = count($loanItems) > 0;
        $hasChit = count($chitItems) > 0;
        $isLoanBulk = count($loanItems) > 1;
        $isChitBulk = count($chitItems) > 1;
        $isTotalBulk = (count($loanItems) + count($chitItems)) > 1;

        $scenario = 'single_loan';
        $receiptTitle = 'LOAN PAYMENT RECEIPT';

        if ($hasLoan && !$hasChit) {
            if ($isLoanBulk) {
                $scenario = 'bulk_loan';
                $receiptTitle = 'LOAN BULK PAYMENT RECEIPT';
            } else {
                $scenario = 'single_loan';
                $receiptTitle = 'LOAN PAYMENT RECEIPT';
            }
        } elseif (!$hasLoan && $hasChit) {
            if ($isChitBulk) {
                $scenario = 'bulk_chit';
                $receiptTitle = 'CHIT BULK PAYMENT RECEIPT';
            } else {
                $scenario = 'single_chit';
                $receiptTitle = 'CHIT PAYMENT RECEIPT';
            }
        } else {
            if ($isTotalBulk) {
                $scenario = 'bulk_loan_and_chit';
                $receiptTitle = 'LOAN & CHIT BULK RECEIPT';
            } else {
                $scenario = 'single_loan_and_chit';
                $receiptTitle = 'LOAN & CHIT COMBINED RECEIPT';
            }
        }

        $allCollectionDates = array_values(array_filter(array_merge(
            array_column($loanItems, 'collected_at'),
            array_column($chitItems, 'collected_at')
        )));
        $paidDateDisplay = !empty($allCollectionDates) ? $allCollectionDates[0] : now()->format('Y-m-d H:i:s');

        $allMethods = array_values(array_unique(array_filter(array_merge(
            array_column($loanItems, 'payment_method'),
            array_column($chitItems, 'payment_method')
        ))));
        $paymentMethodDisplay = !empty($allMethods) ? implode(', ', $allMethods) : 'Cash';

        $allReferences = array_values(array_unique(array_filter(array_merge(
            array_column($loanItems, 'payment_reference'),
            array_column($chitItems, 'payment_reference')
        ))));
        $paymentRefDisplay = !empty($allReferences) && $allReferences[0] !== 'N/A' ? implode(', ', $allReferences) : ($groupId ?? 'N/A');

        $grandTotalPaid = round($totalLoanPaid + $totalChitPaid, 2);
        $grandTotalAmount = round($totalLoanAmount + $totalChitAmount, 2);
        $grandTotalPenalty = round($totalLoanPenalty + $totalChitPenalty, 2);
        $isAnyPartial = collect(array_merge($loanItems, $chitItems))->contains('is_partial', true);

        $primaryId = $loanItems[0]['id'] ?? $chitItems[0]['id'] ?? 1;
        $receiptNumber = $isTotalBulk ? ("RCP-B-" . str_pad($primaryId, 6, '0', STR_PAD_LEFT)) : ("RCP-" . str_pad($primaryId, 6, '0', STR_PAD_LEFT));

        $receiptData = [
            'receipt_number' => $receiptNumber,
            'receipt_title' => $receiptTitle,
            'scenario' => $scenario,
            'client_id' => $client->id ?? null,
            'client_name' => $clientName,
            'customer_id' => $customerId,
            'client_phone' => $clientPhone,
            'paid_date' => $paidDateDisplay,
            'payment_method' => $paymentMethodDisplay,
            'payment_reference' => $paymentRefDisplay,
            'status' => $isAnyPartial ? 'partial' : 'verified',
            'status_label' => $isAnyPartial ? 'Partial Payment' : 'Verified',
            'is_bulk' => $isTotalBulk,
            'is_combined' => $hasLoan && $hasChit,
            'has_partial' => $isAnyPartial,
            'principal_amount' => round($totalLoanPaid + $totalChitPaid, 2),
            'interest_amount' => 0.0,
            'overdue_amount' => $grandTotalPenalty,
            'show_overdue' => $grandTotalPenalty > 0,
            'emi_amount' => $grandTotalAmount,
            'total_amount_display' => $grandTotalAmount,
            'paid_amount' => $grandTotalPaid,
            'outstanding_amount' => round(max(0, $grandTotalAmount - $grandTotalPaid), 2),
            'loan_items' => $loanItems,
            'chit_items' => $chitItems,
            'items' => array_merge($loanItems, $chitItems),
            'all_items' => array_merge($loanItems, $chitItems),
            'application_number' => $loanItems[0]['account_number'] ?? $chitItems[0]['group_code'] ?? 'N/A',
            'disbursed_date' => 'N/A',
            'instalment_label' => $isTotalBulk ? (count($loanItems) + count($chitItems)) . ' Items' : ($loanItems[0]['item_label'] ?? $chitItems[0]['item_label'] ?? 'N/A'),
        ];

        $directory = public_path('receipts/unified');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileSlug = 'receipt_' . strtolower($scenario) . '_' . md5(json_encode($loanIds) . '_' . json_encode($chitIds) . '_' . $grandTotalPaid);
        $fileName = $fileSlug . '.pdf';
        $filePath = $directory . '/' . $fileName;
        $relativePath = 'receipts/unified/' . $fileName;

        if (!file_exists($filePath)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment_receipt', compact('receiptData'));
            $pdf->save($filePath);
        }

        return response()->json([
            'success' => true,
            'receipt_url' => asset($relativePath),
        ]);
    }

    /**
     * Unified Overdue Dues API grouped by Clients.
     * Merges overdue loan EMIs and overdue chit installments with client data inside.
     */
    public function overdueClients(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;

        $typeFilter = $request->input('type', 'all');
        $search = trim($request->input('search', ''));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $loanEmis = collect();
        if ($typeFilter !== 'chit') {
            $loanQuery = Emi::with([
                'loanAccount.client.kycDetail',
                'loanAccount.loanApplication.product'
            ])
            ->whereHas('loanAccount.client', function ($q) use ($agentId, $userId) {
                $q->where(function ($sq) use ($agentId, $userId) {
                    if ($agentId) {
                        $sq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                    }
                    if ($userId) {
                        $sq->orWhere('assigned_to', $userId)->orWhere('added_by', $userId);
                    }
                });
            })
            ->overdue()
            ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');

            if (!empty($search)) {
                $loanQuery->where(function ($q) use ($search) {
                    $q->whereHas('loanAccount.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                           ->orWhere('client_phone', 'LIKE', "%{$search}%")
                           ->orWhere('customer_id', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('loanAccount', function ($lq) use ($search) {
                        $lq->where('account_number', 'LIKE', "%{$search}%");
                    });
                });
            }

            if (!empty($startDate)) {
                $loanQuery->whereDate('due_date', '>=', $startDate);
            }
            if (!empty($endDate)) {
                $loanQuery->whereDate('due_date', '<=', $endDate);
            }

            $loanEmis = $loanQuery->orderBy('due_date', 'asc')->get();
        }

        $chitInstallments = collect();
        if ($typeFilter !== 'loan') {
            $today = \Carbon\Carbon::now()->startOfDay();

            $chitQuery = Installment::with([
                'member.client.kycDetail',
                'group'
            ])
            ->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($sq) use ($today) {
                        $sq->whereIn('status', ['pending', 'partial'])
                            ->whereNotNull('due_date')
                            ->where('due_date', '<', $today);
                    });
            })
            ->whereRaw('(amount + penalty_amount - paid_amount) - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM chit_collections WHERE chit_collections.installment_id = installments.id AND chit_collections.status = "in_progress")');

            $this->applyChitAgentScope($chitQuery, $agentId, $userId);

            if (!empty($search)) {
                $chitQuery->where(function ($q) use ($search) {
                    $q->whereHas('member.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                           ->orWhere('client_phone', 'LIKE', "%{$search}%")
                           ->orWhere('customer_id', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('group', function ($gq) use ($search) {
                        $gq->where('group_code', 'LIKE', "%{$search}%");
                    });
                });
            }

            if (!empty($startDate)) {
                $chitQuery->whereDate('due_date', '>=', $startDate);
            }
            if (!empty($endDate)) {
                $chitQuery->whereDate('due_date', '<=', $endDate);
            }

            $chitInstallments = $chitQuery->orderBy('due_date', 'asc')->get();
        }

        // Group items by Client
        $clientsData = [];

        foreach ($loanEmis as $emi) {
            $client = $emi->loanAccount?->client;
            if (!$client) {
                continue;
            }

            $clientId = $client->id;
            if (!isset($clientsData[$clientId])) {
                $clientsData[$clientId] = [
                    'client' => $client,
                    'loan_emis' => [],
                    'chit_installments' => [],
                ];
            }

            $loan = $emi->loanAccount;
            $product = $loan ? ($loan->loanApplication ? $loan->loanApplication->product : null) : null;
            $amountDue = (float) $emi->pending_amount;

            $clientsData[$clientId]['loan_emis'][] = [
                'id' => $emi->id,
                'type' => 'loan',
                'loan_account_id' => $emi->loan_account_id,
                'account_number' => $loan->account_number ?? 'N/A',
                'loan_name' => $product->loan_name ?? 'N/A',
                'instalment_number' => $emi->instalment_number,
                'emi_label' => "EMI #" . $emi->instalment_number . " - " . ($loan->account_number ?? 'N/A'),
                'amount_due' => round($amountDue, 2),
                'emi_amount' => (float) $emi->total_amount,
                'penalty_amount' => (float) $emi->penalty_amount,
                'paid_amount' => (float) $emi->paid_amount,
                'due_date' => $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->format('Y-m-d') : null,
                'status' => $emi->status,
                'days_overdue' => (int) $emi->dpd_days,
            ];
        }

        foreach ($chitInstallments as $installment) {
            $client = $installment->member?->client;
            if (!$client) {
                continue;
            }

            $clientId = $client->id;
            if (!isset($clientsData[$clientId])) {
                $clientsData[$clientId] = [
                    'client' => $client,
                    'loan_emis' => [],
                    'chit_installments' => [],
                ];
            }

            $amountDue = (float) $installment->amount + (float) $installment->penalty_amount - (float) $installment->paid_amount;

            $clientsData[$clientId]['chit_installments'][] = [
                'id' => $installment->id,
                'type' => 'chit',
                'installment_id' => $installment->id,
                'group_code' => $installment->group->group_code ?? 'N/A',
                'month_number' => $installment->month_number,
                'installment_label' => ($installment->group->group_code ?? 'N/A') . " - Inst #" . $installment->month_number,
                'amount_due' => round(max(0, $amountDue), 2),
                'amount' => (float) $installment->amount,
                'penalty_amount' => (float) $installment->penalty_amount,
                'paid_amount' => (float) $installment->paid_amount,
                'due_date' => $installment->due_date ? \Carbon\Carbon::parse($installment->due_date)->format('Y-m-d') : null,
                'status' => $installment->status,
                'days_overdue' => $installment->due_date ? max(0, \Carbon\Carbon::now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($installment->due_date))) : 0,
            ];
        }

        // Format Client records with overdue details inside
        $formattedClients = collect($clientsData)->map(function ($data) {
            $client = $data['client'];
            $loanEmis = $data['loan_emis'];
            $chitInstallments = $data['chit_installments'];

            $clientImageUrl = null;
            if ($client) {
                $clientImageUrl = $client->profile_image_url;
                if (!$clientImageUrl) {
                    $rawImage = $client->profile_image ?? $client->photo ?? $client->aadhaar_photo_path;
                    if ($rawImage) {
                        $clientImageUrl = str_starts_with($rawImage, 'http') ? $rawImage : asset('storage/' . ltrim($rawImage, '/'));
                    }
                }
            }

            $loanOverdueAmount = collect($loanEmis)->sum('amount_due');
            $chitOverdueAmount = collect($chitInstallments)->sum('amount_due');
            $totalOverdueAmount = $loanOverdueAmount + $chitOverdueAmount;

            $allOverdueItems = array_merge($loanEmis, $chitInstallments);
            usort($allOverdueItems, function ($a, $b) {
                return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
            });

            return [
                'client_id' => $client->id,
                'customer_id' => $client->customer_id ?? 'N/A',
                'client_name' => $client->client_name ?? 'N/A',
                'client_phone' => $client->client_phone ?? 'N/A',
                'alternate_phone' => $client->alternate_phone ?? null,
                'email' => $client->email ?? null,
                'photo' => $clientImageUrl,
                'address' => $client->address ?? null,
                'city' => $client->city ?? null,
                'total_overdue_amount' => round($totalOverdueAmount, 2),
                'loan_overdue_amount' => round($loanOverdueAmount, 2),
                'chit_overdue_amount' => round($chitOverdueAmount, 2),
                'total_overdue_count' => count($allOverdueItems),
                'loan_overdue_count' => count($loanEmis),
                'chit_overdue_count' => count($chitInstallments),
                'overdue_emis' => $loanEmis,
                'overdue_installments' => $chitInstallments,
                'all_overdue_items' => $allOverdueItems,
            ];
        })->values();

        // Overall stats
        $totalLoanAmount = $formattedClients->sum('loan_overdue_amount');
        $totalChitAmount = $formattedClients->sum('chit_overdue_amount');
        $totalLoanCount = $formattedClients->sum('loan_overdue_count');
        $totalChitCount = $formattedClients->sum('chit_overdue_count');

        $stats = [
            'total_clients_count' => $formattedClients->count(),
            'total_overdue_items' => $totalLoanCount + $totalChitCount,
            'total_amount_due' => round($totalLoanAmount + $totalChitAmount, 2),
            'total_loan_overdue_amount' => round($totalLoanAmount, 2),
            'total_chit_overdue_amount' => round($totalChitAmount, 2),
            'total_loan_overdue_count' => $totalLoanCount,
            'total_chit_overdue_count' => $totalChitCount,
        ];

        // Paginate clients
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedClients = new LengthAwarePaginator(
            $formattedClients->slice($offset, $perPage)->values(),
            $formattedClients->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginatedClients,
            'stats' => $stats,
        ]);
    }

    /**
     * Scope to agent's assigned/added clients or active assignments for loan EMI collections.
     */
    private function applyLoanAgentScope(\Illuminate\Database\Eloquent\Builder $query, int $agentId, ?int $userId = null): void
    {
        $query->where(function ($q) use ($agentId, $userId) {
            $q->whereHas('emi.loanAccount.client', function ($cq) use ($agentId, $userId) {
                $cq->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                if ($userId) {
                    $cq->orWhere('assigned_to', $userId)->orWhere('added_by', $userId);
                }
            })
            ->orWhereHas('emi.activeAssignment', function ($aq) use ($agentId, $userId) {
                $aq->where('agent_id', $agentId);
                if ($userId) {
                    $aq->orWhere('agent_id', $userId);
                }
            });
        });
    }

    /**
     * Exclude other agents' chit collections.
     */
    private function excludeOtherAgentChitCollections(\Illuminate\Database\Eloquent\Builder $query, int $agentId, ?int $agentUserId = null): void
    {
        $ownCollectorIds = array_values(array_filter([$agentId, $agentUserId]));

        $otherCollectorIds = Agent::where('id', '!=', $agentId)
            ->get(['id', 'user_id'])
            ->flatMap(fn (Agent $agent) => [(int) $agent->id, $agent->user_id ? (int) $agent->user_id : null])
            ->filter()
            ->reject(fn (int $id) => in_array($id, $ownCollectorIds, true))
            ->unique()
            ->values()
            ->all();

        $query->where(function ($q) use ($otherCollectorIds) {
            $q->whereNull('collected_by');

            if (empty($otherCollectorIds)) {
                $q->orWhereNotNull('collected_by');
            } else {
                $q->orWhereNotIn('collected_by', $otherCollectorIds);
            }
        });
    }

    /**
     * Unified Collections History API grouped by Clients.
     * Merges loan EMI collections and chit installment collections with client data inside.
     */
    public function allCollections(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $currentUser instanceof Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $agentUserId = $currentUser instanceof Agent ? $currentUser->user_id : optional($currentUser)->id;

        $typeFilter = $request->input('type', 'all');
        $search = trim($request->input('search', ''));
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $statusVal = $request->input('status');

        $loanCollectionItems = collect();
        if ($typeFilter !== 'chit') {
            $loanQuery = EmiCollection::with(['agent', 'emi.loanAccount.client.kycDetail']);

            if ($agentId) {
                $this->applyLoanAgentScope($loanQuery, $agentId, $agentUserId);
                $loanQuery->where(function ($q) use ($agentId, $agentUserId) {
                    $q->whereNull('agent_id')->orWhere('agent_id', $agentId);
                    if ($agentUserId) {
                        $q->orWhere('agent_id', $agentUserId);
                    }
                });
            }

            if (!empty($startDate)) {
                $loanQuery->whereDate('collected_at', '>=', $startDate);
            }
            if (!empty($endDate)) {
                $loanQuery->whereDate('collected_at', '<=', $endDate);
            }

            if (!empty($statusVal) && $statusVal !== 'all') {
                if (in_array($statusVal, ['pending', 'in_progress'], true)) {
                    $loanQuery->whereIn('status', ['in_progress', 'pending']);
                } elseif (in_array($statusVal, ['verified', 'paid', 'completed'], true)) {
                    $loanQuery->whereIn('status', ['paid', 'verified', 'completed']);
                } else {
                    $loanQuery->where('status', $statusVal);
                }
            }

            if (!empty($search)) {
                $loanQuery->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                      ->orWhere('amount', 'LIKE', "%{$search}%")
                      ->orWhereHas('agent', function ($aq) use ($search) {
                          $aq->where('agent_name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('emi.loanAccount.client', function ($cq) use ($search) {
                          $cq->where('client_name', 'LIKE', "%{$search}%")
                             ->orWhere('client_phone', 'LIKE', "%{$search}%")
                             ->orWhere('customer_id', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('emi.loanAccount', function ($lq) use ($search) {
                          $lq->where('account_number', 'LIKE', "%{$search}%");
                      });
                });
            }

            $loanQuery->orderBy('collected_at', 'desc')->orderBy('id', 'desc');
            $rawLoanCollections = $loanQuery->get();

            $groupedLoan = BulkPaymentGroup::groupCollections($rawLoanCollections);

            $loanCollectionItems = $groupedLoan->map(function ($group) {
                /** @var EmiCollection $collection */
                $collection = $group['lead'];
                $isBulk = $group['is_bulk'];
                $items = $group['items'];

                $client = $collection->emi?->loanAccount?->client;
                $clientName = $client ? $client->client_name : 'N/A';

                $emiLabel = $isBulk
                    ? 'Bulk Payment ' . BulkPaymentGroup::emiSplitLabel($items)
                    : ($collection->emi && $collection->emi->instalment_number ? 'EMI #' . $collection->emi->instalment_number : '#' . $collection->emi_id);

                $amount = (float) $items->sum('amount');

                $groupId = null;
                if (preg_match('/Group:\s*(\S+)/', $collection->remarks ?? '', $matches)) {
                    $groupId = $matches[1];
                }

                $collectorName = 'Admin';
                $collectorType = 'admin';
                if ($collection->agent_id) {
                    $collectorName = $collection->agent->agent_name ?? 'Agent';
                    $collectorType = 'agent';
                } elseif ($collection->verified_by) {
                    $collectorUser = User::find($collection->verified_by);
                    if ($collectorUser?->agent && $collectorUser->hasRole('Agent')) {
                        $collectorName = $collectorUser->agent->agent_name;
                        $collectorType = 'agent';
                    }
                }

                $emiSplits = $items->map(function ($item) {
                    $instNo = $item->emi?->instalment_number;
                    return [
                        'id' => $item->id,
                        'emi_id' => $item->emi_id,
                        'instalment_number' => $instNo,
                        'label' => $instNo ? "EMI #{$instNo}" : "#{$item->emi_id}",
                        'amount' => round((float) $item->amount, 2),
                    ];
                })->values()->all();

                return [
                    'id' => $collection->id,
                    'type' => 'loan',
                    'collection_type' => 'loan',
                    'client' => $client,
                    'client_id' => $client?->id,
                    'client_name' => $clientName,
                    'label' => $emiLabel,
                    'installment_label' => $emiLabel,
                    'emi_id_label' => $emiLabel,
                    'emi_id' => $collection->emi_id,
                    'amount' => round($amount, 2),
                    'is_bulk' => $isBulk,
                    'bulk_count' => $items->count(),
                    'emi_details' => $emiSplits,
                    'payment_method' => $collection->payment_method,
                    'payment_type' => $isBulk ? BulkPaymentGroup::combinedPaymentType($items) : $collection->payment_type,
                    'status' => $collection->status === 'paid' ? 'verified' : $collection->status,
                    'collected_at' => $collection->collected_at ? $collection->collected_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $collection->created_at ? $collection->created_at->format('Y-m-d H:i:s') : null,
                    'group_id' => $groupId,
                    'collector_name' => $collectorName,
                    'collector_type' => $collectorType,
                    'remarks' => $collection->remarks,
                ];
            });
        }

        $chitCollectionItems = collect();
        if ($typeFilter !== 'loan' && $agentId) {
            $chitQuery = Installment::with([
                'member.client.kycDetail',
                'group',
                'collectedBy.agent',
                'pendingCollections.agent',
                'pendingCollections.client.kycDetail'
            ]);

            $this->applyChitAgentScope($chitQuery, $agentId, $agentUserId);
            $this->excludeOtherAgentChitCollections($chitQuery, $agentId, $agentUserId);

            $chitQuery->where(function ($q) use ($agentId) {
                $q->where('paid_amount', '>', 0)
                    ->orWhereHas('pendingCollections', function ($pq) use ($agentId) {
                        $pq->where('agent_id', $agentId);
                    });
            });

            if (!empty($startDate)) {
                $chitQuery->where(function ($q) use ($startDate) {
                    $q->whereDate('paid_date', '>=', $startDate)
                      ->orWhereHas('pendingCollections', function ($pq) use ($startDate) {
                          $pq->whereDate('collected_at', '>=', $startDate);
                      });
                });
            }

            if (!empty($endDate)) {
                $chitQuery->where(function ($q) use ($endDate) {
                    $q->whereDate('paid_date', '<=', $endDate)
                      ->orWhereHas('pendingCollections', function ($pq) use ($endDate) {
                          $pq->whereDate('collected_at', '<=', $endDate);
                      });
                });
            }

            if (!empty($statusVal) && $statusVal !== 'all') {
                if (in_array($statusVal, ['pending', 'in_progress'], true)) {
                    $chitQuery->where(function ($q) {
                        $q->whereHas('pendingCollections', fn ($pq) => $pq->whereIn('status', ['in_progress', 'pending']))
                            ->orWhere('status', 'partial')
                            ->orWhere('status', 'in_progress');
                    });
                } elseif (in_array($statusVal, ['verified', 'paid', 'completed'], true)) {
                    $chitQuery->where(function ($q) {
                        $q->whereIn('status', ['paid', 'completed', 'verified'])
                            ->whereDoesntHave('pendingCollections', fn ($pq) => $pq->whereIn('status', ['in_progress', 'pending']));
                    });
                } elseif ($statusVal === 'rejected') {
                    $chitQuery->where(function ($q) {
                        $q->where('status', 'rejected')
                            ->orWhereHas('collections', fn ($cq) => $cq->where('status', 'rejected'));
                    });
                } else {
                    $chitQuery->where('status', $statusVal);
                }
            }

            if (!empty($search)) {
                $chitQuery->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                        ->orWhere('paid_amount', 'LIKE', "%{$search}%")
                        ->orWhere('reference_no', 'LIKE', "%{$search}%")
                        ->orWhereHas('member.client', function ($cq) use ($search) {
                            $cq->where('client_name', 'LIKE', "%{$search}%")
                                ->orWhere('client_phone', 'LIKE', "%{$search}%")
                                ->orWhere('customer_id', 'LIKE', "%{$search}%");
                        })
                        ->orWhereHas('group', function ($gq) use ($search) {
                            $gq->where('group_code', 'LIKE', "%{$search}%");
                        });
                });
            }

            $chitQuery->orderBy('id', 'desc');
            $rawInstallments = $chitQuery->get();

            $groupedChit = BulkPaymentGroup::groupInstallments($rawInstallments);

            $chitCollectionItems = $groupedChit->map(function ($group) use ($currentUser) {
                /** @var Installment $installment */
                $installment = $group['lead'];
                $items = $group['items'];
                $isBulk = $group['is_bulk'];

                $groupCode = $installment->group?->group_code ?? 'N/A';
                $pending = $installment->pendingCollections->sortByDesc('collected_at')->first();

                $amount = 0;
                $hasPending = false;
                foreach ($items as $item) {
                    $itemPending = $item->pendingCollections->sortByDesc('collected_at')->first();
                    $amount += (float) ($itemPending ? $itemPending->amount : $item->paid_amount);
                    if ($itemPending || $item->status === 'in_progress') {
                        $hasPending = true;
                    }
                }

                $paymentMode = $pending ? $pending->payment_method : $installment->payment_mode;
                $paymentMode = $paymentMode === 'cash' ? 'in_hand' : ($paymentMode ?? 'cash');

                $paymentType = $pending ? $pending->payment_type : ($installment->status === 'partial' ? 'partial' : 'full');
                $status = $hasPending ? 'in_progress' : ($installment->status === 'paid' ? 'verified' : $installment->status);
                $collectedAt = $pending ? $pending->collected_at : $installment->paid_date;

                $label = $isBulk
                    ? 'Bulk Payment ' . BulkPaymentGroup::chitInstallmentSplitLabel($items)
                    : "{$groupCode} - Inst #{$installment->month_number}";

                $client = $pending && $pending->client_id
                    ? ($pending->relationLoaded('client') ? $pending->client : Client::find($pending->client_id))
                    : ($installment->member?->client);

                $clientName = $client?->client_name ?? 'N/A';

                $collectorName = 'Admin';
                $collectorType = 'admin';

                if ($pending) {
                    $collectorAgent = $pending->relationLoaded('agent') ? $pending->agent : Agent::find($pending->agent_id);
                    if (!$collectorAgent && $pending->agent_id) {
                        $collectorAgent = Agent::where('user_id', $pending->agent_id)->first();
                    }

                    if ($collectorAgent) {
                        $collectorName = $collectorAgent->agent_name;
                        $collectorType = 'agent';
                    } elseif ($pending->agent_id || $currentUser instanceof Agent || $currentUser->hasRole('Agent')) {
                        $agentName = $currentUser instanceof Agent ? $currentUser->agent_name : (optional($currentUser->agent)->agent_name ?? 'Agent');
                        $collectorName = $agentName;
                        $collectorType = 'agent';
                    }
                } else {
                    $collectedBy = $installment->collected_by;
                    if ($collectedBy) {
                        $agent = Agent::where('id', $collectedBy)->orWhere('user_id', $collectedBy)->first();
                        if ($agent) {
                            $user = User::find($agent->user_id);
                            if (!$user || $user->hasRole('Agent') || $user->agent) {
                                $collectorName = $agent->agent_name;
                                $collectorType = 'agent';
                            }
                        } else {
                            $user = $installment->relationLoaded('collectedBy')
                                ? $installment->collectedBy
                                : User::find($collectedBy);

                            if ($user?->agent && $user->hasRole('Agent')) {
                                $collectorName = $user->agent->agent_name;
                                $collectorType = 'agent';
                            }
                        }
                    }
                }

                $chitSplits = $items->map(function ($item) {
                    $itemPending = $item->pendingCollections->sortByDesc('collected_at')->first();
                    $itemAmount = (float) ($itemPending ? $itemPending->amount : $item->paid_amount);
                    return [
                        'id' => $item->id,
                        'installment_id' => $item->id,
                        'instalment_number' => $item->month_number,
                        'month_number' => $item->month_number,
                        'label' => "EMI #{$item->month_number}",
                        'installment_label' => "Inst #{$item->month_number}",
                        'amount' => round($itemAmount, 2),
                    ];
                })->values()->all();

                return [
                    'id' => $installment->id,
                    'type' => 'chit',
                    'collection_type' => 'chit',
                    'client' => $client,
                    'client_id' => $client?->id,
                    'client_name' => $clientName,
                    'label' => $label,
                    'installment_label' => $label,
                    'emi_id_label' => $label,
                    'group_id' => $installment->group_id,
                    'amount' => round($amount, 2),
                    'is_bulk' => $isBulk,
                    'bulk_count' => $items->count(),
                    'items' => $chitSplits,
                    'emi_details' => $chitSplits,
                    'splits' => $chitSplits,
                    'payment_method' => $paymentMode,
                    'payment_type' => $paymentType ?: 'full',
                    'status' => $status,
                    'collected_at' => $collectedAt ? \Carbon\Carbon::parse($collectedAt)->format('Y-m-d H:i:s') : null,
                    'created_at' => $installment->updated_at ? $installment->updated_at->format('Y-m-d H:i:s') : null,
                    'chit_collection_id' => $pending ? $pending->id : null,
                    'collector_name' => $collectorName,
                    'collector_type' => $collectorType,
                    'remarks' => $pending ? $pending->remarks : $installment->remarks,
                ];
            });
        }

        if (!empty($statusVal) && $statusVal !== 'all') {
            $filterStatus = function ($item) use ($statusVal) {
                $st = $item['status'] ?? '';
                if (in_array($statusVal, ['pending', 'in_progress'], true)) {
                    return in_array($st, ['pending', 'in_progress'], true);
                }
                if (in_array($statusVal, ['verified', 'paid', 'completed'], true)) {
                    return in_array($st, ['verified', 'paid', 'completed'], true);
                }
                return $st === $statusVal;
            };

            $loanCollectionItems = $loanCollectionItems->filter($filterStatus)->values();
            $chitCollectionItems = $chitCollectionItems->filter($filterStatus)->values();
        }

        // Group collection items by Client
        $clientsData = [];

        foreach ($loanCollectionItems as $item) {
            $client = $item['client'] ?? null;
            $clientId = $item['client_id'] ?? ($client?->id);
            if (!$clientId) {
                continue;
            }

            if (!isset($clientsData[$clientId])) {
                $clientsData[$clientId] = [
                    'client' => $client,
                    'loan_collections' => [],
                    'chit_collections' => [],
                ];
            }

            unset($item['client']);
            $clientsData[$clientId]['loan_collections'][] = $item;
        }

        foreach ($chitCollectionItems as $item) {
            $client = $item['client'] ?? null;
            $clientId = $item['client_id'] ?? ($client?->id);
            if (!$clientId) {
                continue;
            }

            if (!isset($clientsData[$clientId])) {
                $clientsData[$clientId] = [
                    'client' => $client,
                    'loan_collections' => [],
                    'chit_collections' => [],
                ];
            }

            unset($item['client']);
            $clientsData[$clientId]['chit_collections'][] = $item;
        }

        // Format Client records with loan and chit collections inside
        $formattedClients = collect($clientsData)->map(function ($data) {
            $client = $data['client'];
            $loanCollections = $data['loan_collections'];
            $chitCollections = $data['chit_collections'];

            $clientImageUrl = null;
            if ($client) {
                $clientImageUrl = $client->profile_image_url;
                if (!$clientImageUrl) {
                    $rawImage = $client->profile_image ?? $client->photo ?? $client->aadhaar_photo_path;
                    if ($rawImage) {
                        $clientImageUrl = str_starts_with($rawImage, 'http') ? $rawImage : asset('storage/' . ltrim($rawImage, '/'));
                    }
                }
            }

            $loanCollectedAmount = collect($loanCollections)->sum('amount');
            $chitCollectedAmount = collect($chitCollections)->sum('amount');
            $totalCollectedAmount = $loanCollectedAmount + $chitCollectedAmount;

            $allCollections = array_merge($loanCollections, $chitCollections);
            usort($allCollections, function ($a, $b) {
                return strcmp($b['collected_at'] ?? $b['created_at'] ?? '', $a['collected_at'] ?? $a['created_at'] ?? '');
            });

            return [
                'client_id' => $client?->id,
                'customer_id' => $client?->customer_id ?? 'N/A',
                'client_name' => $client?->client_name ?? 'N/A',
                'client_phone' => $client?->client_phone ?? 'N/A',
                'alternate_phone' => $client?->alternate_phone ?? null,
                'email' => $client?->email ?? null,
                'photo' => $clientImageUrl,
                'address' => $client?->address ?? null,
                'city' => $client?->city ?? null,
                'total_collected_amount' => round($totalCollectedAmount, 2),
                'loan_collected_amount' => round($loanCollectedAmount, 2),
                'chit_collected_amount' => round($chitCollectedAmount, 2),
                'total_collections_count' => count($allCollections),
                'loan_collections_count' => count($loanCollections),
                'chit_collections_count' => count($chitCollections),
                'loan_collections' => $loanCollections,
                'chit_collections' => $chitCollections
            ];
        })->values();

        // Overall stats
        $totalLoanAmount = $formattedClients->sum('loan_collected_amount');
        $totalChitAmount = $formattedClients->sum('chit_collected_amount');
        $totalLoanCount = $formattedClients->sum('loan_collections_count');
        $totalChitCount = $formattedClients->sum('chit_collections_count');

        $stats = [
            'total_clients_count' => $formattedClients->count(),
            'total_collections_count' => $totalLoanCount + $totalChitCount,
            'total_amount_collected' => round($totalLoanAmount + $totalChitAmount, 2),
            'total_loan_collected_amount' => round($totalLoanAmount, 2),
            'total_chit_collected_amount' => round($totalChitAmount, 2),
            'total_loan_collections_count' => $totalLoanCount,
            'total_chit_collections_count' => $totalChitCount,
        ];

        // Paginate clients
        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedClients = new LengthAwarePaginator(
            $formattedClients->slice($offset, $perPage)->values(),
            $formattedClients->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json([
            'success' => true,
            'data' => $paginatedClients,
            'stats' => $stats,
        ]);
    }
}
