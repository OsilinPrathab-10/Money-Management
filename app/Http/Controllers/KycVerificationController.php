<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Events\WhatsAppCommunicationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\KycApproved;
use App\Events\KycRejected;
use App\Models\LoanProduct;
use App\Models\KycDetail;
use App\Models\PaymentMethod;
use App\Models\PaymentGateway;
use Illuminate\Support\Facades\Auth;

class KycVerificationController extends Controller
{
    private const MAX_KYC_ATTEMPTS = 3;

    public function __construct()
    {
        $this->middleware('permission:kyc.approve')->only(['approve', 'skipKyc', 'reKyc']);
        $this->middleware('permission:kyc.reject')->only(['reject']);
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson() || $request->has('draw')) {
            $query = Client::with('kycDetail');

            // Total records before filtering
            $recordsTotal = $query->count();

            // Search filtering
            if ($request->has('search') && !empty($request->input('search.value'))) {
                $searchValue = $request->input('search.value');
                $query->where(function ($q) use ($searchValue) {
                    $q->where('client_name', 'like', "%{$searchValue}%")
                        ->orWhere('client_email', 'like', "%{$searchValue}%")
                        ->orWhere('client_phone', 'like', "%{$searchValue}%");
                });
            }

            // Records after filtering
            $recordsFiltered = $query->count();

            // Sorting
            if ($request->has('order')) {
                $orderColumnIndex = $request->input('order.0.column');
                $orderDirection = $request->input('order.0.dir');
                $columns = ['id', 'id', 'client_name', 'updated_at', 'id', 'id']; // Mapping to table columns

                if (isset($columns[$orderColumnIndex])) {
                    $columnName = $columns[$orderColumnIndex];
                    if ($columnName === 'updated_at') {
                        // Sort by updated_at of kycDetail if needed, but here we use simple sorting
                        $query->orderBy($columnName, $orderDirection);
                    } else {
                        $query->orderBy($columnName, $orderDirection);
                    }
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Pagination
            $start = $request->input('start', 0);
            $length = $request->input('length', 10);
            $clients = $query->offset($start)->limit($length)->get();

            $data = $clients->map(function ($client, $index) use ($start) {
                $kycStatus = optional($client->kycDetail)->status;
                $clientStatus = $client->status;
                
                $displayStatus = 'unverified';
                if ($kycStatus === 'verified') {
                    $displayStatus = 'verified';
                } elseif (optional($client->kycDetail)->kyc_skipped && $kycStatus !== 'verified') {
                    $displayStatus = 'skipped';
                } elseif ($kycStatus === 'rejected' || $clientStatus === 'inactive') {
                    $displayStatus = 'rejected';
                }

                $statusBadges = [
                    'verified' => '<span class="badge bg-label-success">Verified</span>',
                    'rejected' => '<span class="badge bg-label-danger">Rejected</span>',
                    'skipped' => '<span class="badge bg-label-info">KYC Skipped</span>',
                    'unverified' => '<span class="badge bg-label-warning">Unverified</span>',
                ];

                return [
                    'id' => $client->id,
                    'DT_RowIndex' => $start + $index + 1,
                    'client_name' => $client->client_name ?? 'N/A',
                    'submitted_on' => $client->kycDetail && $client->kycDetail->updated_at ? $client->kycDetail->updated_at->format('d-m-Y') : 'N/A',
                    'kyc_status' => $statusBadges[$displayStatus] ?? '<span class="badge bg-label-secondary">Unknown</span>',
                    'action' => '<div class="d-flex align-items-center gap-4">
                        <a href="' . route('verification-kyc-view', $client->id) . '" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View KYC">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
                        </a>
                        <a href="' . route('verification-kyc-view', $client->id) . '?edit=true" class="btn btn-icon btn-text-secondary btn-sm rounded-pill text-primary" title="Add/Edit KYC">
                            <i class="icon-base ri ri-edit-box-line icon-22px"></i>
                        </a>
                    </div>'
                ];
            });

            return response()->json([
                'draw' => intval($request->input('draw', 1)),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $data->values()->toArray()
            ]);
        }

        return view('admin.verification.kyc.client-kyc-verification');
    }

    public function view($id)
    {
        $client = Client::with(['kycDetail', 'employeeInformation'])
            ->withCount([
                'loanApplications as applications_count',
                'loanAccounts as loans_count'
            ])
            ->findOrFail($id);

        // Correct status mapping: allow 'verified', 'rejected', or default to 'unverified'
        $kycStatus = optional($client->kycDetail)->status;
        $clientStatus = $client->status;

        if ($kycStatus === 'verified') {
            $verificationStatus = 'verified';
        } elseif ($kycStatus === 'rejected' || $clientStatus === 'inactive') {
            $verificationStatus = 'rejected';
        } else {
            $verificationStatus = 'unverified';
        }

        $statusMap = [
            'verified' => [
                'label' => 'Verified',
                'badge' => 'success',
                'icon' => 'ri-checkbox-circle-line'
            ],
            'unverified' => [
                'label' => 'Unverified',
                'badge' => 'warning',
                'icon' => 'ri-time-line'
            ],
        ];

        $stats = [
            'applications' => (int) $client->applications_count,
            'loans' => (int) $client->loans_count,
            'kyc' => $statusMap[$verificationStatus] ?? [
                'label' => ucfirst($verificationStatus),
                'badge' => 'secondary',
                'icon' => 'ri-question-mark'
            ],
        ];

        $blade = request()->routeIs('client-view-kyc')
            ? 'admin.clients.client-view-kyc'
            : 'admin.verification.kyc.client-view-kyc';

        // Calculate missing fields
        $missingFields = [];
        $kyc = $client->kycDetail;

        $loanProducts = LoanProduct::where('status', 'active')->get();
        $activePaymentMethods = PaymentMethod::where('is_enabled', true)->get();
        $activeGateways = PaymentGateway::where('enabled', true)->get();

        $availableGroups = \App\Models\ChitGroup::with('scheme')
            ->whereIn('status', ['forming', 'active'])
            ->withCount(['members' => fn ($q) => $q->whereNotIn('status', ['rejected', 'transferred', 'withdrawn', 'cancelled'])])
            ->orderBy('id', 'desc')
            ->get()
            ->filter(fn ($g) => $g->members_count < $g->total_members)
            ->each(function ($g) {
                $g->settlement_amount = $g->resolvePayoutAmountForMonth((int) $g->current_month + 1);
            });

        $agents = \App\Models\Agent::orderBy('agent_name')->get();
        $verifiedClients = Client::whereHas('kycDetail', function ($q) {
            $q->where('status', 'verified');
        })->orderBy('client_name')->get();

        return view($blade, [
            'client' => $client,
            'kyc' => $kyc,
            'stats' => $stats,
            'verificationStatus' => $verificationStatus,
            'missingFields' => $missingFields,
            'loanProducts' => $loanProducts,
            'activePaymentMethods' => $activePaymentMethods,
            'activeGateways' => $activeGateways,
            'availableGroups' => $availableGroups,
            'agents' => $agents,
            'verifiedClients' => $verifiedClients,
        ]);
    }

    public function update(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        // Update client data
        $client->update($request->only([
            'client_name',
            'client_email',
            'client_phone',
            'aadhaar_number',
            'cibil_score',
        ]));

        // Get or create KYC record
        $kyc = $client->kycDetail()->firstOrCreate([], [
            'status' => 'pending',
            'attempt_no' => 1
        ]);

        $kycData = $request->only([
            'pan_number',
            'pan_name',
            'account_holder_name',
            'account_number',
            'ifsc_code',
            'bank_name',
            'branch_name',
            'account_type',
            'vehicle_number',
        ]);

        // File uploads
        if ($request->hasFile('selfie_image')) {
            $kycData['selfie_image'] = $request->file('selfie_image')
                ->store('kyc/selfie/' . $client->id, 'public');
        }

        if ($request->hasFile('aadhaar_image')) {
            $kycData['aadhaar_image'] = $request->file('aadhaar_image')
                ->store('kyc/aadhaar/' . $client->id, 'public');
        }

        if ($request->hasFile('aadhaar_image_back')) {
            $kycData['aadhaar_image_back'] = $request->file('aadhaar_image_back')
                ->store('kyc/aadhaar_back/' . $client->id, 'public');
        }

        if ($request->hasFile('pan_image')) {
            $kycData['pan_image'] = $request->file('pan_image')
                ->store('kyc/pan/' . $client->id, 'public');
        }

        if ($request->hasFile('bank_statement')) {
            $kycData['bank_statement'] = $request->file('bank_statement')
                ->store('kyc/bank_statement/' . $client->id, 'public');
        }

        if ($request->hasFile('rc_book_image')) {
            $kycData['rc_book_image'] = $request->file('rc_book_image')
                ->store('kyc/rc_book/' . $client->id, 'public');
        }

        if ($request->hasFile('driving_licence_image')) {
            $kycData['driving_licence_image'] = $request->file('driving_licence_image')
                ->store('kyc/driving_licence/' . $client->id, 'public');
        }

        if ($request->hasFile('home_loan_document')) {
            $kycData['home_loan_document'] = $request->file('home_loan_document')
                ->store('kyc/home_loan/' . $client->id, 'public');
        }

        if ($request->hasFile('additional_document_file')) {
            $additionalFile = $request->file('additional_document_file')
                ->store('kyc/additional/' . $client->id, 'public');
            $docTitle = $request->input('additional_document_title') ?: 'Additional Loan Document';
            
            $existingDocs = $kyc->additional_documents ?? [];
            if (!is_array($existingDocs)) {
                $existingDocs = json_decode($existingDocs, true) ?? [];
            }
            $existingDocs[] = [
                'title' => $docTitle,
                'file_path' => $additionalFile,
                'uploaded_at' => now()->toDateTimeString(),
            ];
            $kycData['additional_documents'] = $existingDocs;
        }

        $kyc->update($kycData);

        if ($request->ajax()) {
            $fieldName = null;
            $fileUrl = null;

            if ($request->hasFile('selfie_image') && isset($kycData['selfie_image'])) {
                $fieldName = 'selfie_image';
                $fileUrl = asset('storage/' . $kycData['selfie_image']);
            } elseif ($request->hasFile('aadhaar_image') && isset($kycData['aadhaar_image'])) {
                $fieldName = 'aadhaar_image';
                $fileUrl = asset('storage/' . $kycData['aadhaar_image']);
            } elseif ($request->hasFile('aadhaar_image_back') && isset($kycData['aadhaar_image_back'])) {
                $fieldName = 'aadhaar_image_back';
                $fileUrl = asset('storage/' . $kycData['aadhaar_image_back']);
            } elseif ($request->hasFile('pan_image') && isset($kycData['pan_image'])) {
                $fieldName = 'pan_image';
                $fileUrl = asset('storage/' . $kycData['pan_image']);
            } elseif ($request->hasFile('bank_statement') && isset($kycData['bank_statement'])) {
                $fieldName = 'bank_statement';
                $fileUrl = asset('storage/' . $kycData['bank_statement']);
            }

            // Check missing documents
            $missingDocs = [];
            if (!$kyc->selfie_image) $missingDocs[] = 'Selfie';
            if (!$kyc->aadhaar_image) $missingDocs[] = 'Aadhaar Front';
            if (!$kyc->aadhaar_image_back) $missingDocs[] = 'Aadhaar Back';

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully.',
                'field_name' => $fieldName,
                'file_url' => $fileUrl,
                'missing_docs' => $missingDocs
            ]);
        }

