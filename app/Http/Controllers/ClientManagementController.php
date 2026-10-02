<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Client;
use App\Models\KycDetail;
use App\Models\Nominee;
use App\Models\Guarantor;
use App\Models\EmployeeInformation;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\View\View;

class ClientManagementController extends Controller
{
  /**.
   *
   * Redirect to client-management view
   */
  public function ClientManagement(): View
  {
    // Get client statistics
    $currentUser = auth()->user();
    $isAgent = $currentUser->hasRole('Agent');
    $agentId = $isAgent ? optional($currentUser->agent)->id : null;

    $clientQuery = Client::query();
    if ($isAgent && $agentId) {
        $clientQuery->where(function($q) use ($agentId) {
            $q->where('assigned_to', $agentId)
              ->orWhere('added_by', $agentId);
        });
    }

    $totalUser = $clientQuery->count();
    $activeClients = (clone $clientQuery)->where('status', 'active')->count();
    $inactiveClients = (clone $clientQuery)->where('status', 'inactive')->count();
    $pendingClients = (clone $clientQuery)->where('status', 'pending')->count();
    $blacklistedClients = $isAgent ? 0 : (clone $clientQuery)->where('status', 'blacklist')->count();

    $locations = \App\Models\Location::orderBy('name')->get();
    $agents = \App\Models\Agent::where('status', 'active')->orderBy('agent_name')->get();

    // Data for Quick Apply Loan Modal
    $verifiedClientsQuery = Client::whereHas('kycDetail', function($q) {
        $q->where('status', 'verified');
    });

    if ($isAgent && $agentId) {
        $verifiedClientsQuery->where(function($q) use ($agentId) {
            $q->where('added_by', $agentId)
              ->orWhere('assigned_to', $agentId);
        });
    }
    
    $verifiedClients = $verifiedClientsQuery->get();
    $loanProducts = \App\Models\LoanProduct::where('status', 'active')->get();
    $activePaymentMethods = \App\Models\PaymentMethod::where('is_enabled', true)->get();
    $activeGateways = \App\Models\PaymentGateway::where('enabled', true)->get();
    $availableGroups = \App\Models\ChitGroup::with('scheme')
        ->whereIn('status', ['forming', 'active'])
        ->get();
    $fdSchemes = \App\Models\FixedDepositScheme::active()->orderBy('name')->get();
    $payoutOptions = \App\Models\FixedDepositScheme::payoutOptions();

    return view('admin.clients.client-management', [
      'totalUser' => $totalUser,
      'activeClients' => $activeClients,
      'inactiveClients' => $inactiveClients,
      'pendingClients' => $pendingClients,
      'blacklistedClients' => $blacklistedClients,
      'locations' => $locations,
      'agents' => $agents,
      'isAgent' => $isAgent,
      'verifiedClients' => $verifiedClients,
      'loanProducts' => $loanProducts,
      'activePaymentMethods' => $activePaymentMethods,
      'activeGateways' => $activeGateways,
      'availableGroups' => $availableGroups,
      'fdSchemes' => $fdSchemes,
      'payoutOptions' => $payoutOptions
    ]);
  }

  /**
   * Deleted clients awaiting restore or permanent removal.
   */
  public function recycleBin(Request $request)
  {
    if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
      abort(403);
    }

    if ($request->ajax() || $request->has('draw')) {
      $query = Client::onlyTrashed();

      if ($search = $request->input('search.value')) {
        $query->where(function ($q) use ($search) {
          $q->where('client_name', 'LIKE', "%{$search}%")
            ->orWhere('client_phone', 'LIKE', "%{$search}%")
            ->orWhere('client_email', 'LIKE', "%{$search}%")
            ->orWhere('customer_id', 'LIKE', "%{$search}%");
        });
      }

      $totalData = Client::onlyTrashed()->count();
      $totalFiltered = $query->count();

      $limit = (int) $request->input('length', 20);
      $start = (int) $request->input('start', 0);

      $clients = $query->orderByDesc('deleted_at')
        ->skip($start)
        ->take($limit)
        ->get();

      $data = $clients->map(function (Client $client) {
        $summary = $client->trashedAccountSummary();

        return [
          'id' => $client->getRouteKey(),
          'customer_id' => $client->displayCustomerId(),
          'client_name' => $client->client_name,
          'client_phone' => $client->client_phone ?? 'N/A',
          'client_email' => $client->client_email ?? 'N/A',
          'deleted_at' => $client->deleted_at ? $client->deleted_at->format('d-m-Y h:i A') : 'N/A',
          'accounts' => $summary,
          'restore_url' => route('client-management-restore', $client->getRouteKey()),
          'force_delete_url' => route('client-management-force-delete', $client->getRouteKey()),
        ];
      });

      return response()->json([
        'draw' => (int) $request->input('draw'),
        'recordsTotal' => $totalData,
        'recordsFiltered' => $totalFiltered,
        'data' => $data,
      ]);
    }

