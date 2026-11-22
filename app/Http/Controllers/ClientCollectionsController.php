<?php

namespace App\Http\Controllers;

use App\Http\Controllers\EmiController;
use App\Models\Client;
use App\Models\CompanyDetail;
use App\Models\Emi;
use App\Models\LoanAccount;
use App\Models\CollectionNote;
use App\Support\HashId;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if (! in_array($module, ['all', 'loan'], true)) {
            $module = 'loan';
        }

        $status = $request->input('status', 'all');
        if (! in_array($status, ['overdue', 'pending', 'upcoming', 'partial', 'paid', 'all', 'unpaid'], true)) {
            $status = 'all';
        }
        if ($status === 'unpaid') {
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

        // Query accounts matching filters (each LoanAccount is a separate row)
        $accounts = $this->queryMatchingAccounts($module, $status, $today, $agentId, $search, $fromDate, $toDate);

        $total = $accounts->count();
        $pageAccounts = $accounts->slice($start, $length)->values();

        // Collect IDs for bulk preloading
        $pageLoanIds = $pageAccounts->where('record_type', 'loan')->pluck('record_id')->map(fn ($id) => (int) $id)->unique()->all();
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

        // Preload EMIs for page loans
        $emisByLoan = $this->fetchEmisForLoanAccounts($pageLoanIds, $status, $today, $fromDate, $toDate);
        $overdueEmisByLoan = $status === 'overdue'
            ? $emisByLoan
            : $this->fetchEmisForLoanAccounts($pageLoanIds, 'overdue', $today, null, null);
        $payableEmisByLoan = $this->fetchEmisForLoanAccounts($pageLoanIds, 'unpaid', $today, null, null);

        $company = CompanyDetail::query()->first();
        $canPay = (bool) Auth::user()?->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);

        $data = $pageAccounts->map(function ($acc, $index) use (
            $clients, $loanAccounts,
            $emisByLoan, $overdueEmisByLoan, $payableEmisByLoan,
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

                $loanReminders = $emis->filter(fn ($e) => !empty($e['reminder_date_raw']));
                $hasLoanReminderToday = $emis->contains(fn ($e) => !empty($e['is_reminder_today']));
                $latestLoanReminder = $loanReminders->sortByDesc('reminder_date_raw')->first();

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
                    'has_reminder' => $loanReminders->isNotEmpty(),
                    'has_reminder_today' => $hasLoanReminderToday,
                    'reminder_date' => $latestLoanReminder['reminder_date'] ?? null,
                    'reminder_date_raw' => $latestLoanReminder['reminder_date_raw'] ?? null,
                    'is_reminder_today' => $hasLoanReminderToday,
                    'is_reminder_overdue' => !empty($latestLoanReminder['is_reminder_overdue']),
                    'latest_note' => $latestLoanReminder['latest_note'] ?? null,
                    'note_status' => $latestLoanReminder['note_status'] ?? null,
                    'latest_reminder_date' => $latestLoanReminder['reminder_date'] ?? null,
                    'latest_reminder_date_raw' => $latestLoanReminder['reminder_date_raw'] ?? null,
                    'latest_reminder_note' => $latestLoanReminder['latest_note'] ?? null,
                    'reminder_status' => $latestLoanReminder['note_status'] ?? null,
                    'total_due' => $loanDue,
                    'total_due_formatted' => '₹' . number_format($loanDue, 2),
                    'payable_amount' => $loanPayable,
                    'payable_amount_formatted' => '₹' . number_format($loanPayable, 2),
                    'status_summary' => $this->formatStatusBadges($statusCounts),
                    'loan_items' => $emis->values()->all(),
                    'items' => $emis->values()->all(),
                    'loan_items_grouped' => $this->groupByDisplayStatus($emis),
                    'payable_loan_ids' => $payableEmis->pluck('id')->filter()->values()->all(),
                    'public_url' => $publicUrl,
                    'whatsapp_url' => $waMessages['whatsapp'],
                    'sms_url' => $waMessages['sms'],
                    'company_slogan' => $company?->company_slogan ?: ($company?->company_name ?: 'Finance'),
                    'company_phone' => $company?->company_mobile ?: ($company?->support_mobile ?: ''),
                ];
            }

            return null;
        })->filter()->values();

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
            'stats' => $this->computeStats($module, $today, $agentId, $search, $fromDate, $toDate),
        ]);
    }

    public function getNotes(Request $request): JsonResponse
    {
        $type = $request->input('type', 'loan');
        $rawId = $request->input('id');

        $emiId = $request->input('emi_id');
        $installmentId = $request->input('installment_id');
        $loanAccountId = $request->input('loan_account_id');
        $groupMemberId = $request->input('group_member_id') ?: $request->input('member_id');
        $clientId = $request->input('client_id');

        if ($rawId) {
            if (is_string($rawId) && str_starts_with($rawId, 'loan_')) {
                $type = 'loan';
                $clean = substr($rawId, 5);
                if (is_numeric($clean)) {
                    $loanAccountId = (int) $clean;
                }
            } elseif (is_numeric($rawId)) {
                $emiId = (int) $rawId;
            }
        }

        $query = CollectionNote::with(['user', 'agent'])->orderBy('created_at', 'desc');

        $entityInfo = [
            'type' => 'loan',
            'label' => '',
            'account_number' => '',
            'client_name' => '',
            'client_phone' => '',
            'amount_due' => 0,
            'due_date' => '—',
        ];

        if ($type === 'loan') {
            if ($emiId) {
                $query->where('emi_id', $emiId);
                $emi = Emi::with(['loanAccount.client'])->find($emiId);
                if ($emi) {
                    $isPaid = ($emi->status === 'paid') || ((float) ($emi->pending_amount ?? 0) <= 0.009);
                    $due = $isPaid ? 0.0 : (float) ($emi->pending_amount ?? 0);
                    $entityInfo = [
                        'type' => 'loan',
                        'emi_id' => $emi->id,
                        'loan_account_id' => $emi->loan_account_id,
                        'client_id' => $emi->loanAccount?->client_id,
                        'label' => 'EMI #' . $emi->instalment_number,
                        'account_number' => $emi->loanAccount?->account_number ?? 'N/A',
                        'client_name' => $emi->loanAccount?->client?->client_name ?? 'N/A',
                        'client_phone' => $emi->loanAccount?->client?->client_phone ?? 'N/A',
                        'amount_due' => round(max(0, $due), 2),
                        'is_paid' => $isPaid,
                        'status' => $emi->status,
                        'due_date' => $emi->due_date ? Carbon::parse($emi->due_date)->format('d-m-Y') : '—',
                    ];
                }
            } elseif ($loanAccountId) {
                $query->where(function ($q) use ($loanAccountId) {
                    $q->where('loan_account_id', $loanAccountId)
                      ->orWhereHas('emi', fn ($eq) => $eq->where('loan_account_id', $loanAccountId));
                });
                $loan = LoanAccount::with('client')->find($loanAccountId);
                if ($loan) {
                    $due = (float) ($loan->outstanding_principal ?: $loan->outstanding_amount ?: 0);
                    $isPaid = $due <= 0.009;
                    $entityInfo = [
                        'type' => 'loan',
                        'loan_account_id' => $loan->id,
                        'client_id' => $loan->client_id,
                        'label' => 'Loan Account ' . $loan->account_number,
                        'account_number' => $loan->account_number ?? 'N/A',
                        'client_name' => $loan->client?->client_name ?? 'N/A',
                        'client_phone' => $loan->client?->client_phone ?? 'N/A',
                        'amount_due' => round(max(0, $due), 2),
                        'is_paid' => $isPaid,
                        'status' => $isPaid ? 'paid' : 'active',
                        'due_date' => '—',
                    ];
                }
            }
        }

        $notes = $query->get()->map(function ($note) {
            $isToday = $note->reminder_date && Carbon::parse($note->reminder_date)->isToday();
            $isPast = $note->reminder_date && Carbon::parse($note->reminder_date)->startOfDay()->lt(now()->startOfDay());
            return [
                'id' => $note->id,
                'note' => $note->note,
                'reminder_date' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('Y-m-d') : null,
                'reminder_date_formatted' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('d-m-Y') : null,
                'reminder_time' => $note->reminder_time,
                'status' => $note->status ?: 'postponed',
                'is_today' => $isToday,
                'is_overdue' => $isPast && !$note->is_completed,
                'created_by' => $note->creator_name,
                'created_at' => $note->created_at->format('d-m-Y h:i A'),
                'created_at_human' => $note->created_at->diffForHumans(),
            ];
        });

        return response()->json([
            'success' => true,
            'entity' => $entityInfo,
            'notes' => $notes,
        ]);
    }

    public function saveNote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:loan',
            'note' => 'required|string|max:2000',
            'reminder_date' => 'nullable|date',
            'status' => 'nullable|string|max:50',
            'emi_id' => 'nullable|integer',
            'loan_account_id' => 'nullable|integer',
            'client_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $agent = $user?->agent;
        $agentId = $agent?->id;

        $type = 'loan';
        $clientId = $validated['client_id'] ?? null;
        $loanAccountId = $validated['loan_account_id'] ?? null;
        $emiId = $validated['emi_id'] ?? null;

        $dueAmount = 0.0;
        if ($emiId) {
            $emi = Emi::with('loanAccount')->find($emiId);
            if ($emi) {
                $loanAccountId = $loanAccountId ?: $emi->loan_account_id;
                $clientId = $clientId ?: $emi->loanAccount?->client_id;
                $dueAmount = ($emi->status !== 'paid') ? (float) ($emi->pending_amount ?? 0) : 0.0;
            }
        } elseif ($loanAccountId && !$clientId) {
            $loan = LoanAccount::find($loanAccountId);
            $clientId = $loan?->client_id;
            $dueAmount = $loan ? (float) ($loan->outstanding_principal ?: $loan->outstanding_amount ?: 0) : 0.0;
        }

        // If due amount is 0, do not allow adding a reminder
        if (!empty($validated['reminder_date']) && $dueAmount <= 0.009) {
            return response()->json([
                'success' => false,
                'message' => 'Due amount is ₹0.00. Adding a reminder is not allowed for completed/cleared dues.',
            ], 422);
        }

        $note = CollectionNote::create([
            'type' => $type,
            'client_id' => $clientId,
            'loan_account_id' => $loanAccountId,
            'emi_id' => $emiId,
            'user_id' => $user?->id,
            'agent_id' => $agentId,
            'note' => $validated['note'],
            'reminder_date' => $validated['reminder_date'] ?? null,
            'status' => $validated['status'] ?? 'postponed',
        ]);

        // Sync with emi_followups if loan EMI and agent exists
        if ($type === 'loan' && $emiId && $agentId) {
            try {
                \App\Models\EmiFollowup::create([
                    'emi_id' => $emiId,
                    'agent_id' => $agentId,
                    'status' => $validated['status'] ?? 'appointment_to_visit',
                    'followup_at' => $validated['reminder_date'] ? Carbon::parse($validated['reminder_date'])->format('Y-m-d H:i:s') : null,
                    'remarks' => $validated['note'],
                ]);
            } catch (\Throwable $e) {
                Log::warning('Could not sync to emi_followups: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Note and reminder saved successfully.',
            'note' => [
                'id' => $note->id,
                'note' => $note->note,
                'reminder_date' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('Y-m-d') : null,
                'reminder_date_formatted' => $note->reminder_date ? Carbon::parse($note->reminder_date)->format('d-m-Y') : null,
                'status' => $note->status,
                'created_by' => $note->creator_name,
                'created_at' => $note->created_at->format('d-m-Y h:i A'),
            ]
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
            'module' => 'required|in:loan',
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
            'client_id' => 'nullable|integer',
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

        return response()->json(['success' => false, 'message' => 'Invalid collection payment request.'], 422);
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
            'items.*.record_type' => 'required|in:loan',
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
        $mode = $method === 'in_hand' ? 'cash' : $method;
        $collectedBy = $user->id;

        $totalPaid = 0.0;
        $loanSuccessCount = 0;

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
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($loanSuccessCount === 0) {
            return response()->json(['success' => false, 'message' => 'No accounts were processed.'], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Bulk payment of ₹" . number_format($totalPaid, 2) . " processed successfully across {$loanSuccessCount} loan account(s).",
        ]);
    }

    protected function queryMatchingAccounts(string $module, string $status, Carbon $today, ?int $agentId, string $search, ?string $fromDate, ?string $toDate): Collection
    {
        $loanQ = DB::table('loan_accounts')
            ->leftJoin('loan_applications', 'loan_applications.id', '=', 'loan_accounts.loan_application_id')
            ->join('clients', function ($join) {
                $join->on('clients.id', '=', 'loan_accounts.client_id')
                    ->orOn('clients.id', '=', 'loan_applications.client_id');
            })
            ->leftJoin('agents', 'agents.id', '=', 'clients.assigned_to')
            ->leftJoin('locations', 'locations.id', '=', 'clients.location_id')
            ->whereNull('loan_accounts.deleted_at')
            ->where(function ($q) {
                $q->whereNull('loan_accounts.status')
                    ->orWhereRaw("LOWER(TRIM(loan_accounts.status)) NOT IN ('closed', 'foreclosed', 'completed')");
            });

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
                ->whereNull('emis.deleted_at')
                ->where(function ($emiCond) use ($status, $today, $fromDate, $toDate) {
                    $emiCond->where(function ($subQ) use ($status, $today, $fromDate, $toDate) {
                        $this->applyLoanStatus($subQ, $status, $today);
                        $this->applyEmiDueDateRange($subQ, $status, $fromDate, $toDate, 'emis.due_date');
                    });
                    if ($fromDate || $toDate) {
                        $emiCond->orWhereExists(function ($noteSub) use ($fromDate, $toDate) {
                            $noteSub->select(DB::raw(1))
                                ->from('collection_notes')
                                ->whereColumn('collection_notes.emi_id', 'emis.id')
                                ->where('collection_notes.is_completed', false);
                            $this->applyDateRange($noteSub, 'collection_notes.reminder_date', $fromDate, $toDate);
                        });
                    }
                });
        });

        $loanAccounts = $loanQ->select([
            DB::raw("'loan' as record_type"),
            'loan_accounts.id as record_id',
            DB::raw('COALESCE(loan_accounts.client_id, loan_applications.client_id) as client_id'),
            'clients.client_name',
            'clients.nickname as client_nickname',
            'loan_accounts.account_number as identifier',
        ])
            ->groupBy(
                'loan_accounts.id',
                'loan_accounts.client_id',
                'loan_applications.client_id',
                'clients.client_name',
                'clients.nickname',
                'loan_accounts.account_number'
            )
            ->get();

        return $loanAccounts->sortBy(function ($item) {
            return strtolower(trim($item->client_name)) . '_' . $item->record_type . '_' . $item->identifier;
        })->values();
    }

    protected function fetchEmisForLoanAccounts(array $loanAccountIds, string $status, Carbon $today, ?string $fromDate, ?string $toDate): Collection
    {
        if ($loanAccountIds === []) {
            return collect();
        }

        $query = Emi::with(['loanAccount.loanApplication', 'latestCollectionNote.user', 'latestCollectionNote.agent'])
            ->whereIn('loan_account_id', $loanAccountIds);

        if ($fromDate || $toDate) {
            $query->where(function ($q) use ($status, $today, $fromDate, $toDate) {
                $q->where(function ($sub) use ($status, $today, $fromDate, $toDate) {
                    $this->applyLoanStatus($sub, $status, $today);
                    $this->applyEmiDueDateRange($sub, $status, $fromDate, $toDate, 'emis.due_date');
                })->orWhereHas('collectionNotes', function ($noteSub) use ($fromDate, $toDate) {
                    $noteSub->where('is_completed', false);
                    $this->applyDateRange($noteSub, 'reminder_date', $fromDate, $toDate);
                });
            });
        } else {
            $this->applyLoanStatus($query, $status, $today);
        }

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

                    $latestNote = $emi->latestCollectionNote;
                    $remDate = $latestNote?->reminder_date ? Carbon::parse($latestNote->reminder_date) : null;
                    $isRemToday = $remDate ? $remDate->isToday() : false;
                    $isRemOverdue = $remDate ? ($remDate->startOfDay()->lt(now()->startOfDay()) && !$latestNote->is_completed) : false;

                    return [
                        'id' => $emi->getRouteKey(),
                        'raw_id' => (int) $emi->id,
                        'emi_id' => (int) $emi->id,
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
                        'reminder_date' => $remDate ? $remDate->format('d-m-Y') : null,
                        'reminder_date_raw' => $remDate ? $remDate->format('Y-m-d') : null,
                        'is_reminder_today' => $isRemToday,
                        'is_reminder_overdue' => $isRemOverdue,
                        'latest_note' => $latestNote?->note,
                        'note_status' => $latestNote?->status,
                        'has_reminder' => (bool) $remDate,
                    ];
                });
            });
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

    protected function applyLoanStatus($query, string $status, Carbon $today): void
    {
        if ($status === 'all' || $status === 'unpaid') {
            $query->whereIn('emis.status', ['pending', 'overdue', 'partial'])
                ->where('emis.pending_amount', '>', 0);

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

    /**
     * Upcoming EMIs fall after the current month; do not apply a "this month" to_date on that tab.
     */
    protected function applyEmiDueDateRange($query, string $status, ?string $fromDate, ?string $toDate, string $column = 'emis.due_date'): void
    {
        $prefix = str_contains($column, '.') ? explode('.', $column)[0] . '.' : '';
        $statusCol = $prefix . 'status';
        $pendingCol = $prefix . 'pending_amount';

        if (in_array($status, ['upcoming', 'all', 'unpaid'], true)) {
            if ($fromDate) {
                $query->whereDate($column, '>=', $fromDate);
            }

            return;
        }

        if ($fromDate) {
            $query->whereDate($column, '>=', $fromDate);
        }

        if (! $toDate) {
            return;
        }

        $monthEnd = Carbon::today()->endOfMonth()->toDateString();

        if ($status === 'all' && $toDate <= $monthEnd) {
            $query->where(function ($q) use ($column, $fromDate, $toDate, $statusCol, $pendingCol) {
                $q->where(function ($inner) use ($column, $fromDate, $toDate) {
                    if ($fromDate) {
                        $inner->whereDate($column, '>=', $fromDate);
                    }
                    $inner->whereDate($column, '<=', $toDate);
                })->orWhere(function ($future) use ($column, $toDate, $statusCol, $pendingCol) {
                    $future->whereDate($column, '>', $toDate)
                        ->whereIn($statusCol, ['pending', 'overdue', 'partial'])
                        ->where($pendingCol, '>', 0);
                });
            });

            return;
        }

        $query->whereDate($column, '<=', $toDate);
    }

    protected function computeStats(string $module, Carbon $today, ?int $agentId, string $search, ?string $fromDate, ?string $toDate): array
    {
        $loan = ['overdue' => 0, 'pending' => 0, 'upcoming' => 0, 'partial' => 0, 'paid' => 0, 'collected' => 0.0, 'pending_amount' => 0.0];

        foreach (['overdue', 'pending', 'upcoming', 'partial', 'paid'] as $bucket) {
            $q = clone $this->loanBaseQuery($agentId, $search);
            $this->applyEmiDueDateRange($q, $bucket, $fromDate, $toDate, 'emis.due_date');
            $this->applyLoanStatus($q, $bucket, $today);
            $loan[$bucket] = (int) (clone $q)->distinct()->count('loan_accounts.id');
            if ($bucket === 'paid') {
                $loan['collected'] = (float) (clone $q)->sum('emis.paid_amount');
            } else {
                $loan['pending_amount'] += (float) (clone $q)->sum('emis.pending_amount');
            }
        }

        $overdue = $loan['overdue'];
        $pending = $loan['pending'];
        $upcoming = $loan['upcoming'];
        $partial = $loan['partial'];
        $paid = $loan['paid'];

        $collectibleQuery = clone $this->loanBaseQuery($agentId, $search);
        $this->applyLoanStatus($collectibleQuery, 'unpaid', $today);
        $collectibleAccounts = (int) (clone $collectibleQuery)->distinct()->count('loan_accounts.id');

        return [
            'total' => $overdue + $pending + $upcoming + $partial + $paid,
            'collectible' => $collectibleAccounts,
            'unpaid_accounts' => $collectibleAccounts,
            'overdue' => $overdue,
            'pending' => $pending,
            'upcoming' => $upcoming,
            'partial' => $partial,
            'paid' => $paid,
            'collected' => round($loan['collected'], 2),
            'pending_amount' => round($loan['pending_amount'], 2),
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
