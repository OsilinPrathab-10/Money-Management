<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Client;
use App\Models\KycDetail;
use App\Models\Nominee;
use App\Models\Guarantor;
use App\Models\EmployeeInformation;
use App\Models\Location;
use App\Models\GroupMember;
use App\Services\OpenLoanCycleService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class ClientManagementControllerApi extends Controller
{
    /**
     * Display a listing of the clients assigned to or created by the agent.
     */
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('id')) {
            return $this->show($request->id);
        }
        if ($request->filled('client_id')) {
            return $this->show($request->client_id);
        }

        $agentUser = Auth::user();
        if (!$agentUser) {
            return response()->json(['success' => false, 'message' => 'Agent data not found'], 404);
        }

        $agentId = $agentUser instanceof \App\Models\Agent ? $agentUser->id : optional(optional($agentUser)->agent)->id;
        $userId = $agentUser instanceof \App\Models\Agent ? $agentUser->user_id : optional($agentUser)->id;
        $isAdmin = $agentUser && ($agentUser instanceof \App\Models\User ? ($agentUser->hasRole(['admin', 'super_admin', 'super-admin', 'Super Admin']) || $agentUser->id === 1) : false);

        $query = Client::with(['location', 'kycDetail'])
            ->withCount([
                'loanAccounts as loan_accounts_count' => function ($q) {
                    $q->where('status', '!=', 'closed');
                },
                'groupMembers as group_members_count' => function ($q) {
                    $q->whereNotIn('status', \App\Models\GroupMember::INACTIVE_STATUSES)
                        ->whereHas('group', function ($gq) {
                            $gq->where('status', '!=', 'completed');
                        });
                },
            ]);

        // Filter by agent if not admin
        if (!$isAdmin) {
            $query->where(function ($q) use ($agentId, $userId) {
                if ($agentId) {
                    $q->where('assigned_to', $agentId)->orWhere('added_by', $agentId);
                }
                if ($userId) {
                    $q->orWhere('assigned_to', $userId)->orWhere('added_by', $userId);
                }
            });
        }

        // Location filter
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Search handling
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'LIKE', "%{$search}%")
                    ->orWhere('customer_id', 'LIKE', "%{$search}%")
                    ->orWhere('client_name', 'LIKE', "%{$search}%")
                    ->orWhere('client_email', 'LIKE', "%{$search}%")
                    ->orWhere('client_phone', 'LIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $clients = $query->orderBy('id', 'desc')->paginate($perPage);

        $data = collect($clients->items())->map(function ($client) {
            return [
                'id' => $client->id,
                'customer_id' => $client->displayCustomerId(),
                'hashed_id' => $client->getRouteKey(),
                'name' => $client->client_name,
                'client_name' => $client->client_name,
                'profile_image_url' => $this->resolveClientListAvatarUrl($client),
                'email' => $client->client_email ?? '',
                'client_email' => $client->client_email ?? '',
                'mobile' => $client->client_phone ?? 'N/A',
                'phone' => $client->client_phone ?? 'N/A',
                'client_phone' => $client->client_phone ?? 'N/A',
                'zone' => $client->location ? $client->location->name : 'N/A',
                'location_id' => $client->location_id,
                'loans_count' => (int) ($client->loan_accounts_count ?? 0),
                'chits_count' => (int) ($client->group_members_count ?? 0),
                'status' => $client->status ?? 'inactive',
                'status_label' => ucfirst($client->status ?? 'inactive'),
                'kyc_status' => optional($client->kycDetail)->status ?? 'pending',
                'agent_id' => $client->assigned_to,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ]
        ]);
    }

    /**
     * Get Zones & Areas
     */
    public function zones(Request $request): JsonResponse
    {
        $locations = Location::orderBy('name')->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $locations
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
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
            if ($request->exists('alternate_phone')) {
                $request->merge(['alternate_phone' => Client::normalizeOptionalPhone($request->input('alternate_phone'))]);
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
                'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'unique:clients,alternate_phone'],
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

            if ($request->filled('company_name') && $request->filled('business_name')) {
                throw ValidationException::withMessages([
                    'employment_type' => ['Please select only one employment type.']
                ]);
            }

            DB::beginTransaction();

            try {
                // 1. Create Client
                $cleanPhone = preg_replace('/\s+/', '', $validated['phone']);
                $cleanAadhaar = !empty($validated['aadhar_number']) ? preg_replace('/\s+/', '', $validated['aadhar_number']) : null;

                $agent = Auth::user();

                $client = Client::create([
                    'client_name' => $validated['name'],
                    'client_email' => !empty($validated['email']) ? $validated['email'] : null,
                    'client_phone' => $cleanPhone,
                    'alternate_phone' => Client::normalizeOptionalPhone($request->input('alternate_phone')),
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
                    'added_by' => $agent->id,
                    'assigned_to' => $agent->id,
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
                Log::error('Agent API Client Registration Database Error: ' . $e->getMessage());
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
                Log::error('Agent API Client Registration Error: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'An unexpected error occurred during registration: ' . $e->getMessage()
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Agent API Registration Validation Failed: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        }
    }

    public function show($id): JsonResponse
    {
        $agentUser = Auth::user();
        $agentId = $agentUser instanceof \App\Models\Agent ? $agentUser->id : optional(optional($agentUser)->agent)->id;
        $userId = $agentUser instanceof \App\Models\Agent ? $agentUser->user_id : optional($agentUser)->id;
        $isAdmin = $agentUser && ($agentUser instanceof \App\Models\User ? ($agentUser->hasRole(['admin', 'super_admin', 'super-admin', 'Super Admin']) || $agentUser->id === 1) : false);

        $client = Client::with([
            'kycDetail', 'nominee', 'guarantors', 'employeeInformation', 'location',
            'loanApplications.product.loanType', 'loanAccounts.product.loanType', 'loanAccounts.loanApplication', 'loanAccounts.emis'
        ])->findOrFail($id);

        if (! $isAdmin && $client->assigned_to != $agentId && $client->added_by != $agentId && $client->assigned_to != $userId && $client->added_by != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to this client.'], 403);
        }

        $clientId = (int) $client->id;

        $memberships = GroupMember::with(['group.scheme', 'shares.client', 'payouts', 'installments.collections'])
            ->involvingClient($clientId)
            ->orderByDesc('created_at')
            ->get();

        $formatLoanApplication = function (\App\Models\LoanApplication $app) {
            $product = $app->product;
            return [
                'id' => $app->id,
                'application_number' => $app->application_number,
                'loan_product_id' => $app->loan_product_id,
                'product_name' => $product->loan_name ?? '—',
                'loan_type_name' => optional($product?->loanType)->name ?? '—',
                'loan_amount' => (float) $app->loan_amount,
                'interest_rate' => (float) ($app->interest_rate ?? $product?->interest_rate ?? 0),
                'tenure' => (int) $app->tenure,
                'status' => $app->status,
                'status_label' => Str::title(str_replace('_', ' ', $app->status ?? 'pending')),
                'applied_at' => optional($app->created_at)?->format('Y-m-d'),
            ];
        };

        $formatLoanAccount = function (\App\Models\LoanAccount $acc) {
            $product = $acc->product;
            $frequency = $acc->frequency;
            $frequencyLabel = $acc->frequency_label;
            $isOpenLoan = ($acc->loan_mode ?? '') === 'interest_only' || (int) $acc->tenure === 0;

            if ($isOpenLoan && ($acc->loan_mode ?? '') === 'interest_only' && $acc->status === 'active') {
                app(OpenLoanCycleService::class)->syncDueCycles($acc);
                $acc->load('emis');
            }

            $accountNumber = $acc->account_number 
                ?? $acc->customer_loan_account_number 
                ?? $acc->loan_account_number 
                ?? '—';

            $sanctionedAmount = (float) ($acc->loan_amount ?? $acc->sanctioned_amount ?? 0);
            $disbursedAmount = (float) ($acc->disbursed_amount ?? 0);
            $outstandingAmount = $isOpenLoan
                ? (float) $acc->remaining_principal_balance
                : (float) ($acc->outstanding_amount ?? 0);

            $emiAmount = (float) $acc->present_loan_amount;
            if ($isOpenLoan) {
                $nextEmi = $acc->relationLoaded('emis')
                    ? $acc->emis->whereNotIn('status', ['paid', 'closed'])->sortBy('instalment_number')->first()
                    : null;
                $cycleAmount = $nextEmi
                    ? (float) ($nextEmi->interest_amount ?: $nextEmi->total_amount ?: $nextEmi->pending_amount)
                    : round($outstandingAmount * ((float) $acc->interest_rate / 100), 2);
                $emiAmount = $cycleAmount > 0 ? $cycleAmount : $emiAmount;
            }

            $isClosed = $acc->isEffectivelyClosed();
            $effectiveStatus = $isClosed ? 'closed' : (($acc->status === 'closed' && $isOpenLoan) ? 'active' : ($acc->status ?? 'active'));

            return [
                'id' => $acc->id,
                'account_number' => $accountNumber,
                'client_id' => $acc->client_id,
                'loan_product_id' => $acc->loan_product_id ?? optional($product)->id,
                'product_name' => $product->loan_name ?? '—',
                'sanctioned_amount' => $sanctionedAmount,
                'disbursed_amount' => $disbursedAmount,
                'outstanding_amount' => $outstandingAmount,
                'total_payable' => (float) $acc->total_payable,
                'frequency' => $frequency,
                'frequency_label' => $frequencyLabel,
                'emi_amount' => $emiAmount,
                'tenure' => (int) $acc->tenure,
                'tenure_label' => $isOpenLoan ? 'Open Loan' : ((int) $acc->tenure . ' ' . $frequencyLabel),
                'interest_rate' => (float) $acc->interest_rate,
                'is_open_loan' => $isOpenLoan,
                'loan_mode' => $isOpenLoan ? 'interest_only' : ($acc->loan_mode ?? 'emi'),
                'loan_mode_label' => $isOpenLoan ? 'Open Loan' : 'EMI',
                'principal_amount' => $isOpenLoan ? $outstandingAmount : null,
                'status' => $effectiveStatus,
                'status_label' => $isClosed ? 'Closed' : Str::title(str_replace('_', ' ', $effectiveStatus)),
                'disbursed_at' => optional($acc->disbursed_at)?->format('Y-m-d'),
            ];
        };

        $formatChitMember = function (GroupMember $member, bool $withSchedule = false) use ($clientId) {
            $group = $member->group;
            $scheme = $group ? $group->scheme : null;

            $isShared = (bool) $member->is_shared;
            $ownershipPct = (float) $member->ownershipPercentageFor($clientId);
            if ($ownershipPct <= 0) {
                $ownershipPct = (float) ($member->effective_share_percentage ?? $member->share_percentage ?? 100);
            }

            $fullChitValue = (float) ($group->chit_value ?? 0);
            $chitValueShare = $isShared
                ? (float) $member->amountForClient($fullChitValue, $clientId)
                : round($fullChitValue * ($ownershipPct / 100.0), 2);

            $clientInstallment = (float) $member->apiInstallmentAmountForClient($clientId, $group);

            $fullSettlement = (float) $member->settlement_amount;
            $clientSettlement = $isShared
                ? (float) $member->amountForClient($fullSettlement, $clientId)
                : round($fullSettlement * ($ownershipPct / 100.0), 2);

            $isCompleted = in_array(strtolower((string) $member->status), ['completed', 'closed'], true)
                || ($group && strtolower((string) $group->status) === 'completed')
                || ($group && (int) $group->current_month >= (int) $group->total_months && (int) $group->total_months > 0);

            $effectiveStatus = $isCompleted ? 'completed' : ($member->status ?? 'pending');
            $freq = $member->collection_frequency ?? 'monthly';

            [$dueDate, $nextDueDate] = $this->chitCurrentDueWindow($member);
            $installmentAmount = $member->collectionSplitAmount($clientInstallment, $dueDate, $nextDueDate);

            $item = [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'group_id' => $member->group_id,
                'group_code' => $group->group_code ?? '—',
                'group_status' => $group->status ?? '—',
                'scheme_name' => $scheme->name ?? '—',
                'is_shared' => $isShared,
                'share_percentage' => $ownershipPct,
                'chit_value' => $chitValueShare,
                'installment_amount' => $installmentAmount,
                'collection_frequency' => $freq,
                'collection_frequency_label' => $member->collection_frequency_label ?? ucfirst($freq),
                'total_months' => (int) ($group->total_months ?? 0),
                'settlement_amount' => $clientSettlement,
                'status' => $effectiveStatus,
                'status_label' => ucfirst($effectiveStatus),
                'applied_at' => $member->created_at ? $member->created_at->format('Y-m-d') : '—',
                'joined_date' => optional($member->joined_date)->format('Y-m-d') ?? '—',
                'chit_need_month_label' => $member->chit_need_month_label,
            ];

            if (! $withSchedule) {
                return $item;
            }

            $window = $member->collectionWindow($dueDate, $nextDueDate);
            $paidAmt = 0.0;
            $pendingAmt = 0.0;
            $currentInst = $this->chitCurrentInstallment($member);
            if ($currentInst) {
                $paidAmt = (float) $currentInst->clientPaidShare($clientId);
                $pendingAmt = (float) $currentInst->pendingCollectedAmount($clientId);
            }

            $collectionPeriods = [];
            if (in_array($freq, ['daily', 'weekly'], true)) {
                $collectionPeriods = $this->serializeChitCollectionPeriods(
                    $member->collectionPeriodSchedule(
                        $dueDate,
                        $clientInstallment,
                        $paidAmt,
                        $pendingAmt,
                        $nextDueDate
                    )
                );
            }

            $item['collection_split_count'] = $member->collectionSplitCount($dueDate, $nextDueDate);
            $item['collection_window_start'] = $window['start']?->format('Y-m-d');
            $item['collection_window_end'] = $window['end']?->format('Y-m-d');
            $item['collection_periods'] = $collectionPeriods;
            $item['month_wise_installments'] = $this->chitMonthSummaries($member, $clientId);

            return $item;
        };

        $kyc = $client->kycDetail;
        $nominee = $client->nominee;
        $emp = $client->employeeInformation;

        $clientData = [
            'id' => $client->id,
            'customer_id' => $client->displayCustomerId(),
            'hashed_id' => $client->getRouteKey(),
            'name' => $client->client_name,
            'client_name' => $client->client_name,
            'email' => $client->client_email ?? '',
            'mobile' => $client->client_phone ?? '',
            'phone' => $client->client_phone ?? '',
            'alternate_phone' => $client->alternate_phone ?? '',
            'profile_image_url' => $this->resolveClientListAvatarUrl($client),
            'aadhaar_number' => $client->aadhaar_number,
            'gender' => $client->gender,
            'date_of_birth' => optional($client->date_of_birth)->format('Y-m-d'),
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'pincode' => $client->pincode,
            'marital_status' => $client->marital_status,
            'cibil_score' => $client->cibil_score,
            'location_id' => $client->location_id,
            'zone' => $client->location ? $client->location->name : 'N/A',
            'collection_day' => $client->collection_day,
            'risk_level' => $client->risk_level ?? 'low',
            'assigned_to' => $client->assigned_to,
            'status' => $client->status ?? 'active',
            'status_label' => ucfirst($client->status ?? 'active'),
            'remarks' => $client->remarks,
            'created_at' => optional($client->created_at)->format('Y-m-d H:i:s'),

            'kyc_detail' => $kyc ? [
                'id' => $kyc->id,
                'client_id' => $kyc->client_id,
                'aadhaar_number' => $kyc->aadhaar_number,
                'aadhaar_name' => $kyc->aadhaar_name,
                'aadhaar_image' => $kyc->aadhaar_image,
                'aadhaar_image_back' => $kyc->aadhaar_image_back,
                'aadhaar_verified' => (bool) $kyc->aadhaar_verified,
                'selfie_image' => $kyc->selfie_image,
                'pan_number' => $kyc->pan_number,
                'pan_name' => $kyc->pan_name,
                'pan_image' => $kyc->pan_image,
                'pan_verified' => (bool) $kyc->pan_verified,
                'account_holder_name' => $kyc->account_holder_name,
                'account_number' => $kyc->account_number,
                'ifsc_code' => $kyc->ifsc_code,
                'account_type' => $kyc->account_type,
                'bank_name' => $kyc->bank_name,
                'branch_name' => $kyc->branch_name,
                'bank_verified' => (bool) $kyc->bank_verified,
                'bank_statement' => $kyc->bank_statement,
                'status' => $kyc->status ?? 'pending',
            ] : null,

            'nominee' => $nominee ? [
                'id' => $nominee->id,
                'nominee1_name' => $nominee->nominee1_name,
                'nominee1_relationship' => $nominee->nominee1_relationship,
                'nominee1_mobile' => $nominee->nominee1_mobile,
                'nominee2_name' => $nominee->nominee2_name,
                'nominee2_relationship' => $nominee->nominee2_relationship,
                'nominee2_mobile' => $nominee->nominee2_mobile,
            ] : null,

            'employee_information' => $emp ? [
                'id' => $emp->id,
                'employment_type' => $emp->employment_type,
                'company_name' => $emp->company_name,
                'monthly_salary' => $emp->monthly_salary ? (float) $emp->monthly_salary : null,
                'business_name' => $emp->business_name,
                'monthly_turnover' => $emp->monthly_turnover ? (float) $emp->monthly_turnover : null,
                'business_address' => $emp->business_address,
                'gst_number' => $emp->gst_number,
            ] : null,

            'guarantors' => $client->guarantors->map(function ($g) {
                return [
                    'id' => $g->id,
                    'name' => $g->name,
                    'phone' => $g->phone,
                    'relationship' => $g->relationship,
                    'address' => $g->address,
                ];
            })->values(),

            'loan_accounts' => $client->loanAccounts->map($formatLoanAccount)->values(),
            'applications' => [
                'loan' => $client->loanApplications->map($formatLoanApplication)->values(),
                'chits' => $memberships->map(fn (GroupMember $member) => $formatChitMember($member, false))->values(),
            ],
            'chit_accounts' => $memberships
                ->whereIn('status', ['active', 'approved', 'completed', 'defaulted'])
                ->values()
                ->map(fn (GroupMember $member) => $formatChitMember($member, true)),
        ];

        return response()->json([
            'success' => true,
            'data' => $clientData
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $agent = Auth::user();
            $client = Client::findOrFail($id);

            if ($client->assigned_to != $agent->id && $client->added_by != $agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this client.'], 403);
            }

            $kyc = $client->kycDetail;

            // Clean masked inputs before validation
            if ($request->has('phone')) $request->merge(['phone' => preg_replace('/\s+/', '', $request->phone)]);
            if ($request->has('aadhar_number')) $request->merge(['aadhar_number' => preg_replace('/\s+/', '', $request->aadhar_number)]);
            if ($request->has('pan_number')) $request->merge(['pan_number' => strtoupper(preg_replace('/\s+/', '', $request->pan_number))]);
            if ($request->has('ifsc_code')) $request->merge(['ifsc_code' => strtoupper(preg_replace('/\s+/', '', $request->ifsc_code))]);
            if ($request->has('account_number')) $request->merge(['account_number' => preg_replace('/\s+/', '', $request->account_number)]);
            if ($request->exists('alternate_phone')) {
                $request->merge(['alternate_phone' => Client::normalizeOptionalPhone($request->input('alternate_phone'))]);
            }
            if ($request->has('pincode')) $request->merge(['pincode' => preg_replace('/\s+/', '', $request->pincode)]);
            if ($request->has('nominee1_mobile')) $request->merge(['nominee1_mobile' => preg_replace('/\s+/', '', $request->nominee1_mobile)]);
            if ($request->has('nominee2_mobile')) $request->merge(['nominee2_mobile' => preg_replace('/\s+/', '', $request->nominee2_mobile)]);

            $validated = $request->validate([
                'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
                'email' => 'nullable|email|unique:clients,client_email,' . $id,
                'phone' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{10}$/', 'unique:clients,client_phone,' . $id],
                'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'unique:clients,alternate_phone,' . $id],
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
                'aadhar_number' => ['sometimes', 'required', 'string', 'regex:/^[0-9]{12}$/', 'unique:clients,aadhaar_number,' . $id],
                'pan_number' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', 'unique:kyc_details,pan_number,' . ($kyc ? $kyc->id : 'NULL')],
                'account_holder' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z\s]+$/'],
                'account_number' => ['sometimes', 'required', 'string', 'regex:/^[0-9]+$/', 'unique:kyc_details,account_number,' . ($kyc ? $kyc->id : 'NULL')],
                'ifsc_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
                'bank_name' => ['nullable', 'string', 'max:255'],
                'branch_name' => ['sometimes', 'required', 'string', 'max:255'],
                'account_type' => ['nullable', 'string', 'in:savings,current'],
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
                'location_id' => 'sometimes|required|exists:locations,id',
                'collection_day' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday,Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            ]);

            if ($request->filled('company_name') && $request->filled('business_name')) {
                throw ValidationException::withMessages([
                    'employment_type' => ['Please select only one employment type.']
                ]);
            }

            DB::beginTransaction();

            $clientData = [];
            if ($request->has('name')) $clientData['client_name'] = $validated['name'];
            if ($request->has('email')) $clientData['client_email'] = $validated['email'] ?? null;
            if ($request->has('phone')) $clientData['client_phone'] = $validated['phone'];
            if ($request->exists('alternate_phone')) {
                $clientData['alternate_phone'] = Client::normalizeOptionalPhone($validated['alternate_phone'] ?? null);
            }
            if ($request->has('address')) $clientData['address'] = $validated['address'] ?? null;
            if ($request->has('date_of_birth')) $clientData['date_of_birth'] = !empty($validated['date_of_birth']) ? \Carbon\Carbon::createFromFormat('d-m-Y', str_replace('/', '-', $validated['date_of_birth']))->format('Y-m-d') : null;
            if ($request->has('gender')) $clientData['gender'] = $validated['gender'] ?? null;
            if ($request->has('marital_status')) $clientData['marital_status'] = $validated['marital_status'] ?? null;
            if ($request->has('city')) $clientData['city'] = $validated['city'] ?? null;
            if ($request->has('state')) $clientData['state'] = $validated['state'] ?? null;
            if ($request->has('pincode')) $clientData['pincode'] = $validated['pincode'] ?? null;
            if ($request->has('aadhar_number')) $clientData['aadhaar_number'] = $validated['aadhar_number'];
            if ($request->has('location_id')) $clientData['location_id'] = $validated['location_id'];
            if ($request->has('collection_day')) $clientData['collection_day'] = !empty($validated['collection_day']) ? ucfirst(strtolower($validated['collection_day'])) : null;

            if (!empty($clientData)) {
                $client->update($clientData);
            }

            // Update basic fields in associated User model
            if ($client->user) {
                $userData = [];
                if ($request->has('name')) $userData['name'] = $validated['name'];
                if ($request->has('email')) $userData['email'] = $validated['email'] ?? null;
                if ($request->has('phone')) $userData['phone'] = $validated['phone'];
                if (!empty($userData)) {
                    $client->user->update($userData);
                }
            }

            // Handle File Uploads
            $paths = [];
            if ($request->hasFile('selfie_photo')) $paths['selfie'] = $request->file('selfie_photo')->store('kyc/selfie/' . $client->id, 'public');
            if ($request->hasFile('aadhar_photo_front')) $paths['aadhar_front'] = $request->file('aadhar_photo_front')->store('kyc/aadhar/' . $client->id, 'public');
            if ($request->hasFile('aadhar_photo_back')) $paths['aadhar_back'] = $request->file('aadhar_photo_back')->store('kyc/aadhar/' . $client->id, 'public');
            if ($request->hasFile('pan_photo')) $paths['pan'] = $request->file('pan_photo')->store('kyc/pan/' . $client->id, 'public');
            if ($request->hasFile('bank_statement')) $paths['bank_statement'] = $request->file('bank_statement')->store('kyc/bank_statement/' . $client->id, 'public');
            if ($request->hasFile('payslip')) $paths['payslip'] = $request->file('payslip')->store('kyc/payslip/' . $client->id, 'public');
            if ($request->hasFile('business_document')) $paths['business_document'] = $request->file('business_document')->store('kyc/business_proof/' . $client->id, 'public');

            // Update KYC record
            if ($kyc) {
                $kycData = [];
                if ($request->has('aadhar_number')) $kycData['aadhaar_number'] = $validated['aadhar_number'];
                if ($request->has('name')) $kycData['aadhaar_name'] = $validated['name'];
                if (isset($paths['aadhar_front'])) $kycData['aadhaar_image'] = $paths['aadhar_front'];
                if (isset($paths['aadhar_back'])) $kycData['aadhaar_image_back'] = $paths['aadhar_back'];
                if (isset($paths['selfie'])) $kycData['selfie_image'] = $paths['selfie'];
                
                if ($request->has('pan_number')) $kycData['pan_number'] = $validated['pan_number'] ?? null;
                if ($request->has('name')) $kycData['pan_name'] = $validated['name'] ?? null;
                if (isset($paths['pan'])) $kycData['pan_image'] = $paths['pan'];

                if ($request->has('account_holder')) $kycData['account_holder_name'] = $validated['account_holder'] ?? null;
                if ($request->has('account_number')) $kycData['account_number'] = $validated['account_number'] ?? null;
                if ($request->has('ifsc_code')) $kycData['ifsc_code'] = $validated['ifsc_code'] ?? null;
                if ($request->has('bank_name')) $kycData['bank_name'] = $validated['bank_name'] ?? null;
                if ($request->has('branch_name')) $kycData['branch_name'] = $validated['branch_name'] ?? null;
                if ($request->has('account_type')) $kycData['account_type'] = $validated['account_type'] ?? null;
                if (isset($paths['bank_statement'])) $kycData['bank_statement'] = $paths['bank_statement'];

                if (!empty($kycData)) {
                    $kyc->update($kycData);
                }
            } else {
                // Create KYC if missing
                $hasPan = !empty($validated['pan_number']);
                $hasBank = !empty($validated['account_number']);
                KycDetail::create([
                    'client_id' => $client->id,
                    'aadhaar_number' => $validated['aadhar_number'] ?? $client->aadhaar_number,
                    'aadhaar_name' => $validated['name'] ?? $client->client_name,
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
                ]);
            }

            // Update Nominee
            $nomineeData = [];
            if ($request->has('nominee1_name')) $nomineeData['nominee1_name'] = $validated['nominee1_name'] ?? 'N/A';
            if ($request->has('nominee1_relationship')) $nomineeData['nominee1_relationship'] = $validated['nominee1_relationship'] ?? 'N/A';
            if ($request->has('nominee1_mobile')) $nomineeData['nominee1_mobile'] = $validated['nominee1_mobile'] ?? 'N/A';
            if ($request->has('nominee2_name')) $nomineeData['nominee2_name'] = $validated['nominee2_name'] ?? null;
            if ($request->has('nominee2_relationship')) $nomineeData['nominee2_relationship'] = $validated['nominee2_relationship'] ?? null;
            if ($request->has('nominee2_mobile')) $nomineeData['nominee2_mobile'] = $validated['nominee2_mobile'] ?? null;
            
            if (!empty($nomineeData)) {
                if ($client->nominee) {
                    $client->nominee->update($nomineeData);
                } else {
                    $nomineeData['client_id'] = $client->id;
                    Nominee::create($nomineeData);
                }
            }

            // Update Guarantors/Referrals
            if ($request->has('guarantorName') || $request->has('guarantorPhone') || $request->has('guarantorRelationship')) {
                Guarantor::where('client_id', $client->id)->where('type', 'guarantor')->delete();
                Guarantor::create([
                    'client_id' => $client->id,
                    'name' => $validated['guarantorName'] ?? 'N/A',
                    'phone' => $validated['guarantorPhone'] ?? 'N/A',
                    'relationship' => $validated['guarantorRelationship'] ?? 'N/A',
                    'type' => 'guarantor'
                ]);
            }
            if ($request->has('referralName') || $request->has('referralPhone') || $request->has('referralRelationship')) {
                Guarantor::where('client_id', $client->id)->where('type', 'referral')->delete();
                Guarantor::create([
                    'client_id' => $client->id,
                    'name' => $validated['referralName'] ?? 'N/A',
                    'phone' => $validated['referralPhone'] ?? null,
                    'relationship' => $validated['referralRelationship'] ?? 'Associate',
                    'type' => 'referral'
                ]);
            }

            // Update Employee Information
            if ($request->has('employment_type') || $request->has('company_name') || $request->has('business_name')) {
                $empData = [];
                if ($request->has('employment_type')) {
                    $empData['employment_type'] = ($validated['employment_type'] ?? null) === 'business' ? 'self_employed' : 'salaried';
                }
                
                if (($validated['employment_type'] ?? null) === 'salaried' || ($client->employeeInformation && $client->employeeInformation->employment_type === 'salaried')) {
                    if ($request->has('company_name')) $empData['company_name'] = $validated['company_name'];
                    if ($request->has('monthly_salary')) $empData['monthly_salary'] = $validated['monthly_salary'];
                    if (isset($paths['payslip'])) $empData['payslip_documents'] = [$paths['payslip']];
                } else {
                    if ($request->has('business_name')) $empData['business_name'] = $validated['business_name'];
                    if ($request->has('monthly_income')) $empData['monthly_turnover'] = $validated['monthly_income'];
                    if (isset($paths['business_document'])) $empData['business_proof_documents'] = [$paths['business_document']];
                }

                if (!empty($empData)) {
                    if ($client->employeeInformation) {
                        $client->employeeInformation->update($empData);
                    } else {
                        $empData['client_id'] = $client->id;
                        EmployeeInformation::create($empData);
                    }
                }
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
     */
    public function destroy($id): JsonResponse
    {
        try {
            $agent = Auth::user();
            $client = Client::findOrFail($id);

            if ($client->assigned_to != $agent->id && $client->added_by != $agent->id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized access to this client.'], 403);
            }

            if ($blockReason = $client->deletionBlockReason()) {
                return response()->json([
                    'success' => false,
                    'blocked' => true,
                    'message' => $blockReason,
                ], 422);
            }

            $client->delete();

            return response()->json(['success' => true, 'message' => 'Client deleted successfully.'], 200);
        } catch (\Exception $e) {
            Log::error('Agent API Client deletion failed', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Lightweight avatar URL
     */
    protected function resolveClientListAvatarUrl(Client $client): string
    {
        if (!empty($client->profile_image)) {
            return url('storage/app/public/' . ltrim($client->profile_image, '/'));
        }

        $selfie = optional($client->kycDetail)->selfie_image;
        if (!empty($selfie)) {
            if (str_starts_with($selfie, 'data:') || filter_var($selfie, FILTER_VALIDATE_URL)) {
                return $selfie;
            }

            return url('storage/app/public/' . ltrim($selfie, '/'));
        }

        $name = $client->client_name ?: 'User';

        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&size=64&background=696cff&color=fff';
    }

    protected function chitCurrentInstallment(GroupMember $member): ?\App\Models\Installment
    {
        $installments = $member->relationLoaded('installments')
            ? $member->installments->sortBy('month_number')->values()
            : collect();

        if ($installments->isEmpty()) {
            return null;
        }

        return $installments->first(function ($inst) {
            return ! in_array((string) $inst->status, ['paid', 'waived'], true);
        }) ?? $installments->first();
    }

    /**
     * @return array{0: \Carbon\Carbon|null, 1: \Carbon\Carbon|null}
     */
    protected function chitCurrentDueWindow(GroupMember $member): array
    {
        $installments = $member->relationLoaded('installments')
            ? $member->installments->sortBy('month_number')->values()
            : collect();

        $current = $this->chitCurrentInstallment($member);
        $due = $current?->due_date
            ?? optional($member->group)->start_date
            ?? $member->joined_date;

        $next = null;
        if ($current) {
            $idx = $installments->search(fn ($inst) => (int) $inst->id === (int) $current->id);
            if ($idx !== false) {
                $next = optional($installments->get($idx + 1))->due_date;
            }
        }

        if (! $next && $due) {
            $next = \Carbon\Carbon::parse($due)->addMonthsNoOverflow(1);
        }

        return [
            $due ? \Carbon\Carbon::parse($due) : null,
            $next ? \Carbon\Carbon::parse($next) : null,
        ];
    }

    protected function serializeChitCollectionPeriods(array $periods): array
    {
        return array_map(static function (array $p) {
            $due = $p['due_date'] ?? null;
            $end = $p['period_end'] ?? null;

            return [
                'period_number' => (int) ($p['index'] ?? $p['period_number'] ?? 0),
                'label' => (string) ($p['label'] ?? $p['period_label'] ?? ''),
                'due_date' => $due ? \Carbon\Carbon::parse($due)->format('Y-m-d') : null,
                'period_end' => $end ? \Carbon\Carbon::parse($end)->format('Y-m-d') : null,
                'amount' => (float) ($p['amount'] ?? 0),
                'paid_amount' => (float) ($p['paid'] ?? $p['paid_amount'] ?? 0),
                'balance' => (float) ($p['balance'] ?? $p['due_amount'] ?? 0),
                'status' => (string) ($p['status'] ?? 'pending'),
                'is_current' => (bool) ($p['is_current'] ?? false),
                'is_next' => (bool) ($p['is_next'] ?? false),
            ];
        }, $periods);
    }

    /**
     * One row per chit month — no nested daily/weekly periods.
     */
    protected function chitMonthSummaries(GroupMember $member, int $clientId): array
    {
        $installments = $member->relationLoaded('installments')
            ? $member->installments->sortBy('month_number')->values()
            : collect();

        return $installments->map(function ($inst) use ($member, $clientId) {
            $pending = (float) $inst->pendingCollectedAmount($clientId);
            $balance = (float) $inst->clientBalanceShare($clientId);

            return [
                'id' => $inst->id,
                'month_number' => (int) $inst->month_number,
                'due_date' => optional($inst->due_date)?->format('Y-m-d'),
                'amount' => (float) $member->displayAmountForInstallment($inst, $clientId),
                'paid_amount' => (float) $inst->clientPaidShare($clientId),
                'balance' => round(max(0, $balance - $pending), 2),
                'status' => $inst->effectiveStatus($clientId),
                'pending_verification_amount' => $pending,
            ];
        })->values()->all();
    }
}