    return view('admin.clients.client-recycle-bin', [
      'trashedCount' => Client::onlyTrashed()->count(),
    ]);
  }

  /**
   * Bring a deleted client back along with the accounts archived with it.
   */
  public function restore(string $id): JsonResponse
  {
    if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
      return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
    }

    try {
      $client = Client::onlyTrashed()->findOrFail($this->decodeClientId($id));
      $client->restoreWithRelations();

      return response()->json([
        'success' => true,
        'message' => $client->client_name . ' and all related accounts have been restored.',
      ]);
    } catch (\Exception $e) {
      Log::error('Client restore failed', ['error' => $e->getMessage(), 'id' => $id]);

      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Permanently remove a client from the recycle bin.
   */
  public function forceDelete(string $id): JsonResponse
  {
    if (!auth()->user()->hasRole('Admin')) {
      return response()->json(['success' => false, 'message' => 'Only an administrator can permanently delete a client.'], 403);
    }

    try {
      $client = Client::onlyTrashed()->findOrFail($this->decodeClientId($id));
      $name = $client->client_name;
      $client->forceDelete();

      return response()->json([
        'success' => true,
        'message' => $name . ' has been permanently deleted.',
      ]);
    } catch (\Exception $e) {
      Log::error('Client permanent deletion failed', ['error' => $e->getMessage(), 'id' => $id]);

      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  private function decodeClientId(string $id)
  {
    $decoded = \App\Support\HashId::decode($id);

    return is_array($decoded) ? ($decoded[0] ?? $id) : ($decoded ?? $id);
  }

  /**
   * Bulk assign clients to an agent
   */
  public function bulkAssignAgent(Request $request): JsonResponse
  {
    if (!auth()->user()->hasRole('Admin')) {
      return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
    }

    $request->validate([
      'client_ids' => 'required|array',
      'client_ids.*' => 'required',
      'agent_id' => 'required|exists:agents,id',
      'remarks' => 'nullable|string'
    ]);

    $agent = \App\Models\Agent::findOrFail($request->agent_id);
    $clientIds = $request->client_ids;
    $count = 0;

    DB::beginTransaction();
    try {
      foreach ($clientIds as $hashedId) {
        $clientId = \App\Support\HashId::decode($hashedId);
        $clientId = is_array($clientId) ? ($clientId[0] ?? $hashedId) : ($clientId ?? $hashedId);
        
        $client = Client::findOrFail($clientId);
        $previousAgentId = $client->assigned_to;
        $client->update(['assigned_to' => $agent->id]);
        if ((int) $previousAgentId !== (int) $agent->id) {
          event(new \App\Events\ClientAssignedToAgentEvent($client->fresh(), $agent));
        }

        // Optional: Also assign active EMIs of this client to the agent
        $activeEmis = \App\Models\Emi::whereHas('loanAccount', function($q) use ($clientId) {
          $q->where('client_id', $clientId);
        })->where('status', '!=', 'paid')->get();

        foreach ($activeEmis as $emi) {
          \App\Models\EmiAgentAssignment::updateOrCreate(
            ['emi_id' => $emi->id],
            [
              'agent_id' => $agent->id,
              'status' => 'assigned',
              'assigned_at' => now(),
              'remarks' => $request->remarks ?: 'Bulk assigned via Clients Overview'
            ]
          );
        }

        $count++;
      }

      DB::commit();
      return response()->json([
        'success' => true,
        'message' => "Successfully assigned {$count} clients and their active EMIs to {$agent->agent_name}"
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Bulk client assignment failed', ['error' => $e->getMessage()]);
      return response()->json([
        'success' => false,
        'message' => 'Assignment failed: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Bulk delete clients
   */
  public function bulkDelete(Request $request): JsonResponse
  {
    if (!auth()->user()->hasRole('Admin') && !auth()->user()->hasRole('Staff')) {
      return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
    }

    $request->validate([
      'client_ids' => 'required|array',
      'client_ids.*' => 'required'
    ]);

    $clientIds = $request->client_ids;
    $count = 0;
    $blocked = [];

    DB::beginTransaction();
    try {
      foreach ($clientIds as $hashedId) {
        $clientId = \App\Support\HashId::decode($hashedId);
        $clientId = is_array($clientId) ? ($clientId[0] ?? $hashedId) : ($clientId ?? $hashedId);

        $client = Client::findOrFail($clientId);

        if ($blockReason = $client->deletionBlockReason()) {
          $blocked[] = $client->client_name ?? ('ID ' . $client->id);
          continue;
        }

        $client->delete();
        $count++;
      }

      if ($count === 0 && !empty($blocked)) {
        DB::rollBack();
        return response()->json([
          'success' => false,
          'blocked' => true,
          'message' => 'Cannot delete selected client(s). They have active loan(s) and/or active chit membership(s): '
            . implode(', ', $blocked) . '. Close those accounts first.',
        ], 422);
      }

      DB::commit();

      $message = "Successfully deleted {$count} client(s).";
      if (!empty($blocked)) {
        $message .= ' Skipped (active loan/chit): ' . implode(', ', $blocked) . '.';
      }

      return response()->json([
        'success' => true,
        'message' => $message,
        'deleted_count' => $count,
        'blocked_names' => $blocked,
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Bulk client deletion failed', ['error' => $e->getMessage()]);
      return response()->json([
        'success' => false,
        'message' => 'Deletion failed: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request): JsonResponse
  {
    $columns = [
      1 => 'id',
      2 => 'customer_id',
      3 => 'client_name',
      4 => 'client_email',
      5 => 'client_phone',
      6 => 'location_id',
      7 => 'loan_accounts_count',
      8 => 'group_members_count',
      9 => 'assigned_to',
      10 => 'added_by',
      11 => 'status',
    ];

      $query = \App\Models\Client::with(['location', 'agent', 'creator', 'kycDetail'])->withCount([
        'loanAccounts as loan_accounts_count' => function ($q) {
          $q->where('status', '!=', 'closed');
        },
        'loanAccounts as emi_accounts_count' => function ($q) {
          $q->where('status', '!=', 'closed')
            ->where(function ($sub) {
              $sub->where('loan_mode', 'emi')
                ->orWhere(function ($m) {
                  $m->whereNull('loan_mode')->whereHas('loanApplication.product', fn($p) => $p->where('loan_mode', 'emi'));
                });
            });
        },
        'loanAccounts as open_loan_accounts_count' => function ($q) {
          $q->where('status', '!=', 'closed')
            ->where(function ($sub) {
              $sub->where('loan_mode', 'interest_only')
                ->orWhereHas('loanApplication.product', fn($p) => $p->where('loan_mode', 'interest_only'));
            });
        },
        'groupMembers as group_members_count' => function ($q) {
          $q->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
            ->whereHas('group', function ($gq) {
              $gq->where('status', '!=', 'completed');
            });
        },
      ]);

      // Filter by agent if applicable
      $currentUser = auth()->user();
      if ($currentUser->hasRole('Agent')) {
        $agentId = optional($currentUser->agent)->id;
        if ($agentId) {
          $query->where(function($q) use ($agentId) {
            $q->where('assigned_to', $agentId)
              ->orWhere('added_by', $agentId);
          });
        }
      }

      $totalData = $query->count();

      $limit = $request->input('length');
      $start = $request->input('start');
      $order = $columns[$request->input('order.0.column')] ?? 'id';
      $dir = $request->input('order.0.dir') ?? 'desc';

      // Location filter
      if ($request->filled('location_id')) {
        $query->where('location_id', $request->location_id);
      }
      // Status filter
      if ($request->filled('status')) {
        $query->where('status', $request->status);
      }
      // Account type filter (EMI / Open Loan / Chit)
      if ($request->filled('account_type')) {
        $accountType = $request->input('account_type');
        if ($accountType === 'emi') {
          $query->whereHas('loanAccounts', function ($q) {
            $q->where('status', '!=', 'closed')
              ->where(function ($sub) {
                $sub->where('loan_mode', 'emi')
                  ->orWhere(function ($m) {
                    $m->whereNull('loan_mode')->whereHas('loanApplication.product', fn($p) => $p->where('loan_mode', 'emi'));
                  });
              });
          });
        } elseif ($accountType === 'interest_only' || $accountType === 'open_loan') {
          $query->whereHas('loanAccounts', function ($q) {
            $q->where('status', '!=', 'closed')
              ->where(function ($sub) {
                $sub->where('loan_mode', 'interest_only')
                  ->orWhereHas('loanApplication.product', fn($p) => $p->where('loan_mode', 'interest_only'));
              });
          });
        } elseif ($accountType === 'chit') {
          $query->whereHas('groupMembers', function ($q) {
            $q->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
              ->whereHas('group', function ($gq) {
                $gq->where('status', '!=', 'completed');
              });
          });
        } elseif ($accountType === 'any_loan') {
          $query->whereHas('loanAccounts', function ($q) {
            $q->where('status', '!=', 'closed');
          });
        } elseif ($accountType === 'no_accounts') {
          $query->whereDoesntHave('loanAccounts', function ($q) {
            $q->where('status', '!=', 'closed');
          })->whereDoesntHave('groupMembers', function ($q) {
            $q->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
              ->whereHas('group', function ($gq) {
                $gq->where('status', '!=', 'completed');
              });
          });
        }
      }

      // Search handling
      if (!empty($request->input('search.value'))) {
        $search = $request->input('search.value');

        $query->where(function ($q) use ($search) {
          $q->where('id', 'LIKE', "%{$search}%")
            ->orWhere('customer_id', 'LIKE', "%{$search}%")
            ->orWhere('client_name', 'LIKE', "%{$search}%")
            ->orWhere('client_email', 'LIKE', "%{$search}%")
            ->orWhere('client_phone', 'LIKE', "%{$search}%");
        });
      }

      $totalFiltered = $query->count();

      $clients = $query->offset($start)
        ->limit($limit)
        ->orderBy($order, $dir)
        ->get();

      $data = [];
      $ids = $start;

      foreach ($clients as $client) {
        $data[] = [
          'id' => $client->getRouteKey(),
          'fake_id' => (string) $client->id,
          'customer_id' => $client->displayCustomerId(),
          'name' => $client->client_name,
          'nickname' => $client->nickname,
          'profile_image_url' => $this->resolveClientListAvatarUrl($client),
          'email' => $client->client_email,
          'mobile' => $client->client_phone ?? 'N/A',
          'zone' => $client->location ? $client->location->name : 'N/A',
          'loans_count' => $client->loan_accounts_count ?? 0,
          'emi_count' => $client->emi_accounts_count ?? 0,
          'open_loan_count' => $client->open_loan_accounts_count ?? 0,
          'chits_count' => $client->group_members_count ?? 0,
          'status' => $client->status ?? 'inactive',
          'agent_name' => $client->agent ? $client->agent->agent_name : null,
          'added_by_name' => $client->creator ? $client->creator->agent_name : 'Admin',
          'agent_id' => $client->assigned_to,
          'action' => '', // Action buttons will be rendered by DataTables
        ];
      }

      //  Always return full DataTables structure, even if no results
      return response()->json([
        'draw' => intval($request->input('draw')),
        'recordsTotal' => intval($totalData),
        'recordsFiltered' => intval($totalFiltered),
        'data' => $data,
      ]);
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create(): View
  {
    // Keep this query relation-safe; missing optional relations should not break page load.
    $locations = \App\Models\Location::orderBy('name')->get();
    return view('admin.clients.client-add', compact('locations'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request): \Illuminate\Http\JsonResponse
  {
    try {
      // Clean masked inputs before validation
      if ($request->has('phone')) {
          $request->merge(['phone' => preg_replace('/\s+/', '', $request->phone)]);
      }
      if ($request->has('aadhar_number')) {
          $request->merge(['aadhar_number' => preg_replace('/\s+/', '', $request->aadhar_number)]);
      }
      if ($request->has('pan_number')) {
          $request->merge(['pan_number' => strtoupper(preg_replace('/\s+/', '', $request->pan_number))]);
      }
      if ($request->has('ifsc_code')) {
          $request->merge(['ifsc_code' => strtoupper(preg_replace('/\s+/', '', $request->ifsc_code))]);
      }
      if ($request->has('account_number')) {
          $request->merge(['account_number' => preg_replace('/\s+/', '', $request->account_number)]);
      }
      if ($request->has('alternate_phone')) {
          $request->merge(['alternate_phone' => preg_replace('/\s+/', '', $request->alternate_phone)]);
      }
      if ($request->has('pincode')) {
          $request->merge(['pincode' => preg_replace('/\s+/', '', $request->pincode)]);
      }
      if ($request->has('nominee1_mobile')) {
          $request->merge(['nominee1_mobile' => preg_replace('/\s+/', '', $request->nominee1_mobile)]);
      }
      if ($request->has('nominee2_mobile')) {
          $request->merge(['nominee2_mobile' => preg_replace('/\s+/', '', $request->nominee2_mobile)]);
      }

      $validated = $request->validate([
        'name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
        'nickname' => 'nullable|string|max:255',
        'email' => 'nullable|email|unique:clients,client_email',
        'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/', 'unique:clients,client_phone'],
        'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'address' => 'nullable|string',
        'date_of_birth' => 'nullable|date_format:d-m-Y',
        'gender' => 'nullable|string|in:male,female,other',
        'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
        'company_name' => ['nullable', 'regex:/^(?=.*[A-Za-z])[A-Za-z0-9&().,\-\s\']+$/'],
        'monthly_salary' => 'nullable|numeric',
        'business_name' => 'nullable|string',
        'monthly_income' => 'nullable|numeric',
        'city' => 'nullable|string|max:255',
        'state' => 'nullable|string|max:255',
        'pincode' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
        'aadhar_number' => ['required', 'string', 'regex:/^[0-9]{12}$/', 'unique:clients,aadhaar_number'],
        'pan_number' => ['required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', 'unique:kyc_details,pan_number'],
        'account_holder' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
        'account_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'unique:kyc_details,account_number'],
        'ifsc_code' => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
        'bank_name' => ['nullable', 'string', 'max:255'],
        'branch_name' => ['required', 'string', 'max:255'],
        'account_type' => ['nullable', 'string', 'in:savings,current'],
        'employment_type' => 'nullable|in:salaried,business',
        'nominee1_name' => 'nullable|string',
        'nominee1_relationship' => 'nullable|string',
        'nominee1_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'nominee2_name' => 'nullable|string',
        'nominee2_relationship' => 'nullable|string',
        'nominee2_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'selfie_photo' => 'required|image|max:5120',
        'aadhar_photo_front' => 'required|file|max:5120',
        'aadhar_photo_back' => 'required|file|max:5120',
        'pan_photo' => 'nullable|file|max:5120',
        'bank_statement' => 'nullable|file|max:10240',
        'payslip' => 'nullable|file|max:5120',
        'business_document' => 'nullable|file|max:5120',
        'referralRelationship' => 'nullable|string|max:255',
        'guarantorName' => 'nullable|string|max:255',
        'guarantorPhone' => 'nullable|string|regex:/^[0-9]{10}$/',
        'guarantorRelationship' => 'nullable|string|max:255',
        'referralName' => 'nullable|string|max:255',
        'referralPhone' => 'nullable|string|regex:/^[0-9]{10}$/',
        'location_id' => 'required|exists:locations,id',
        'collection_day' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
      ], [
        'aadhar_number.required' => 'Aadhar number is mandatory.',
        'pan_number.required' => 'PAN number is mandatory.',
        'pan_number.unique' => 'The PAN number has already been registered.',
        'account_holder.required' => 'Account holder name is mandatory.',
        'account_number.required' => 'Bank account number is mandatory.',
        'account_number.unique' => 'The Bank Account number has already been registered.',
        'account_number.regex' => 'Bank account number must contain only numbers.',
        'ifsc_code.required' => 'IFSC code is mandatory.',
        'bank_name.required' => 'Bank name is mandatory.',
        'branch_name.required' => 'Branch name is mandatory.',
        'account_type.required' => 'Account type is mandatory.',
        'account_type.in' => 'Please select a valid account type (Savings or Current).',
        'selfie_photo.required' => 'Selfie photo is mandatory.',
        'aadhar_photo_front.required' => 'Aadhar front photo is mandatory.',
        'aadhar_photo_back.required' => 'Aadhar back photo is mandatory.',
        'ifsc_code.regex' => 'Invalid IFSC format. It must be 11 characters (e.g., HDFC0001234).',
        'pan_number.regex' => 'Invalid PAN format. It must be 10 characters (e.g., ABCDE1234F).',
        'aadhar_number.regex' => 'Aadhar number must be exactly 12 digits.',
        'phone.regex' => 'Phone number must be exactly 10 digits.',
        'nominee1_mobile.regex' => 'Nominee mobile must be exactly 10 digits.',
        'guarantorPhone.required' => 'Guarantor phone number is mandatory.',
        'guarantorPhone.regex' => 'Guarantor phone must be exactly 10 digits.',
        'guarantorName.required' => 'Guarantor name is mandatory.',
        'guarantorRelationship.required' => 'Guarantor relationship is mandatory.',
        'company_name.regex' => 'Company name must contain letters and can include numbers/spaces/safe symbols.'
      ]);

      // Mutual exclusivity check for employment fields. Some Laravel
      // installations may not have `prohibited_with` rule available.
      if ($request->filled('company_name') && $request->filled('business_name')) {
        throw ValidationException::withMessages([
          'employment_type' => ['Please select only one employment type.']
        ]);
      }

      DB::beginTransaction();

      try {
        // 1. Create Client (clean spaces from phone & aadhaar)
        $cleanPhone = preg_replace('/\s+/', '', $validated['phone']);
        $cleanAadhaar = !empty($validated['aadhar_number']) ? preg_replace('/\s+/', '', $validated['aadhar_number']) : null;

        $client = Client::create([
          'client_name' => $validated['name'],
          'nickname' => $request->filled('nickname') ? trim((string) $request->input('nickname')) : null,
          'client_email' => !empty($validated['email']) ? $validated['email'] : null,
          'client_phone' => $cleanPhone,
          'alternate_phone' => $request->filled('alternate_phone') ? $request->alternate_phone : null,
          'address' => $validated['address'] ?? null,
          'date_of_birth' => !empty($validated['date_of_birth']) ? \Carbon\Carbon::createFromFormat('d-m-Y', str_replace('/', '-', $validated['date_of_birth']))->format('Y-m-d') : null,
          'gender' => $validated['gender'] ?? null,
          'marital_status' => $validated['marital_status'] ?? null,
          'city' => $validated['city'] ?? null,
          'state' => $validated['state'] ?? null,
          'pincode' => $validated['pincode'] ?? null,
          'aadhaar_number' => $cleanAadhaar,
          'location_id' => $validated['location_id'],
          'collection_day' => isset($validated['collection_day']) ? ucfirst(strtolower($validated['collection_day'])) : null,
          'status' => 'pending',
          'added_by' => auth()->user()->hasRole('Agent') ? optional(auth()->user()->agent)->id : null,
        ]);

        // 2. Handle File Uploads
        $paths = [];
        if ($request->hasFile('selfie_photo')) {
          $paths['selfie'] = $request->file('selfie_photo')->store('kyc/selfie/' . $client->id, 'public');
        }
        if ($request->hasFile('aadhar_photo_front')) {
          $paths['aadhar_front'] = $request->file('aadhar_photo_front')->store('kyc/aadhar/' . $client->id, 'public');
        }
        if ($request->hasFile('aadhar_photo_back')) {
          $paths['aadhar_back'] = $request->file('aadhar_photo_back')->store('kyc/aadhar/' . $client->id, 'public');
        }
        if ($request->hasFile('pan_photo')) {
          $paths['pan'] = $request->file('pan_photo')->store('kyc/pan/' . $client->id, 'public');
        }
        if ($request->hasFile('bank_statement')) {
          $paths['bank_statement'] = $request->file('bank_statement')->store('kyc/bank_statement/' . $client->id, 'public');
        }

        // 3. Create KYC Detail
        $hasPan = !empty($validated['pan_number']);
        $hasBank = !empty($validated['account_number']);

        KycDetail::create([
          'client_id' => $client->id,
          'aadhaar_number' => $cleanAadhaar,
          'aadhaar_name' => $validated['name'],
          'aadhaar_image' => $paths['aadhar_front'] ?? null,
          'aadhaar_image_back' => $paths['aadhar_back'] ?? null,
          'selfie_image' => $paths['selfie'] ?? null,
          'pan_number' => $hasPan ? $validated['pan_number'] : null,
          'pan_name' => $validated['name'] ?? null,
          'pan_image' => $paths['pan'] ?? null,
          'account_holder_name' => $validated['account_holder'] ?? null,
          'account_number' => $hasBank ? $validated['account_number'] : null,
          'ifsc_code' => $validated['ifsc_code'] ?? null,
          'bank_name' => $validated['bank_name'] ?? null,
          'branch_name' => $validated['branch_name'] ?? null,
          'account_type' => $validated['account_type'] ?? null,
          'bank_statement' => $paths['bank_statement'] ?? null,
          'status' => 'pending',
          'aadhaar_verified' => false,
          'pan_verified' => false,
          'bank_verified' => false,
        ]);

        // 4. Create Nominee Record
        Nominee::create([
          'client_id' => $client->id,
          'nominee1_name' => $validated['nominee1_name'] ?? 'N/A',
          'nominee1_relationship' => $validated['nominee1_relationship'] ?? 'N/A',
          'nominee1_mobile' => $validated['nominee1_mobile'] ?? 'N/A',
          'nominee2_name' => $request->nominee2_name ?? null,
          'nominee2_relationship' => $request->nominee2_relationship ?? null,
          'nominee2_mobile' => $request->nominee2_mobile ?? null,
        ]);
        
        // 4.5. Create Guarantor/Referral Records
        if ($request->filled('guarantorName') || $request->filled('guarantorPhone') || $request->filled('guarantorRelationship')) {
          Guarantor::create([
            'client_id' => $client->id,
            'name' => $request->guarantorName ?? 'N/A',
            'phone' => $request->guarantorPhone ?? 'N/A',
            'relationship' => $request->guarantorRelationship ?? 'N/A',
            'type' => 'guarantor'
          ]);
        }
        if ($request->filled('referralName') || $request->filled('referralPhone') || $request->filled('referralRelationship')) {
          Guarantor::create([
            'client_id' => $client->id,
            'name' => $request->referralName ?? 'N/A',
            'phone' => $request->referralPhone ?? null,
            'relationship' => $request->referralRelationship ?? 'Associate',
            'type' => 'referral'
          ]);
        }

        // 5. Create Employee Information
        $empData = [
          'client_id' => $client->id,
          'employment_type' => ($validated['employment_type'] ?? null) === 'business' ? 'self_employed' : ($validated['employment_type'] ?? 'salaried'),
        ];

        if (($validated['employment_type'] ?? null) === 'salaried') {
          $empData['company_name'] = $request->company_name;
          $empData['monthly_salary'] = $request->monthly_salary;
          if ($request->hasFile('payslip')) {
            $empData['payslip_documents'] = [$request->file('payslip')->store('kyc/payslip/' . $client->id, 'public')];
          }
        } else {
          $empData['business_name'] = $request->business_name;
          $empData['monthly_turnover'] = $request->monthly_income;
          if ($request->hasFile('business_document')) {
            $empData['business_proof_documents'] = [$request->file('business_document')->store('kyc/business_proof/' . $client->id, 'public')];
          }
        }
        EmployeeInformation::create($empData);

        DB::commit();

        return response()->json([
          'success' => true,
          'message' => 'Client registered successfully and moved to KYC verification.',
          'client_id' => $client->id
        ]);

      } catch (\Illuminate\Database\QueryException $e) {
        DB::rollBack();
        Log::error('Client Registration Database Error: ' . $e->getMessage());
        $message = 'An unexpected database error occurred. Please try again.';
        if ($e->getCode() == 23000 || str_contains($e->getMessage(), '1062 Duplicate entry')) {
          $message = 'Registration failed: A duplicate record was detected. The Aadhaar, PAN, or Bank Account number is already registered.';
        }
        return response()->json([
          'success' => false,
          'message' => $message
        ], 422);
      } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Client Registration Error: ' . $e->getMessage());
        return response()->json([
          'success' => false,
          'message' => 'An unexpected error occurred during registration: ' . $e->getMessage()
        ], 500);
      }
    } catch (\Illuminate\Validation\ValidationException $e) {
      Log::error('Registration Validation Failed: ' . json_encode($e->errors()));
      return response()->json([
        'success' => false,
        'message' => 'Validation error',
        'errors' => $e->errors()
      ], 422);
    }
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
    //
  }

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id): JsonResponse
  {
    $user = User::findOrFail($id);
    return response()->json([
      'id' => $user->getRouteKey(),
      'name' => $user->name,
      'email' => $user->email,
      'phone' => $user->phone,
    ]);
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id)
  {
    try {
      $client = Client::findOrFail($id);
      $kyc = $client->kycDetail;

      // Validate data with exclusion for the current record ID
      $validated = $request->validate([
        'formValidationName' => 'required|string|max:255',
        'formValidationNickname' => 'nullable|string|max:255',
        'nickname' => 'nullable|string|max:255',
        'formValidationEmail' => [
          'nullable',
          'email',
          'unique:clients,client_email,' . $id,
          'unique:users,email,' . ($client->user_id ?? 'NULL')
        ],
        'formValidationMobile' => [
          'required',
          'regex:/^[0-9]{10}$/',
          'unique:clients,client_phone,' . $id,
          'unique:users,phone,' . ($client->user_id ?? 'NULL')
        ],
        'formValidationAadhar' => [
          'nullable',
          'regex:/^[0-9]{12}$/',
          'unique:clients,aadhaar_number,' . $id,
          'unique:kyc_details,aadhaar_number,' . ($kyc ? $kyc->id : 'NULL')
        ],
        'formValidationPan' => [
          'nullable',
          'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
          'unique:kyc_details,pan_number,' . ($kyc ? $kyc->id : 'NULL'),
        ],
        'formValidationBankAccount' => [
          'nullable',
          'regex:/^[0-9]+$/',
          'unique:kyc_details,account_number,' . ($kyc ? $kyc->id : 'NULL'),
        ],
        'formValidationIFSC' => [
          'nullable',
          'string',
          'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
        ],
      ], [
        'formValidationBankAccount.regex' => 'Bank account number must contain only numbers.',
      ]);

      DB::beginTransaction();

      // Update Client
      $clientUpdates = [
        'client_name' => $validated['formValidationName'],
        'client_email' => $validated['formValidationEmail'],
        'client_phone' => $validated['formValidationMobile'],
        'address' => $request->formValidationAddress,
        'aadhaar_number' => $validated['formValidationAadhar'],
      ];

      $nicknameVal = $request->input('formValidationNickname', $request->input('nickname'));
      if ($request->has('formValidationNickname') || $request->has('nickname')) {
        $clientUpdates['nickname'] = ($nicknameVal !== null && trim((string)$nicknameVal) !== '') ? trim((string)$nicknameVal) : null;
      }

      $client->update($clientUpdates);

      // Update basic fields in associated User model
      if ($client->user) {
        $userUpdates = [
          'name' => $validated['formValidationName'],
          'email' => $validated['formValidationEmail'],
          'phone' => $validated['formValidationMobile'],
        ];
        if (array_key_exists('nickname', $clientUpdates)) {
          $userUpdates['nickname'] = $clientUpdates['nickname'];
        }
        $client->user->update($userUpdates);
      }

      // Update KYC record
      if ($kyc) {
        $kyc->update([
          'pan_number' => $validated['formValidationPan'],
          'account_number' => $validated['formValidationBankAccount'],
          'ifsc_code' => $request->formValidationIFSC,
          'bank_name' => $request->formValidationBankName,
          'branch_name' => $request->formValidationBranchName,
          'account_type' => $request->formValidationAccountType,
        ]);
      }

      DB::commit();

      return response()->json([
        'success' => true,
        'message' => 'Client updated successfully!',
      ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
      return response()->json([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $e->errors()
      ], 422);
    } catch (\Exception $e) {
      DB::rollBack();
      return response()->json([
        'success' => false,
        'message' => 'Error updating client: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Remove the specified resource from storage.
   *
   * @return \Illuminate\Http\JsonResponse
   */
  public function destroy(Request $request, $id): JsonResponse
  {
    try {
      $decodedId = \App\Support\HashId::decode($id);
      $realId = is_array($decodedId) ? ($decodedId[0] ?? $id) : ($decodedId ?? $id);

      $client = Client::findOrFail($realId);

      if ($blockReason = $client->deletionBlockReason()) {
        return response()->json([
          'success' => false,
          'blocked' => true,
          'message' => $blockReason,
        ], 422);
      }

      $client->delete();

      return response()->json(['success' => true], 200);
    } catch (\Exception $e) {
      Log::error('Client deletion failed', ['error' => $e->getMessage(), 'id' => $id]);
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  /**
   * Check for duplicate field values (Email, Phone, PAN, Aadhaar)
   */
  public function checkDuplicate(Request $request): JsonResponse
  {
    $field = $request->input('field');
    $value = $request->input('value');
    
    // Clean value for phone/aadhar (remove spaces)
    if (in_array($field, ['phone', 'aadhar_number'])) {
        $value = preg_replace('/\s+/', '', $value);
    }

    $isDuplicate = false;

    switch ($field) {
      case 'email':
        $isDuplicate = Client::where('client_email', $value)->exists() || User::where('email', $value)->exists();
        break;
      case 'phone':
        $isDuplicate = Client::where('client_phone', $value)->exists() || User::where('phone', $value)->exists();
        break;
      case 'aadhar_number':
        $isDuplicate = Client::where('aadhaar_number', $value)->exists() || KycDetail::where('aadhaar_number', $value)->exists();
        break;
      case 'pan_number':
        $isDuplicate = KycDetail::where('pan_number', $value)->exists();
        break;
      case 'account_number':
        $isDuplicate = KycDetail::where('account_number', $value)->exists();
        break;
    }

    return response()->json([
      'valid' => !$isDuplicate,
      'message' => $isDuplicate ? "This " . str_replace('_', ' ', $field) . " is already registered." : ""
    ]);
  }

  public function toggleStatus(Request $request, $id)
  {
    try {
      $id = \App\Support\HashId::decode($id) ?? $id;
      $client = Client::with('kycDetail')->findOrFail($id);
      $isActive = in_array($client->status, ['active', 'verified'], true);

      if ($isActive) {
        $client->update(['status' => 'inactive']);

        return response()->json(['success' => true, 'message' => 'Status updated to inactive', 'status' => 'inactive']);
      }

      // Activation is allowed only after KYC verification.
      if (! $client->isKycVerified()) {
        return response()->json([
          'success' => false,
          'message' => 'Client cannot be activated. KYC is ' . $client->kycStatus() . '. Please verify KYC first.',
          'status' => $client->status,
          'kyc_status' => $client->kycStatus(),
        ], 422);
      }

      $client->update(['status' => 'active']);

      return response()->json(['success' => true, 'message' => 'Status updated to active', 'status' => 'active']);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }

  public function verifyAadhaar(Request $request, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
  {
    $request->validate([
      'aadhaar_number' => 'required|string|regex:/^[0-9]{12}$/'
    ]);

    $aadhaarNumber = preg_replace('/\s+/', '', $request->aadhaar_number);

    $exists = Client::where('aadhaar_number', $aadhaarNumber)->exists();
    if ($exists) {
      return response()->json([
        'status' => false,
        'message' => 'This Aadhaar number is already linked with another client.'
      ], 422);
    }

    $result = $curlService->verifyAadhaarOtpRequest($aadhaarNumber);

    return response()->json($result);
  }

  public function resendAadhaarOtp(Request $request, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
  {
    $request->validate([
      'aadhaar_number' => 'required|string|regex:/^[0-9]{12}$/'
    ]);

    $aadhaarNumber = preg_replace('/\s+/', '', $request->aadhaar_number);

    $exists = Client::where('aadhaar_number', $aadhaarNumber)->exists();
    if ($exists) {
      return response()->json([
        'status' => false,
        'message' => 'This Aadhaar number is already linked with another client.'
      ], 422);
    }

    $result = $curlService->verifyAadhaarOtpRequest($aadhaarNumber, true);

    return response()->json($result);
  }

  public function verifyAadhaarOtp(Request $request, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
  {
    $request->validate([
      'aadhaar_number' => 'required|string|regex:/^[0-9]{12}$/',
      'otp' => 'required|string|digits:6',
      'request_id' => 'required|string',
    ]);

    $aadhaarNumber = preg_replace('/\s+/', '', $request->aadhaar_number);

    $result = $curlService->submitAadhaarOtp($request->otp, $request->request_id);

    $isSuccess =
        isset($result['status']) &&
        $result['status'] === true &&
        ($result['data']['status'] ?? '') === 'success' &&
        ($result['data']['data']['status'] ?? '') === 'success_aadhaar';

    if (!$isSuccess) {
        Log::warning("Aadhaar OTP verification failed for Aadhaar {$aadhaarNumber} using Request ID {$request->request_id}. Response: " . json_encode($result));
        return response()->json([
            'status' => false,
            'message' => $result['data']['message'] ?? $result['message'] ?? 'Aadhaar verification failed',
            'data' => $result
        ], 422);
    }

    $d = $result['data']['data'];
    
    $fullAddress = '';
    $city = null;
    $state = null;
    $pincode = $d['zip'] ?? $d['pincode'] ?? null;

    if (isset($d['address'])) {
        if (is_array($d['address'])) {
            $addr = $d['address'];
            $fullAddress = implode(', ', array_filter([
                $addr['house']   ?? null,
                $addr['street']  ?? null,
                $addr['loc']     ?? null,
                $addr['vtc']     ?? null,
                $addr['po']      ?? null,
                $addr['subdist'] ?? null,
                $addr['dist']    ?? null,
                $addr['state']   ?? null,
                $addr['country'] ?? null,
            ]));
            $city = $addr['vtc'] ?? $addr['city'] ?? $d['city'] ?? null;
            $state = $addr['state'] ?? $d['state'] ?? null;
        } else {
            $fullAddress = (string) $d['address'];
            $city = $d['city'] ?? $d['vtc'] ?? null;
            $state = $d['state'] ?? null;
        }
    } else {
        $city = $d['city'] ?? $d['vtc'] ?? null;
        $state = $d['state'] ?? null;
    }

    $dobFormatted = null;
    if (!empty($d['dob'])) {
        try {
            $dobRaw = trim($d['dob']);
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dobRaw)) {
                $dobFormatted = $dobRaw;
            } else {
                $dobFormatted = \Carbon\Carbon::parse($dobRaw)->format('d-m-Y');
            }
        } catch (\Throwable $e) {}
    }

    return response()->json([
        'status' => true,
        'message' => 'Aadhaar verified successfully.',
        'data' => [
            'name' => $d['full_name'] ?? null,
            'gender' => strtolower($d['gender'] ?? ''),
            'dob' => $dobFormatted,
            'address' => $fullAddress,
            'city' => $city,
            'state' => $state,
            'pincode' => $pincode,
            'profile_image' => $d['profile_image'] ?? null
        ]
    ]);
  }

  public function verifyPan(Request $request, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
  {
    $request->validate([
      'pan_number' => 'required|string|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
    ]);

    $panNumber = strtoupper(preg_replace('/\s+/', '', $request->pan_number));

    $exists = KycDetail::where('pan_number', $panNumber)->exists();
    if ($exists) {
      return response()->json([
        'status' => false,
        'message' => 'This PAN number is already linked with another client.'
      ], 422);
    }

    $result = $curlService->verifyPan($panNumber);

    if (isset($result['status']) && $result['status'] === true) {
      return response()->json([
        'status' => true,
        'message' => 'PAN verified successfully.',
        'data' => $result['data'] ?? null
      ]);
    }

    return response()->json([
      'status' => false,
      'message' => $result['message'] ?? 'PAN verification failed.',
      'data' => $result
    ], 422);
  }

  public function verifyBank(Request $request, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
  {
    $request->validate([
      'account_number' => 'required|string|regex:/^[0-9]+$/',
      'ifsc_code' => 'required|string|regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
      'name' => 'nullable|string',
    ]);

    $accountNumber = preg_replace('/\s+/', '', $request->account_number);
    $ifscCode = strtoupper(preg_replace('/\s+/', '', $request->ifsc_code));
    $clientName = $request->name;

    $exists = KycDetail::where('account_number', $accountNumber)->exists();
    if ($exists) {
      return response()->json([
        'status' => false,
        'message' => 'This Bank Account number is already registered with another client.'
      ], 422);
    }

    $result = $curlService->verifyAgentBank($accountNumber, $ifscCode, $clientName);

    if (isset($result['success']) && $result['success'] === true && !empty($result['bank'])) {
      $bankData = $result['bank'];
      return response()->json([
        'status' => true,
        'message' => 'Bank Account verified successfully.',
        'data' => [
          'bank_name' => $bankData['bank_name'] ?? null,
          'branch' => $bankData['branch'] ?? null,
          'full_name' => $bankData['full_name'] ?? $bankData['beneficiary_name'] ?? null
        ]
      ]);
    }

    $verifyResult = $curlService->verifyBank($accountNumber, $ifscCode, $clientName);
    if (isset($verifyResult['status']) && $verifyResult['status'] === true) {
      $bankName = null;
      $branch = null;
      try {
        $response = \Illuminate\Support\Facades\Http::get("https://ifsc.razorpay.com/{$ifscCode}");
        if ($response->successful()) {
          $ifscData = $response->json();
          $bankName = $ifscData['BANK'] ?? null;
          $branch = $ifscData['BRANCH'] ?? null;
        }
      } catch (\Throwable $e) {
        Log::error('Razorpay IFSC API call failed', ['error' => $e->getMessage()]);
      }

      return response()->json([
        'status' => true,
        'message' => 'Bank Account verified successfully.',
        'data' => [
          'bank_name' => $bankName ?? $verifyResult['data']['bank_name'] ?? $verifyResult['data']['data']['bank_name'] ?? null,
          'branch' => $branch ?? $verifyResult['data']['branch'] ?? $verifyResult['data']['data']['branch'] ?? null,
          'full_name' => $verifyResult['data']['full_name'] ?? $verifyResult['data']['data']['full_name'] ?? null
        ]
      ]);
    }

    return response()->json([
      'status' => false,
      'message' => $result['data']['message'] ?? $verifyResult['message'] ?? 'Bank Account verification failed.',
      'data' => $result
    ], 422);
  }

  /**
   * Download the Excel template for importing clients.
   */
  public function downloadTemplate()
  {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
      'Client Name',
      'Client Email',
      'Client Phone',
      'Gender',
      'Date of Birth',
      'Address',
      'Pincode',
      'Aadhaar Number',
      'PAN Number',
      'Bank Account Number',
      'Bank IFSC Code',
      'Bank Name',
      'Bank Branch Name',
      'Location (Name or ID)'
    ];

    foreach ($headers as $colIndex => $header) {
      $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
      $sheet->setCellValue($colLetter . '1', $header);
    }

    $firstLoc = \App\Models\Location::first();
    $defaultLocationName = $firstLoc ? $firstLoc->name : 'Singanallur';

    $exampleData = [
      [
        'John Doe',
        'john@example.com',
        '9876543210',
        'Male',
        '1990-05-15',
        '123 Main Street',
        '600001',
        '234567890123',
        'ABCDE1234F',
        '1234567890',
        'HDFC0000123',
        'HDFC Bank',
        'Main Branch',
        $defaultLocationName
      ]
    ];

    foreach ($exampleData as $rowIndex => $rowData) {
      foreach ($rowData as $colIndex => $value) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->setCellValue($colLetter . ($rowIndex + 2), (string) $value);
      }
    }

    foreach (range(1, count($headers)) as $colIndex) {
      $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
      $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    
    if (ob_get_length() > 0) {
      ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="client_import_template.xlsx"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
    exit;
  }

  /**
   * Import clients in bulk from Excel file.
   */
  public function bulkImport(Request $request): JsonResponse
  {
    $request->validate([
      'import_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
    ]);

    try {
      $file = $request->file('import_file');
      $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
      $sheet = $spreadsheet->getActiveSheet();
      $rows = $sheet->toArray();

      if (count($rows) <= 1) {
        return response()->json([
          'success' => false,
          'message' => 'The uploaded file is empty or contains no client data rows.'
        ], 422);
      }

      $headerRow = array_map('trim', $rows[0]);
      
      // Define expected column headers (case-insensitive search)
      $fieldMappings = [
        'client_name' => 'Client Name',
        'client_email' => 'Client Email',
        'client_phone' => 'Client Phone',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of Birth',
        'address' => 'Address',
        'pincode' => 'Pincode',
        'aadhaar_number' => 'Aadhaar Number',
        'pan_number' => 'PAN Number',
        'account_number' => 'Bank Account Number',
        'ifsc_code' => 'Bank IFSC Code',
        'bank_name' => 'Bank Name',
        'branch_name' => 'Bank Branch Name',
        'location' => 'Location (Name or ID)'
      ];

      $colIndexes = [];
      foreach ($fieldMappings as $key => $label) {
        $index = -1;
        foreach ($headerRow as $i => $headerVal) {
          if (strcasecmp($headerVal, $label) === 0) {
            $index = $i;
            break;
          }
        }
        $colIndexes[$key] = $index;
      }

      // Check if critical columns exist
      if ($colIndexes['client_name'] === -1 || $colIndexes['client_phone'] === -1 || $colIndexes['aadhaar_number'] === -1) {
        return response()->json([
          'success' => false,
          'message' => 'Missing required columns in header. Please ensure "Client Name", "Client Phone", and "Aadhaar Number" columns exist.'
        ], 422);
      }

      $errors = [];
      $clientsToCreate = [];

      // Validate each row
      for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
        $row = $rows[$rowIndex];
        
        // Skip completely empty rows
        if (empty(array_filter($row))) {
          continue;
        }

        $rowNum = $rowIndex + 1;

        $name = trim($row[$colIndexes['client_name']] ?? '');
        $email = trim($row[$colIndexes['client_email']] ?? '');
        $phone = preg_replace('/\s+/', '', $row[$colIndexes['client_phone']] ?? '');
        $gender = trim($row[$colIndexes['gender']] ?? '');
        $dob = trim($row[$colIndexes['date_of_birth']] ?? '');
        $address = trim($row[$colIndexes['address']] ?? '');
        $pincode = preg_replace('/\s+/', '', $row[$colIndexes['pincode']] ?? '');
        $aadhaar = preg_replace('/\s+/', '', $row[$colIndexes['aadhaar_number']] ?? '');
        $pan = strtoupper(preg_replace('/\s+/', '', $row[$colIndexes['pan_number']] ?? ''));
        $accountNum = preg_replace('/\s+/', '', $row[$colIndexes['account_number']] ?? '');
        $ifsc = strtoupper(preg_replace('/\s+/', '', $row[$colIndexes['ifsc_code']] ?? ''));
        $bankName = trim($row[$colIndexes['bank_name']] ?? '');
        $branchName = trim($row[$colIndexes['branch_name']] ?? '');
        $locationInput = trim($row[$colIndexes['location']] ?? '');

        // Validation checks
        $rowErrors = [];

        if (empty($name)) {
          $rowErrors[] = 'Client Name is required.';
        }

        if (empty($phone)) {
          $rowErrors[] = 'Client Phone is required.';
        } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
          $rowErrors[] = 'Client Phone must be exactly 10 digits.';
        } else {
          // Check duplicates for phone
          if (Client::where('client_phone', $phone)->exists() || User::where('phone', $phone)->exists()) {
            $rowErrors[] = "Phone number {$phone} is already registered.";
          }
        }

        if (!empty($email)) {
          if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = 'Invalid email address format.';
          } else {
            if (Client::where('client_email', $email)->exists() || User::where('email', $email)->exists()) {
              $rowErrors[] = "Email {$email} is already registered.";
            }
          }
        } else {
          $email = null;
        }

        if (empty($aadhaar)) {
          $rowErrors[] = 'Aadhaar Number is required.';
        } elseif (!preg_match('/^[0-9]{12}$/', $aadhaar)) {
          $rowErrors[] = 'Aadhaar Number must be exactly 12 digits.';
        } else {
          if (Client::where('aadhaar_number', $aadhaar)->exists() || KycDetail::where('aadhaar_number', $aadhaar)->exists()) {
            $rowErrors[] = "Aadhaar Number {$aadhaar} is already registered.";
          }
        }

        if (!empty($pan)) {
          if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            $rowErrors[] = 'Invalid PAN format. Must be like ABCDE1234F.';
          } else {
            if (KycDetail::where('pan_number', $pan)->exists()) {
              $rowErrors[] = "PAN Number {$pan} is already registered.";
            }
          }
        } else {
          $pan = null;
        }

        if (!empty($accountNum)) {
          if (KycDetail::where('account_number', $accountNum)->exists()) {
            $rowErrors[] = "Bank Account number {$accountNum} is already registered.";
          }
        } else {
          $accountNum = null;
        }

        if (!empty($ifsc) && !preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc)) {
          $rowErrors[] = 'Invalid Bank IFSC code format.';
        }

        if (!empty($pincode) && !preg_match('/^[0-9]{6}$/', $pincode)) {
          $rowErrors[] = 'Invalid pincode format (must be 6 digits).';
        }

        // Validate date of birth format
        $dobFormatted = null;
        if (!empty($dob)) {
          try {
            // Try YYYY-MM-DD
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
              $dobFormatted = $dob;
            } elseif (preg_match('/^\d{2}-\d{2}-\d{4}$/', $dob)) {
              $dobFormatted = \Carbon\Carbon::createFromFormat('d-m-Y', $dob)->format('Y-m-d');
            } elseif (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dob)) {
              $dobFormatted = \Carbon\Carbon::createFromFormat('d/m/Y', $dob)->format('Y-m-d');
            } else {
              $dobFormatted = \Carbon\Carbon::parse($dob)->format('Y-m-d');
            }
          } catch (\Exception $e) {
            $rowErrors[] = "Invalid Date of Birth format: '{$dob}'. Please use YYYY-MM-DD.";
          }
        }

        // Look up location
        $locationId = null;
        if (!empty($locationInput)) {
          $loc = \App\Models\Location::where('name', $locationInput)
            ->orWhere('id', $locationInput)
            ->first();
          if ($loc) {
            $locationId = $loc->id;
          } else {
            $rowErrors[] = "Location '{$locationInput}' not found in system.";
          }
        }



        // Normalize gender
        $genderNormalized = null;
        if (!empty($gender)) {
          $gLower = strtolower($gender);
          if (in_array($gLower, ['male', 'female', 'other'])) {
            $genderNormalized = $gLower;
          } else {
            $rowErrors[] = "Gender must be 'Male', 'Female', or 'Other'. Received: '{$gender}'.";
          }
        }

        if (!empty($rowErrors)) {
          $errors[] = "Row {$rowNum}: " . implode(' ', $rowErrors);
        } else {
          $clientsToCreate[] = [
            'client_name' => $name,
            'client_email' => $email,
            'client_phone' => $phone,
            'gender' => $genderNormalized,
            'date_of_birth' => $dobFormatted,
            'address' => $address,
            'pincode' => $pincode,
            'aadhaar_number' => $aadhaar,
            'pan_number' => $pan,
            'account_number' => $accountNum,
            'ifsc_code' => $ifsc,
            'bank_name' => $bankName,
            'branch_name' => $branchName,
            'location_id' => $locationId
          ];
        }
      }

      if (!empty($errors)) {
        return response()->json([
          'success' => false,
          'message' => 'Import validation failed. Please fix the errors in your template and try again.',
          'errors' => $errors
        ], 422);
      }

      // Check if duplicate entries are present *within* the uploaded file
      $phones = array_column($clientsToCreate, 'client_phone');
      $aadhaars = array_column($clientsToCreate, 'aadhaar_number');
      if (count($phones) !== count(array_unique($phones))) {
        return response()->json([
          'success' => false,
          'message' => 'Duplicate Phone numbers detected inside the uploaded file.'
        ], 422);
      }
      if (count($aadhaars) !== count(array_unique($aadhaars))) {
        return response()->json([
          'success' => false,
          'message' => 'Duplicate Aadhaar numbers detected inside the uploaded file.'
        ], 422);
      }

      DB::beginTransaction();
      $importedCount = 0;

      foreach ($clientsToCreate as $cData) {
        $client = Client::create([
          'client_name' => $cData['client_name'],
          'client_email' => $cData['client_email'],
          'client_phone' => $cData['client_phone'],
          'gender' => $cData['gender'],
          'date_of_birth' => $cData['date_of_birth'],
          'address' => $cData['address'],
          'pincode' => $cData['pincode'],
          'aadhaar_number' => $cData['aadhaar_number'],
          'location_id' => $cData['location_id'],
          'status' => 'pending',
        ]);

        KycDetail::create([
          'client_id' => $client->id,
          'aadhaar_number' => $cData['aadhaar_number'],
          'pan_number' => $cData['pan_number'],
          'account_number' => $cData['account_number'],
          'ifsc_code' => $cData['ifsc_code'],
          'bank_name' => $cData['bank_name'],
          'branch_name' => $cData['branch_name'],
          'status' => 'pending',
          'aadhaar_verified' => false,
          'pan_verified' => false,
          'bank_verified' => false,
        ]);

        $importedCount++;
      }

      DB::commit();

      return response()->json([
        'success' => true,
        'message' => "Successfully imported {$importedCount} clients."
      ]);

    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Bulk import error: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'An error occurred during import: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * Bulk assign clients to a zone/location
   */
  public function bulkAssignZone(Request $request): JsonResponse
  {
    if (auth()->user()->hasRole('Agent')) {
      return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
    }

    $request->validate([
      'client_ids' => 'required|array',
      'client_ids.*' => 'required',
      'location_id' => 'nullable|exists:locations,id',
    ]);

    $location = $request->location_id ? \App\Models\Location::find($request->location_id) : null;
    $clientIds = $request->client_ids;
    $count = 0;

    DB::beginTransaction();
    try {
      foreach ($clientIds as $hashedId) {
        $clientId = \App\Support\HashId::decode($hashedId);
        $clientId = is_array($clientId) ? ($clientId[0] ?? $hashedId) : ($clientId ?? $hashedId);
        
        $client = Client::findOrFail($clientId);
        $client->update(['location_id' => $location ? $location->id : null]);
        $count++;
      }

      DB::commit();
      
      $zoneName = $location ? $location->name : 'N/A';
      return response()->json([
        'success' => true,
        'message' => "Successfully assigned {$count} clients to zone: {$zoneName}"
      ]);
    } catch (\Exception $e) {
      DB::rollBack();
      Log::error('Bulk client zone assignment failed', ['error' => $e->getMessage()]);
      return response()->json([
        'success' => false,
        'message' => 'Zone assignment failed: ' . $e->getMessage()
      ], 500);
    }
  }

  /**
   * List loan accounts or chit memberships for the client penalty popup.
   */
  public function penaltyAccounts(Request $request, Client $client): JsonResponse
  {
    if (! $this->canManageClientPenalties()) {
      return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $type = $request->query('type', 'loan');
    if (! in_array($type, ['loan', 'chit'], true)) {
      return response()->json(['success' => false, 'message' => 'Invalid type. Use loan or chit.'], 422);
    }

    if ($type === 'loan') {
      $accounts = $client->loanAccounts()
        ->orderByDesc('id')
        ->get()
        ->map(function ($account) {
          return [
            'id' => $account->id,
            'label' => $account->account_number ?: ('Loan #' . $account->id),
            'status' => $account->status,
            'amount' => (float) ($account->loan_amount ?? 0),
            'outstanding' => (float) ($account->outstanding_amount ?? 0),
            'penalty' => (float) ($account->penalty ?? 0),
            'penalty_type' => ($account->penalty_type === 'percentage') ? 'percentage' : 'fixed',
            'grace_period_days' => (int) ($account->grace_period_days ?? 0),
          ];
        })
        ->values();

      return response()->json([
        'success' => true,
        'client_name' => $client->client_name,
        'type' => 'loan',
        'accounts' => $accounts,
      ]);
    }

    $memberships = \App\Models\GroupMember::involvingClient($client->id)
      ->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
      ->with('group')
      ->orderByDesc('id')
      ->get()
      ->map(function ($member) use ($client) {
        $groupCode = $member->group?->group_code ?? 'CHT';
        $memberLabel = $member->memberNumberForClient((int) $client->id);

        return [
          'id' => $member->id,
          'label' => $groupCode . ' · Member #' . $memberLabel,
          'group_name' => $member->group?->group_code ?? '—',
          'status' => $member->status,
          'amount' => (float) ($member->group?->chit_value ?? 0),
          'penalty_enabled' => (bool) ($member->penalty_enabled ?? false),
          'penalty' => (float) ($member->penalty_value ?? 0),
          'penalty_type' => $member->penalty_type ?: 'fixed',
          'grace_period_days' => $member->penalty_grace_days !== null
            ? (int) $member->penalty_grace_days
            : null,
        ];
      })
      ->values();

    return response()->json([
      'success' => true,
      'client_name' => $client->client_name,
      'type' => 'chit',
      'accounts' => $memberships,
    ]);
  }

  /**
   * Apply fixed/% penalty to one loan account (settings + overdue EMIs).
   */
  public function applyLoanPenalty(Request $request): JsonResponse
  {
    if (! $this->canManageClientPenalties()) {
      return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $validated = $request->validate([
      'loan_account_id' => 'required|integer|exists:loan_accounts,id',
      'penalty_type' => 'required|in:fixed,percentage',
      'penalty_value' => 'required|numeric|min:0',
      'grace_period_days' => 'nullable|integer|min:0',
    ]);

    if ($validated['penalty_type'] === 'percentage' && (float) $validated['penalty_value'] > 100) {
      return response()->json([
        'success' => false,
        'message' => 'Percentage penalty cannot exceed 100%.',
      ], 422);
    }

    $loanAccount = \App\Models\LoanAccount::findOrFail($validated['loan_account_id']);

    try {
      $result = app(\App\Services\ClientPenaltyService::class)->applyLoanPenalty(
        $loanAccount,
        $validated['penalty_type'],
        (float) $validated['penalty_value'],
        array_key_exists('grace_period_days', $validated) ? (int) $validated['grace_period_days'] : null
      );

      return response()->json([
        'success' => true,
        'message' => $result['updated_emis'] > 0
          ? "Penalty applied to {$result['updated_emis']} overdue EMI(s)."
          : 'Penalty settings saved. No overdue EMIs past grace period.',
        'data' => $result,
      ]);
    } catch (\Throwable $e) {
      Log::error('Client loan penalty apply failed', [
        'loan_account_id' => $loanAccount->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to apply loan penalty: ' . $e->getMessage(),
      ], 500);
    }
  }

  /**
   * Apply fixed/% penalty to one chit membership (settings + overdue installments).
   */
  public function applyChitPenalty(Request $request): JsonResponse
  {
    if (! $this->canManageClientPenalties()) {
      return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
    }

    $validated = $request->validate([
      'group_member_id' => 'required|integer|exists:group_members,id',
      'penalty_type' => 'required|in:fixed,percentage',
      'penalty_value' => 'required|numeric|min:0',
      'grace_period_days' => 'nullable|integer|min:0',
    ]);

    if ($validated['penalty_type'] === 'percentage' && (float) $validated['penalty_value'] > 100) {
      return response()->json([
        'success' => false,
        'message' => 'Percentage penalty cannot exceed 100%.',
      ], 422);
    }

    $member = \App\Models\GroupMember::findOrFail($validated['group_member_id']);

    try {
      $result = app(\App\Services\ClientPenaltyService::class)->applyChitPenalty(
        $member,
        $validated['penalty_type'],
        (float) $validated['penalty_value'],
        array_key_exists('grace_period_days', $validated) ? (int) $validated['grace_period_days'] : null
      );

      return response()->json([
        'success' => true,
        'message' => $result['updated_installments'] > 0
          ? "Penalty applied to {$result['updated_installments']} overdue installment(s)."
          : 'Penalty settings saved. No overdue installments past grace period.',
        'data' => $result,
      ]);
    } catch (\Throwable $e) {
      Log::error('Client chit penalty apply failed', [
        'group_member_id' => $member->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Failed to apply chit penalty: ' . $e->getMessage(),
      ], 500);
    }
  }

  protected function canManageClientPenalties(): bool
  {
    $user = auth()->user();

    return $user && ($user->hasRole('Admin') || $user->hasRole('Staff'));
  }

  /**
   * Lightweight avatar URL for DataTables (avoids writing base64 selfies to disk).
   */
  protected function resolveClientListAvatarUrl(Client $client): string
  {
    if (! empty($client->profile_image)) {
      return url(\Illuminate\Support\Facades\Storage::url($client->profile_image));
    }

    $selfie = optional($client->kycDetail)->selfie_image;
    if (! empty($selfie)) {
      if (str_starts_with($selfie, 'data:') || filter_var($selfie, FILTER_VALIDATE_URL)) {
        return $selfie;
      }

      return url(\Illuminate\Support\Facades\Storage::url($selfie));
    }

    $name = $client->client_name ?: 'User';

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&size=64&background=696cff&color=fff';
  }
}