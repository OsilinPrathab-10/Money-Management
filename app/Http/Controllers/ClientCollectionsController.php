<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EmiController;
use App\Models\Client;
use App\Models\CompanyDetail;
use App\Models\Emi;
use App\Models\GroupMember;
use App\Models\Installment;
use App\Models\LoanAccount;
use App\Support\HashId;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClientCollectionsController extends Controller
{
    public function index(): View
    {
        $bankAccounts = \App\Models\Account\BankAccount::query()
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get();

        return view('admin.collections.index', compact('bankAccounts'));
    }

    public function data(Request $request): JsonResponse
    {
        $module = $request->input('module', 'all');
        if (! in_array($module, ['all', 'loan', 'chit'], true)) {
            $module = 'all';
        }

        $status = $request->input('status', 'all');
        if (! in_array($status, ['overdue', 'pending', 'upcoming', 'partial', 'paid', 'all'], true)) {
            $status = 'all';
        }

        $rawSearch = $request->input('search_filter');
        if (! is_string($rawSearch) || $rawSearch === '') {
            $rawSearch = $request->input('search.value');
        }
        if (! is_string($rawSearch) || $rawSearch === '') {
            $searchParam = $request->input('search');
            if (is_string($searchParam)) {
                $rawSearch = $searchParam;
            } elseif (is_array($searchParam)) {
                $rawSearch = $searchParam['value'] ?? '';
            } else {
                $rawSearch = $request->input('q', $request->input('keyword', ''));
            }
        }
        $search = is_string($rawSearch) ? trim($rawSearch) : '';
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 25);
        if ($length < 1) {
            $length = 25;
        }

        $today = Carbon::now()->startOfDay();
        $agentId = $this->agentId();
        $fromDate = $this->dateOrNull($request->input('from_date'));
        $toDate = $this->dateOrNull($request->input('to_date'));

        // Query accounts matching filters (each LoanAccount or Chit Group Member Seat is a separate row)
        $accounts = $this->queryMatchingAccounts($module, $status, $today, $agentId, $search, $fromDate, $toDate);

        $total = $accounts->count();
        $pageAccounts = $accounts->slice($start, $length)->values();

        // Collect IDs for bulk preloading
        $pageLoanIds = $pageAccounts->where('record_type', 'loan')->pluck('record_id')->map(fn ($id) => (int) $id)->unique()->all();
        $pageMemberIds = $pageAccounts->where('record_type', 'chit')->pluck('record_id')->map(fn ($id) => (int) $id)->unique()->all();
        $pageClientIds = $pageAccounts->pluck('client_id')->map(fn ($id) => (int) $id)->unique()->all();

        $clients = $pageClientIds === []
            ? collect()
            : Client::query()
                ->with(['location', 'agent', 'creator'])
                ->whereIn('id', $pageClientIds)
                ->get()
                ->keyBy('id');

        $loanAccounts = $pageLoanIds === []
            ? collect()
            : LoanAccount::query()
                ->with(['loanApplication'])
                ->whereIn('id', $pageLoanIds)
                ->get()
                ->keyBy('id');

        $groupMembers = $pageMemberIds === []
            ? collect()
            : GroupMember::query()
                ->with(['group', 'shares.client'])
                ->whereIn('id', $pageMemberIds)
                ->get()
                ->keyBy('id');

        // Preload EMIs for page loans
        $emisByLoan = $this->fetchEmisForLoanAccounts($pageLoanIds, $status, $today, $fromDate, $toDate);
        $overdueEmisByLoan = $status === 'overdue'
            ? $emisByLoan
            : $this->fetchEmisForLoanAccounts($pageLoanIds, 'overdue', $today, null, null);
        $payableEmisByLoan = $this->fetchEmisForLoanAccounts($pageLoanIds, 'unpaid', $today, null, null);

        // Preload Chit installments for page group members
        $instsByMemberClient = $this->fetchInstallmentsForChits($pageMemberIds, $pageClientIds, $status, $today, $fromDate, $toDate);
        $overdueInstsByMemberClient = $status === 'overdue'
            ? $instsByMemberClient
            : $this->fetchInstallmentsForChits($pageMemberIds, $pageClientIds, 'overdue', $today, null, null);
        $payableInstsByMemberClient = $this->fetchInstallmentsForChits($pageMemberIds, $pageClientIds, 'unpaid', $today, null, null);

        $company = CompanyDetail::query()->first();
        $canPay = (bool) Auth::user()?->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);

        $data = $pageAccounts->map(function ($acc, $index) use (
            $clients, $loanAccounts, $groupMembers,
            $emisByLoan, $overdueEmisByLoan, $payableEmisByLoan,
            $instsByMemberClient, $overdueInstsByMemberClient, $payableInstsByMemberClient,
            $company, $canPay, $start
        ) {
            $clientId = (int) $acc->client_id;
            $client = $clients->get($clientId);
            $agentName = $client?->agent?->agent_name ?? $client?->creator?->agent_name ?? 'Unassigned';
            $zone = $client?->location?->name ?? 'N/A';
            $publicUrl = $client ? route('public.view-client-schedule', HashId::encode((int) $clientId)) : '#';

            if ($acc->record_type === 'loan') {
                $loanId = (int) $acc->record_id;
                $loan = $loanAccounts->get($loanId);
                $emis = $emisByLoan->get($loanId, collect());
                $overdueEmis = $overdueEmisByLoan->get($loanId, collect());
                $payableEmis = $payableEmisByLoan->get($loanId, collect());

                $loanDue = round((float) $emis->sum(fn ($row) => (float) ($row['pending_amount'] ?? $row['amount'] ?? 0)), 2);
                $loanPayable = round((float) $payableEmis->sum(fn ($row) => (float) ($row['pending_amount'] ?? 0)), 2);
                $isOpenLoan = ($loan?->loan_mode ?? 'emi') === 'interest_only';
                $principalRemaining = $loan ? (float) ($isOpenLoan ? $loan->openLoanRemainingPrincipal() : $loan->outstanding_amount) : 0.0;
                $accountNumber = $loan?->customer_loan_account_number ?: ($loan?->account_number ?? $acc->identifier);
                $appNumber = $loan?->application_number ?? $loan?->loanApplication?->application_number;
                $scheduleUrl = $appNumber ? route('emi-details', $appNumber) : ($client ? route('client-view-loans', $client) : '#');

                $targetLoanEmis = $overdueEmis->isNotEmpty() ? $overdueEmis : ($payableEmis->isNotEmpty() ? $payableEmis : $emis->filter(fn ($e) => (float) ($e['pending_amount'] ?? 0) > 0));
                $waMessages = $this->buildLoanOverdueMessage($client?->client_phone, $client?->client_name ?? 'Client', $accountNumber, $targetLoanEmis, $company, $publicUrl);

                $statusCounts = $this->countStatuses($emis);

                return [
                    'id' => 'loan_' . $loanId,
                    'row_id' => 'loan_' . $loanId,
                    'sno' => $start + $index + 1,
                    'record_type' => 'loan',
                    'record_id' => $loanId,
                    'loan_account_id' => $loanId,
                    'client_id' => $clientId,
                    'client_name' => $client?->client_name ?? 'Client',
                    'client_nickname' => $client?->nickname,
                    'client_phone' => $client?->client_phone ?? 'N/A',
                    'client_url' => $client ? route('client-view-account', $client) : '#',
                    'account_number' => $accountNumber,
                    'account_title' => 'Loan: ' . $accountNumber . ($isOpenLoan ? ' (Open Loan)' : ' (EMI)'),
                    'account_url' => $scheduleUrl,
                    'account_badge' => $isOpenLoan
                        ? '<span class="badge bg-label-warning"><i class="ri-fire-line me-1"></i>Open Loan</span>'
                        : '<span class="badge bg-label-primary"><i class="ri-bank-line me-1"></i>EMI Loan</span>',
                    'loan_mode' => $isOpenLoan ? 'interest_only' : 'emi',
                    'is_open_loan' => $isOpenLoan,
                    'principal_outstanding' => $principalRemaining,
                    'principal_outstanding_formatted' => '₹' . number_format($principalRemaining, 2),
                    'loan_amount' => (float) ($loan?->loan_amount ?? 0),
                    'agent_name' => $agentName,
                    'zone' => $zone,
                    'can_pay' => $canPay && ($loanPayable > 0.009 || ($isOpenLoan && $principalRemaining > 0.009)),
                    'has_overdue' => $overdueEmis->isNotEmpty(),
                    'total_due' => $loanDue,
                    'total_due_formatted' => '₹' . number_format($loanDue, 2),
                    'payable_amount' => $loanPayable,
                    'payable_amount_formatted' => '₹' . number_format($loanPayable, 2),
                    'status_summary' => $this->formatStatusBadges($statusCounts),
                    'loan_items' => $emis->values()->all(),
                    'chit_items' => [],
                    'items' => $emis->values()->all(),
                    'loan_items_grouped' => $this->groupByDisplayStatus($emis),
                    'payable_loan_ids' => $payableEmis->pluck('id')->filter()->values()->all(),
                    'payable_chit_items' => [],
                    'public_url' => $publicUrl,
                    'whatsapp_url' => $waMessages['whatsapp'],
                    'sms_url' => $waMessages['sms'],
                    'company_slogan' => $company?->company_slogan ?: ($company?->company_name ?: 'Finance'),
                    'company_phone' => $company?->company_mobile ?: ($company?->support_mobile ?: ''),
                ];
            }

            // Chit Row
            $memberId = (int) $acc->record_id;
            $member = $groupMembers->get($memberId);
            $key = $memberId . '_' . $clientId;
            $insts = $instsByMemberClient->get($key, collect());
            $overdueInsts = $overdueInstsByMemberClient->get($key, collect());
            $payableInsts = $payableInstsByMemberClient->get($key, collect());

            $chitDue = round((float) $insts->sum(fn ($row) => (float) ($row['balance'] ?? $row['amount'] ?? 0)), 2);
            $chitPayable = round((float) $payableInsts->sum(fn ($row) => (float) ($row['balance'] ?? 0)), 2);
            $groupCode = $member?->group?->group_code ?? $acc->identifier ?? 'Chit';
            $memberNumber = $member
                ? ($member->is_shared ? ($member->memberNumberForClient($clientId) ?? '—') : ($member->display_member_number ?? $member->member_number ?? '—'))
                : '—';
            $chitsUrl = route('chit.installments.client', HashId::encode((int) $clientId));

            $targetChitInsts = $overdueInsts->isNotEmpty() ? $overdueInsts : ($payableInsts->isNotEmpty() ? $payableInsts : $insts->filter(fn ($i) => (float) ($i['balance'] ?? 0) > 0));
            $waMessages = $this->buildChitOverdueMessage($client?->client_phone, $client?->client_name ?? 'Client', $groupCode, $memberNumber, $targetChitInsts, $company, $publicUrl);

            $statusCounts = $this->countStatuses($insts);

            return [
                'id' => 'chit_' . $memberId . '_' . $clientId,
                'row_id' => 'chit_' . $memberId . '_' . $clientId,
                'sno' => $start + $index + 1,
                'record_type' => 'chit',
                'record_id' => $memberId,
                'member_id' => $memberId,
                'client_id' => $clientId,
                'client_name' => $client?->client_name ?? 'Client',
                'client_nickname' => $client?->nickname,
                'client_phone' => $client?->client_phone ?? 'N/A',
                'client_url' => $client ? route('client-view-account', $client) : '#',
                'account_number' => $groupCode,
                'member_number' => $memberNumber,
                'account_title' => 'Chit: ' . $groupCode . ' (' . ($memberNumber !== '—' ? 'Member #' . $memberNumber : 'Seat') . ')',
                'account_url' => $chitsUrl,
                'account_badge' => '<span class="badge bg-label-info"><i class="ri-group-line me-1"></i>Chit</span>',
                'loan_mode' => 'chit',
                'is_open_loan' => false,
                'principal_outstanding' => 0.0,
                'principal_outstanding_formatted' => '—',
                'loan_amount' => 0.0,
                'agent_name' => $agentName,
                'zone' => $zone,
                'can_pay' => $canPay && ($chitPayable > 0.009),
                'has_overdue' => $overdueInsts->isNotEmpty(),
                'total_due' => $chitDue,
                'total_due_formatted' => '₹' . number_format($chitDue, 2),
                'payable_amount' => $chitPayable,
                'payable_amount_formatted' => '₹' . number_format($chitPayable, 2),
                'status_summary' => $this->formatStatusBadges($statusCounts),
                'loan_items' => [],
                'chit_items' => $insts->values()->all(),
                'items' => $insts->values()->all(),
                'chit_items_grouped' => $this->groupByDisplayStatus($insts),
                'payable_loan_ids' => [],
                'payable_chit_items' => $payableInsts->map(fn ($row) => [
                    'installment_id' => (int) ($row['installment_id'] ?? 0),
                    'client_id' => $clientId,
                    'amount' => (float) ($row['balance'] ?? $row['amount'] ?? 0),
                ])->filter(fn ($row) => $row['installment_id'] > 0 && $row['amount'] > 0.009)->values()->all(),
                'public_url' => $publicUrl,
                'whatsapp_url' => $waMessages['whatsapp'],
                'sms_url' => $waMessages['sms'],
                'company_slogan' => $company?->company_slogan ?: ($company?->company_name ?: 'Finance'),
                'company_phone' => $company?->company_mobile ?: ($company?->support_mobile ?: ''),
            ];
        })->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
            'stats' => $this->computeStats($module, $today, $agentId, $search, $fromDate, $toDate),
        ]);
    }

    public function pay(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        // If bulk array is provided or items array has multiple accounts, delegate to bulkPay
        if ($request->has('bulk') || (is_array($request->input('items')) && count($request->input('items')) > 0 && isset($request->input('items')[0]['record_type']))) {
            return $this->bulkPay($request);
        }

        $validated = $request->validate([
            'module' => 'required|in:loan,chit',
            'pay_type' => 'required|in:full,partial',
            'paid_amount' => 'required|numeric|min:0',
            'principal_amount' => 'nullable|numeric|min:0',
            'paid_date' => 'required|date',
            'payment_method' => 'required|string',
            'internal_bank_account_id' => 'nullable',
            'remarks' => 'nullable|string',
            'emi_ids' => 'nullable|array',
            'emi_id' => 'nullable',
            'loan_account_id' => 'nullable|integer',
            'installment_id' => 'nullable',
            'client_id' => 'nullable|integer',
            'items' => 'nullable|array',
            'items.*.installment_id' => 'nullable|integer',
            'items.*.client_id' => 'nullable|integer',
            'items.*.amount' => 'nullable|numeric',
        ]);

        $paidAmount = (float) $validated['paid_amount'];
        $principalAmount = (float) ($validated['principal_amount'] ?? 0);
        if ($paidAmount <= 0.009 && $principalAmount <= 0.009) {
            return response()->json(['success' => false, 'message' => 'Please enter a valid payment amount.'], 422);
        }

        $method = $validated['payment_method'];
        if (in_array($method, ['upi', 'bank_transfer'], true) && ! $request->filled('internal_bank_account_id')) {
            return response()->json(['success' => false, 'message' => 'Please select a collection bank account.'], 422);
        }

        if ($validated['module'] === 'loan') {
            $loanAccountId = (int) $request->input('loan_account_id');
            $loanAccount = $loanAccountId ? LoanAccount::find($loanAccountId) : null;
            if (! $loanAccount && $request->filled('emi_id')) {
                $targetEmi = Emi::find((int) $request->input('emi_id'));
                $loanAccount = $targetEmi?->loanAccount;
                $loanAccountId = (int) ($loanAccount?->id ?? 0);
            }
            if (! $loanAccount && $request->filled('emi_ids')) {
                $firstId = $request->input('emi_ids')[0] ?? null;
                $targetEmi = $firstId ? Emi::find((int) $firstId) : null;
                $loanAccount = $targetEmi?->loanAccount;
                $loanAccountId = (int) ($loanAccount?->id ?? 0);
            }

            $isOpenLoan = ($loanAccount?->loan_mode ?? 'emi') === 'interest_only';
            $explicitPrincipal = $principalAmount;
            $paidInterest = $paidAmount;

            // Handle Open Loan (Interest-Only) single pay
            if ($isOpenLoan && $loanAccount) {
                $paymentService = app(\App\Services\LoanPaymentService::class);
                $targetEmiId = (int) $request->input('emi_id');
                $targetEmi = $targetEmiId ? $loanAccount->emis()->find($targetEmiId) : null;
                if (! $targetEmi) {
                    $targetEmi = $loanAccount->emis()
                        ->whereIn('status', ['pending', 'overdue', 'partial'])
                        ->orderBy('instalment_number')
                        ->first();
                }

                if (! $targetEmi && $explicitPrincipal > 0.01) {
                    $targetEmi = $loanAccount->emis()->orderByDesc('instalment_number')->first();
                }

                if (! $targetEmi) {
                    return response()->json(['success' => false, 'message' => 'No active interest cycle found for this loan.'], 422);
                }

                $res = $paymentService->processPayment(
                    $targetEmi->id,
                    $paidInterest,
                    $validated['paid_date'],
                    $method,
                    null,
                    $validated['remarks'] ?? 'Client collection open loan pay',
                    false,
                    $explicitPrincipal,
                    true,
                    $request->input('internal_bank_account_id')
                );

                if (! ($res['success'] ?? false)) {
                    return response()->json(['success' => false, 'message' => $res['message'] ?? 'Payment failed.'], 422);
                }

                return response()->json([
                    'success' => true,
                    'message' => $res['message'] ?? 'Payment collected successfully.',
                ]);
            }

            // Standard EMI Loan
            $emiIds = $request->input('emi_ids', []);
            if (empty($emiIds) && $request->filled('emi_id')) {
                $emiIds = [(int) $request->input('emi_id')];
            }
            if (empty($emiIds) && $loanAccount) {
                $emiIds = $loanAccount->emis()
                    ->whereIn('status', ['pending', 'overdue', 'partial'])
                    ->orderBy('instalment_number')
                    ->pluck('id')
                    ->all();
            }

            $loanPayload = [
                'emi_ids' => $emiIds,
                'payment_method' => $method,
                'internal_bank_account_id' => $request->input('internal_bank_account_id'),
                'paid_date' => $validated['paid_date'],
                'remarks' => $validated['remarks'] ?? null,
                'paid_amount' => $paidAmount,
            ];

            $loanRequest = Request::create(route('emi-repayments-bulk-pay'), 'POST', $loanPayload);
            $loanRequest->setUserResolver(fn () => $user);
            $loanRequest->headers->set('Accept', 'application/json');

            return app(EmiController::class)->bulkPay($loanRequest);
        }

        // Chit single pay
        $items = collect($request->input('items', []));
        if ($items->isEmpty() && $request->filled('installment_id')) {
            $items = collect([[
                'installment_id' => (int) $request->input('installment_id'),
                'client_id' => (int) ($request->input('client_id') ?? 0),
                'amount' => $paidAmount,
            ]]);
        }
        $items = $items->filter(fn ($item) => (int) ($item['installment_id'] ?? 0) > 0 && (float) ($item['amount'] ?? 0) > 0.009)->values();

        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No unpaid chit installments selected.'], 422);
        }

        $paymentService = app(\App\Services\ChitPaymentService::class);
        $collectedBy = $user->id;
        $mode = $method === 'in_hand' ? 'cash' : $method;
        $paid = 0;
        $count = 0;
        $pool = $paidAmount;
        $isPartial = ($validated['pay_type'] === 'partial');

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                $installment = Installment::with(['member.shares', 'group'])->find((int) $item['installment_id']);
                if (! $installment) {
                    continue;
                }
                $itemAmount = (float) ($item['amount'] ?? 0);
                if ($isPartial) {
                    if ($pool <= 0.009) {
                        break;
                    }
                    $itemAmount = min($pool, $itemAmount);
                }
                if ($itemAmount <= 0.009) {
                    continue;
                }

                $result = $paymentService->collectInstallment($installment, [
                    'paid_amount' => $itemAmount,
                    'payment_mode' => $mode,
                    'payment_type' => $isPartial ? 'partial' : 'full',
                    'paid_date' => $validated['paid_date'],
                    'remarks' => $validated['remarks'] ?? 'Client collections pay',
                    'client_id' => (int) ($item['client_id'] ?? 0) ?: null,
                    'single_seat_only' => 1,
                    'internal_bank_account_id' => $request->input('internal_bank_account_id'),
                    'bypass_min_validation' => true,
                ], $collectedBy);

                $paid += $itemAmount;
                $count++;

                if ($isPartial) {
                    $pool -= $itemAmount;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($count === 0) {
            return response()->json(['success' => false, 'message' => 'No chit installment could be collected.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Collected ₹" . number_format($paid, 2) . " across {$count} chit installment(s).",
        ]);
    }

    public function bulkPay(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'paid_date' => 'required|date',
            'payment_method' => 'required|string',
            'internal_bank_account_id' => 'nullable',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.record_type' => 'required|in:loan,chit',
            'items.*.account_id' => 'required|integer',
            'items.*.client_id' => 'nullable|integer',
            'items.*.amount' => 'nullable|numeric|min:0',
            'items.*.principal_amount' => 'nullable|numeric|min:0',
        ]);

        $method = $validated['payment_method'];
        $bankAccountId = $validated['internal_bank_account_id'] ?? null;
        if (in_array($method, ['upi', 'bank_transfer'], true) && empty($bankAccountId)) {
            return response()->json(['success' => false, 'message' => 'Please select a collection bank account.'], 422);
        }

        $paidDate = $validated['paid_date'];
        $customRemarks = $validated['remarks'] ?? 'Bulk Collection Payment';
        $batchKey = \App\Support\BulkPaymentGroup::generateKey();
        $bulkRemarks = \App\Support\BulkPaymentGroup::appendRemarks($customRemarks, $batchKey, \App\Support\BulkPaymentGroup::ADMIN_MARKER);
        $loanPaymentService = app(\App\Services\LoanPaymentService::class);
        $chitPaymentService = app(\App\Services\ChitPaymentService::class);
        $mode = $method === 'in_hand' ? 'cash' : $method;
        $collectedBy = $user->id;

        $totalPaid = 0.0;
        $loanSuccessCount = 0;
        $chitSuccessCount = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['items'] as $item) {
                $recordType = $item['record_type'];
                $accountId = (int) $item['account_id'];
                $clientId = (int) ($item['client_id'] ?? 0);
                $amount = round((float) ($item['amount'] ?? 0), 2);
                $principalAmount = round((float) ($item['principal_amount'] ?? 0), 2);

                if ($amount <= 0.009 && $principalAmount <= 0.009) {
                    continue;
                }

                if ($recordType === 'loan') {
                    $loanAccount = LoanAccount::with(['client'])->find($accountId);
                    if (! $loanAccount) {
                        continue;
                    }

                    $isOpenLoan = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';

                    if ($isOpenLoan) {
                        // Open Loan (Kandhuvatti) logic
                        $targetEmi = $loanAccount->emis()
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->orderBy('instalment_number')
                            ->first();

                        if (! $targetEmi && $principalAmount > 0.01) {
                            $targetEmi = $loanAccount->emis()->orderByDesc('instalment_number')->first();
                        }

                        if (! $targetEmi) {
                            throw new \Exception("No active interest cycle found for Open Loan: {$loanAccount->account_number}");
                        }

                        $res = $loanPaymentService->processPayment(
                            $targetEmi->id,
                            $amount,
                            $paidDate,
                            $method,
                            $batchKey,
                            $bulkRemarks . ' [Bulk Open Loan Pay]',
                            false,
                            $principalAmount,
                            true,
                            $bankAccountId
                        );

                        if (! ($res['success'] ?? false)) {
                            throw new \Exception("Loan {$loanAccount->account_number} failed: " . ($res['message'] ?? 'Unknown error'));
                        }

                        $totalPaid += ($amount + $principalAmount);
                        $loanSuccessCount++;
                    } else {
                        // Regular EMI loan: cascade amount across unpaid EMIs
                        $unpaidEmis = $loanAccount->emis()
                            ->whereIn('status', ['pending', 'overdue', 'partial'])
                            ->orderBy('instalment_number')
                            ->get();

                        if ($unpaidEmis->isEmpty()) {
                            continue;
                        }

                        $rem = $amount;
                        foreach ($unpaidEmis as $emi) {
                            if ($rem <= 0.009) {
                                break;
                            }
                            $pendingAmount = (float) ($emi->pending_amount ?: max(0, (float) $emi->total_amount - (float) $emi->paid_amount));
                            if ($pendingAmount <= 0.009) {
                                continue;
                            }

                            $payThisEmi = min($rem, $pendingAmount);
                            $res = $loanPaymentService->processPayment(
                                $emi->id,
                                $payThisEmi,
                                $paidDate,
                                $method,
                                $batchKey,
                                $bulkRemarks . ' [Bulk EMI Pay]',
                                false,
                                0,
                                true,
                                $bankAccountId
                            );

                            if (! ($res['success'] ?? false)) {
                                throw new \Exception("Loan {$loanAccount->account_number} EMI #{$emi->instalment_number} failed: " . ($res['message'] ?? 'Unknown error'));
                            }

                            $rem -= $payThisEmi;
                            $totalPaid += $payThisEmi;
                        }
                        $loanSuccessCount++;
                    }
                } elseif ($recordType === 'chit') {
                    $groupMember = GroupMember::with(['group', 'shares'])->find($accountId);
                    if (! $groupMember) {
                        continue;
                    }

                    $unpaidInsts = Installment::with(['member.shares', 'group'])
                        ->where('group_id', $groupMember->group_id)
                        ->where('member_id', $groupMember->id)
                        ->whereNull('deleted_at')
                        ->whereIn('status', ['pending', 'overdue', 'partial'])
                        ->orderBy('month_number')
                        ->get();

                    if ($unpaidInsts->isEmpty()) {
                        continue;
                    }

                    $rem = $amount;
                    foreach ($unpaidInsts as $inst) {
                        if ($rem <= 0.009) {
                            break;
                        }
                        $balance = (float) $inst->clientBalanceShare($clientId ?: (int) $groupMember->client_id);
                        if ($balance <= 0.009) {
                            continue;
                        }

                        $payThisInst = min($rem, $balance);
                        $res = $chitPaymentService->collectInstallment($inst, [
                            'paid_amount' => $payThisInst,
                            'payment_mode' => $mode,
                            'payment_type' => $payThisInst >= ($balance - 0.01) ? 'full' : 'partial',
                            'paid_date' => $paidDate,
                            'reference_no' => $batchKey,
                            'remarks' => $bulkRemarks . ' [Bulk Chit Pay]',
                            'client_id' => $clientId ?: (int) $groupMember->client_id,
                            'single_seat_only' => 1,
                            'internal_bank_account_id' => $bankAccountId,
                            'bypass_min_validation' => true,
                        ], $collectedBy);

                        if (! ($res['success'] ?? false)) {
                            throw new \Exception("Chit Group {$groupMember->group?->group_code} Month #{$inst->month_number} failed: " . ($res['message'] ?? 'Unknown error'));
                        }

                        $rem -= $payThisInst;
                        $totalPaid += $payThisInst;
                    }
                    $chitSuccessCount++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($loanSuccessCount === 0 && $chitSuccessCount === 0) {
            return response()->json(['success' => false, 'message' => 'No accounts were processed.'], 422);
        }

        $parts = [];
        if ($loanSuccessCount > 0) {
            $parts[] = "{$loanSuccessCount} loan account(s)";
        }
        if ($chitSuccessCount > 0) {
            $parts[] = "{$chitSuccessCount} chit seat(s)";
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk payment of ₹" . number_format($totalPaid, 2) . " processed successfully across " . implode(' and ', $parts) . ".",
        ]);
    }

    protected function queryMatchingAccounts(string $module, string $status, Carbon $today, ?int $agentId, string $search, ?string $fromDate, ?string $toDate): Collection
    {
        $loanAccounts = collect();
        $chitSeats = collect();

        if ($module !== 'chit') {
            $loanQ = DB::table('loan_accounts')
                ->join('clients', 'clients.id', '=', 'loan_accounts.client_id')
                ->leftJoin('agents', 'agents.id', '=', 'clients.assigned_to')
                ->leftJoin('locations', 'locations.id', '=', 'clients.location_id')
                ->whereNull('loan_accounts.deleted_at')
                ->where('loan_accounts.status', '!=', 'closed');

            if ($agentId) {
                $loanQ->where(function ($q) use ($agentId) {
                    $q->where('clients.assigned_to', $agentId)
                      ->orWhere('clients.added_by', $agentId);
                });
            }

            if ($search !== '') {
                $cleanDigits = preg_replace('/\D+/', '', $search);
                $loanQ->where(function ($q) use ($search, $cleanDigits) {
                    $q->where('loan_accounts.account_number', 'like', "%{$search}%")
                      ->orWhere('loan_accounts.customer_loan_account_number', 'like', "%{$search}%")
                      ->orWhere('loan_accounts.application_number', 'like', "%{$search}%")
                      ->orWhere('loan_accounts.loan_code', 'like', "%{$search}%")
                      ->orWhere('clients.client_name', 'like', "%{$search}%")
                      ->orWhere('clients.nickname', 'like', "%{$search}%")
                      ->orWhere('clients.client_phone', 'like', "%{$search}%")
                      ->orWhere('agents.agent_name', 'like', "%{$search}%")
                      ->orWhere('locations.name', 'like', "%{$search}%");

                    if ($cleanDigits !== '') {
                        $q->orWhere('clients.client_phone', 'like', "%{$cleanDigits}%");
                    }
                });
            }

            $loanQ->whereExists(function ($sub) use ($status, $today, $fromDate, $toDate) {
                $sub->select(DB::raw(1))
                    ->from('emis')
                    ->whereColumn('emis.loan_account_id', 'loan_accounts.id')
                    ->whereNull('emis.deleted_at');
                $this->applyLoanStatus($sub, $status, $today);
                $this->applyDateRange($sub, 'emis.due_date', $fromDate, $toDate);
            });

            $loanAccounts = $loanQ->select([
                DB::raw("'loan' as record_type"),
                'loan_accounts.id as record_id',
                'loan_accounts.client_id',
                'clients.client_name',
                'clients.nickname as client_nickname',
                'loan_accounts.account_number as identifier',
            ])->get();
        }

        if ($module !== 'loan') {
            $clientExpr = 'COALESCE(group_member_shares.client_id, group_members.client_id)';
            $chitQ = DB::table('group_members')
                ->join('chit_groups', 'chit_groups.id', '=', 'group_members.group_id')
                ->leftJoin('group_member_shares', function ($j) {
                    $j->on('group_member_shares.group_member_id', '=', 'group_members.id')
                      ->where('group_members.is_shared', '=', 1);
                })
                ->join('clients', 'clients.id', '=', DB::raw($clientExpr))
                ->leftJoin('agents', 'agents.id', '=', 'clients.assigned_to')
                ->leftJoin('locations', 'locations.id', '=', 'clients.location_id')
                ->whereNull('group_members.deleted_at')
                ->whereNotIn('group_members.status', GroupMember::INACTIVE_STATUSES);

            if ($agentId) {
                $chitQ->where(function ($q) use ($agentId) {
                    $q->where('clients.assigned_to', $agentId)
                      ->orWhere('clients.added_by', $agentId);
                });
            }

            if ($search !== '') {
                $cleanDigits = preg_replace('/\D+/', '', $search);
                $chitQ->where(function ($q) use ($search, $cleanDigits) {
                    $q->where('chit_groups.group_code', 'like', "%{$search}%")
                      ->orWhere('group_members.member_number', 'like', "%{$search}%")
                      ->orWhere('clients.client_name', 'like', "%{$search}%")
                      ->orWhere('clients.nickname', 'like', "%{$search}%")
                      ->orWhere('clients.client_phone', 'like', "%{$search}%")
                      ->orWhere('agents.agent_name', 'like', "%{$search}%")
                      ->orWhere('locations.name', 'like', "%{$search}%");

                    if ($cleanDigits !== '') {
                        $q->orWhere('clients.client_phone', 'like', "%{$cleanDigits}%")
                          ->orWhere('group_members.member_number', '=', $cleanDigits);
                    }
                });
            }

            $chitQ->whereExists(function ($sub) use ($status, $today, $fromDate, $toDate) {
                $sub->select(DB::raw(1))
                    ->from('installments')
                    ->whereColumn('installments.group_id', 'group_members.group_id')
                    ->whereColumn('installments.member_id', 'group_members.id')
                    ->whereNull('installments.deleted_at');
                $this->applyChitStatus($sub, $status, $today);
                $this->applyDateRange($sub, 'installments.due_date', $fromDate, $toDate);
            });

            $chitSeats = $chitQ->select([
                DB::raw("'chit' as record_type"),
                'group_members.id as record_id',
                DB::raw("{$clientExpr} as client_id"),
                'clients.client_name',
                'clients.nickname as client_nickname',
                'chit_groups.group_code as identifier',
            ])->distinct()->get();
        }

        return $loanAccounts->merge($chitSeats)->sortBy(function ($item) {
            return strtolower(trim($item->client_name)) . '_' . $item->record_type . '_' . $item->identifier;
        })->values();
    }

    protected function fetchEmisForLoanAccounts(array $loanAccountIds, string $status, Carbon $today, ?string $fromDate, ?string $toDate): Collection
    {
        if ($loanAccountIds === []) {
            return collect();
        }

        $query = Emi::with(['loanAccount.loanApplication'])
            ->whereIn('loan_account_id', $loanAccountIds);

        $this->applyLoanStatus($query, $status, $today);
        $this->applyDateRange($query, 'emis.due_date', $fromDate, $toDate);

        return $query->orderBy('instalment_number')
            ->get()
            ->groupBy('loan_account_id')
            ->map(function ($emis) use ($today) {
                return $emis->map(function (Emi $emi) use ($today) {
                    $isOpen = ($emi->loanAccount?->loanApplication?->loan_mode ?? 'emi') === 'interest_only';
                    $display = $this->loanDisplayStatus($emi, $today);
                    $pending = (float) ($emi->pending_amount ?: max(0, (float) $emi->total_amount - (float) $emi->paid_amount));
                    $applicationNumber = $emi->loanAccount?->application_number
                        ?? $emi->loanAccount?->loanApplication?->application_number;
                    $scheduleUrl = $applicationNumber
                        ? route('emi-details', $applicationNumber)
                        : route('emi-repayments-show', $emi);

                    return [
                        'id' => $emi->getRouteKey(),
                        'raw_id' => (int) $emi->id,
                        'label' => ($isOpen ? 'Cycle #' : 'EMI #') . $emi->instalment_number,
                        'instalment_number' => $emi->instalment_number,
                        'account' => $emi->loanAccount?->customer_loan_account_number
                            ?: ($emi->loanAccount?->account_number ?? 'Loan'),
                        'account_number' => $emi->loanAccount?->account_number ?? '—',
                        'loan_account_id' => (int) ($emi->loan_account_id ?? 0),
                        'due_date' => $emi->due_date ? $emi->due_date->format('d-m-Y') : '—',
                        'total_amount_formatted' => '₹' . number_format((float) $emi->total_amount, 2),
                        'interest_amount_formatted' => '₹' . number_format((float) $emi->interest_amount, 2),
                        'principal_amount_formatted' => '₹' . number_format((float) $emi->principal_amount, 2),
                        'paid_amount' => (float) $emi->paid_amount,
                        'paid_formatted' => (float) $emi->paid_amount > 0 ? '₹' . number_format((float) $emi->paid_amount, 2) : '—',
                        'paid_date' => $emi->paid_date
                            ? $emi->paid_date->format('d-m-Y')
                            : ($emi->partial_paid_date ? $emi->partial_paid_date->format('d-m-Y') : ''),
                        'pending_amount_formatted' => '₹' . number_format($pending, 2),
                        'pending_amount' => $pending,
                        'amount' => $display === 'paid' ? (float) ($emi->paid_amount ?: $emi->total_amount) : $pending,
                        'status' => $display,
                        'status_badge' => $this->statusBadge($display),
                        'is_open_loan' => $isOpen,
                        'can_pay' => $display !== 'paid' && ($pending > 0.009 || $isOpen),
                        'url' => $scheduleUrl,
                    ];
                });
            });
    }

    protected function fetchInstallmentsForChits(array $memberIds, array $clientIds, string $status, Carbon $today, ?string $fromDate, ?string $toDate): Collection
    {
        if ($memberIds === [] || $clientIds === []) {
            return collect();
        }

        $installments = Installment::with(['group', 'member.shares'])
            ->whereIn('member_id', $memberIds);

        $this->applyChitStatus($installments, $status, $today);
        $this->applyDateRange($installments, 'installments.due_date', $fromDate, $toDate);

        $rows = collect();
        foreach ($installments->orderBy('month_number')->get() as $inst) {
            $member = $inst->member;
            if (! $member) {
                continue;
            }

            $ownerIds = $member->is_shared
                ? $member->resolveOwnershipShares()->pluck('client_id')->map(fn ($id) => (int) $id)
                : collect([(int) $member->client_id]);

            foreach ($ownerIds->intersect($clientIds) as $clientId) {
                $paid = (float) $inst->clientPaidShare((int) $clientId);
                $balance = (float) $inst->clientBalanceShare((int) $clientId);
                $amount = (float) ($inst->amount ?? 0);
                $penalty = (float) ($inst->penalty_amount ?? 0);
                $display = $this->chitDisplayStatus($inst, $today, $paid, $balance);
                if ($balance <= 0.009 && $display !== 'paid' && $inst->status !== 'paid') {
                    continue;
                }

                $key = $member->id . '_' . $clientId;
                $rows->push([
                    'member_client_key' => $key,
                    'client_id' => (int) $clientId,
                    'label' => 'Month #' . $inst->month_number,
                    'period_label' => 'Month ' . $inst->month_number,
                    'account' => $inst->group?->group_code ?? 'Chit',
                    'group_id' => (int) ($inst->group_id ?? 0),
                    'member_id' => (int) ($member->id ?? 0),
                    'member_number' => $member->is_shared
                        ? ($member->memberNumberForClient((int) $clientId) ?? '—')
                        : ($member->display_member_number ?? $member->member_number ?? '—'),
                    'group_code' => $inst->group?->group_code ?? 'Chit',
                    'due_date' => $inst->due_date ? Carbon::parse($inst->due_date)->format('d-m-Y') : '—',
                    'amount_formatted' => '₹' . number_format($amount, 2),
                    'penalty_formatted' => $penalty > 0 ? '₹' . number_format($penalty, 2) : '—',
                    'paid_amount' => $paid,
                    'paid_formatted' => $paid > 0 ? '₹' . number_format($paid, 2) : '—',
                    'paid_date' => $inst->paid_date ? Carbon::parse($inst->paid_date)->format('d-m-Y') : '',
                    'balance_formatted' => $balance > 0.009 ? '₹' . number_format($balance, 2) : '—',
                    'balance' => $balance,
                    'amount' => $display === 'paid' ? $paid : $balance,
                    'status' => $display,
                    'status_badge' => $this->statusBadge($display),
                    'url' => route('chit.installments.client', HashId::encode((int) $clientId)),
                    'group_url' => $inst->group
                        ? route('chit.installments.show', [$inst->group, (int) $inst->month_number])
                        : '#',
                    'installment_id' => (int) $inst->id,
                    'month_number' => (int) $inst->month_number,
                    'can_collect' => $display !== 'paid' && $balance > 0.009,
                ]);
            }
        }

        return $rows->groupBy('member_client_key');
    }

    protected function agentId(): ?int
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole('Agent') || $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff'])) {
            return null;
        }

        return $user->agent_id ?? optional($user->agent)->id;
    }

    protected function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    protected function loanBaseQuery(?int $agentId, string $search)
    {
        $query = Emi::query()
            ->join('loan_accounts', 'loan_accounts.id', '=', 'emis.loan_account_id')
            ->join('clients as loan_clients', 'loan_clients.id', '=', 'loan_accounts.client_id')
            ->leftJoin('agents as loan_agents', 'loan_agents.id', '=', 'loan_clients.assigned_to')
            ->leftJoin('locations as loan_locations', 'loan_locations.id', '=', 'loan_clients.location_id')
            ->whereNull('emis.deleted_at')
            ->whereNull('loan_accounts.deleted_at')
            ->where('loan_accounts.status', '!=', 'closed');

        if ($agentId) {
            $query->where(function ($q) use ($agentId) {
                $q->where('loan_clients.assigned_to', $agentId)
                    ->orWhere('loan_clients.added_by', $agentId);
            });
        }

        if ($search !== '') {
            $cleanDigits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($search, $cleanDigits) {
                $q->where('loan_accounts.account_number', 'like', "%{$search}%")
                    ->orWhere('loan_accounts.customer_loan_account_number', 'like', "%{$search}%")
                    ->orWhere('loan_accounts.application_number', 'like', "%{$search}%")
                    ->orWhere('loan_accounts.loan_code', 'like', "%{$search}%")
                    ->orWhere('loan_clients.client_name', 'like', "%{$search}%")
                    ->orWhere('loan_clients.nickname', 'like', "%{$search}%")
                    ->orWhere('loan_clients.client_phone', 'like', "%{$search}%")
                    ->orWhere('loan_agents.agent_name', 'like', "%{$search}%")
                    ->orWhere('loan_locations.name', 'like', "%{$search}%");

                if ($cleanDigits !== '') {
                    $q->orWhere('loan_clients.client_phone', 'like', "%{$cleanDigits}%");
                }
            });
        }

        return $query;
    }

    protected function chitBaseQuery(?int $agentId, string $search)
    {
        $clientExpr = 'COALESCE(group_member_shares.client_id, group_members.client_id)';
        $query = Installment::query()
            ->join('group_members', function ($j) {
                $j->on('installments.member_id', '=', 'group_members.id')
                    ->whereNull('group_members.deleted_at')
                    ->whereNotIn('group_members.status', GroupMember::INACTIVE_STATUSES);
            })
            ->leftJoin('group_member_shares', 'group_member_shares.group_member_id', '=', 'group_members.id')
            ->join('chit_groups', 'chit_groups.id', '=', 'installments.group_id')
            ->join('clients as chit_clients', 'chit_clients.id', '=', DB::raw($clientExpr))
            ->leftJoin('agents as chit_agents', 'chit_agents.id', '=', 'chit_clients.assigned_to')
            ->leftJoin('locations as chit_locations', 'chit_locations.id', '=', 'chit_clients.location_id')
            ->whereNull('installments.deleted_at');

        if ($agentId) {
            $query->where(function ($q) use ($agentId) {
                $q->where('chit_clients.assigned_to', $agentId)
                    ->orWhere('chit_clients.added_by', $agentId);
            });
        }

        if ($search !== '') {
            $cleanDigits = preg_replace('/\D+/', '', $search);
            $query->where(function ($q) use ($search, $cleanDigits) {
                $q->where('chit_groups.group_code', 'like', "%{$search}%")
                    ->orWhere('group_members.member_number', 'like', "%{$search}%")
                    ->orWhere('chit_clients.client_name', 'like', "%{$search}%")
                    ->orWhere('chit_clients.nickname', 'like', "%{$search}%")
                    ->orWhere('chit_clients.client_phone', 'like', "%{$search}%")
                    ->orWhere('chit_agents.agent_name', 'like', "%{$search}%")
                    ->orWhere('chit_locations.name', 'like', "%{$search}%");

                if ($cleanDigits !== '') {
                    $q->orWhere('chit_clients.client_phone', 'like', "%{$cleanDigits}%")
                      ->orWhere('group_members.member_number', '=', $cleanDigits);
                }
            });
        }

        return $query;
    }

    protected function applyLoanStatus($query, string $status, Carbon $today): void
    {
        if ($status === 'all') {
            return;
        }

        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $todayDate = $today->toDateString();

        if ($status === 'overdue') {
            $query->whereDate('emis.due_date', '<', $todayDate)
                ->whereIn('emis.status', ['pending', 'overdue', 'partial'])
                ->where('emis.pending_amount', '>', 0);
            return;
        }
        if ($status === 'pending') {
            $query->whereDate('emis.due_date', '>=', $todayDate)
                ->whereDate('emis.due_date', '<=', $monthEnd)
                ->whereIn('emis.status', ['pending', 'overdue'])
                ->where('emis.pending_amount', '>', 0);
            return;
        }
        if ($status === 'upcoming') {
            $query->whereDate('emis.due_date', '>', $monthEnd)
                ->whereIn('emis.status', ['pending', 'overdue'])
                ->where('emis.pending_amount', '>', 0);
            return;
        }
        if ($status === 'partial') {
            $query->where('emis.pending_amount', '>', 0)
                ->where(function ($q) {
                    $q->where('emis.status', 'partial')
                        ->orWhere(function ($sq) {
                            $sq->where('emis.paid_amount', '>', 0)
                                ->where('emis.status', '!=', 'paid');
                        });
                });
            return;
        }
        if ($status === 'paid') {
            $query->where('emis.status', 'paid');
            return;
        }
        if ($status === 'unpaid') {
            $query->whereIn('emis.status', ['pending', 'overdue', 'partial'])
                ->where('emis.pending_amount', '>', 0);
        }
    }

    protected function applyChitStatus($query, string $status, Carbon $today): void
    {
        if ($status === 'all') {
            return;
        }

        $monthEnd = $today->copy()->endOfMonth()->toDateString();
        $todayDate = $today->toDateString();

        if ($status === 'overdue') {
            $query->whereDate('installments.due_date', '<', $todayDate)
                ->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            return;
        }
        if ($status === 'pending') {
            $query->whereDate('installments.due_date', '>=', $todayDate)
                ->whereDate('installments.due_date', '<=', $monthEnd)
                ->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            return;
        }
        if ($status === 'upcoming') {
            $query->whereDate('installments.due_date', '>', $monthEnd)
                ->whereIn('installments.status', ['pending', 'overdue', 'partial']);
            return;
        }
        if ($status === 'partial') {
            $query->where('installments.status', 'partial');
            return;
        }
        if ($status === 'paid') {
            $query->where('installments.status', 'paid');
            return;
        }
        if ($status === 'unpaid') {
            $query->whereIn('installments.status', ['pending', 'overdue', 'partial']);
        }
    }

    protected function applyDateRange($query, string $column, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate) {
            $query->whereDate($column, '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate($column, '<=', $toDate);
        }
    }

    protected function computeStats(string $module, Carbon $today, ?int $agentId, string $search, ?string $fromDate, ?string $toDate): array
    {
        $loan = ['overdue' => 0, 'pending' => 0, 'upcoming' => 0, 'partial' => 0, 'paid' => 0, 'collected' => 0.0, 'pending_amount' => 0.0];
        $chit = $loan;

        if ($module !== 'chit') {
            $base = $this->loanBaseQuery($agentId, $search);
            $this->applyDateRange($base, 'emis.due_date', $fromDate, $toDate);
            foreach (['overdue', 'pending', 'upcoming', 'partial', 'paid'] as $bucket) {
                $q = clone $base;
                $this->applyLoanStatus($q, $bucket, $today);
                $loan[$bucket] = (int) (clone $q)->distinct()->count('loan_accounts.id');
                if ($bucket === 'paid') {
                    $loan['collected'] = (float) (clone $q)->sum('emis.paid_amount');
                } else {
                    $loan['pending_amount'] += (float) (clone $q)->sum('emis.pending_amount');
                }
            }
        }

        if ($module !== 'loan') {
            $base = $this->chitBaseQuery($agentId, $search);
            $this->applyDateRange($base, 'installments.due_date', $fromDate, $toDate);
            foreach (['overdue', 'pending', 'upcoming', 'partial', 'paid'] as $bucket) {
                $q = clone $base;
                $this->applyChitStatus($q, $bucket, $today);
                $chit[$bucket] = (int) (clone $q)->distinct()->count('installments.id');
                if ($bucket === 'paid') {
                    $chit['collected'] = (float) (clone $q)->sum('installments.paid_amount');
                } else {
                    $chit['pending_amount'] += (float) (clone $q)->sum(DB::raw('GREATEST(0, installments.amount + COALESCE(installments.penalty_amount, 0) - COALESCE(installments.paid_amount, 0))'));
                }
            }
        }

        $overdue = $loan['overdue'] + $chit['overdue'];
        $pending = $loan['pending'] + $chit['pending'];
        $upcoming = $loan['upcoming'] + $chit['upcoming'];
        $partial = $loan['partial'] + $chit['partial'];
        $paid = $loan['paid'] + $chit['paid'];

        return [
            'total' => $overdue + $pending + $upcoming + $partial + $paid,
            'overdue' => $overdue,
            'pending' => $pending,
            'upcoming' => $upcoming,
            'partial' => $partial,
            'paid' => $paid,
            'collected' => round($loan['collected'] + $chit['collected'], 2),
            'pending_amount' => round($loan['pending_amount'] + $chit['pending_amount'], 2),
        ];
    }

    protected function buildLoanOverdueMessage(?string $phone, string $clientName, string $accountNumber, $overdueEmis, $company, ?string $publicUrl = null): array
    {
        $empty = ['whatsapp' => null, 'sms' => null];
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || $overdueEmis->isEmpty()) {
            return $empty;
        }
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        $hasOverdue = collect($overdueEmis)->contains(fn ($r) => ($r['status'] ?? '') === 'overdue');
        $dueLabel = $hasOverdue ? 'overdue dues' : 'dues';
        $wa = ["Dear *{$clientName}*,", '', "Your {$dueLabel} for loan *{$accountNumber}* are:"];
        $sms = ["Dear {$clientName},", '', "Your {$dueLabel} for loan {$accountNumber} are:"];
        $total = 0.0;

        foreach ($overdueEmis as $row) {
            $amount = (float) ($row['pending_amount'] ?? $row['amount'] ?? 0);
            $total += $amount;
            $line = '- ' . ($row['label'] ?? 'EMI') . ' — Due ' . ($row['due_date'] ?? '—') . ' — Rs.' . number_format($amount, 2);
            $wa[] = $line;
            $sms[] = $line;
        }

        $slogan = $company?->company_slogan ?: ($company?->company_name ?: 'Finance');
        $companyPhone = $company?->company_mobile ?: ($company?->support_mobile ?: '');
        $link = ($publicUrl && $publicUrl !== '#') ? $publicUrl : '';
        $wa[] = '';
        $wa[] = '*Total due: Rs.' . number_format($total, 2) . '*';
        $sms[] = '';
        $sms[] = 'Total due: Rs.' . number_format($total, 2);
        if ($link !== '') {
            $wa[] = '';
            $wa[] = 'View schedule: ' . $link;
            $sms[] = '';
            $sms[] = 'View schedule: ' . $link;
        }
        $wa[] = '';
        $wa[] = 'Thank you, ' . $slogan . '.' . ($companyPhone !== '' ? ' For queries: ' . $companyPhone . '.' : '');
        $sms[] = '';
        $sms[] = 'Thank you, ' . $slogan . '.' . ($companyPhone !== '' ? ' For queries: ' . $companyPhone . '.' : '');

        return [
            'whatsapp' => 'https://wa.me/' . $digits . '?text=' . rawurlencode(implode("\n", $wa)),
            'sms' => 'sms:+' . $digits . '?body=' . rawurlencode(implode("\n", $sms)),
        ];
    }

    protected function buildChitOverdueMessage(?string $phone, string $clientName, string $groupCode, string $memberNumber, $overdueInsts, $company, ?string $publicUrl = null): array
    {
        $empty = ['whatsapp' => null, 'sms' => null];
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '' || $overdueInsts->isEmpty()) {
            return $empty;
        }
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }

        $seatText = ($memberNumber !== '—') ? " (Member #{$memberNumber})" : '';
        $hasOverdue = collect($overdueInsts)->contains(fn ($r) => ($r['status'] ?? '') === 'overdue');
        $dueLabel = $hasOverdue ? 'overdue dues' : 'dues';
        $wa = ["Dear *{$clientName}*,", '', "Your {$dueLabel} for chit group *{$groupCode}*{$seatText} are:"];
        $sms = ["Dear {$clientName},", '', "Your {$dueLabel} for chit group {$groupCode}{$seatText} are:"];
        $total = 0.0;

        foreach ($overdueInsts as $row) {
            $amount = (float) ($row['balance'] ?? $row['amount'] ?? 0);
            $total += $amount;
            $line = '- ' . ($row['period_label'] ?? $row['label'] ?? 'Month') . ' — Due ' . ($row['due_date'] ?? '—') . ' — Rs.' . number_format($amount, 2);
            $wa[] = $line;
            $sms[] = $line;
        }

        $slogan = $company?->company_slogan ?: ($company?->company_name ?: 'Finance');
        $companyPhone = $company?->company_mobile ?: ($company?->support_mobile ?: '');
        $link = ($publicUrl && $publicUrl !== '#') ? $publicUrl : '';
        $wa[] = '';
        $wa[] = '*Total due: Rs.' . number_format($total, 2) . '*';
        $sms[] = '';
        $sms[] = 'Total due: Rs.' . number_format($total, 2);
        if ($link !== '') {
            $wa[] = '';
            $wa[] = 'View schedule: ' . $link;
            $sms[] = '';
            $sms[] = 'View schedule: ' . $link;
        }
        $wa[] = '';
        $wa[] = 'Thank you, ' . $slogan . '.' . ($companyPhone !== '' ? ' For queries: ' . $companyPhone . '.' : '');
        $sms[] = '';
        $sms[] = 'Thank you, ' . $slogan . '.' . ($companyPhone !== '' ? ' For queries: ' . $companyPhone . '.' : '');

        return [
            'whatsapp' => 'https://wa.me/' . $digits . '?text=' . rawurlencode(implode("\n", $wa)),
            'sms' => 'sms:+' . $digits . '?body=' . rawurlencode(implode("\n", $sms)),
        ];
    }

    protected function loanDisplayStatus(Emi $emi, Carbon $today): string
    {
        if ($emi->status === 'paid') {
            return 'paid';
        }

        $pending = (float) ($emi->pending_amount ?: max(0, (float) $emi->total_amount - (float) $emi->paid_amount));
        $paid = (float) $emi->paid_amount;
        if ($emi->status === 'partial' || ($paid > 0.009 && $pending > 0.009)) {
            return 'partial';
        }

        $dueDate = $emi->due_date ? $emi->due_date->copy()->startOfDay() : null;
        if (! $dueDate) {
            return $emi->status ?: 'pending';
        }
        if ($dueDate->lt($today)) {
            return 'overdue';
        }

        return $dueDate->lte($today->copy()->endOfMonth()->startOfDay()) ? 'pending' : 'upcoming';
    }

    protected function chitDisplayStatus(Installment $inst, Carbon $today, float $paid, float $balance): string
    {
        if ($inst->status === 'paid' || ($balance <= 0.009 && $paid > 0.009)) {
            return 'paid';
        }
        if ($inst->status === 'waived') {
            return 'waived';
        }

        $dueDate = $inst->due_date ? Carbon::parse($inst->due_date)->startOfDay() : null;
        if (! $dueDate) {
            return $paid > 0.009 ? 'partial' : 'pending';
        }
        if ($dueDate->lt($today)) {
            return 'overdue';
        }
        if ($paid > 0.009 && $balance > 0.009) {
            return 'partial';
        }

        return $dueDate->lte($today->copy()->endOfMonth()->startOfDay()) ? 'pending' : 'upcoming';
    }

    protected function statusBadge(string $status): string
    {
        $map = [
            'paid' => ['Paid', 'success'],
            'partial' => ['Partial', 'info'],
            'pending' => ['Pending', 'warning'],
            'upcoming' => ['Upcoming', 'secondary'],
            'overdue' => ['Overdue', 'danger'],
            'waived' => ['Waived', 'secondary'],
        ];
        [$label, $color] = $map[$status] ?? [ucfirst($status), 'secondary'];

        return sprintf('<span class="badge bg-label-%s">%s</span>', $color, $label);
    }

    protected function countStatuses($items): array
    {
        $counts = ['overdue' => 0, 'pending' => 0, 'upcoming' => 0, 'partial' => 0, 'paid' => 0];
        foreach ($items as $row) {
            $key = $row['status'] ?? '';
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }
        return $counts;
    }

    protected function formatStatusBadges(array $counts): string
    {
        $parts = [];
        if ($counts['overdue'] > 0) {
            $parts[] = '<span class="badge bg-label-danger me-1">' . $counts['overdue'] . ' Overdue</span>';
        }
        if ($counts['pending'] > 0) {
            $parts[] = '<span class="badge bg-label-warning me-1">' . $counts['pending'] . ' Pending</span>';
        }
        if ($counts['upcoming'] > 0) {
            $parts[] = '<span class="badge bg-label-secondary me-1">' . $counts['upcoming'] . ' Upcoming</span>';
        }
        if ($counts['partial'] > 0) {
            $parts[] = '<span class="badge bg-label-info me-1">' . $counts['partial'] . ' Partial</span>';
        }
        if ($counts['paid'] > 0) {
            $parts[] = '<span class="badge bg-label-success me-1">' . $counts['paid'] . ' Paid</span>';
        }

        return $parts ? implode('', $parts) : '<span class="text-muted">No dues</span>';
    }

    protected function groupByDisplayStatus($items): array
    {
        $grouped = ['overdue' => [], 'pending' => [], 'upcoming' => [], 'partial' => [], 'paid' => []];
        foreach ($items as $row) {
            $key = $row['status'] ?? '';
            if (isset($grouped[$key])) {
                $grouped[$key][] = $row;
            }
        }

        return $grouped;
    }
}
