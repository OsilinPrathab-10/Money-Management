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

class KycVerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:kyc.approve')->only(['approve']);
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
                } elseif ($kycStatus === 'rejected' || $clientStatus === 'inactive') {
                    $displayStatus = 'rejected';
                }

                $statusBadges = [
                    'verified' => '<span class="badge bg-label-success">Verified</span>',
                    'rejected' => '<span class="badge bg-label-danger">Rejected</span>',
                    'unverified' => '<span class="badge bg-label-warning">Unverified</span>',
                ];

                return [
                    'id' => $client->id,
                    'DT_RowIndex' => $start + $index + 1,
                    'client_name' => $client->client_name ?? 'N/A',
                    'submitted_on' => $client->kycDetail && $client->kycDetail->updated_at ? $client->kycDetail->updated_at->format('d-m-Y') : 'N/A',
                    'kyc_status' => $statusBadges[$displayStatus] ?? '<span class="badge bg-label-secondary">Unknown</span>',
                    'action' => '<div class="d-flex align-items-center gap-4">
                        <a href="' . route('verification-kyc-view', $client->id) . '" class="btn btn-icon btn-text-secondary btn-sm rounded-pill">
                            <i class="icon-base ri ri-eye-line icon-22px"></i>
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
        
        return view($blade, [
            'client' => $client,
            'kyc' => $kyc,
            'stats' => $stats,
            'verificationStatus' => $verificationStatus,
            'missingFields' => $missingFields,
            'loanProducts' => $loanProducts,
            'activePaymentMethods' => $activePaymentMethods,
            'activeGateways' => $activeGateways,
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

        // Update KYC data if exists
        if ($client->kyc) {
            $client->kyc->update($request->only([
                'pan_number',
                'account_holder_name',
                'account_number',
                'ifsc_code',
                'bank_name',
            ]));
        }

        return back()->with('success', 'KYC details updated successfully!');
    }

    public function approve(Request $request, $id)
    {
        $client = Client::with('kycDetail')->findOrFail($id);

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

    // Trigger Re-KYC from admin
    public function reKyc(Request $request, $clientId)
    {
        DB::transaction(function () use ($clientId, $request) {
            // Get current KYC record
            $kyc = KycDetail::where('client_id', $clientId)
                ->where('status', 'rejected')
                ->latest()
                ->firstOrFail();

            if ($kyc->attempt_no >= 3) {
                return response()->json([
                    'message' => 'Maximum Re-KYC attempts reached'
                ], 403);
            }
            // Archive old record
            $kyc->archive();

            // Increment attempt
            $attempt = $kyc->attempt_no + 1;

            // Prepare new data
            $newData = $request->only([
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
            ]);

            // Handle file uploads
            if ($request->hasFile('selfie_image')) {
                $newData['selfie_image'] = $request->file('selfie_image')
                    ->store('kyc/selfie/' . $clientId, 'public');
            }

            if ($request->hasFile('bank_statement')) {
                $newData['bank_statement'] = $request->file('bank_statement')
                    ->store('kyc/bank_statement/' . $clientId, 'public');
            }

            // Save new KYC record
            KycDetail::create(array_merge($newData, [
                'client_id' => $clientId,
                'status' => 'pending',
                'attempt_no' => $attempt,
            ]));
        });

        return response()->json([
            'message' => 'Re-KYC initiated successfully',
        ]);
    }
}
