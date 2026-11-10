<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\EmployeeInformation;
use App\Models\LoanAccount;
use App\Models\Emi;
use App\Models\EmiCollection;
use App\Support\ClientLedgerEntries;
use App\Support\HashId;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class ClientViewAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:client.update')->only(['update', 'updateEmployment']);
        // Blacklist / unblacklist are status updates — allow update or delete permission.
        $this->middleware('permission:client.update|client.delete')->only(['blacklist', 'unblacklist']);
    }

  public function index($id)
  {
    $client = Client::with(['kycDetail', 'user', 'guarantors', 'employeeInformation', 'nominee'])
      ->withCount([
        'loanApplications as applications_count',
        'loanAccounts as loans_count' => function ($q) {
          $q->where('status', '!=', 'closed');
        },
      ])
      ->findOrFail($id);

    // Agent guard: agents can only view clients they added
    if (auth()->user()->hasRole('Agent')) {
      $agentId = optional(auth()->user()->agent)->id;
      if (!$agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
        abort(403, 'You do not have permission to view this client.');
      }
    }

    $clientStatus = $client->status ?? 'unverified';
    $clientStatusMap = [
      'active' => [
        'label' => 'Active',
        'badge' => 'success',
        'icon' => 'ri-checkbox-circle-line'
      ],
      'inactive' => [
        'label' => 'Inactive',
        'badge' => 'danger',
        'icon' => 'ri-close-circle-line'
      ],
      'blacklist' => [
        'label' => 'Blacklist',
        'badge' => 'dark',
        'icon' => 'ri-error-warning-line'
      ],
      'unverified' => [
        'label' => 'Unverified',
        'badge' => 'warning',
        'icon' => 'ri-time-line'
      ],
      'pending' => [
        'label' => 'Pending',
        'badge' => 'warning',
        'icon' => 'ri-time-line'
      ],
    ];

    $statusDisplay = $clientStatusMap[$clientStatus] ?? [
      'label' => ucfirst($clientStatus),
      'badge' => 'secondary',
      'icon' => 'ri-question-mark'
    ];

    $stats = $this->clientSidebarStats($client, $statusDisplay);

    $locations = \App\Models\Location::orderBy('name')->get();
    
    // Add data for Quick Loan & FD Modal
    $loanProducts = \App\Models\LoanProduct::where('status', 'active')->get();
    $fdSchemes = \App\Models\FixedDepositScheme::active()->orderBy('name')->get();
    $payoutOptions = \App\Models\FixedDepositScheme::payoutOptions();
    $activePaymentMethods = \App\Models\PaymentMethod::where('is_enabled', true)->get();
    $activeGateways = \App\Models\PaymentGateway::where('enabled', true)->get();

    $agents = \App\Models\Agent::orderBy('agent_name')->get();
    $verifiedClients = Client::whereHas('kycDetail', function ($q) {
        $q->where('status', 'verified');
    })->orderBy('client_name')->get();

    return view('admin.clients.client-view-account', [
      'client' => $client,
      'stats' => $stats,
      'locations' => $locations,
      'loanProducts' => $loanProducts,
      'fdSchemes' => $fdSchemes,
      'payoutOptions' => $payoutOptions,
      'activePaymentMethods' => $activePaymentMethods,
      'activeGateways' => $activeGateways,
      'agents' => $agents,
      'verifiedClients' => $verifiedClients,
    ]);
  }

  public function update(Request $request, $id): JsonResponse
  {
    $client = Client::with(['kycDetail'])->findOrFail($id);

    if ($request->exists('alternate_phone')) {
      $request->merge(['alternate_phone' => Client::normalizeOptionalPhone($request->input('alternate_phone'))]);
    }

    $validated = $request->validate([
      'client_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
      'nickname' => 'nullable|string|max:255',
      'client_email' => 'nullable|email|max:255',
      'client_phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
      'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
      'date_of_birth' => 'nullable|date',
      'gender' => 'nullable|string|in:male,female,other',
      'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
      'status' => 'nullable|in:active,inactive,pending,rejected,blacklist',
      'address' => 'nullable|string',
      'city' => 'nullable|string|max:255',
      'state' => 'nullable|string|max:255',
      'pincode' => 'nullable|string|max:10',
      'location_id' => 'required|exists:locations,id',
      'collection_day' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
    ]);

    if (array_key_exists('alternate_phone', $validated)) {
      $validated['alternate_phone'] = Client::normalizeOptionalPhone($validated['alternate_phone'] ?? null);
    }

    if (!empty($validated['date_of_birth'])) {
      $validated['date_of_birth'] = \Carbon\Carbon::parse($validated['date_of_birth'])->format('Y-m-d');
    }

    // Editing a client profile must never activate the client.
    // Client can become "active" only after admin verifies KYC.
    $requestedStatus = $validated['status'] ?? $client->status;
    $resolvedStatus = $client->resolveAllowedStatus($requestedStatus);
    $validated['status'] = $resolvedStatus;

    $client->update($validated);

    if ($client->user) {
      $userSync = [];
      if (isset($validated['client_name'])) $userSync['name'] = $validated['client_name'];
      if (array_key_exists('nickname', $validated)) $userSync['nickname'] = $validated['nickname'];
      if (isset($validated['client_email'])) $userSync['email'] = $validated['client_email'];
      if (isset($validated['client_phone'])) $userSync['phone'] = $validated['client_phone'];
      if (!empty($userSync)) {
        $client->user->update($userSync);
      }
    }

    $statusForced = in_array(strtolower((string) $requestedStatus), ['active', 'verified'], true)
      && $resolvedStatus !== 'active';

    return response()->json([
      'success' => true,
      'message' => $statusForced
        ? 'Client profile updated. Status kept as Pending until KYC is verified.'
        : 'Client profile updated successfully.',
      'status' => $resolvedStatus,
      'kyc_status' => $client->kycStatus(),
    ]);
  }

  public function updateEmployment(Request $request, $id): JsonResponse
  {
    $client = Client::findOrFail($id);

    $validated = $request->validate([
      'employment_type' => 'nullable|in:salaried,business,self_employed',
      'company_name' => 'nullable|string|max:255',
      'monthly_salary' => 'nullable|numeric',
      'payslip' => 'nullable|file|max:5120',
      'business_name' => 'nullable|string|max:255',
      'monthly_income' => 'nullable|numeric',
      'business_document' => 'nullable|file|max:5120',
    ]);

    $rawEmpType = $validated['employment_type'] ?? null;
    $empType = ($rawEmpType === 'business' || $rawEmpType === 'self_employed') ? 'self_employed' : 'salaried';

    $empData = [
      'client_id' => $client->id,
      'employment_type' => $empType,
    ];

    if ($rawEmpType === 'salaried' || $empType === 'salaried') {
      $empData['company_name'] = $request->input('company_name');
      $empData['monthly_salary'] = $request->input('monthly_salary');
      if ($request->hasFile('payslip')) {
        $empData['payslip_documents'] = [$request->file('payslip')->store('kyc/payslip/' . $client->id, 'public')];
      }
    } else {
      $empData['business_name'] = $request->input('business_name');
      $empData['monthly_turnover'] = $request->input('monthly_income');
      if ($request->hasFile('business_document')) {
        $empData['business_proof_documents'] = [$request->file('business_document')->store('kyc/business_proof/' . $client->id, 'public')];
      }
    }

    $emp = EmployeeInformation::updateOrCreate(
      ['client_id' => $client->id],
      $empData
    );

    return response()->json([
      'success' => true,
      'message' => 'Employment & Documents updated successfully.',
      'data' => $emp
    ]);
  }

  public function blacklist(Request $request, $id): JsonResponse
  {
    $validated = $request->validate([
      'reason' => 'required|string|max:500'
    ]);

    $client = Client::findOrFail($id);

    // Update client status to blacklist and save reason to remarks
    $client->status = 'blacklist';
    $client->remarks = $validated['reason'];
    $client->save();

    return response()->json([
      'success' => true,
      'message' => 'Client has been blacklisted successfully.'
    ]);
  }

  public function unblacklist(Request $request, $id): JsonResponse
  {
    $validated = $request->validate([
      'reason' => 'nullable|string|max:500',
    ]);

    $client = Client::findOrFail($id);

    if ($client->status !== 'blacklist') {
      return response()->json([
        'success' => false,
        'message' => 'This client is not blacklisted.',
      ], 422);
    }

    $note = trim((string) ($validated['reason'] ?? ''));
    // Removing blacklist restores the KYC-based status (active only when KYC verified).
    $client->status = $client->resolveAllowedStatus('active');
    if ($note !== '') {
      $client->remarks = trim(($client->remarks ? $client->remarks . ' | ' : '') . 'Unblacklisted: ' . $note);
    } else {
      $client->remarks = trim(($client->remarks ? $client->remarks . ' | ' : '') . 'Unblacklisted on ' . now()->format('d M Y'));
    }
    $client->save();

    return response()->json([
      'success' => true,
      'message' => $client->status === 'active'
        ? 'Client has been removed from blacklist and set to Active.'
        : 'Client removed from blacklist. Status is Pending until KYC is verified.',
      'status' => $client->status,
      'kyc_status' => $client->kycStatus(),
    ]);
  }

  public function ledger($id)
  {
    $decodedId = HashId::decode($id);
    $realId = is_array($decodedId) ? ($decodedId[0] ?? $id) : ($decodedId ?? $id);

    $client = Client::with(['location', 'kycDetail', 'agent'])
      ->withCount([
        'loanApplications as applications_count',
        'loanAccounts as loans_count' => function ($q) {
          $q->where('status', '!=', 'closed');
        },
      ])
      ->findOrFail($realId);

    // Agent guard: agents can only view clients they added
    if (auth()->user()->hasRole('Agent')) {
      $agentId = optional(auth()->user()->agent)->id;
      if (!$agentId || ($client->added_by !== $agentId && $client->assigned_to !== $agentId)) {
        abort(403, 'You do not have permission to view this client.');
      }
    }

    $clientStatus = $client->status ?? 'unverified';
    $clientStatusMap = [
      'active' => [
        'label' => 'Active',
        'badge' => 'success',
        'icon' => 'ri-checkbox-circle-line'
      ],
      'inactive' => [
        'label' => 'Inactive',
        'badge' => 'danger',
        'icon' => 'ri-close-circle-line'
      ],
      'blacklist' => [
        'label' => 'Blacklist',
        'badge' => 'dark',
        'icon' => 'ri-error-warning-line'
      ],
      'unverified' => [
        'label' => 'Unverified',
        'badge' => 'warning',
        'icon' => 'ri-time-line'
      ],
      'pending' => [
        'label' => 'Pending',
        'badge' => 'warning',
        'icon' => 'ri-time-line'
      ],
    ];

    $statusDisplay = $clientStatusMap[$clientStatus] ?? [
      'label' => ucfirst($clientStatus),
      'badge' => 'secondary',
      'icon' => 'ri-question-mark'
    ];

    $stats = $this->clientSidebarStats($client, $statusDisplay);

    // 1. Fetch Loan Accounts & compute summaries
    $loanAccounts = LoanAccount::with(['emis.collections', 'loanApplication'])
        ->where('client_id', $realId)
        ->get();

    $loanStats = [
        'total_loans' => $loanAccounts->where('status', '!=', 'closed')->count(),
        'active_loans' => $loanAccounts->where('status', 'active')->count(),
        'closed_loans' => $loanAccounts->where('status', 'closed')->count(),
        'total_amount' => $loanAccounts->where('status', '!=', 'closed')->sum('loan_amount'),
        'total_payable' => $loanAccounts->where('status', '!=', 'closed')->sum('total_payable'),
        'total_paid' => $loanAccounts->sum('paid_amount'),
        'outstanding' => $loanAccounts->where('status', 'active')->sum('outstanding_amount'),
    ];

    $closedLoanAccounts = $loanAccounts->where('status', 'closed')->values();
    $activeLoanAccounts = $loanAccounts->where('status', '!=', 'closed')->values();

    // 2. Build Unified Chronological Transaction History (Ledger Entries)
    $ledgerEntries = [];

    // A. Loan Disbursements & Foreclosures
    foreach ($loanAccounts as $loan) {
        if ($loan->disbursed_at) {
            $ledgerEntries[] = [
                'date' => $loan->disbursed_at,
                'type' => 'Loan Disbursement',
                'reference' => $loan->account_number,
                'details' => 'Principal Disbursed via ' . ucfirst(str_replace('_', ' ', $loan->payment_method ?? 'Bank')),
                'method' => ucfirst(str_replace('_', ' ', $loan->payment_method ?? 'Bank')),
                'flow' => 'OUT',
                'amount' => $loan->disbursed_amount ?: $loan->loan_amount,
                'badge_color' => 'danger'
            ];
        }
        if ($loan->is_foreclosed && (float)$loan->foreclosure_amount > 0) {
            $ledgerEntries[] = [
                'date' => $loan->closed_at ?: $loan->updated_at,
                'type' => 'Loan Foreclosure',
                'reference' => $loan->account_number,
                'details' => 'Loan Foreclosure Settlement Received' . ($loan->foreclosure_payment_method ? ' via ' . ucfirst(str_replace('_', ' ', $loan->foreclosure_payment_method)) : ''),
                'method' => ucfirst(str_replace('_', ' ', $loan->foreclosure_payment_method ?? 'Bank')),
                'flow' => 'IN',
                'amount' => round((float) $loan->foreclosure_amount, 2),
                'badge_color' => 'dark'
            ];
        }
    }

    // B. EMI / Loan incoming receipts
    $loanIds = $loanAccounts->pluck('id')->toArray();
    $emiCollections = collect();
    if (! empty($loanIds)) {
        $emiCollections = EmiCollection::with(['emi.loanAccount.loanApplication'])
            ->whereIn('emi_id', function ($query) use ($loanIds) {
                $query->select('id')->from('emis')->whereIn('loan_account_id', $loanIds);
            })
            ->whereIn('status', ClientLedgerEntries::postedCollectionStatuses())
            ->get();
    }

    foreach (ClientLedgerEntries::incomingPaymentRows($emiCollections, (int) $realId) as $paymentRow) {
        $ledgerEntries[] = $paymentRow;
    }

    // Sort entries by date descending, then by type to ensure stable sorting
    usort($ledgerEntries, function ($a, $b) {
        $t1 = strtotime((string)$a['date']);
        $t2 = strtotime((string)$b['date']);
        if ($t1 === $t2) {
            return strcmp($a['type'], $b['type']);
        }
        return $t2 - $t1; // descending order
    });

    return view('admin.clients.client-view-ledger', compact(
        'client',
        'stats',
        'loanAccounts',
        'loanStats',
        'closedLoanAccounts',
        'activeLoanAccounts',
        'ledgerEntries'
    ));
  }

  public function notifications($id)
  {
      $client = Client::with('kycDetail')->findOrFail($id);

      $statusDisplay = [
          'statusText' => $client->status == 'active' ? 'Active' : ucfirst($client->status),
          'statusColor' => $client->status == 'active' ? 'bg-success' : 'bg-danger'
      ];
      $stats = $this->clientSidebarStats($client, $statusDisplay);

      $notifications = \App\Models\CustomerNotification::where('client_id', $client->id)
          ->latest()
          ->paginate(15);

      return view('admin.clients.client-view-notifications', array_merge(
          compact('client', 'notifications'),
          $stats
      ));
  }

  /**
   * Sidebar counters on client view: loans, applications, fixed deposits.
   */
  protected function clientSidebarStats(Client $client, array $statusDisplay): array
  {
    $fdCount = $client->fixedDeposits()
      ->whereNotIn('status', ['closed', 'cancelled', 'premature_closed', 'renewed'])
      ->count();

    return [
      'applications' => (int) ($client->applications_count ?? 0),
      'loans' => (int) ($client->loans_count ?? 0),
      'fixed_deposits' => (int) $fdCount,
      'kyc' => $statusDisplay,
    ];
  }
}