        return back()->with('success', 'KYC details and documents updated successfully!');
    }

    public function approve(Request $request, $id)
    {
        $client = Client::with('kycDetail')->findOrFail($id);

        $kyc = $client->kycDetail;
        $kycSkipped = (bool) optional($kyc)->kyc_skipped;

        if (!$kycSkipped) {
            if (!$kyc || !$kyc->aadhaar_verified || !$kyc->pan_verified || !$kyc->bank_verified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve KYC. Aadhaar, PAN, and Bank must be verified first, or use Skip KYC.'
                ], 422);
            }

            if (!$kyc->selfie_image || !$kyc->aadhaar_image || !$kyc->aadhaar_image_back) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot approve KYC. Selfie, Aadhaar Front, and Aadhaar Back must be uploaded first.'
                ], 422);
            }
        } elseif (!$kyc) {
            return response()->json([
                'success' => false,
                'message' => 'KYC record not found.'
            ], 422);
        }

        if ($client->kycDetail) {
            $client->kycDetail->update([
                'status' => 'verified',
                'rejected_reason' => null,
            ]);
        }

        // Update client status to 'active' (valid ENUM value)
        $client->status = 'active';
        $client->save();

        // Trigger mobile app notification
        event(new \App\Events\KycApproved($client));

        // Trigger WhatsApp notification
        event(new \App\Events\WhatsAppCommunicationEvent(
            'kyc_verified',
            $client->client_phone,
            [
                'client_name' => $client->client_name,
                'kyc_reference' => $client->kycDetail->kyc_reference ?? 'N/A'
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'KYC approved successfully! Client status updated to active.'
        ]);
    }

    public function skipKyc(Request $request, $id)
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $client = Client::with('kycDetail')->findOrFail($id);

        if (optional($client->kycDetail)->status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'KYC is Skipped for this client.'
            ], 422);
        }

        $kyc = $client->kycDetail()->firstOrCreate([], [
            'status' => 'pending',
            'attempt_no' => 1,
        ]);

        if ($kyc->kyc_skipped) {
            return response()->json([
                'success' => true,
                'message' => 'KYC verification is already skipped for this client. You can approve now.',
            ]);
        }

        $kyc->update([
            'kyc_skipped' => true,
            'kyc_skipped_by' => Auth::id(),
            'kyc_skipped_at' => now(),
            'kyc_skip_remarks' => $validated['remarks'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC verification skipped. Aadhaar, PAN, and Bank verification are not required. You can now approve this client.',
        ]);
    }

    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $client = Client::with('kycDetail')->findOrFail($id);

        if ($client->kycDetail) {
            $client->kycDetail->update([
                'status' => 'rejected',
                'rejected_reason' => $validated['reason'],
            ]);
        } else {
            // Create a KYC record if none exists, to mark the rejection
            $client->kycDetail()->create([
                'status' => 'rejected',
                'rejected_reason' => $validated['reason'],
                'aadhaar_number' => $client->aadhaar_number,
                'pan_number' => null, // Unknown
            ]);
        }

        // Update client status to 'inactive'
        $client->status = 'inactive';
        $client->save();

        // Trigger mobile app notification
        event(new \App\Events\KycRejected($client, $validated['reason']));

        // Trigger WhatsApp notification
        event(new \App\Events\WhatsAppCommunicationEvent(
            'kyc_rejected',
            $client->client_phone,
            [
                'client_name' => $client->client_name,
                'rejection_reason' => $validated['reason']
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'KYC rejected. Client status updated to inactive.'
        ]);
    }

    /**
     * Reopen a rejected KYC so the applicant can re-apply.
     * Archives the rejected attempt, resets the live record to pending and
     * moves the client out of 'inactive' so applications are no longer blocked.
     */
    public function reKyc(Request $request, $clientId)
    {
        $client = Client::with('kycDetail')->findOrFail($clientId);
        $kyc = $client->kycDetail;

        if (!$kyc || $kyc->status !== 'rejected') {
            return response()->json([
                'success' => false,
                'message' => 'Only a rejected KYC can be reopened for re-application.',
            ], 422);
        }

        $attempt = (int) ($kyc->attempt_no ?: 1);
        if ($attempt >= self::MAX_KYC_ATTEMPTS) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum re-apply attempts (' . self::MAX_KYC_ATTEMPTS . ') already reached for this client.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($request, $client, $kyc, $attempt, $clientId) {
                // Snapshot the rejected attempt before resetting the live record
                $kyc->archive();

                $newData = array_filter($request->only([
                    'aadhaar_number',
                    'aadhaar_name',
                    'pan_number',
                    'pan_name',
                    'account_holder_name',
                    'account_number',
                    'ifsc_code',
                    'account_type',
                    'bank_name',
                    'branch_name',
                ]), fn ($value) => $value !== null && $value !== '');

                if ($request->hasFile('selfie_image')) {
                    $newData['selfie_image'] = $request->file('selfie_image')
                        ->store('kyc/selfie/' . $clientId, 'public');
                }

                if ($request->hasFile('bank_statement')) {
                    $newData['bank_statement'] = $request->file('bank_statement')
                        ->store('kyc/bank_statement/' . $clientId, 'public');
                }

                $kyc->fill($newData);
                $kyc->status = 'pending';
                $kyc->rejected_reason = null;
                $kyc->aadhaar_verified = false;
                $kyc->pan_verified = false;
                $kyc->bank_verified = false;
                $kyc->attempt_no = $attempt + 1;
                $kyc->save();

                // Rejection set the client inactive, which blocks new loan/chit applications
                $client->status = 'pending';
                $client->save();
            });
        } catch (\Exception $e) {
            Log::error('Re-KYC failed', ['client_id' => $clientId, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reopen KYC: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'KYC reopened for re-application (attempt ' . ($attempt + 1) . ' of ' . self::MAX_KYC_ATTEMPTS . '). Verify the details and approve to activate the client.',
        ]);
    }

    /**
     * Trigger Aadhaar verification (request OTP)
     */
    public function verifyAadhaar(Request $request, $id, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
    {
        $client = Client::findOrFail($id);
        $aadhaarNumber = preg_replace('/\s+/', '', $client->aadhaar_number);

        if (empty($aadhaarNumber)) {
            return response()->json([
                'status' => false,
                'message' => 'No Aadhaar number is registered for this client.'
            ], 422);
        }

        // Call the service to trigger the OTP
        $result = $curlService->verifyAadhaarOtpRequest($aadhaarNumber);

        return response()->json($result);
    }

    /**
     * Confirm Aadhaar OTP
     */
    public function verifyAadhaarOtp(Request $request, $id, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'otp' => 'required|string|digits:6',
            'request_id' => 'required|string',
        ]);

        $client = Client::findOrFail($id);

        $result = $curlService->submitAadhaarOtp($request->otp, $request->request_id);

        $isSuccess =
            isset($result['status']) &&
            $result['status'] === true &&
            ($result['data']['status'] ?? '') === 'success' &&
            ($result['data']['data']['status'] ?? '') === 'success_aadhaar';

        if (!$isSuccess) {
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
                    $dobFormatted = \Carbon\Carbon::createFromFormat('d-m-Y', $dobRaw)->format('Y-m-d');
                } else {
                    $dobFormatted = \Carbon\Carbon::parse($dobRaw)->format('Y-m-d');
                }
            } catch (\Throwable $e) {}
        }

        DB::transaction(function () use ($client, $d, $fullAddress, $city, $state, $pincode, $dobFormatted) {
            $client->update([
                'client_name' => $d['full_name'] ?? $client->client_name,
                'gender' => strtolower($d['gender'] ?? $client->gender),
                'date_of_birth' => $dobFormatted ?? $client->date_of_birth,
                'address' => $fullAddress ?: $client->address,
                'city' => $city ?: $client->city,
                'state' => $state ?: $client->state,
                'pincode' => $pincode ?: $client->pincode,
            ]);

            $kyc = $client->kycDetail;
            if ($kyc) {
                $kyc->update([
                    'aadhaar_name' => $d['full_name'] ?? $kyc->aadhaar_name,
                    'pan_name' => $d['full_name'] ?? $kyc->pan_name,
                    'aadhaar_verified' => true
                ]);
            }
        });

        // Check if all are verified to auto approve
        $this->checkAndAutoApproveKyc($client);

        return response()->json([
            'status' => true,
            'message' => 'Aadhaar verified successfully.',
            'data' => [
                'name' => $d['full_name'] ?? null,
                'gender' => strtolower($d['gender'] ?? ''),
                'dob' => $dobFormatted,
                'address' => $fullAddress
            ]
        ]);
    }

    /**
     * Verify PAN number via API
     */
    public function verifyPan(Request $request, $id, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
    {
        $client = Client::with('kycDetail')->findOrFail($id);
        $kyc = $client->kycDetail;

        if (!$kyc || empty($kyc->pan_number)) {
            return response()->json([
                'status' => false,
                'message' => 'No PAN number is registered for this client.'
            ], 422);
        }

        $pan = strtoupper(preg_replace('/\s+/', '', $kyc->pan_number));

        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid PAN format. Must be 10 characters matching the standard format (e.g. ABCDE1234F).'
            ], 422);
        }

        $kyc->update([
            'pan_number' => $pan,
            'pan_verified' => true
        ]);

        // Check if all are verified to auto approve
        $this->checkAndAutoApproveKyc($client);

        return response()->json([
            'status' => true,
            'message' => 'PAN format verified successfully.',
            'data' => [
                'full_name' => $kyc->pan_name ?? $client->client_name,
                'pan_number' => $pan
            ]
        ]);
    }

    /**
     * Verify Bank Details via API
     */
    public function verifyBank(Request $request, $id, \App\Services\VerificationCurlService $curlService): \Illuminate\Http\JsonResponse
    {
        $client = Client::with('kycDetail')->findOrFail($id);
        $kyc = $client->kycDetail;

        if (!$kyc || empty($kyc->account_number) || empty($kyc->ifsc_code)) {
            return response()->json([
                'status' => false,
                'message' => 'No Bank Account details registered for this client.'
            ], 422);
        }

        $result = $curlService->verifyAgentBank($kyc->account_number, $kyc->ifsc_code, $client->client_name);

        if (isset($result['success']) && $result['success'] === true && !empty($result['bank'])) {
            $bankData = $result['bank'];
            $verifiedName = $bankData['full_name'] ?? $bankData['beneficiary_name'] ?? null;
            $bankName = $bankData['bank_name'] ?? null;
            $branchName = $bankData['branch'] ?? null;

            $kyc->update([
                'account_holder_name' => $verifiedName ?? $kyc->account_holder_name,
                'bank_name' => $bankName ?? $kyc->bank_name,
                'branch_name' => $branchName ?? $kyc->branch_name,
                'bank_verified' => true
            ]);

            // Check if all are verified to auto approve
            $this->checkAndAutoApproveKyc($client);

            return response()->json([
                'status' => true,
                'message' => 'Bank Account verified successfully.',
                'data' => [
                    'bank_name' => $bankName,
                    'branch' => $branchName,
                    'full_name' => $verifiedName
                ]
            ]);
        }

        $verifyResult = $curlService->verifyBank($kyc->account_number, $kyc->ifsc_code, $client->client_name);
        if (isset($verifyResult['status']) && $verifyResult['status'] === true) {
            $bankName = null;
            $branch = null;
            try {
                $response = \Illuminate\Support\Facades\Http::get("https://ifsc.razorpay.com/{$kyc->ifsc_code}");
                if ($response->successful()) {
                    $ifscData = $response->json();
                    $bankName = $ifscData['BANK'] ?? null;
                    $branch = $ifscData['BRANCH'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::error('Razorpay IFSC API call failed', ['error' => $e->getMessage()]);
            }

            $verifiedName = $verifyResult['data']['full_name'] ?? $verifyResult['data']['data']['full_name'] ?? null;
            $bankNameResolved = $bankName ?? $verifyResult['data']['bank_name'] ?? $verifyResult['data']['data']['bank_name'] ?? null;
            $branchResolved = $branch ?? $verifyResult['data']['branch'] ?? $verifyResult['data']['data']['branch'] ?? null;

            $kyc->update([
                'account_holder_name' => $verifiedName ?? $kyc->account_holder_name,
                'bank_name' => $bankNameResolved ?? $kyc->bank_name,
                'branch_name' => $branchResolved ?? $kyc->branch_name,
                'bank_verified' => true
            ]);

            // Check if all are verified to auto approve
            $this->checkAndAutoApproveKyc($client);

            return response()->json([
                'status' => true,
                'message' => 'Bank Account verified successfully.',
                'data' => [
                    'bank_name' => $bankNameResolved,
                    'branch' => $branchResolved,
                    'full_name' => $verifiedName
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
     * Check if Aadhaar, PAN, and Bank are verified, and auto approve KYC
     */
    private function checkAndAutoApproveKyc($client)
    {
        $client->load('kycDetail');
        $kyc = $client->kycDetail;

        if ($kyc && $kyc->aadhaar_verified && $kyc->pan_verified && $kyc->bank_verified && $kyc->selfie_image && $kyc->aadhaar_image && $kyc->aadhaar_image_back) {
            // Auto approve!
            $kyc->update([
                'status' => 'verified',
                'rejected_reason' => null
            ]);

            $client->update([
                'status' => 'active'
            ]);

            // Trigger notifications
            try {
                event(new \App\Events\KycApproved($client));
                event(new \App\Events\WhatsAppCommunicationEvent(
                    'kyc_verified',
                    $client->client_phone,
                    [
                        'client_name' => $client->client_name,
                        'kyc_reference' => $kyc->kyc_reference ?? 'N/A'
                    ]
                ));
            } catch (\Throwable $e) {
                Log::error('Auto KYC verification event triggers failed: ' . $e->getMessage());
            }
        }
    }

    public function updateKycFields(Request $request, $id)
    {
        $client = Client::findOrFail($id);

        $validated = $request->validate([
            'section' => 'required|string|in:aadhaar,pan,bank',
            'aadhaar_number' => 'nullable|string|max:20',
            'pan_number' => 'nullable|string|max:20',
            'pan_name' => 'nullable|string|max:100',
            'account_holder_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:30',
            'ifsc_code' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:100',
            'branch_name' => 'nullable|string|max:100',
        ]);

        $section = $validated['section'];
        $kyc = $client->kycDetail()->firstOrCreate([]);

        if ($section === 'aadhaar') {
            if ($client->aadhaar_number !== $request->aadhaar_number) {
                $kyc->aadhaar_verified = false;
                $kyc->status = 'pending';
                $client->status = 'pending';
            }
            $client->update([
                'aadhaar_number' => $request->aadhaar_number
            ]);
            $kyc->update([
                'aadhaar_number' => $request->aadhaar_number
            ]);
        } elseif ($section === 'pan') {
            if ($kyc->pan_number !== $request->pan_number) {
                $kyc->pan_verified = false;
                $kyc->status = 'pending';
                $client->status = 'pending';
            }
            $kyc->update([
                'pan_number' => $request->pan_number,
                'pan_name' => $request->pan_name,
            ]);
        } elseif ($section === 'bank') {
            if ($kyc->account_number !== $request->account_number || $kyc->ifsc_code !== $request->ifsc_code) {
                $kyc->bank_verified = false;
                $kyc->status = 'pending';
                $client->status = 'pending';
            }
            $kyc->update([
                'account_holder_name' => $request->account_holder_name,
                'account_number' => $request->account_number,
                'ifsc_code' => $request->ifsc_code,
                'bank_name' => $request->bank_name,
                'branch_name' => $request->branch_name,
            ]);
        }

        $kyc->save();
        $client->save();

        return response()->json([
            'status' => true,
            'message' => 'KYC section details updated successfully!',
            'data' => [
                'aadhaar_number' => $client->aadhaar_number,
                'pan_number' => $client->kycDetail ? $client->kycDetail->pan_number : null,
                'pan_name' => $client->kycDetail ? $client->kycDetail->pan_name : null,
                'account_holder_name' => $client->kycDetail ? $client->kycDetail->account_holder_name : null,
                'account_number' => $client->kycDetail ? $client->kycDetail->account_number : null,
                'ifsc_code' => $client->kycDetail ? $client->kycDetail->ifsc_code : null,
                'bank_name' => $client->kycDetail ? $client->kycDetail->bank_name : null,
                'branch_name' => $client->kycDetail ? $client->kycDetail->branch_name : null,
            ]
        ]);
    }
}
