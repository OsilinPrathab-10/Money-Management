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
      'activeGateways' => $activeGateways
    ]);
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
        $client->update(['assigned_to' => $agent->id]);

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

    DB::beginTransaction();
    try {
      foreach ($clientIds as $hashedId) {
        $clientId = \App\Support\HashId::decode($hashedId);
        $clientId = is_array($clientId) ? ($clientId[0] ?? $hashedId) : ($clientId ?? $hashedId);
        
        $client = Client::findOrFail($clientId);
        $client->delete();
        $count++;
      }

      DB::commit();
      return response()->json([
        'success' => true,
        'message' => "Successfully deleted {$count} clients."
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
      2 => 'id',
      3 => 'client_name',
      4 => 'client_email',
      5 => 'client_phone',
      6 => 'location_id',
      7 => 'assigned_to',
      8 => 'status',
    ];

      $query = \App\Models\Client::with(['location', 'agent', 'creator']);

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
      $totalFiltered = $totalData;

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
      // Search handling
    if (!empty($request->input('search.value'))) {
      $search = $request->input('search.value');

      $query->where(function ($q) use ($search) {
        $q->where('id', 'LIKE', "%{$search}%")
          ->orWhere('client_name', 'LIKE', "%{$search}%")
          ->orWhere('client_email', 'LIKE', "%{$search}%")
          ->orWhere('client_phone', 'LIKE', "%{$search}%");
      });

      $totalFiltered = $query->count();
    }

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
        'name' => $client->client_name,
        'email' => $client->client_email,
        'mobile' => $client->client_phone ?? 'N/A',
        'zone' => $client->location ? $client->location->name : 'N/A',
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
        'email' => 'nullable|email|unique:clients,client_email',
        'phone' => ['required', 'string', 'regex:/^[0-9]{10}$/', 'unique:clients,client_phone'],
        'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'address' => 'nullable|string',
        'date_of_birth' => 'nullable|date',
        'gender' => 'nullable|string|in:male,female,other',
        'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
        'company_name' => ['nullable', 'regex:/^(?=.*[A-Za-z])[A-Za-z0-9&().,\-\s\']+$/'],
        'monthly_salary' => 'nullable|numeric',
        'business_name' => 'nullable|string',
        'monthly_income' => 'nullable|numeric',
        'city' => 'nullable|string|max:255',
        'state' => 'nullable|string|max:255',
        'pincode' => ['nullable', 'string', 'regex:/^[0-9]{6}$/'],
        'aadhar_number' => ['nullable', 'string', 'regex:/^[0-9]{12}$/', 'unique:clients,aadhaar_number'],
        'pan_number' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
        'account_number' => 'nullable|string',
        'ifsc_code' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
        'bank_name' => 'nullable|string',
        'account_type' => 'nullable|string',
        'employment_type' => 'nullable|in:salaried,business',
        'nominee1_name' => 'nullable|string',
        'nominee1_relationship' => 'nullable|string',
        'nominee1_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'nominee2_name' => 'nullable|string',
        'nominee2_relationship' => 'nullable|string',
        'nominee2_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        'selfie_photo' => 'nullable|image|max:5120',
        'aadhar_photo_front' => 'nullable|file|max:5120',
        'aadhar_photo_back' => 'nullable|file|max:5120',
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
        'ifsc_code.regex' => 'Invalid IFSC format. It must be 11 characters (e.g., HDFC0001234).',
        'pan_number.regex' => 'Invalid PAN format. It must be 10 characters (e.g., ABCDE1234F).',
        'aadhar_number.regex' => 'Aadhar number must be exactly 12 digits.',
        'phone.regex' => 'Phone number must be exactly 10 digits.',
        'nominee1_mobile.regex' => 'Nominee mobile must be exactly 10 digits.',
        'guarantorPhone.required' => 'Guarantor phone number is mandatory.',
        'guarantorPhone.regex' => 'Guarantor phone must be exactly 10 digits.',
        'guarantorName.required' => 'Guarantor name is mandatory.',
        'guarantorRelationship.required' => 'Guarantor relationship is mandatory.',
        'company_name.regex' => 'Company name must contain letters and can include numbers/spaces/safe symbols.',
        'monthly_salary.required_if' => 'Monthly net salary is required for salaried applicants.'
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
        KycDetail::create([
          'client_id' => $client->id,
          'aadhaar_number' => $cleanAadhaar,
          'aadhaar_name' => $validated['name'],
          'aadhaar_image' => $paths['aadhar_front'] ?? null,
          'aadhaar_image_back' => $paths['aadhar_back'] ?? null,
          'selfie_image' => $paths['selfie'] ?? null,
          'pan_number' => !empty($validated['pan_number']) ? $validated['pan_number'] : null,
          'pan_name' => $validated['name'] ?? null,
          'pan_image' => $paths['pan'] ?? null,
          'account_holder_name' => !empty($request->account_holder) ? $request->account_holder : null,
          'account_number' => !empty($validated['account_number']) ? $validated['account_number'] : null,
          'ifsc_code' => !empty($validated['ifsc_code']) ? $validated['ifsc_code'] : null,
          'bank_name' => !empty($validated['bank_name']) ? $validated['bank_name'] : null,
          'account_type' => !empty($validated['account_type']) ? $validated['account_type'] : null,
          'bank_statement' => $paths['bank_statement'] ?? null,
          'status' => 'pending',
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

      } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Client Registration Error: ' . $e->getMessage());
        return response()->json([
          'success' => false,
          'message' => 'Server error: ' . $e->getMessage()
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
          'unique:kyc_details,account_number,' . ($kyc ? $kyc->id : 'NULL'),
        ],
        'formValidationIFSC' => [
          'nullable',
          'string',
          'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/',
        ],
      ]);

      DB::beginTransaction();

      // Update Client
      $client->update([
        'client_name' => $validated['formValidationName'],
        'client_email' => $validated['formValidationEmail'],
        'client_phone' => $validated['formValidationMobile'],
        'address' => $request->formValidationAddress,
        'aadhaar_number' => $validated['formValidationAadhar'],
      ]);

      // Update basic fields in associated User model
      if ($client->user) {
        $client->user->update([
          'name' => $validated['formValidationName'],
          'email' => $validated['formValidationEmail'],
          'phone' => $validated['formValidationMobile'],
        ]);
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
  public function destroy($id): JsonResponse
  {
    try {
      $decodedId = \App\Support\HashId::decode($id);
      $realId = is_array($decodedId) ? ($decodedId[0] ?? $id) : ($decodedId ?? $id);

      $client = Client::findOrFail($realId);
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
      $client = Client::findOrFail($id);
      $newStatus = ($client->status === 'active' || $client->status === 'verified') ? 'inactive' : 'active';
      $client->update(['status' => $newStatus]);
      return response()->json(['success' => true, 'message' => 'Status updated to ' . $newStatus, 'status' => $newStatus]);
    } catch (\Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
  }
}