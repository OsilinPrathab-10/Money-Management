<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoanDocument;
use App\Models\LoanAccount;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanType;
use App\Services\LoanDocumentService;
use App\Support\CollectedDocuments;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoanControllerApi extends Controller
{
    protected function authenticatedClient(): Client
    {
        $user = Auth::user();
        if (! $user) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401));
        }

        $client = $user->client
            ?? Client::where('user_id', $user->id)->first()
            ?? Client::where('client_phone', $user->phone ?? '')->first();

        if (! $client) {
            abort(response()->json([
                'success' => false,
                'message' => 'Client profile not found.',
            ], 404));
        }

        return $client;
    }

    protected function assertKycVerified(Client $client): void
    {
        if (optional($client->kycDetail)->status !== 'verified') {
            abort(response()->json([
                'success' => false,
                'message' => 'KYC must be verified before applying.',
            ], 422));
        }
    }

    public function loanDropdowns(Request $request): JsonResponse
    {
        $loanModes = [
            [
                'value' => 'emi',
                'label' => 'EMI',
                'name' => 'Standard EMI',
                'requires_tenure' => true,
            ],
            [
                'value' => 'interest_only',
                'label' => 'Open Loan',
                'name' => 'Open Loan',
                'requires_tenure' => false,
            ],
        ];

        $repaymentFrequencies = [
            ['value' => 'monthly', 'label' => 'Monthly'],
            ['value' => 'weekly', 'label' => 'Weekly'],
            ['value' => 'daily', 'label' => 'Daily'],
        ];

        // Query active products
        $productsQuery = LoanProduct::with('loanType')
            ->where(function ($q) {
                $q->where('status', 'active')->orWhere('status', 1);
            })
            ->orderBy('loan_name');

        if ($request->filled('loan_type_id')) {
            $productsQuery->where('loan_type_id', (int) $request->input('loan_type_id'));
        }

        if ($request->filled('loan_product_id')) {
            $productsQuery->where('id', (int) $request->input('loan_product_id'));
        }

        $allFormattedProducts = $productsQuery->get()->map(fn (LoanProduct $p) => [
            'id' => $p->id,
            'loan_name' => $p->loan_name,
            'loan_code' => $p->loan_code,
            'loan_type_id' => $p->loan_type_id,
            'loan_amount_min' => (float) $p->loan_amount_min,
            'loan_amount_max' => (float) $p->loan_amount_max,
            'interest_rate' => (float) $p->interest_rate,
            'interest_type' => $p->interest_type,
            'term_unit' => $p->term_unit,
            'min_tenure' => (int) ($p->min_tenture ?? 0),
            'max_tenure' => (int) ($p->max_tenture ?? 0),
            'frequencies' => $repaymentFrequencies,
            'loan_modes' => $loanModes,
        ]);

        $typesQuery = LoanType::query()
            ->where(function ($q) {
                $q->where('status', true)->orWhere('status', 1)->orWhere('status', 'active');
            })
            ->orderBy('name');

        if ($request->filled('loan_type_id')) {
            $typesQuery->where('id', (int) $request->input('loan_type_id'));
        }

        $types = $typesQuery->get()->map(function (LoanType $t) use ($allFormattedProducts) {
            $typeProducts = $allFormattedProducts->where('loan_type_id', $t->id)->values();
            return [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'icon' => $t->loan_type_icon_url,
                'image' => $t->loan_type_image_url,
                'banner' => $t->loan_type_banner_url,
                'products' => $typeProducts,
            ];
        });

        if ($request->filled('loan_product_id') || $request->filled('loan_type_id')) {
            $types = $types->filter(fn ($t) => count($t['products']) > 0)->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Type based products fetched successfully',
            'data' => [
                'type_based_products' => $types,
                'repayment_frequencies' => $repaymentFrequencies,
                'loan_modes' => $loanModes,
            ],
        ]);
    }


    public function applyLoan(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->assertKycVerified($client);

        // Normalize loan_mode from customer request
        $loanModeInput = $request->input('loan_mode');
        $normalizedLoanMode = null;
        if (! empty($loanModeInput)) {
            $rawMode = strtolower(str_replace([' ', '_', '-'], '', (string) $loanModeInput));
            if (in_array($rawMode, ['openloan', 'interestonly', 'open', 'kandhuvatti', 'kanduvatti'], true)) {
                $normalizedLoanMode = 'interest_only';
            } elseif (in_array($rawMode, ['emi', 'standardemi'], true)) {
                $normalizedLoanMode = 'emi';
            }
        }

        if ($normalizedLoanMode) {
            $request->merge(['loan_mode' => $normalizedLoanMode]);
        }

        $emiDayMax = LoanApplication::maxEmiDayFor(
            $request->input('repayment_frequency'),
            $request->input('emi_start_date')
        );

        $validated = $request->validate([
            'loan_code' => 'required_without:loan_product_id|exists:loan_products,loan_code',
            'loan_product_id' => 'required_without:loan_code|exists:loan_products,id',
            'loan_amount' => 'required|numeric|min:1',
            'loan_mode' => 'nullable|string',
            'tenure' => 'nullable|integer|min:0',
            'repayment_frequency' => 'required|in:daily,weekly,monthly',
            'emi_day' => 'required|integer|min:1|max:' . $emiDayMax,
            'emi_start_date' => 'required|date',
        ], [
            'emi_day.max' => 'EMI day cannot be greater than ' . $emiDayMax . ' for the selected start month.',
        ]);

        $existing = LoanApplication::where('client_id', $client->id)
            ->whereIn('status', ['pending', 'approved', 'process', 'in_progress'])
            ->exists();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending loan application.',
            ], 422);
        }

        $product = ! empty($validated['loan_code'])
            ? LoanProduct::where('loan_code', $validated['loan_code'])->firstOrFail()
            : LoanProduct::findOrFail($validated['loan_product_id']);

        $amount = (float) $validated['loan_amount'];
        if ($amount < (float) $product->loan_amount_min || $amount > (float) $product->loan_amount_max) {
            return response()->json([
                'success' => false,
                'message' => "Loan amount must be between {$product->loan_amount_min} and {$product->loan_amount_max}.",
            ], 422);
        }

        $loanMode = $normalizedLoanMode ?: ($product->interest_type === 'interest_only' ? 'interest_only' : 'emi');

        if ($loanMode === 'interest_only') {
            $tenure = 0;
        } else {
            $tenure = isset($validated['tenure']) && $validated['tenure'] !== '' ? (int) $validated['tenure'] : (int) ($product->max_tenture ?? 12);
        }

        $emiStartDate = Carbon::parse($validated['emi_start_date']);
        $emiDay = (int) $validated['emi_day'];
        if ($validated['repayment_frequency'] === 'daily') {
            $emiDay = 1;
        } elseif ($validated['repayment_frequency'] === 'monthly' && $emiDay > $emiStartDate->daysInMonth) {
            return response()->json([
                'success' => false,
                'message' => 'EMI day cannot be greater than ' . $emiStartDate->daysInMonth . ' for ' . $emiStartDate->format('F Y') . '.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $application = LoanApplication::create([
                'client_id' => $client->id,
                'loan_product_id' => $product->id,
                'loan_code' => $product->loan_code,
                'loan_amount' => $amount,
                'tenure' => $tenure,
                'term_unit' => $validated['repayment_frequency'],
                'interest_rate' => (float) $product->interest_rate,
                'loan_mode' => $loanMode,
                'repayment_frequency' => $validated['repayment_frequency'],
                'emi_day' => $emiDay,
                'emi_start_date' => $emiStartDate->format('Y-m-d'),
                'emi_start_year' => $emiStartDate->year,
                'emi_start_month' => $emiStartDate->month,
                'emi_start_day' => $emiStartDate->day,
                'status' => 'pending',
                'applied_at' => now(),
            ]);

            DB::commit();

            event(new \App\Events\NewLoanApplicationEvent($application, 'customer'));

            return response()->json([
                'success' => true,
                'message' => 'Loan application submitted successfully',
                'data' => $this->formatLoanApplication($application->fresh('product')),
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Customer apply loan failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Loan application failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function loanApplications(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->validateLoanListParams($request);
        $query = LoanApplication::with(['product', 'loanAccount.emis'])->where('client_id', $client->id);
        $this->applyLoanApplicationFilters($query, $request);

        $id = $id ?: $request->input('id') ?: $request->input('application_id');
        $id = $this->resolveRequestId($id);

        if ($id) {
            $application = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Loan application fetched successfully',
                'data' => $this->formatLoanApplication($application),
            ]);
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(fn ($a) => $this->formatLoanApplication($a));

        return response()->json([
            'success' => true,
            'message' => 'Loan applications fetched successfully',
            'data' => $items,
        ]);
    }

    protected function formatLoanApplication(LoanApplication $application, ?LoanAccount $account = null): array
    {
        $rawStatus = (string) ($application->status ?? 'pending');
        $displayStatus = strtolower($rawStatus) === 'applied' ? 'pending' : $rawStatus;
        $statusLabel = $application->status_label;
        $statusBadge = $application->status_badge;

        $account = $account ?? ($application->relationLoaded('loanAccount') ? $application->loanAccount : null);
        $closedMeta = $this->closedLoanDisplayStatus($account);
        if ($closedMeta) {
            $displayStatus = $closedMeta['status'];
            $statusLabel = $closedMeta['status_label'];
            $statusBadge = $closedMeta['status_badge'];
        }

        return [
            'id' => $application->id,
            'application_number' => $application->application_number,
            'loan_code' => $application->loan_code,
            'loan_name' => optional($application->product)->loan_name,
            'loan_amount' => (float) $application->loan_amount,
            'tenure' => (int) $application->tenure,
            'term_unit' => $application->term_unit,
            'loan_mode' => $application->loan_mode,
            'loan_mode_label' => $application->loan_mode === 'interest_only' ? 'Open Loan' : 'EMI',
            'interest_rate' => (float) $application->interest_rate,
            'emi_day' => (int) $application->emi_day,
            'emi_start_date' => $application->emi_start_date,
            'status' => $displayStatus,
            'status_label' => $statusLabel,
            'status_badge' => $statusBadge,
            'applied_at' => optional($application->applied_at ?? $application->created_at)?->format('Y-m-d'),
        ];
    }

    /**
     * Fully paid / closed accounts surface as completed or closed on customer APIs.
     *
     * @return array{status: string, status_label: string, status_badge: string}|null
     */
    protected function closedLoanDisplayStatus(?LoanAccount $account, string $as = 'application'): ?array
    {
        if (! $account || ! $account->isEffectivelyClosed()) {
            return null;
        }

        $isForeclosed = (bool) $account->is_foreclosed
            || in_array(strtolower((string) $account->status), ['foreclosed'], true);

        if ($isForeclosed || $as === 'account') {
            return [
                'status' => 'closed',
                'status_label' => 'Closed',
                'status_badge' => 'info',
            ];
        }

        return [
            'status' => 'completed',
            'status_label' => 'Completed',
            'status_badge' => 'success',
        ];
    }

    public function applicationsWithAccounts(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->validateLoanListParams($request);
        
        $query = LoanApplication::with(['product', 'loanAccount.emis'])
            ->where('client_id', $client->id);
        $this->applyLoanApplicationFilters($query, $request);

        $id = $this->resolveRequestId($request->input('id') ?: $request->input('application_id'));
        if ($id) {
            $query->where('id', $id);
        }
            
        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $applications = $items->getCollection();
        $accountsByApplication = $this->loanAccountsForApplications($client->id, $applications);

        $items->getCollection()->transform(function ($application) use ($accountsByApplication) {
            $account = $accountsByApplication->get((int) $application->id)
                ?? $accountsByApplication->get((string) $application->application_number);
            $accountDetails = $account ? $this->formatLoanAccount($account, true) : null;
                
            return [
                'application' => $this->formatLoanApplication($application, $account),
                'account_details' => $accountDetails,
                'loan_documents' => $accountDetails['loan_documents'] ?? $this->emptyLoanDocumentsSection(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Loan applications with accounts fetched successfully',
            'data' => $items,
        ]);
    }

    public function loanAccounts(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->validateLoanListParams($request);
        $query = LoanAccount::with($this->loanAccountRelations())
            ->where('client_id', $client->id);

        $id = $id ?: $request->input('id') ?: $request->input('account_id');
        $id = $this->resolveRequestId($id);

        if ($id) {
            $account = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Loan account fetched successfully',
                'data' => $this->formatLoanAccount($account, true),
            ]);
        }

        $status = $request->input('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'like', "%{$search}%")
                    ->orWhere('customer_loan_account_number', 'like', "%{$search}%")
                    ->orWhere('application_number', 'like', "%{$search}%")
                    ->orWhere('loan_code', 'like', "%{$search}%");
            });
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(fn ($a) => $this->formatLoanAccount($a, true));

        return response()->json([
            'success' => true,
            'message' => 'Loan accounts fetched successfully',
            'data' => $items,
        ]);
    }

    public function accountSummary(Request $request, $id): JsonResponse
    {
        $client = $this->authenticatedClient();
        $account = LoanAccount::with($this->loanAccountRelations())
            ->where('client_id', $client->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Loan account summary fetched successfully',
            'data' => $this->formatLoanAccount($account, true),
        ]);
    }

    protected function formatLoanAccount(\App\Models\LoanAccount $account, bool $includeEmis = true): array
    {
        $emis = $account->relationLoaded('emis') ? $account->emis : $account->emis()->with('collections')->get();

        $totalEmis = $emis->count();
        $paidEmis = $emis->where('status', 'paid')->count();
        $pendingEmis = $emis->where('status', 'pending')->count();
        $overdueEmis = $emis->where('status', 'overdue')->count();
        $partialEmis = $emis->where('status', 'partial')->count();

        $nextEmi = $emis
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('instalment_number')
            ->first();

        $nextDueDate = optional(optional($nextEmi)->due_date)->format('Y-m-d');
        $nextEmiAmount = (float) (optional($nextEmi)->total_amount ?? $account->emi_amount ?? 0);

        $totalPayable = (float) $account->total_payable;
        $paidAmount = (float) $account->paid_amount;
        $percentageComplete = $totalPayable > 0 ? round(($paidAmount / $totalPayable) * 100, 2) : 0;
        $remainingPrincipal = (float) ($account->remaining_principal_balance ?? $account->outstanding_amount);
        $closedMeta = $this->closedLoanDisplayStatus($account, 'account');
        $accountStatus = $closedMeta['status'] ?? $account->status;
        $accountStatusLabel = $closedMeta['status_label'] ?? ucfirst(str_replace('_', ' ', (string) $account->status));

        $encodedAccountId = \App\Support\HashId::encode($account->id);
        $statementUrl = url('/loan/statement/' . ($encodedAccountId ?: $account->id));
        $adminStatementUrl = url('/emi/statement/print/' . ($encodedAccountId ?: $account->id));

        $formattedEmis = $emis->sortBy('instalment_number')->values()->map(function ($emi) {
            $hasInProgress = $emi->collections ? $emi->collections->where('status', 'in_progress')->isNotEmpty() : false;
            $status = (string) $emi->status;
            if ($hasInProgress && $status !== 'paid') {
                $status = 'payment_processing';
            }

            $encodedEmiId = \App\Support\HashId::encode($emi->id);
            $receiptUrl = url('/emi/receipt/' . ($encodedEmiId ?: $emi->id));
            $adminReceiptUrl = url('/emi/receipts/view/' . ($encodedEmiId ?: $emi->id));
            $receiptPrintUrl = url('/emi/receipts/print/' . ($encodedEmiId ?: $emi->id));
            $receiptApiUrl = url('/api/loans/emi/' . $emi->id . '/receipt');

        return [
                'id' => $emi->id,
                'instalment_number' => (int) $emi->instalment_number,
                'due_date' => optional($emi->due_date)?->format('Y-m-d'),
                'principal_amount' => (float) $emi->principal_amount,
                'interest_amount' => (float) $emi->interest_amount,
                'total_amount' => (float) $emi->total_amount,
                'paid_amount' => (float) $emi->paid_amount,
                'pending_amount' => (float) ($emi->pending_amount ?? max(0, $emi->total_amount - $emi->paid_amount)),
                'penalty_amount' => (float) $emi->penalty_amount,
                'status' => $status,
                'status_label' => ucfirst(str_replace('_', ' ', $status)),
                'paid_date' => optional($emi->paid_date)?->format('Y-m-d'),
                'receipt_url' => $receiptUrl,
                'receipt_view_url' => $receiptUrl,
                'admin_receipt_url' => $adminReceiptUrl,
                'receipt_print_url' => $receiptPrintUrl,
                'receipt_api_url' => $receiptApiUrl,
            ];
        })->all();

        $product = $account->loanProduct ?? optional($account->loanApplication)->product;
        $application = $account->loanApplication;
        $disbursement = $application?->disbursementDetail
            ?? ($application ? $application->disbursementDetail()->first() : null);

        $collectedDocuments = CollectedDocuments::forLoanDisbursement($disbursement, $application);
        $loanDocuments = $this->formatLoanDocumentsSection($account);

        $collateralDoc = collect($collectedDocuments)->firstWhere('type', 'collateral_document');
        $otherDoc = collect($collectedDocuments)->firstWhere('type', 'other_document');
        $paymentMode = $disbursement
            ? ((strtoupper((string) $disbursement->bank_name) === 'CASH' || strtoupper((string) $disbursement->bank_account_number) === 'OFFLINE')
                ? 'cash'
                : 'bank_transfer')
            : null;

        $res = [
            'id' => $account->id,
            'account_number' => $account->account_number,
            'customer_loan_account_number' => $account->customer_loan_account_number ?? $account->account_number,
            'application_number' => $account->application_number,
            'loan_code' => $account->loan_code,
            'loan_name' => optional($product)->loan_name ?? 'Loan Account',
            'loan_mode' => $account->loan_mode ?? 'emi',
            'loan_amount' => (float) $account->loan_amount,
            'disbursed_amount' => (float) $account->disbursed_amount,
            'interest_rate' => (float) $account->interest_rate,
            'tenure' => (int) $account->tenure,
            'emi_amount' => (float) $account->emi_amount,
            'total_payable' => $totalPayable,
            'paid_amount' => $paidAmount,
            'outstanding_amount' => $account->isOpenLoan() ? $remainingPrincipal : (float) $account->outstanding_amount,
            'remaining_principal_balance' => $remainingPrincipal,
            'penalty' => (float) $account->penalty,
            'status' => $accountStatus,
            'status_label' => $accountStatusLabel,
            'disbursed_at' => optional($account->disbursed_at)?->format('Y-m-d'),
            'closed_at' => optional($account->closed_at)?->format('Y-m-d'),
            'statement_url' => $statementUrl,
            'statement_view_url' => $statementUrl,
            'statement_download_url' => $statementUrl,
            'admin_statement_url' => $adminStatementUrl,
            'disbursement_details' => [
                'disbursement_amount' => (float) ($disbursement?->disbursement_amount ?? $account->disbursed_amount),
                'loan_amount' => (float) $account->loan_amount,
                'disbursed_at' => optional($disbursement?->disburse_at ?? $account->disbursed_at)?->format('Y-m-d'),
                'transaction_id' => $disbursement?->transaction_id ?? $account->transaction_id,
                'utr_number' => $disbursement?->utr_number ?? $account->utr_number,
                'payment_mode' => $paymentMode,
                'payment_mode_label' => $paymentMode === 'cash' ? 'Cash' : ($paymentMode === 'bank_transfer' ? 'Bank Transfer' : null),
                'bank_name' => $disbursement?->bank_name,
                'account_number' => $disbursement?->bank_account_number,
                'ifsc_code' => $disbursement?->ifsc_code,
                'holder_name' => $disbursement?->holder_name,
                'account_type' => $disbursement?->account_type,
                'live_photo_url' => CollectedDocuments::url($application?->live_photo),
                'cash_photo_url' => CollectedDocuments::url($application?->cash_photo),
                'collateral_document_url' => $collateralDoc['file_url'] ?? null,
                'other_document_url' => $otherDoc['file_url'] ?? null,
                'documents' => $collectedDocuments,
            ],
            'documents' => array_values(array_merge($collectedDocuments, $loanDocuments['documents'] ?? [])),
            'loan_documents' => $loanDocuments,
            'collateral_documents' => $collectedDocuments,
            'summary' => [
                'total_payable' => $totalPayable,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $account->isOpenLoan() ? $remainingPrincipal : (float) $account->outstanding_amount,
                'remaining_principal_balance' => $remainingPrincipal,
                'percentage_complete' => $percentageComplete,
                'total_emis' => $totalEmis,
                'paid_emis_count' => $paidEmis,
                'pending_emis_count' => $pendingEmis,
                'overdue_emis_count' => $overdueEmis,
                'partial_emis_count' => $partialEmis,
                'next_emi_due_date' => $nextDueDate,
                'next_emi_amount' => $nextEmiAmount,
                'statement_url' => $statementUrl,
                'statement_view_url' => $statementUrl,
                'statement_download_url' => $statementUrl,
                'admin_statement_url' => $adminStatementUrl,
            ],
        ];

        if ($includeEmis) {
            $res['emis'] = $formattedEmis;
        }

        return $res;
    }

    protected function validateLoanListParams(Request $request): void
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'status' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'id' => 'nullable',
            'application_id' => 'nullable',
            'account_id' => 'nullable',
            'application_number' => 'nullable|string|max:50',
        ]);
    }

    protected function applyLoanApplicationFilters($query, Request $request): void
    {
        $status = strtolower(trim((string) $request->input('status', '')));
        if ($status !== '' && $status !== 'all') {
            if (in_array($status, ['pending', 'applied'], true)) {
                $query->whereIn('status', ['pending', 'applied']);
            } elseif (in_array($status, ['closed', 'completed', 'foreclosed'], true)) {
                $query->where(function ($q) use ($status) {
                    $q->whereIn('status', ['closed', 'completed', 'foreclosed']);
                    $q->orWhereHas('loanAccount', function ($accountQuery) use ($status) {
                        $accountQuery->where(function ($inner) use ($status) {
                            $inner->where('is_foreclosed', true);
                            if ($status === 'foreclosed') {
                                return;
                            }
                            $inner->orWhereIn('status', ['closed', 'completed', 'foreclosed'])
                                ->orWhere(function ($paid) {
                                    $paid->whereNotNull('disbursed_at')
                                        ->where('outstanding_amount', '<=', 0.05)
                                        ->where(function ($mode) {
                                            $mode->where('loan_mode', '!=', 'interest_only')
                                                ->orWhereNull('loan_mode');
                                        });
                                });
                        });
                    });
                });
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('application_number')) {
            $query->where('application_number', $request->input('application_number'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'like', "%{$search}%")
                    ->orWhere('loan_code', 'like', "%{$search}%")
                    ->orWhere('loan_amount', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('loan_name', 'like', "%{$search}%"));
            });
        }
    }

    protected function resolveRequestId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        if (is_numeric($id)) {
            return (int) $id;
        }

        $decoded = \App\Support\HashId::decode((string) $id);
        if ($decoded === null) {
            abort(response()->json([
                'success' => false,
                'message' => 'Invalid id.',
            ], 422));
        }

        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    protected function loanAccountRelations(): array
    {
        return [
            'loanApplication.product',
            'loanApplication.disbursementDetail',
            'emis.collections',
            'clientLoanDocuments.loanAccount',
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, LoanApplication>  $applications
     * @return \Illuminate\Support\Collection<string|int, LoanAccount>
     */
    protected function loanAccountsForApplications(int $clientId, $applications)
    {
        $applicationIds = $applications->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $applicationNumbers = $applications->pluck('application_number')->filter()->unique()->values();

        if ($applicationIds->isEmpty() && $applicationNumbers->isEmpty()) {
            return collect();
        }

        $accounts = LoanAccount::with($this->loanAccountRelations())
            ->where('client_id', $clientId)
            ->where(function ($q) use ($applicationIds, $applicationNumbers) {
                if ($applicationIds->isNotEmpty()) {
                    $q->whereIn('loan_application_id', $applicationIds);
                }
                if ($applicationNumbers->isNotEmpty()) {
                    $q->orWhereIn('application_number', $applicationNumbers);
                }
            })
            ->get();

        $indexed = collect();
        foreach ($accounts as $account) {
            if ($account->loan_application_id) {
                $indexed->put((int) $account->loan_application_id, $account);
            }
            if ($account->application_number) {
                $indexed->put((string) $account->application_number, $account);
            }
        }

        return $indexed;
    }

    /**
     * Same shape as the admin Loan Documents table: S.No, Document Name, Actions.
     *
     * @return array{title: string, status: string|null, status_label: string|null, documents: list<array<string, mixed>>}
     */
    protected function formatLoanDocumentsSection(?LoanAccount $account): array
    {
        if (! $account) {
            return $this->emptyLoanDocumentsSection();
        }

        $account->loadMissing(['loanApplication.product', 'product', 'clientLoanDocuments.loanAccount']);
        $service = app(LoanDocumentService::class);
        $templates = $service->getAvailableDocuments($account);
        $rows = $service->buildDocumentRows($templates, $account->clientLoanDocuments);

        $documents = $rows
            ->filter(function ($row) {
                $saved = $row->saved ?? null;

                return ! $saved instanceof ClientLoanDocument || $saved->isVisible();
            })
            ->values()
            ->map(function ($row, $index) {
                $saved = $row->saved ?? null;
                $url = $saved?->file_url;
                $name = $this->displayDocumentName(
                    (string) ($row->document_title ?? ''),
                    (string) ($row->document_type ?? '')
                );

                return [
                    's_no' => $index + 1,
                    'document_name' => $name,
                    'document_type' => $row->document_type,
                    'type' => $row->document_type,
                    'title' => $name,
                    'document_title' => $name,
                    'generated' => (bool) ($row->generated ?? false),
                    'id' => $saved?->id,
                    'file_name' => $saved?->file_name,
                    'file_path' => $saved?->file_path,
                    'file_url' => $url,
                    'url' => $url,
                    'view_url' => $url,
                    'download_url' => $url,
                    'file_size' => $saved?->file_size,
                    'formatted_file_size' => $saved?->formatted_file_size,
                    'generated_at' => optional($saved?->generated_at)?->format('Y-m-d H:i:s'),
                    'actions' => [
                        'view' => $url,
                        'download' => $url,
                    ],
                ];
            })
            ->all();

        $status = strtolower((string) ($account->status ?? ''));

        return [
            'title' => 'Loan Documents',
            'status' => $status !== '' ? $status : null,
            'status_label' => $status !== '' ? ucfirst($status) : null,
            'documents' => $documents,
        ];
    }

    protected function emptyLoanDocumentsSection(): array
    {
        return [
            'title' => 'Loan Documents',
            'status' => null,
            'status_label' => null,
            'documents' => [],
        ];
    }

    protected function displayDocumentName(string $title, string $type): string
    {
        $title = trim($title);
        $baseType = strtolower($type);
        if (str_starts_with($baseType, 'loan_agreement')) {
            $baseType = 'loan_agreement';
        }

        $labels = [
            'loan_agreement' => 'Loan Agreement',
            'loan_sanction_letter' => 'Loan Sanction Letter',
            'repayment_schedule' => 'Repayment Schedule',
            'statement' => 'Loan Statement',
            'loan_statement' => 'Loan Statement',
            'payment_receipt' => 'Payment Receipt',
            'loan_closure_certificate' => 'Loan Closure Certificate',
            'noc' => 'No Objection Certificate',
            'foreclosure_letter' => 'Foreclosure Letter',
        ];

        if (isset($labels[$baseType])) {
            return $labels[$baseType];
        }

        if ($title !== '') {
            return $title;
        }

        return ucwords(str_replace('_', ' ', $baseType));
    }
}
