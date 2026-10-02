<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\FixedDepositScheme;
use App\Models\FixedDepositApplication;
use App\Models\FixedDeposit;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FixedDeposit\FixedDepositService;

class FDManagementControllerApi extends Controller
{
    public function __construct(protected FixedDepositService $fdService) {}

    /**
     * Get active FD schemes (without pagination)
     */
    public function schemes(): JsonResponse
    {
        $schemes = FixedDepositScheme::active()->orderBy('name')->get();
        $payoutOptions = FixedDepositScheme::payoutOptions();
        
        // Include payout options as array of objects for easier client parsing
        $payoutOptionsList = [];
        foreach ($payoutOptions as $key => $label) {
            $payoutOptionsList[] = ['id' => $key, 'name' => $label];
        }

        $agentId = auth()->user()->id;
        $verifiedClients = Client::whereHas('kycDetail', function($q) {
            $q->where('status', 'verified');
        })->where(function($q) use ($agentId) {
            $q->where('added_by', $agentId)
              ->orWhere('assigned_to', $agentId);
        })->get(['id', 'client_name', 'client_phone', 'client_email']);

        return response()->json([
            'success' => true,
            'data' => [
                'schemes' => $schemes,
                'payout_options' => $payoutOptionsList,
                'verified_clients' => $verifiedClients
            ]
        ]);
    }

