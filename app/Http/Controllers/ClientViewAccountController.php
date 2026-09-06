<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;


class ClientViewAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:client.update')->only(['update']);
        $this->middleware('permission:client.delete')->only(['blacklist']);
    }

  public function index($id)
  {
    $client = Client::with(['kycDetail', 'user', 'guarantors', 'employeeInformation', 'nominee'])
      ->withCount([
        'loanApplications as applications_count',
        'loanAccounts as loans_count'
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

    $stats = [
      'applications' => (int) $client->applications_count,
      'loans' => (int) $client->loans_count,
      'kyc' => $statusDisplay, // Keeping key as 'kyc' to avoid changing view, but content is now Client Status
    ];

    $locations = \App\Models\Location::orderBy('name')->get();
    
    // Add data for Quick Loan Modal
    $loanProducts = \App\Models\LoanProduct::where('status', 'active')->get();
    $activePaymentMethods = \App\Models\PaymentMethod::where('is_enabled', true)->get();
    $activeGateways = \App\Models\PaymentGateway::where('enabled', true)->get();

    return view('admin.clients.client-view-account', [
      'client' => $client,
      'stats' => $stats,
      'locations' => $locations,
      'loanProducts' => $loanProducts,
      'activePaymentMethods' => $activePaymentMethods,
      'activeGateways' => $activeGateways,
    ]);
  }

  public function update(Request $request, $id): JsonResponse
  {
    $client = Client::with(['kycDetail'])->findOrFail($id);

    $validated = $request->validate([
      'client_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9\s]+$/'],
      'client_email' => 'nullable|email|max:255',
      'client_phone' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
      'alternate_phone' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
      'date_of_birth' => 'nullable|date',
      'gender' => 'nullable|string|in:male,female,other',
      'marital_status' => 'nullable|string|in:single,married,divorced,widowed',
      'status' => 'required|in:active,inactive,blacklist',
      'address' => 'nullable|string',
      'city' => 'nullable|string|max:255',
      'state' => 'nullable|string|max:255',
      'pincode' => 'nullable|string|max:10',
      'location_id' => 'required|exists:locations,id',
      'collection_day' => 'nullable|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
    ]);

    if (!empty($validated['date_of_birth'])) {
      $validated['date_of_birth'] = \Carbon\Carbon::parse($validated['date_of_birth'])->format('d-m-Y');
    }

    $client->update($validated);

    return response()->json([
      'success' => true,
      'message' => 'Client profile updated successfully.'
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
}
