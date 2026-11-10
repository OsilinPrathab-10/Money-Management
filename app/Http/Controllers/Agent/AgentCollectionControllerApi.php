<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\EmiCollection;
use App\Models\Agent;
use App\Models\Emi;
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
use App\Models\CollectionNote;
use Carbon\Carbon;

class AgentCollectionControllerApi extends Controller
{
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
     * Return loan dues available to the logged-in agent for collection.
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
            'type' => 'nullable|in:loan,all',
            'search' => 'nullable|string|max:100',
        ]);

        $type = $validated['type'] ?? 'all';
        $search = trim($validated['search'] ?? '');
        $rows = collect();

        $loanQuery = Emi::with(['loanAccount.client', 'collections', 'latestCollectionNote'])
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

                $latestNote = $emi->latestCollectionNote;
                $remDate = $latestNote?->reminder_date ? Carbon::parse($latestNote->reminder_date) : null;
                $isRemToday = $remDate ? $remDate->isToday() : false;
                $isRemOverdue = $remDate ? ($remDate->startOfDay()->lt(now()->startOfDay()) && !$latestNote->is_completed) : false;

                $forceEnable = $status === 'overdue' || ($status === 'pending' && $isDueToday) || $isRemToday;
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
                    'reminder_date' => $remDate ? $remDate->format('Y-m-d') : null,
                    'reminder_date_formatted' => $remDate ? $remDate->format('d-m-Y') : null,
                    'is_reminder_today' => $isRemToday,
                    'is_reminder_overdue' => $isRemOverdue,
                    'latest_note' => $latestNote?->note,
                    'followup_status' => $latestNote?->status,
                ];

                return $amount > 0 ? [$row] : [];
            });
            $loanRows = $this->applyRupeeRoundingToLoanDueRows($loanRows)
                ->reject(fn ($row) => $this->isOpenLoanPrincipalDueRow($row))
                ->values();
            $rows = $rows->concat($loanRows);
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
                   $request->filled('emi_id');
                   


        if (!$hasLoan) {
            return response()->json(['success' => false, 'message' => 'Loan EMI details are required.'], 422);
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



        $request->validate($rules);

        DB::beginTransaction();
        try {
            $messages = [];

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
                    ? 'Loan collections recorded and sent for admin verification.' 
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
                    $rawId = (string) ($it['id'] ?? $it['due_id'] ?? $it['emi_id'] ?? '');
                    if (!str_starts_with($rawId, 'loan_') && $rawId !== '') {
                        $newId = 'loan_' . $rawId;
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
            'items.*.id' => ['required', 'string', 'distinct', 'regex:/^loan_[A-Za-z0-9_\-]+$/'],
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
                                $preferredRootClientId = $request->filled('client_id') ? (int) $request->input('client_id') : null;

                foreach ($validated['items'] as $item) {
                    $itemId = (string) $item['id'];
                    $type = 'loan';
                    $amount = round((float) $item['amount'], 2);
                    $paymentType = $item['payment_type'] ?? 'full';



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



    public function unifiedReceipt(Request $request, $id = null): JsonResponse
    {
        $agentUser = Auth::user();
        if (!$agentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $rawId = $id ?? $request->input('id') ?? $request->input('receipt_id') ?? $request->input('group_id') ?? $request->input('bulk_key');
        $loanIdsInput = $request->input('loan_id') ?? $request->input('loan_ids') ?? $request->input('emi_id') ?? $request->input('emi_ids');
        $typeInput = strtolower(trim((string) $request->input('type', '')));

        $loanIds = [];
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

        if (!empty($rawId)) {
            $rawItems = $parseIds($rawId);
            foreach ($rawItems as $rItem) {
                $rItemStr = (string) $rItem;
                if (str_starts_with(strtolower($rItemStr), 'loan_') || str_starts_with(strtolower($rItemStr), 'emi_')) {
                    $clean = preg_replace('/^(?:loan_|emi_)/i', '', $rItemStr);
                    if (is_numeric($clean)) {
                        $loanIds[] = (int) $clean;
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
                    }

                    if (!$foundInColl) {
                        if (true) {
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
        }

        $loanIds = array_values(array_unique(array_filter($loanIds)));

        if (empty($loanIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid loan collection identifiers provided for receipt generation.'
            ], 404);
        }

        $loanEmis = Emi::with([
            'loanAccount.client',
            'loanAccount.loanApplication.product',
            'collections' => function ($q) {
                $q->whereIn('status', ['paid', 'in_progress', 'verified', 'approved'])->orderBy('id', 'desc');
            }
        ])->whereIn('id', $loanIds)->get();

        if ($loanEmis->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Receipt data not found for the provided identifiers.'
            ], 404);
        }

        $client = $loanEmis->first()?->loanAccount?->client;

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

        
        $isLoanBulk = count($loanItems) > 1;
        $isTotalBulk = $isLoanBulk;
        $scenario = $isLoanBulk ? 'bulk_loan' : 'single_loan';
        $receiptTitle = 'LOAN PAYMENT RECEIPT';

        $allCollectionDates = array_values(array_filter(array_column($loanItems, 'collected_at')));
        $paidDateDisplay = !empty($allCollectionDates) ? $allCollectionDates[0] : now()->format('Y-m-d H:i:s');

        $allMethods = array_values(array_unique(array_filter(array_column($loanItems, 'payment_method'))));
        $paymentMethodDisplay = !empty($allMethods) ? implode(', ', $allMethods) : 'Cash';

        $allReferences = array_values(array_unique(array_filter(array_column($loanItems, 'payment_reference'))));
        $paymentRefDisplay = !empty($allReferences) && $allReferences[0] !== 'N/A' ? implode(', ', $allReferences) : ($groupId ?? 'N/A');

        $grandTotalPaid = round($totalLoanPaid, 2);
        $grandTotalAmount = round($totalLoanAmount, 2);
        $grandTotalPenalty = round($totalLoanPenalty, 2);
        $isAnyPartial = collect($loanItems)->contains('is_partial', true);

        $primaryId = $loanItems[0]['id'] ?? 1;
        $receiptNumber = $isTotalBulk ? ("RCP-B-" . str_pad($primaryId, 6, '0', STR_PAD_LEFT)) : ("RCP-" . str_pad($primaryId, 6, '0', STR_PAD_LEFT));

        $receiptData = [
            'receipt_number' => $receiptNumber,
            'receipt_title' => $receiptTitle,
            'scenario' => $scenario,
            'module' => 'loan',
            'account_label' => 'Loan ID',
            'start_date_label' => 'Disbursement Date',
            'split_item_label' => 'EMI',
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
            'is_combined' => false,
            'has_partial' => $isAnyPartial,
            'principal_amount' => round($totalLoanPaid, 2),
            'interest_amount' => 0.0,
            'overdue_amount' => $grandTotalPenalty,
            'show_overdue' => $grandTotalPenalty > 0,
            'total_due_amount' => $grandTotalAmount,
            'due_amount' => $grandTotalAmount,
            'previously_paid' => 0.0,
            'previous_paid_amount' => 0.0,
            'current_payment' => $grandTotalPaid,
            'today_payment' => $grandTotalPaid,
            'emi_amount' => $grandTotalPaid,
            'total_amount_display' => $grandTotalPaid,
            'paid_amount' => $grandTotalPaid,
            'total_paid_amount' => $grandTotalPaid,
            'outstanding_amount' => round(max(0, $grandTotalAmount - $grandTotalPaid), 2),
            'loan_items' => $loanItems,
            'chit_items' => [],
            'items' => $loanItems,
            'all_items' => $loanItems,
            'application_number' => $loanItems[0]['account_number'] ?? 'N/A',
            'disbursed_date' => 'N/A',
            'instalment_label' => $isTotalBulk ? count($loanItems) . ' Items' : ($loanItems[0]['item_label'] ?? 'N/A'),
        ];

        $directory = public_path('receipts/unified');
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileSlug = 'receipt_' . strtolower($scenario) . '_' . md5(json_encode($loanIds) . '_' . $grandTotalPaid);
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
     * Merges overdue loan EMIs with client data inside.
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
        if (true) {
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

        // Format Client records with overdue details inside
        $formattedClients = collect($clientsData)->map(function ($data) {
            $client = $data['client'];
            $loanEmis = $data['loan_emis'];
            $chitInstallments = [];

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
            $chitOverdueAmount = 0.0;
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
     * Return active collection reminders for loan EMIs and chit installments.
     * GET /api/agent/agent-collections/reminders?type=loan&page=1&per_page=50
     * Supports: filter=today|overdue|upcoming|all, date=YYYY-MM-DD, search, type=loan|chit|all
     */
    public function getReminders(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
        $isAdminOrStaff = ($currentUser instanceof User && ($currentUser->hasRole('Admin') || $currentUser->hasRole('Staff') || $currentUser->hasRole('Super Admin')));

        $typeFilter = $request->input('type', 'all');
        $search = trim($request->input('search', ''));
        $filter = $request->input('filter', 'all'); // 'today', 'overdue', 'upcoming', 'all'
        $filterDate = $request->input('date'); // optional specific date YYYY-MM-DD
        $statusFilter = $request->input('status'); // optional status
        $today = Carbon::now()->startOfDay();

        $items = collect();

        // 1. LOAN EMIs WITH ACTIVE REMINDERS
        if (true) {
            $loanEmiQuery = Emi::with([
                'loanAccount.client.kycDetail',
                'loanAccount.loanApplication.product',
                'collectionNotes' => fn ($q) => $q->orderBy('created_at', 'desc'),
                'collectionNotes.user',
                'collectionNotes.agent',
                'followups' => fn ($q) => $q->orderBy('created_at', 'desc'),
                'followups.agent',
            ])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');

            // Apply Agent Access Scope
            if (!$isAdminOrStaff && ($agentId || $userId)) {
                $loanEmiQuery->where(function ($q) use ($agentId, $userId) {
                    $q->whereHas('loanAccount.client', function ($clientQ) use ($agentId, $userId) {
                        if ($agentId) {
                            $clientQ->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                        }
                        if ($userId) {
                            $clientQ->orWhere('assigned_to', $userId)->orWhere('added_by', $userId);
                        }
                    })->orWhereHas('activeAssignment', function ($assignmentQ) use ($agentId, $userId) {
                        if ($agentId) {
                            $assignmentQ->where('agent_id', $agentId);
                        }
                        if ($userId) {
                            $assignmentQ->orWhere('agent_id', $userId);
                        }
                    });
                });
            }

            // Must have a reminder!
            $loanEmiQuery->where(function ($q) use ($filterDate, $filter, $today, $agentId, $userId, $isAdminOrStaff) {
                $q->whereHas('collectionNotes', function ($noteQ) use ($filterDate, $filter, $today) {
                    $noteQ->where('is_completed', false)->whereNotNull('reminder_date');
                    if ($filterDate) {
                        $noteQ->whereDate('reminder_date', $filterDate);
                    } elseif ($filter === 'today') {
                        $noteQ->whereDate('reminder_date', $today);
                    } elseif ($filter === 'overdue') {
                        $noteQ->whereDate('reminder_date', '<', $today);
                    } elseif ($filter === 'upcoming') {
                        $noteQ->whereDate('reminder_date', '>', $today);
                    }
                })->orWhereHas('followups', function ($fQ) use ($filterDate, $filter, $today, $agentId, $userId, $isAdminOrStaff) {
                    if (!$isAdminOrStaff && ($agentId || $userId)) {
                        $fQ->where(function ($sub) use ($agentId, $userId) {
                            if ($agentId) $sub->where('agent_id', $agentId);
                            if ($userId) $sub->orWhere('agent_id', $userId);
                        });
                    }
                    if ($filterDate) {
                        $fQ->whereDate('followup_at', $filterDate);
                    } elseif ($filter === 'today') {
                        $fQ->whereDate('followup_at', $today);
                    } elseif ($filter === 'overdue') {
                        $fQ->whereDate('followup_at', '<', $today);
                    } elseif ($filter === 'upcoming') {
                        $fQ->whereDate('followup_at', '>', $today);
                    }
                });
            });

            if (!empty($search)) {
                $loanEmiQuery->where(function ($q) use ($search) {
                    $q->whereHas('loanAccount.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                           ->orWhere('client_phone', 'LIKE', "%{$search}%")
                           ->orWhere('customer_id', 'LIKE', "%{$search}%");
                    })->orWhereHas('loanAccount', function ($lq) use ($search) {
                        $lq->where('account_number', 'LIKE', "%{$search}%");
                    });
                });
            }

            foreach ($loanEmiQuery->get() as $emi) {
                $client = $emi->loanAccount?->client;
                $loan = $emi->loanAccount;
                $product = $loan?->loanApplication?->product;

                $latestNote = $emi->collectionNotes->first();
                $latestFollowup = $emi->followups->first();

                $reminderDate = null;
                $reminderTime = null;
                $latestRemarks = '';
                $followupStatus = 'postponed';
                $createdBy = '';
                $createdAt = null;

                if ($latestNote && $latestNote->reminder_date) {
                    $reminderDate = Carbon::parse($latestNote->reminder_date);
                    $reminderTime = $latestNote->reminder_time;
                    $latestRemarks = $latestNote->note;
                    $followupStatus = $latestNote->status ?: 'postponed';
                    $createdBy = $latestNote->creator_name;
                    $createdAt = $latestNote->created_at;
                } elseif ($latestFollowup && $latestFollowup->followup_at) {
                    $reminderDate = Carbon::parse($latestFollowup->followup_at);
                    $latestRemarks = $latestFollowup->remarks;
                    $followupStatus = $latestFollowup->status ?: 'postponed';
                    $createdBy = $latestFollowup->agent?->agent_name ? $latestFollowup->agent->agent_name . ' (Agent)' : 'Agent';
                    $createdAt = $latestFollowup->created_at;
                }

                if (!$reminderDate) {
                    continue;
                }

                $isReminderToday = $reminderDate->isToday();
                $isReminderOverdue = $reminderDate->startOfDay()->lt($today);
                $isReminderUpcoming = $reminderDate->startOfDay()->gt($today);

                if ($filterDate && $reminderDate->toDateString() !== $filterDate) {
                    continue;
                }
                if ($filter === 'today' && !$isReminderToday) {
                    continue;
                }
                if ($filter === 'overdue' && !$isReminderOverdue) {
                    continue;
                }
                if ($filter === 'upcoming' && !$isReminderUpcoming) {
                    continue;
                }
                if ($statusFilter && $followupStatus !== $statusFilter) {
                    continue;
                }

                $history = [];
                foreach ($emi->collectionNotes as $note) {
                    $history[] = [
                        'id' => $note->id,
                        'source' => 'note',
                        'note' => $note->note,
                        'reminder_date' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('Y-m-d') : null,
                        'reminder_date_formatted' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('d-m-Y') : null,
                        'status' => $note->status ?: 'postponed',
                        'created_by' => $note->creator_name,
                        'created_at' => $note->created_at ? $note->created_at->format('d-m-Y h:i A') : '',
                    ];
                }

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

                $items->push([
                    'id' => 'loan_' . $emi->id,
                    'type' => 'loan',
                    'emi_id' => $emi->id,
                    'loan_account_id' => $loan?->id,
                    'account_number' => $loan?->account_number ?? 'N/A',
                    'loan_name' => $product?->loan_name ?? 'Loan',
                    'client_id' => $client?->id,
                    'customer_id' => $client?->customer_id ?? 'N/A',
                    'client_name' => $client?->client_name ?? 'N/A',
                    'client_phone' => $client?->client_phone ?? 'N/A',
                    'client_photo' => $clientImageUrl,
                    'address' => $client?->address ?? null,
                    'city' => $client?->city ?? null,
                    'label' => 'EMI #' . $emi->instalment_number,
                    'instalment_number' => $emi->instalment_number,
                    'amount_due' => (float) $emi->pending_amount,
                    'due_date' => $emi->due_date ? Carbon::parse($emi->due_date)->format('Y-m-d') : null,
                    'due_date_formatted' => $emi->due_date ? Carbon::parse($emi->due_date)->format('d-m-Y') : null,
                    'status' => $emi->status,
                    'reminder_date' => $reminderDate->format('Y-m-d'),
                    'reminder_date_formatted' => $reminderDate->format('d-m-Y'),
                    'reminder_time' => $reminderTime ? Carbon::parse($reminderTime)->format('h:i A') : null,
                    'followup_status' => $followupStatus,
                    'is_reminder_today' => $isReminderToday,
                    'is_reminder_overdue' => $isReminderOverdue,
                    'is_reminder_upcoming' => $isReminderUpcoming,
                    'latest_note' => $latestRemarks,
                    'created_by' => $createdBy,
                    'created_at' => $createdAt ? Carbon::parse($createdAt)->format('d-m-Y h:i A') : '',
                    'notes_history' => $history,
                ]);
            }
        }

        // Sorting: today's reminders first (0), overdue reminders next (1), upcoming reminders next (2), by reminder_date asc
        $sortedItems = $items->sort(function ($a, $b) {
            $priorityA = $a['is_reminder_today'] ? 0 : ($a['is_reminder_overdue'] ? 1 : 2);
            $priorityB = $b['is_reminder_today'] ? 0 : ($b['is_reminder_overdue'] ? 1 : 2);
            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }
            return strcmp($a['reminder_date'] ?? '', $b['reminder_date'] ?? '');
        })->values();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(200, (int) $request->input('per_page', 50)));
        $offset = ($page - 1) * $perPage;
        $paginatedItems = $sortedItems->slice($offset, $perPage)->values();

        $stats = [
            'total_reminders' => $sortedItems->count(),
            'today_reminders' => $sortedItems->where('is_reminder_today', true)->count(),
            'overdue_reminders' => $sortedItems->where('is_reminder_overdue', true)->count(),
            'upcoming_reminders' => $sortedItems->where('is_reminder_upcoming', true)->count(),
            'total_amount_due' => round($sortedItems->sum('amount_due'), 2),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Reminders retrieved successfully',
            'data' => $paginatedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $sortedItems->count(),
                'last_page' => (int) ceil($sortedItems->count() / max(1, $perPage)),
                'from' => $sortedItems->isEmpty() ? 0 : $offset + 1,
                'to' => min($offset + $perPage, $sortedItems->count()),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Return pending follow-ups and collection reminders for loan EMIs.
     * GET /api/agent/agent-collections/pending-follow-ups?type=loan&page=1&per_page=50
     */
    public function pendingFollowUps(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;
        $isAdminOrStaff = ($currentUser instanceof User && ($currentUser->hasRole('Admin') || $currentUser->hasRole('Staff') || $currentUser->hasRole('Super Admin')));

        $typeFilter = $request->input('type', 'all');
        $search = trim($request->input('search', ''));
        $filterDate = $request->input('date'); // optional specific date
        $statusFilter = $request->input('status'); // optional status
        $today = Carbon::now()->startOfDay();
        $targetDate = $filterDate ? Carbon::parse($filterDate)->startOfDay() : $today;
        $targetDateStr = $targetDate->toDateString();
        $onlyReminders = filter_var($request->input('only_reminders', $request->input('has_reminder', false)), FILTER_VALIDATE_BOOLEAN);

        $items = collect();

        // 1. LOAN EMIs WITH FOLLOW-UPS
        if (true) {
            $loanEmiQuery = Emi::with([
                'loanAccount.client.kycDetail',
                'loanAccount.loanApplication.product',
                'collectionNotes' => fn ($q) => $q->orderBy('created_at', 'desc'),
                'collectionNotes.user',
                'collectionNotes.agent',
                'followups' => fn ($q) => $q->orderBy('created_at', 'desc'),
                'followups.agent',
            ])
            ->whereIn('status', ['pending', 'overdue', 'partial'])
            ->whereRaw('pending_amount - 0.01 > (SELECT COALESCE(SUM(amount), 0) FROM emi_collections WHERE emi_collections.emi_id = emis.id AND emi_collections.status = "in_progress")');

            // Apply Agent Access Scope for regular agents
            if (!$isAdminOrStaff && ($agentId || $userId)) {
                $loanEmiQuery->where(function ($q) use ($agentId, $userId) {
                    $q->whereHas('loanAccount.client', function ($clientQ) use ($agentId, $userId) {
                        if ($agentId) {
                            $clientQ->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                        }
                        if ($userId) {
                            $clientQ->orWhere('assigned_to', $userId)->orWhere('added_by', $userId);
                        }
                    })->orWhereHas('activeAssignment', function ($assignmentQ) use ($agentId, $userId) {
                        if ($agentId) {
                            $assignmentQ->where('agent_id', $agentId);
                        }
                        if ($userId) {
                            $assignmentQ->orWhere('agent_id', $userId);
                        }
                    });
                });
            }

            // Only today collections, OR items whose reminder is set for that day
            $loanEmiQuery->where(function ($q) use ($targetDateStr, $onlyReminders, $agentId, $userId, $isAdminOrStaff) {
                // 1. Dues with an active reminder set for this specific day
                $q->whereHas('collectionNotes', function ($noteQ) use ($targetDateStr) {
                    $noteQ->where('is_completed', false)->whereDate('reminder_date', $targetDateStr);
                })->orWhereHas('followups', function ($fQ) use ($targetDateStr, $agentId, $userId, $isAdminOrStaff) {
                    if (!$isAdminOrStaff && ($agentId || $userId)) {
                        $fQ->where(function ($sub) use ($agentId, $userId) {
                            if ($agentId) $sub->where('agent_id', $agentId);
                            if ($userId) $sub->orWhere('agent_id', $userId);
                        });
                    }
                    $fQ->whereDate('followup_at', $targetDateStr);
                });

                // 2. Regular collection dues on that day (due date matches target date)
                if (!$onlyReminders) {
                    $q->orWhereDate('due_date', $targetDateStr);
                }
            });

            if (!empty($search)) {
                $loanEmiQuery->where(function ($q) use ($search) {
                    $q->whereHas('loanAccount.client', function ($cq) use ($search) {
                        $cq->where('client_name', 'LIKE', "%{$search}%")
                           ->orWhere('client_phone', 'LIKE', "%{$search}%")
                           ->orWhere('customer_id', 'LIKE', "%{$search}%");
                    })->orWhereHas('loanAccount', function ($lq) use ($search) {
                        $lq->where('account_number', 'LIKE', "%{$search}%");
                    });
                });
            }

            foreach ($loanEmiQuery->get() as $emi) {
                $client = $emi->loanAccount?->client;
                $loan = $emi->loanAccount;
                $product = $loan?->loanApplication?->product;

                $latestNote = $emi->collectionNotes->first();
                $latestFollowup = $emi->followups->first();

                $reminderDate = null;
                $reminderTime = null;
                $latestRemarks = '';
                $followupStatus = $emi->status;
                $createdBy = '';
                $createdAt = null;

                if ($latestNote) {
                    $reminderDate = $latestNote->reminder_date ? Carbon::parse($latestNote->reminder_date) : null;
                    $reminderTime = $latestNote->reminder_time;
                    $latestRemarks = $latestNote->note;
                    $followupStatus = $latestNote->status ?: 'postponed';
                    $createdBy = $latestNote->creator_name;
                    $createdAt = $latestNote->created_at;
                } elseif ($latestFollowup) {
                    $reminderDate = $latestFollowup->followup_at ? Carbon::parse($latestFollowup->followup_at) : null;
                    $latestRemarks = $latestFollowup->remarks;
                    $followupStatus = $latestFollowup->status ?: 'postponed';
                    $createdBy = $latestFollowup->agent?->agent_name ? $latestFollowup->agent->agent_name . ' (Agent)' : 'Agent';
                    $createdAt = $latestFollowup->created_at;
                }

                // If this due was postponed to a different reminder date, hide from today's list
                // It will only be sent on that reminder day!
                if ($reminderDate && $reminderDate->toDateString() !== $targetDateStr) {
                    continue;
                }

                $isReminderToday = $reminderDate && $reminderDate->toDateString() === $targetDateStr;
                $isReminderOverdue = $reminderDate && $reminderDate->startOfDay()->lt($today) && (!($latestNote?->is_completed));

                if ($statusFilter && $followupStatus !== $statusFilter) {
                    continue;
                }

                $history = [];
                foreach ($emi->collectionNotes as $note) {
                    $history[] = [
                        'id' => $note->id,
                        'source' => 'note',
                        'note' => $note->note,
                        'reminder_date' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('Y-m-d') : null,
                        'reminder_date_formatted' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('d-m-Y') : null,
                        'status' => $note->status ?: 'postponed',
                        'created_by' => $note->creator_name,
                        'created_at' => $note->created_at ? $note->created_at->format('d-m-Y h:i A') : '',
                    ];
                }
                foreach ($emi->followups as $f) {
                    $history[] = [
                        'id' => $f->id,
                        'source' => 'followup',
                        'note' => $f->remarks,
                        'reminder_date' => $f->followup_at ? Carbon::parse($f->followup_at)->format('Y-m-d') : null,
                        'reminder_date_formatted' => $f->followup_at ? Carbon::parse($f->followup_at)->format('d-m-Y') : null,
                        'status' => $f->status ?: 'postponed',
                        'created_by' => $f->agent?->agent_name ? $f->agent->agent_name . ' (Agent)' : 'Agent',
                        'created_at' => $f->created_at ? $f->created_at->format('d-m-Y h:i A') : '',
                    ];
                }

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

                $amountDue = (float) $emi->pending_amount;
                $daysOverdue = $emi->due_date ? max(0, $today->diffInDays(Carbon::parse($emi->due_date), false) * -1) : 0;

                $items->push([
                    'id' => 'loan_' . $emi->id,
                    'type' => 'loan',
                    'emi_id' => $emi->id,
                    'loan_account_id' => $emi->loan_account_id,
                    'account_number' => $loan->account_number ?? 'N/A',
                    'loan_name' => $product->loan_name ?? 'Loan',
                    'client_id' => $client?->id,
                    'customer_id' => $client?->customer_id ?? 'N/A',
                    'client_name' => $client?->client_name ?? 'N/A',
                    'client_phone' => $client?->client_phone ?? 'N/A',
                    'client_photo' => $clientImageUrl,
                    'address' => $client?->address ?? null,
                    'city' => $client?->city ?? null,
                    'label' => 'EMI #' . $emi->instalment_number,
                    'instalment_number' => $emi->instalment_number,
                    'amount_due' => round($amountDue, 2),
                    'total_amount' => (float) $emi->total_amount,
                    'penalty_amount' => (float) $emi->penalty_amount,
                    'paid_amount' => (float) $emi->paid_amount,
                    'due_date' => $emi->due_date ? Carbon::parse($emi->due_date)->format('Y-m-d') : null,
                    'days_overdue' => (int) $daysOverdue,
                    'status' => $emi->status,
                    'reminder_date' => $reminderDate ? $reminderDate->format('Y-m-d') : null,
                    'reminder_date_formatted' => $reminderDate ? $reminderDate->format('d-m-Y') : null,
                    'reminder_time' => $reminderTime,
                    'followup_status' => $followupStatus,
                    'is_reminder_today' => $isReminderToday,
                    'is_reminder_overdue' => $isReminderOverdue,
                    'latest_note' => $latestRemarks,
                    'created_by' => $createdBy,
                    'created_at' => $createdAt ? $createdAt->format('d-m-Y h:i A') : '',
                    'notes_history' => $history,
                ]);
            }
        }

        // Sort items: Today first, then overdue reminders, then future reminders, then by reminder date
        $sortedItems = $items->sort(function ($a, $b) {
            if ($a['is_reminder_today'] && !$b['is_reminder_today']) return -1;
            if (!$a['is_reminder_today'] && $b['is_reminder_today']) return 1;

            if ($a['is_reminder_overdue'] && !$b['is_reminder_overdue']) return -1;
            if (!$a['is_reminder_overdue'] && $b['is_reminder_overdue']) return 1;

            return strcmp($a['reminder_date'] ?? '9999-99-99', $b['reminder_date'] ?? '9999-99-99');
        })->values();

        $stats = [
            'total_follow_ups' => $sortedItems->count(),
            'today_reminders' => $sortedItems->where('is_reminder_today', true)->count(),
            'overdue_reminders' => $sortedItems->where('is_reminder_overdue', true)->count(),
            'loan_count' => $sortedItems->where('type', 'loan')->count(),
            'chit_count' => 0,
            'total_amount_due' => round($sortedItems->sum('amount_due'), 2),
        ];

        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);
        $offset = ($page - 1) * $perPage;

        $paginatedItems = $sortedItems->slice($offset, $perPage)->values();

        return response()->json([
            'success' => true,
            'message' => 'Pending follow-ups retrieved successfully',
            'data' => $paginatedItems,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $sortedItems->count(),
                'last_page' => (int) ceil($sortedItems->count() / max(1, $perPage)),
                'from' => $sortedItems->isEmpty() ? 0 : $offset + 1,
                'to' => min($offset + $perPage, $sortedItems->count()),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Store a follow-up / collection note from mobile agent app.
     * POST /api/agent/agent-collections/pending-follow-ups
     * POST /api/agent/agent-collections/follow-up
     * POST /api/agent/agent-collections/notes
     */
    public function storeFollowUp(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $userId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;

        $validated = $request->validate([
            'type' => 'nullable|in:loan',
            'id' => 'nullable',
            'emi_id' => 'nullable',
            'installment_id' => 'nullable',
            'loan_account_id' => 'nullable|integer',
            'group_member_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
            'reminder_date' => 'nullable',
            'remarks' => 'nullable|string|max:2000',
            'note' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $remarksText = trim($validated['note'] ?? $validated['remarks'] ?? $validated['notes'] ?? '');
        if ($remarksText === '') {
            return response()->json([
                'success' => false,
                'message' => 'Note or remarks content is required.',
            ], 422);
        }

        $type = $validated['type'] ?? null;
        $rawId = $validated['id'] ?? $validated['emi_id'] ?? $validated['installment_id'];

        if (is_string($rawId) && str_starts_with($rawId, 'loan_')) {
            $type = 'loan';
            $rawId = substr($rawId, 5);
        }
        if (!$type) {
            $type = 'loan';
        }

        $emiId = $validated['emi_id'] ?? null;
        $installmentId = $validated['installment_id'] ?? null;
        $clientId = $validated['client_id'] ?? null;
        $loanAccountId = $validated['loan_account_id'] ?? null;
        $groupMemberId = $validated['group_member_id'] ?? null;

        $isAdminOrStaff = ($currentUser instanceof User && ($currentUser->hasRole('Admin') || $currentUser->hasRole('Staff') || $currentUser->hasRole('Super Admin')));

        $dueAmount = 0.0;


        if ($type === 'loan') {
            $emiId = (int) ($emiId ?: $rawId);
            $emi = Emi::with('loanAccount.client')->find($emiId);
            if ($emi) {
                if (!$isAdminOrStaff && $agentId && !$this->agentCanAccessLoanEmi($emi, $agentId, $userId)) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized to access this loan EMI'], 403);
                }
                $loanAccountId = $loanAccountId ?: $emi->loan_account_id;
                $clientId = $clientId ?: $emi->loanAccount?->client_id;
                $dueAmount = ($emi->status !== 'paid') ? (float) ($emi->pending_amount ?? 0) : 0.0;
            } elseif ($loanAccountId && !$clientId) {
                $loan = LoanAccount::find($loanAccountId);
                $clientId = $loan?->client_id;
                $dueAmount = $loan ? (float) ($loan->outstanding_principal ?: $loan->outstanding_amount ?: 0) : 0.0;
            }
        }

        // If due amount is 0, do not allow adding a reminder
        if (!empty($validated['reminder_date']) && $dueAmount <= 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add a reminder because the due amount is 0.',
            ], 422);
        }

        $dateOnly = null;
        $timeOnly = null;
        $parsedDate = null;
        if (!empty($validated['reminder_date'])) {
            try {
                $parsedDate = Carbon::parse($validated['reminder_date']);
                $dateOnly = $parsedDate->toDateString();
                $timeOnly = $parsedDate->format('H:i:s');
                if ($timeOnly === '00:00:00') {
                    $timeOnly = null;
                }
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => 'Invalid reminder date format'], 422);
            }
        }

        $status = !empty($validated['status']) ? $validated['status'] : 'postponed';

        $note = CollectionNote::create([
            'type' => $type,
            'client_id' => $clientId,
            'loan_account_id' => $loanAccountId,
            'emi_id' => $emiId,
            'group_member_id' => $groupMemberId,
            'installment_id' => $installmentId,
            'user_id' => $userId,
            'agent_id' => $agentId,
            'note' => $remarksText,
            'reminder_date' => $dateOnly,
            'reminder_time' => $timeOnly,
            'status' => $status,
        ]);

        if ($type === 'loan' && $emiId && $agentId) {
            try {
                \App\Models\EmiFollowup::create([
                    'emi_id' => $emiId,
                    'agent_id' => $agentId,
                    'status' => $status,
                    'followup_at' => $dateOnly ? ($timeOnly ? "{$dateOnly} {$timeOnly}" : "{$dateOnly} 09:00:00") : null,
                    'remarks' => $remarksText,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Could not sync to emi_followups: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Note and reminder saved successfully.',
            'data' => [
                'id' => $note->id,
                'note_id' => $note->id,
                'type' => $type,
                'client_id' => $clientId,
                'loan_account_id' => $loanAccountId,
                'group_member_id' => $groupMemberId,
                'emi_id' => $emiId,
                'installment_id' => $installmentId,
                'note' => $remarksText,
                'remarks' => $remarksText,
                'reminder_date' => $dateOnly,
                'reminder_date_formatted' => $dateOnly ? Carbon::parse($dateOnly)->format('d-m-Y') : null,
                'reminder_time' => $timeOnly ? Carbon::parse($timeOnly)->format('h:i A') : null,
                'status' => $status,
                'created_by' => $note->creator_name,
                'created_at' => $note->created_at->format('d-m-Y h:i A'),
            ]
        ]);
    }

    /**
     * Get notes history for a specific EMI.
     * GET /api/agent/agent-collections/notes/{type}/{id}
     * GET /api/agent/agent-collections/notes?type=loan&id=7178&emi_id=7178
     */
    public function getNotesHistory(Request $request, ?string $type = null, $id = null): JsonResponse
    {
        $currentUser = Auth::user();
        if (!$currentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $type = $type ?: $request->input('type');
        $rawId = $id ?: $request->input('id') ?: $request->input('emi_id') ?: $request->input('installment_id');

        if (is_string($rawId) && str_starts_with($rawId, 'loan_')) {
            $type = 'loan';
            $rawId = substr($rawId, 5);
        }
        if (!$type) {
            $type = 'loan';
        }

        $query = CollectionNote::with(['user', 'agent'])->where('type', $type);

        $entityInfo = null;
        if ($type === 'loan') {
            $query->where('emi_id', (int) $rawId);
            $emi = Emi::with(['loanAccount.client'])->find((int) $rawId);
            if ($emi) {
                $entityInfo = [
                    'type' => 'loan',
                    'emi_id' => $emi->id,
                    'client_id' => $emi->loanAccount?->client_id,
                    'loan_account_id' => $emi->loan_account_id,
                    'label' => 'EMI #' . $emi->instalment_number,
                    'account_number' => $emi->loanAccount?->account_number ?? 'N/A',
                    'client_name' => $emi->loanAccount?->client?->client_name ?? 'N/A',
                    'client_phone' => $emi->loanAccount?->client?->client_phone ?? 'N/A',
                    'amount_due' => (float) $emi->pending_amount,
                    'due_date' => $emi->due_date ? Carbon::parse($emi->due_date)->format('d-m-Y') : '—',
                ];
            }
        } else {
                    }

        $notes = $query->orderBy('created_at', 'desc')->get()->map(function ($note) {
            $isToday = $note->reminder_date && Carbon::parse($note->reminder_date)->isToday();
            $isPast = $note->reminder_date && Carbon::parse($note->reminder_date)->startOfDay()->lt(now()->startOfDay());
            return [
                'id' => $note->id,
                'note_id' => $note->id,
                'note' => $note->note,
                'remarks' => $note->note,
                'reminder_date' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('Y-m-d') : null,
                'reminder_date_formatted' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('d-m-Y') : null,
                'reminder_time' => $note->reminder_time,
                'status' => $note->status ?: 'postponed',
                'is_today' => $isToday,
                'is_overdue' => $isPast && !$note->is_completed,
                'created_by' => $note->creator_name,
                'created_at' => $note->created_at ? $note->created_at->format('d-m-Y h:i A') : '',
            ];
        });

        return response()->json([
            'success' => true,
            'entity' => $entityInfo,
            'notes' => $notes,
            'data' => $notes,
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
     * Unified Collections History API grouped by Clients.
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
        if (true) {
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

        // Format Client records with loan collections inside
        $formattedClients = collect($clientsData)->map(function ($data) {
            $client = $data['client'];
            $loanCollections = $data['loan_collections'];

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
            $allCollections = $loanCollections;
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
                'total_collected_amount' => round($loanCollectedAmount, 2),
                'loan_collected_amount' => round($loanCollectedAmount, 2),
                'chit_collected_amount' => 0.0,
                'total_collections_count' => count($allCollections),
                'loan_collections_count' => count($loanCollections),
                'chit_collections_count' => 0,
                'loan_collections' => $loanCollections,
                'chit_collections' => []
            ];
        })->values();

        // Overall stats
        $totalLoanAmount = $formattedClients->sum('loan_collected_amount');
        $totalLoanCount = $formattedClients->sum('loan_collections_count');

        $stats = [
            'total_clients_count' => $formattedClients->count(),
            'total_collections_count' => $totalLoanCount,
            'total_amount_collected' => round($totalLoanAmount, 2),
            'total_loan_collected_amount' => round($totalLoanAmount, 2),
            'total_chit_collected_amount' => 0.0,
            'total_loan_collections_count' => $totalLoanCount,
            'total_chit_collections_count' => 0,
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