    /**
     * Check client eligibility for FD application (for agents)
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $request->validate(['client_id' => 'required|exists:clients,id']);

        $clientId = $request->input('client_id');
        $agentId = auth()->user()->id;

        $client = Client::with('kycDetail')->find($clientId);
        if (! $client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission for this client.',
            ], 403);
        }

        $kycStatus = optional($client->kycDetail)->status;
        $kycApproved = in_array($kycStatus, ['verified', 'approved'], true);

        $pendingApplication = FixedDepositApplication::where('client_id', $clientId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        $eligible = true;
        $message = 'Client is eligible to apply for a Fixed Deposit.';

        if (! $kycApproved) {
            $eligible = false;
            $message = 'Customer KYC is not approved yet. Only KYC verified clients can apply for Fixed Deposits.';
        } elseif ($pendingApplication) {
            $eligible = false;
            $message = 'Client already has a pending or approved FD application. Resolve it before applying again.';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $eligible,
                'message' => $message,
                'kyc_status' => $kycStatus ?: 'pending',
                'kyc_verified' => $kycApproved,
                'has_pending_application' => (bool) $pendingApplication,
                'pending_application' => $pendingApplication ? [
                    'id' => $pendingApplication->id,
                    'application_number' => $pendingApplication->application_number,
                    'scheme_id' => $pendingApplication->scheme_id,
                    'scheme_name' => optional($pendingApplication->scheme)->name,
                    'deposit_amount' => (float) $pendingApplication->deposit_amount,
                    'status' => $pendingApplication->status,
                    'status_label' => $pendingApplication->status_label,
                    'applied_at' => optional($pendingApplication->applied_at ?? $pendingApplication->created_at)?->format('Y-m-d'),
                ] : null,
            ],
        ]);
    }

    /**
     * Preview / calculate FD maturity and interest for an agent
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scheme_id' => 'required|exists:fixed_deposit_schemes,id',
            'deposit_amount' => 'nullable|numeric|min:1',
            'amount' => 'nullable|numeric|min:1',
            'tenure' => 'required|integer|min:1',
            'start_date' => 'nullable|date',
            'deposit_date' => 'nullable|date',
        ]);

        $scheme = FixedDepositScheme::find($validated['scheme_id']);
        if (! $scheme || ! $scheme->status) {
            return response()->json([
                'success' => false,
                'message' => 'Selected Fixed Deposit scheme is not active.',
            ], 422);
        }

        $amount = (float) ($validated['deposit_amount'] ?? $validated['amount'] ?? 0);
        $tenure = (int) $validated['tenure'];
        $startDate = $validated['start_date'] ?? $validated['deposit_date'] ?? now()->toDateString();

        if ($amount < (float) $scheme->min_deposit_amount || $amount > (float) $scheme->max_deposit_amount) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Deposit amount must be between ₹%s and ₹%s.',
                    number_format((float) $scheme->min_deposit_amount, 0),
                    number_format((float) $scheme->max_deposit_amount, 0)
                ),
            ], 422);
        }

        if ($tenure < $scheme->min_tenure || $tenure > $scheme->max_tenure) {
            return response()->json([
                'success' => false,
                'message' => "Tenure must be between {$scheme->min_tenure} and {$scheme->max_tenure} {$scheme->tenure_type}.",
            ], 422);
        }

        $calc = $this->fdService->previewCalculation(
            $scheme,
            $amount,
            $tenure,
            $startDate
        );

        return response()->json([
            'status' => true,
            'success' => true,
            'message' => 'FD calculation generated successfully',
            'data' => [
                'scheme_id' => $scheme->id,
                'scheme_name' => $scheme->name,
                'scheme_code' => $scheme->scheme_code,
                'deposit_amount' => $amount,
                'tenure' => $tenure,
                'tenure_type' => $scheme->tenure_type,
                'tenure_months' => $calc['tenure_months'] ?? ($scheme->tenure_type === 'years' ? $tenure * 12 : $tenure),
                'interest_rate' => (float) $scheme->interest_rate,
                'interest_type' => $scheme->deposit_type,
                'interest_type_label' => $scheme->deposit_type_label,
                'interest_frequency' => $scheme->interest_frequency,
                'interest_frequency_label' => $scheme->interest_frequency_label,
                'interest_amount' => (float) $calc['interest_amount'],
                'maturity_amount' => (float) $calc['maturity_amount'],
                'start_date' => Carbon::parse($startDate)->format('Y-m-d'),
                'maturity_date' => $calc['maturity_date'] instanceof Carbon ? $calc['maturity_date']->format('Y-m-d') : (string) $calc['maturity_date'],
                'maturity_date_formatted' => $calc['maturity_date'] instanceof Carbon ? $calc['maturity_date']->format('d M Y') : (string) $calc['maturity_date'],
                'default_payout_option' => $scheme->default_payout_option,
                'auto_renewal' => (bool) $scheme->auto_renewal,
                'renewal_type' => $scheme->renewal_type,
                'premature_allowed' => (bool) $scheme->premature_withdrawal_allowed,
                'limits' => [
                    'min_deposit' => (float) $scheme->min_deposit_amount,
                    'max_deposit' => (float) $scheme->max_deposit_amount,
                    'min_tenure' => (int) $scheme->min_tenure,
                    'max_tenure' => (int) $scheme->max_tenure,
                    'tenure_type' => $scheme->tenure_type,
                ],
            ],
        ]);
    }

    /**
     * Apply for a new Fixed Deposit
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'scheme_id' => 'required|exists:fixed_deposit_schemes,id',
            'deposit_amount' => 'required|numeric|min:1',
            'tenure' => 'required|integer|min:1',
            'deposit_date' => 'required|date',
            'start_date' => 'required|date',
            'applied_at' => 'nullable|date',
            'nominee_name' => 'nullable|string|max:255',
            'nominee_relation' => 'nullable|string|max:100',
            'payout_option' => ['nullable', 'string'],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $client = Client::with('kycDetail')->findOrFail($validated['client_id']);

        if (optional($client->kycDetail)->status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'KYC is not verified for this client. Complete KYC before applying for an FD.',
            ], 422);
        }

        $currentUser = auth()->user();
        $agentId = $currentUser instanceof \App\Models\Agent ? $currentUser->id : optional(optional($currentUser)->agent)->id;
        $agentUserId = $currentUser instanceof \App\Models\Agent ? $currentUser->user_id : optional($currentUser)->id;

        if (!$agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only apply for FDs for clients you have added or are assigned to you.',
            ], 403);
        }

        $scheme = FixedDepositScheme::active()->find($validated['scheme_id']);
        if (!$scheme) {
            return response()->json([
                'success' => false,
                'message' => 'Selected FD scheme is not active.',
            ], 422);
        }

        $amount = round((float) $validated['deposit_amount'], 2);
        if ($amount < (float) $scheme->min_deposit_amount || $amount > (float) $scheme->max_deposit_amount) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    'Deposit amount must be between ₹%s and ₹%s.',
                    number_format((float) $scheme->min_deposit_amount, 0),
                    number_format((float) $scheme->max_deposit_amount, 0)
                ),
            ], 422);
        }

        $tenure = (int) $validated['tenure'];
        if ($tenure < $scheme->min_tenure || $tenure > $scheme->max_tenure) {
            return response()->json([
                'success' => false,
                'message' => "Tenure must be between {$scheme->min_tenure} and {$scheme->max_tenure} {$scheme->tenure_type}.",
            ], 422);
        }

        $existing = FixedDepositApplication::where('client_id', $client->id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This client already has a pending or approved FD application. Resolve it before applying again.',
            ], 422);
        }

        try {
            $depositDate = Carbon::parse($validated['deposit_date'])->startOfDay();
            $startDate = Carbon::parse($validated['start_date'])->startOfDay();
            $appliedAt = !empty($validated['applied_at'])
                ? Carbon::parse($validated['applied_at'])->startOfDay()
                : now();

            $calc = $this->fdService->previewCalculation(
                $scheme,
                $amount,
                $tenure,
                $startDate->toDateString()
            );

            $application = FixedDepositApplication::create([
                'client_id' => $client->id,
                'scheme_id' => $scheme->id,
                'deposit_amount' => $amount,
                'tenure' => $tenure,
                'tenure_type' => $scheme->tenure_type,
                'interest_rate' => $scheme->interest_rate,
                'interest_amount' => $calc['interest_amount'],
                'maturity_amount' => $calc['maturity_amount'],
                'deposit_date' => $depositDate->toDateString(),
                'start_date' => $startDate->toDateString(),
                'maturity_date' => $calc['maturity_date']->toDateString(),
                'nominee_name' => $validated['nominee_name'] ?? null,
                'nominee_relation' => $validated['nominee_relation'] ?? null,
                'payout_option' => $validated['payout_option'] ?? $scheme->default_payout_option,
                'status' => 'pending',
                'remarks' => $validated['remarks'] ?? null,
                'applied_at' => $appliedAt,
                'created_by' => $agentUserId, // agent's user id
            ]);

            event(new \App\Events\NewFdApplicationEvent(
                $application->loadMissing(['client', 'scheme']),
                'agent'
            ));

            return response()->json([
                'success' => true,
                'message' => 'FD application submitted successfully.',
                'data' => $application
            ]);
        } catch (\Throwable $e) {
            Log::error('Agent FD application store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List FD Applications
     */
    public function applications(Request $request): JsonResponse
    {
        $agentId = auth()->user()->id;

        $query = FixedDepositApplication::with(['client.location', 'scheme'])
            ->whereHas('client', function($q) use ($agentId) {
                $q->where('added_by', $agentId)
                  ->orWhere('assigned_to', $agentId);
            });

        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if (!empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'LIKE', "%{$search}%")
                  ->orWhere('deposit_amount', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($c) use ($search) {
                      $c->where('client_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('scheme', function($s) use ($search) {
                      $s->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $baseQuery = FixedDepositApplication::whereHas('client', function($q) use ($agentId) {
            $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
        });

        $statusCounts = (clone $baseQuery)->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending' => $statusCounts['pending'] ?? 0,
            'approved' => $statusCounts['approved'] ?? 0,
            'booked' => $statusCounts['booked'] ?? 0,
            'rejected' => $statusCounts['rejected'] ?? 0,
        ];

        $applications = $query->latest()->paginate($request->input('per_page', 15));
        
        $applications->getCollection()->transform(function ($app) {
            return [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'client_name' => optional($app->client)->client_name ?? 'N/A',
                'zone' => optional(optional($app->client)->location)->name ?? 'N/A',
                'scheme_name' => optional($app->scheme)->name ?? 'N/A',
                'deposit_amount' => $app->deposit_amount,
                'tenure_formatted' => $app->tenure . ' ' . $app->tenure_type,
                'applied_at' => optional($app->applied_at ?? $app->created_at)->format('Y-m-d'),
                'status' => $app->status,
                'status_label' => $app->status_label,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $applications,
            'stats' => $stats
        ]);
    }

    /**
     * Show a specific FD Application Info
     */
    public function showApplication($id): JsonResponse
    {
        $agentId = auth()->user()->id;

        $application = FixedDepositApplication::with(['client.location', 'scheme', 'fixedDeposit'])
            ->find($id);

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'FD application not found.'], 404);
        }

        $client = $application->client;
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to view this application.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $application
        ]);
    }

    /**
     * List FD Accounts (Booked Deposits)
     */
    public function fixedDeposits(Request $request): JsonResponse
    {
        $agentId = auth()->user()->id;

        $query = FixedDeposit::with(['client.location', 'scheme'])
            ->whereHas('client', function($q) use ($agentId) {
                $q->where('assigned_to', $agentId);
            });

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('fd_number', 'LIKE', "%{$search}%")
                  ->orWhere('deposit_amount', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($c) use ($search) {
                      $c->where('client_name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('scheme', function($s) use ($search) {
                      $s->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $baseQuery = FixedDeposit::whereHas('client', function($q) use ($agentId) {
            $q->where('added_by', $agentId)->orWhere('assigned_to', $agentId);
        });

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'matured' => (clone $baseQuery)->where('status', 'matured')->count(),
            'closed' => (clone $baseQuery)->where('status', 'closed')->count(),
        ];

        $deposits = $query->latest()->paginate($request->input('per_page', 15));
        
        $deposits->getCollection()->transform(function ($fd) {
            return [
                'id' => $fd->id,
                'fd_number' => $fd->fd_number,
                'client_name' => optional($fd->client)->client_name ?? 'N/A',
                'zone' => optional(optional($fd->client)->location)->name ?? 'N/A',
                'scheme_name' => optional($fd->scheme)->name ?? 'N/A',
                'deposit_amount' => $fd->deposit_amount,
                'maturity_amount' => $fd->maturity_amount,
                'tenure_formatted' => $fd->tenure . ' ' . $fd->tenure_type,
                'deposit_date' => optional($fd->deposit_date)->format('Y-m-d'),
                'maturity_date' => optional($fd->maturity_date)->format('Y-m-d'),
                'auto_renewal' => (bool) $fd->auto_renewal,
                'renewal_type' => $fd->renewal_type,
                'total_days' => $fd->total_days,
                'completed_days' => $fd->completed_days,
                'remaining_days' => $fd->remaining_days,
                'tenure_progress_percentage' => $fd->tenure_progress_percentage,
                'tenure_progress' => $fd->tenure_progress,
                'status' => $fd->status,
                'status_label' => $fd->status_label,
                'certificate_url' => route('fd.deposits.certificate', $fd->id),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $deposits,
            'stats' => $stats
        ]);
    }

    /**
     * Show specific FD Account Info
     */
    public function showFixedDeposit($id): JsonResponse
    {
        $agentId = auth()->user()->id;

        $fd = FixedDeposit::with(['client.location', 'scheme', 'application', 'interestPayouts'])
            ->find($id);

        if (!$fd) {
            return response()->json(['success' => false, 'message' => 'Fixed Deposit not found.'], 404);
        }

        $client = $fd->client;
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to view this fixed deposit.'], 403);
        }

        $fd->setAttribute('certificate_url', route('fd.deposits.certificate', $fd->id));

        return response()->json([
            'success' => true,
            'data' => $fd
        ]);
    }

    /**
     * Toggle / Update Auto Renewal for a Fixed Deposit
     */
    public function toggleAutoRenewal(Request $request, $id): JsonResponse
    {
        $agentId = auth()->user()->id;

        $fd = FixedDeposit::with(['client'])->find($id);

        if (!$fd) {
            return response()->json(['success' => false, 'message' => 'Fixed Deposit not found.'], 404);
        }

        $client = $fd->client;
        if (!$client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            return response()->json(['success' => false, 'message' => 'You do not have permission to modify this fixed deposit.'], 403);
        }

        if ($fd->status !== 'active') {
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
            : !$fd->auto_renewal;

        $fd->auto_renewal = $autoRenewal;

        if (!empty($validated['renewal_type'])) {
            $fd->renewal_type = $validated['renewal_type'];
        }

        $fd->save();

        return response()->json([
            'success' => true,
            'message' => 'Auto renewal updated successfully.',
            'data' => $fd->fresh(['client.location', 'scheme', 'application', 'interestPayouts'])
        ]);
    }
}
