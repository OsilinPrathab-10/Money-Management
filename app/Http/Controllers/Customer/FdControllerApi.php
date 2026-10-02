<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\FixedDepositApplication;
use App\Models\FixedDepositScheme;
use App\Services\FixedDeposit\FixedDepositService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class FdControllerApi extends Controller
{
    public function __construct(
        protected FixedDepositService $fdService
    ) {}

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

    public function fdDropdowns(): JsonResponse
    {
        $schemes = FixedDepositScheme::active()->orderBy('name')->get()->map(fn (FixedDepositScheme $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'scheme_code' => $s->scheme_code,
            'interest_rate' => (float) $s->interest_rate,
            'interest_frequency' => $s->interest_frequency,
            'interest_frequency_label' => $s->interest_frequency_label,
            'deposit_type' => $s->deposit_type,
            'deposit_type_label' => $s->deposit_type_label,
            'min_deposit_amount' => (float) $s->min_deposit_amount,
            'max_deposit_amount' => (float) $s->max_deposit_amount,
            'min_tenure' => (int) $s->min_tenure,
            'max_tenure' => (int) $s->max_tenure,
            'tenure_type' => $s->tenure_type,
            'default_payout_option' => $s->default_payout_option,
        ]);

        $payoutOptions = collect(FixedDepositScheme::payoutOptions())
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'FD application dropdowns fetched successfully',
            'data' => [
                'schemes' => $schemes,
                'payout_options' => $payoutOptions,
                'interest_frequencies' => collect(FixedDepositScheme::frequencies())
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                    ->values(),
            ],
        ]);
    }

    public function applyFd(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        $this->assertKycVerified($client);

        $validated = $request->validate([
            'scheme_id' => 'required|exists:fixed_deposit_schemes,id',
            'deposit_amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|min:1',
            'payout_option' => 'nullable|string|in:cumulative,monthly,quarterly,half_yearly,yearly,on_maturity',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_ifsc' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'bank_branch' => 'nullable|string|max:100',
            'nominee_name' => 'nullable|string|max:100',
            'nominee_relationship' => 'nullable|string|max:50',
            'remarks' => 'nullable|string|max:500',
        ]);

        $scheme = FixedDepositScheme::findOrFail($validated['scheme_id']);

        try {
            $applicationData = array_merge($validated, [
                'client_id' => $client->id,
                'tenure_type' => $scheme->tenure_type,
                'payout_option' => $validated['payout_option'] ?? $scheme->default_payout_option,
            ]);

            $application = $this->fdService->submitCustomerApplication($applicationData);

            event(new \App\Events\NewFdApplicationEvent($application->fresh(['client', 'scheme']), 'customer'));

            return response()->json([
                'success' => true,
                'message' => 'FD application submitted successfully. Pending admin approval.',
                'data' => $this->formatFdApplication($application->fresh('scheme')),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Customer FD application failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'FD application failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function fdApplications(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $query = FixedDepositApplication::with('scheme')->where('client_id', $client->id);

        if ($id) {
            $application = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'FD application fetched successfully',
                'data' => $this->formatFdApplication($application),
            ]);
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(fn ($a) => $this->formatFdApplication($a));

        return response()->json([
            'success' => true,
            'message' => 'FD applications fetched successfully',
            'data' => $items,
        ]);
    }

    protected function formatFdApplication(FixedDepositApplication $application): array
    {
        return [
            'id' => $application->id,
            'application_number' => $application->application_number,
            'scheme_id' => $application->scheme_id,
            'scheme_name' => optional($application->scheme)->name,
            'deposit_amount' => (float) $application->deposit_amount,
            'tenure' => (int) $application->tenure,
            'tenure_type' => $application->tenure_type,
            'interest_rate' => (float) $application->interest_rate,
            'interest_frequency' => optional($application->scheme)->interest_frequency,
            'interest_frequency_label' => optional($application->scheme)->interest_frequency_label,
            'interest_amount' => (float) $application->interest_amount,
            'maturity_amount' => (float) $application->maturity_amount,
            'maturity_date' => optional($application->maturity_date)?->format('Y-m-d'),
            'payout_option' => $application->payout_option,
            'nominee_name' => $application->nominee_name,
            'nominee_relation' => $application->nominee_relation,
            'status' => $application->status,
            'status_label' => $application->status_label,
            'applied_at' => optional($application->applied_at ?? $application->created_at)?->format('Y-m-d'),
        ];
    }

    public function applicationsWithAccounts(Request $request): JsonResponse
    {
        $client = $this->authenticatedClient();
        
        $query = FixedDepositApplication::with('scheme')
            ->where('client_id', $client->id);
            
        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        
        $items->getCollection()->transform(function ($application) {
            $account = \App\Models\FixedDeposit::where('id', $application->fixed_deposit_id)
                ->orWhere('fd_number', $application->application_number)
                ->first();
                
            return [
                'application' => $this->formatFdApplication($application),
                'account_details' => $account ? $this->formatFdAccount($account) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'FD applications with accounts fetched successfully',
            'data' => $items,
        ]);
    }

    public function fdAccounts(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $query = \App\Models\FixedDeposit::with(['scheme', 'transactions'])->where('client_id', $client->id);

        if ($id) {
            $account = $query->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'FD account fetched successfully',
                'data' => $this->formatFdAccount($account, true),
            ]);
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));
        $items->getCollection()->transform(fn ($a) => $this->formatFdAccount($a));

        return response()->json([
            'success' => true,
            'message' => 'FD accounts fetched successfully',
            'data' => $items,
        ]);
    }

    public function settlementHistory(Request $request, $id = null): JsonResponse
    {
        $client = $this->authenticatedClient();
        $fdId = $id ?? $request->input('fd_id') ?? $request->input('account_id') ?? $request->input('fixed_deposit_id');

        $query = \App\Models\FixedDeposit::with(['scheme', 'transactions', 'renewalsAsOld.newDeposit', 'closedByUser'])
            ->where('client_id', $client->id);

        if ($fdId) {
            $query->where('id', $fdId);
        } else {
            $query->where(function ($q) {
                $q->whereIn('status', ['matured', 'closed', 'premature_closed', 'renewed'])
                  ->orWhereHas('transactions', function ($tq) {
                      $tq->whereIn('transaction_type', ['closure', 'premature_closure', 'maturity', 'renewal', 'chit_adjustment']);
                  });
            });
        }

        $items = $query->latest()->paginate((int) $request->input('per_page', 15));

        $items->getCollection()->transform(function ($account) {
            $clientKyc = optional($account->client)->kycDetail;
            $grossAmount = (float) ($account->status === 'matured' ? $account->maturity_amount : $account->deposit_amount);
            $proFee = (float) ($account->processing_fee ?? 0);
            $docFee = (float) ($account->document_charges ?? 0);
            $othFee = (float) ($account->other_charges ?? 0);
            $bankFee = (float) ($account->banking_charges ?? 0);
            $totDeductions = round($proFee + $docFee + $othFee, 2);

            return [
                'account_details' => $this->formatFdAccount($account, true),
                'settlement_details' => [
                    'status' => $account->status,
                    'status_label' => $account->status_label,
                    'deposit_amount' => (float) $account->deposit_amount,
                    'maturity_amount' => (float) $account->maturity_amount,
                    'closure_amount' => $account->closure_amount ? (float) $account->closure_amount : null,
                    'closure_date' => optional($account->closure_date)?->format('Y-m-d'),
                    'closure_payment_mode' => $account->closure_payment_mode ?? $account->payout_option,
                    'closure_transaction_ref' => $account->closure_transaction_ref ?? $account->utr_reference,
                    'closure_remarks' => $account->closure_remarks,
                    'maturity_processed_at' => optional($account->maturity_processed_at)?->format('Y-m-d H:i:s'),
                    'payment_proof_url' => $account->payment_proof_url,
                    'settlement_charges' => [
                        'gross_amount' => $grossAmount,
                        'processing_fee' => $proFee,
                        'document_charges' => $docFee,
                        'banking_charges' => $bankFee,
                        'other_charges' => $othFee,
                        'total_deductions' => $totDeductions,
                        'net_payout_amount' => (float) ($account->closure_amount ?? ($grossAmount - $totDeductions)),
                    ],
                    'customer_bank_details' => [
                        'bank_name' => $account->customer_bank_name ?: ($account->bank_name ?: optional($clientKyc)->bank_name),
                        'account_number' => $account->customer_account_number ?: ($account->account_number ?: optional($clientKyc)->account_number),
                        'ifsc_code' => $account->customer_ifsc_code ?: ($account->ifsc_code ?: optional($clientKyc)->ifsc_code),
                        'branch_name' => $account->customer_branch_name ?: optional($clientKyc)->branch_name,
                        'holder_name' => $account->customer_holder_name ?: optional($clientKyc)->account_holder_name ?: optional($account->client)->client_name,
                    ],
                ],
                'settlement_transactions' => $account->transactions->map(fn ($t) => [
                    'id' => $t->id,
                    'transaction_type' => $t->transaction_type,
                    'transaction_type_label' => str_replace('_', ' ', ucfirst($t->transaction_type)),
                    'amount' => (float) $t->amount,
                    'principal_amount' => (float) $t->principal_amount,
                    'interest_amount' => (float) $t->interest_amount,
                    'penalty_amount' => (float) $t->penalty_amount,
                    'payment_mode' => $t->payment_mode,
                    'reference' => $t->reference,
                    'description' => $t->description,
                    'created_at' => optional($t->created_at)?->format('Y-m-d H:i:s'),
                ])->values()->all(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'FD settlement history fetched successfully',
            'data' => $items,
        ]);
    }

    public function toggleAutoRenewal(Request $request, $id): JsonResponse
    {
        $client = $this->authenticatedClient();
        $account = \App\Models\FixedDeposit::where('client_id', $client->id)->findOrFail($id);

        if ($account->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Auto renewal can only be toggled for active Fixed Deposits.',
            ], 422);
        }

        $validated = $request->validate([
            'auto_renewal' => 'nullable|boolean',
            'renewal_type' => 'nullable|string|in:principal_only,principal_interest',
        ]);

        $autoRenewal = isset($validated['auto_renewal'])
            ? (bool) $validated['auto_renewal']
            : ! $account->auto_renewal;

        $account->auto_renewal = $autoRenewal;

        if (! empty($validated['renewal_type'])) {
            $account->renewal_type = $validated['renewal_type'];
        }

        $account->save();

        return response()->json([
            'success' => true,
            'message' => 'Auto renewal updated successfully',
            'data' => $this->formatFdAccount($account->fresh()),
        ]);
    }

    protected function formatFdAccount(\App\Models\FixedDeposit $account, bool $includeDetails = false): array
    {
        $clientKyc = optional($account->client)->kycDetail;
        $grossAmount = (float) ($account->status === 'matured' ? $account->maturity_amount : $account->deposit_amount);
        $proFee = (float) ($account->processing_fee ?? 0);
        $docFee = (float) ($account->document_charges ?? 0);
        $othFee = (float) ($account->other_charges ?? 0);
        $bankFee = (float) ($account->banking_charges ?? 0);
        $totDeductions = round($proFee + $docFee + $othFee, 2);

        $data = [
            'id' => $account->id,
            'fd_number' => $account->fd_number,
            'scheme_id' => $account->scheme_id,
            'scheme_name' => optional($account->scheme)->name,
            'deposit_amount' => (float) $account->deposit_amount,
            'deposit_date' => optional($account->deposit_date)?->format('Y-m-d'),
            'start_date' => optional($account->start_date)?->format('Y-m-d'),
            'maturity_date' => optional($account->maturity_date)?->format('Y-m-d'),
            'tenure' => (int) $account->tenure,
            'tenure_type' => $account->tenure_type,
            'interest_rate' => (float) $account->interest_rate,
            'interest_type' => $account->interest_type,
            'interest_frequency' => $account->interest_frequency,
            'interest_amount' => (float) $account->interest_amount,
            'maturity_amount' => (float) $account->maturity_amount,
            'interest_paid_to_wallet' => (float) $account->interest_paid_to_wallet,
            'last_interest_payout_date' => optional($account->last_interest_payout_date)?->format('Y-m-d'),
            'payout_option' => $account->payout_option,
            'auto_renewal' => (bool) $account->auto_renewal,
            'renewal_type' => $account->renewal_type,
            'nominee_name' => $account->nominee_name,
            'nominee_relation' => $account->nominee_relation,
            'status' => $account->status,
            'status_label' => $account->status_label,
            'closure_date' => optional($account->closure_date)?->format('Y-m-d'),
            'closure_amount' => $account->closure_amount ? (float) $account->closure_amount : null,
            'closure_payment_mode' => $account->closure_payment_mode,
            'closure_transaction_ref' => $account->closure_transaction_ref,
            'closure_remarks' => $account->closure_remarks,
            'maturity_processed_at' => optional($account->maturity_processed_at)?->format('Y-m-d H:i:s'),
            'payment_proof_url' => $account->payment_proof_url,
            'settlement_charges' => [
                'gross_amount' => $grossAmount,
                'processing_fee' => $proFee,
                'document_charges' => $docFee,
                'banking_charges' => $bankFee,
                'other_charges' => $othFee,
                'total_deductions' => $totDeductions,
                'net_payout_amount' => (float) ($account->closure_amount ?? ($grossAmount - $totDeductions)),
            ],
            'customer_bank_details' => [
                'bank_name' => $account->customer_bank_name ?: ($account->bank_name ?: optional($clientKyc)->bank_name),
                'account_number' => $account->customer_account_number ?: ($account->account_number ?: optional($clientKyc)->account_number),
                'ifsc_code' => $account->customer_ifsc_code ?: ($account->ifsc_code ?: optional($clientKyc)->ifsc_code),
                'branch_name' => $account->customer_branch_name ?: optional($clientKyc)->branch_name,
                'holder_name' => $account->customer_holder_name ?: optional($clientKyc)->account_holder_name ?: optional($account->client)->client_name,
            ],
            'total_days' => $account->total_days,
            'completed_days' => $account->completed_days,
            'remaining_days' => $account->remaining_days,
            'tenure_progress_percentage' => $account->tenure_progress_percentage,
            'tenure_progress' => $account->tenure_progress,
            'certificate_url' => route('fd.deposits.certificate', $account->id),
        ];

        if ($includeDetails || $account->relationLoaded('transactions')) {
            $data['transactions'] = $account->transactions->map(fn ($t) => [
                'id' => $t->id,
                'transaction_type' => $t->transaction_type,
                'transaction_type_label' => str_replace('_', ' ', ucfirst($t->transaction_type)),
                'amount' => (float) $t->amount,
                'principal_amount' => (float) $t->principal_amount,
                'interest_amount' => (float) $t->interest_amount,
                'penalty_amount' => (float) $t->penalty_amount,
                'payment_mode' => $t->payment_mode,
                'reference' => $t->reference,
                'description' => $t->description,
                'created_at' => optional($t->created_at)?->format('Y-m-d H:i:s'),
            ])->values()->all();

            $data['settlement_history'] = $data['transactions'];
        }

        return $data;
    }
}
