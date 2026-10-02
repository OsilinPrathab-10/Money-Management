<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\FixedDepositApplication;
use App\Models\FixedDepositScheme;
use App\Services\FixedDeposit\FixedDepositService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FdApplicationsController extends Controller
{
    public function __construct(
        protected FixedDepositService $fdService
    ) {}

    public function index()
    {
        $baseQuery = $this->scopedQuery();

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

        $verifiedClientsQuery = Client::whereHas('kycDetail', function ($q) {
            $q->where('status', 'verified');
        });

        if ($this->isAgentUser()) {
            $agentId = $this->currentAgentId();
            if ($agentId) {
                $verifiedClientsQuery->where(function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                        ->orWhere('assigned_to', $agentId);
                });
            }
        }

        $verifiedClients = $verifiedClientsQuery->orderBy('client_name')->get();
        $fdSchemes = FixedDepositScheme::active()->orderBy('name')->get();
        $payoutOptions = FixedDepositScheme::payoutOptions();

        return view('admin.fd.applications.index', compact(
            'stats',
            'verifiedClients',
            'fdSchemes',
            'payoutOptions'
        ));
    }

    public function show(FixedDepositApplication $application)
    {
        $this->authorizeAgentAccess($application);

        $application->load(['client.location', 'scheme', 'fixedDeposit', 'creator']);
        $payoutOptions = FixedDepositScheme::payoutOptions();

        return view('admin.fd.applications.show', compact('application', 'payoutOptions'));
    }

    public function data(Request $request): JsonResponse
    {
        $columns = [
            0 => 'id',
            1 => 'application_number',
            2 => 'id',
            3 => 'id',
            4 => 'id',
            5 => 'id',
            6 => 'deposit_amount',
            7 => 'status',
            8 => 'applied_at',
        ];

        $query = $this->scopedQuery()->with(['client.location', 'scheme']);

        $totalData = (clone $query)->count();

        $limit = (int) $request->input('length', 10);
        $start = (int) $request->input('start', 0);
        $orderIndex = (int) $request->input('order.0.column', 1);
        $order = $columns[$orderIndex] ?? 'id';
        $dir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($order, ['id', 'application_number', 'deposit_amount', 'status', 'applied_at'], true)) {
            $order = 'id';
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if (! empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('application_number', 'LIKE', "%{$search}%")
                    ->orWhere('deposit_amount', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%")
                    ->orWhereHas('client', function ($c) use ($search) {
                        $c->where('client_name', 'LIKE', "%{$search}%")
                            ->orWhere('client_phone', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('scheme', function ($s) use ($search) {
                        $s->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('scheme_code', 'LIKE', "%{$search}%");
                    });
            });
        }

        $totalFiltered = $query->count();

        $applications = $query->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir)
            ->get()
            ->map(function (FixedDepositApplication $application) {
                return [
                    'id' => $application->id,
                    'application_number' => $application->application_number,
                    'client_name' => optional($application->client)->client_name ?? 'N/A',
                    'client_phone' => optional($application->client)->client_phone ?? 'N/A',
                    'zone' => optional(optional($application->client)->location)->name ?? 'N/A',
                    'scheme_name' => optional($application->scheme)->name ?? 'N/A',
                    'deposit_amount' => '₹' . number_format((float) $application->deposit_amount, 0),
                    'applied_at' => optional($application->applied_at ?? $application->created_at)?->format('d-m-Y'),
                    'status' => $application->status,
                    'status_label' => $application->status_label,
                    'status_color' => $application->status_color,
                    'fixed_deposit_id' => $application->fixed_deposit_id,
                ];
            });

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalData),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $applications,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        foreach (['deposit_date', 'start_date', 'applied_at'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => $this->parseDate($request->input($field))->toDateString()]);
            }
        }

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
            'payout_option' => ['nullable', Rule::in(array_keys(FixedDepositScheme::payoutOptions()))],
            'remarks' => 'nullable|string|max:1000',
        ]);

        $client = Client::with('kycDetail')->findOrFail($validated['client_id']);

        if (optional($client->kycDetail)->status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'KYC is not verified for this client. Complete KYC before applying for an FD.',
            ], 422);
        }

        if ($this->isAgentUser()) {
            $agentId = $this->currentAgentId();
            if (! $agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only apply for FDs for clients you have added or are assigned to you.',
                ], 403);
            }
        }

        $scheme = FixedDepositScheme::active()->find($validated['scheme_id']);
        if (! $scheme) {
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
            $depositDate = $this->parseDate($validated['deposit_date']);
            $startDate = $this->parseDate($validated['start_date']);
            $appliedAt = ! empty($validated['applied_at'])
                ? $this->parseDate($validated['applied_at'])
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
                'created_by' => Auth::id(),
            ]);

            event(new \App\Events\NewFdApplicationEvent(
                $application->loadMissing(['client', 'scheme']),
                $this->isAgentUser() ? 'agent' : 'admin'
            ));

            return response()->json([
                'success' => true,
                'message' => 'FD application submitted successfully.',
                'redirect' => route('fd.applications.show', $application),
            ]);
        } catch (Throwable $e) {
            Log::error('FD application store failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, FixedDepositApplication $application): JsonResponse
    {
        $this->authorizeAgentAccess($application);

        if (! $this->isApproverUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators and staff can approve FD applications.',
            ], 403);
        }

        if ($application->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending applications can be approved.',
            ], 422);
        }

        $application->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        event(new \App\Events\FdApplicationApproved($application->fresh(['client', 'scheme'])));

        return response()->json([
            'success' => true,
            'message' => 'FD application approved successfully.',
        ]);
    }

    public function reject(Request $request, FixedDepositApplication $application): JsonResponse
    {
        $this->authorizeAgentAccess($application);

        if (! $this->isApproverUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators and staff can reject FD applications.',
            ], 403);
        }

        if (! in_array($application->status, ['pending', 'approved'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This application cannot be rejected.',
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $application->update([
            'status' => 'rejected',
            'remarks' => $request->input('reason') ?: $application->remarks,
            'rejected_by' => Auth::id(),
        ]);

        event(new \App\Events\FdApplicationRejected(
            $application->fresh(['client']),
            $request->input('reason')
        ));

        return response()->json([
            'success' => true,
            'message' => 'FD application rejected.',
        ]);
    }

    public function book(Request $request, FixedDepositApplication $application): JsonResponse
    {
        $this->authorizeAgentAccess($application);

        if (! $this->isApproverUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Only administrators and staff can book an FD.',
            ], 403);
        }

        if ($application->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved applications can be booked.',
            ], 422);
        }

        if ($application->fixed_deposit_id) {
            return response()->json([
                'success' => false,
                'message' => 'This application is already booked.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_mode' => ['nullable', Rule::in(['cash', 'upi', 'bank_transfer'])],
            'internal_bank_account_id' => ['nullable', 'exists:bank_accounts,id'],
        ]);

        try {
            $fd = DB::transaction(function () use ($application, $validated) {
                $application = FixedDepositApplication::where('id', $application->id)->lockForUpdate()->first();

                if ($application->status !== 'approved' || $application->fixed_deposit_id) {
                    throw ValidationException::withMessages([
                        'status' => 'This application is no longer available to book.',
                    ]);
                }

                $fd = $this->fdService->create([
                    'client_id' => $application->client_id,
                    'scheme_id' => $application->scheme_id,
                    'deposit_amount' => (float) $application->deposit_amount,
                    'tenure' => (int) $application->tenure,
                    'deposit_date' => optional($application->deposit_date)->toDateString(),
                    'start_date' => optional($application->start_date)->toDateString(),
                    'nominee_name' => $application->nominee_name,
                    'nominee_relation' => $application->nominee_relation,
                    'payout_option' => $application->payout_option,
                    'remarks' => $application->remarks,
                    'payment_mode' => $validated['payment_mode'] ?? 'cash',
                    'internal_bank_account_id' => $validated['internal_bank_account_id'] ?? null,
                ]);

                $application->update([
                    'status' => 'booked',
                    'booked_at' => now(),
                    'booked_by' => Auth::id(),
                    'fixed_deposit_id' => $fd->id,
                ]);

                return $fd;
            });

            event(new \App\Events\FdApplicationBooked($application->fresh(['client', 'scheme'])));

            return response()->json([
                'success' => true,
                'message' => 'Fixed Deposit booked successfully.',
                'redirect' => route('fd.deposits.show', $fd),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('FD application book failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    protected function scopedQuery()
    {
        $query = FixedDepositApplication::query();

        if ($this->isAgentUser()) {
            $agentId = $this->currentAgentId();
            if ($agentId) {
                $query->whereHas('client', function ($q) use ($agentId) {
                    $q->where('added_by', $agentId)
                        ->orWhere('assigned_to', $agentId);
                });
            }
        }

        return $query;
    }

    protected function authorizeAgentAccess(FixedDepositApplication $application): void
    {
        if (! $this->isAgentUser()) {
            return;
        }

        $agentId = $this->currentAgentId();
        $client = $application->client;
        if (! $agentId || ! $client || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
            abort(403, 'You do not have permission to view this application.');
        }
    }

    protected function isAgentUser(): bool
    {
        $user = Auth::user();

        return $user && $user->hasRole('Agent') && ! $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    }

    protected function isApproverUser(): bool
    {
        $user = Auth::user();

        return $user && $user->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    }

    protected function currentAgentId(): ?int
    {
        $user = Auth::user();

        return $user ? optional($user->agent)->id : null;
    }

    protected function parseDate(string $value): Carbon
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::parse($value)->startOfDay();
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (Throwable $e) {
                continue;
            }
        }

        return Carbon::parse($value)->startOfDay();
    }
}
