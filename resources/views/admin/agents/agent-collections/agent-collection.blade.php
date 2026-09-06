@extends('layouts/layoutMaster')

@section('title', 'Agent Collections')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection


@section('page-script')
  <script>
    window.partialPaymentGlobal = @json($partialPaymentGlobal ?? []);
  </script>
  @vite(['resources/assets/custom-js/agent-collections.js'])
@endsection

@section('content')
  <!-- Success Alert -->
  @if(session('success'))
    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="alert alert-success alert-dismissible" role="alert">
          <strong>Success!</strong> {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    </div>
  @endif

  @if(session('error'))
    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="alert alert-danger alert-dismissible" role="alert">
          <strong>Error!</strong> {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    </div>
  @endif

  <!-- Stats Cards -->
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="mb-0 h6 fw-normal">Agent Collected</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-2" id="stat-agent-count">{{ $agentCollectedCount }}</h4>
              </div>
              <small class="mb-0">Total: <span id="stat-agent-amount">₹{{ number_format($agentCollectedAmount, 0) }}</span></small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <div class="icon-base ri ri-user-star-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Admin Collected</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1" id="stat-admin-count">{{ $adminCollectedCount }}</h4>
              </div>
              <small class="mb-0">Total: <span id="stat-admin-amount">₹{{ number_format($adminCollectedAmount, 0) }}</span></small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded">
                <div class="icon-base ri ri-admin-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Payment Link Collections</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1" id="stat-link-count">{{ $paymentLinkCount }}</h4>
              </div>
              <small class="mb-0">Total: <span id="stat-link-amount">₹{{ number_format($paymentLinkAmount, 0) }}</span></small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-info rounded-3">
                <div class="icon-base ri ri-link icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- @if($isAgent)
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Pending Assignments</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1 text-warning">{{ $pendingTasksCount }}</h4>
              </div>
              <small class="mb-0">Tasks to complete</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-warning rounded-3">
                <div class="icon-base ri ri-task-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    @endif -->
  </div>

  <!-- @if($isAgent && $myAssignments->isNotEmpty())
 My Assignments (Agent View) 
  <div class="card mb-6 border-warning" style="border-width:2px">
    <div class="card-header d-flex justify-content-between align-items-center border-bottom bg-label-warning">
      <div>
        <h5 class="card-title mb-0"><i class="ri-task-line me-2 text-warning"></i>My Assignments</h5>
        <small class="text-muted">EMIs assigned to you by Admin — tap Collect to submit a collection</small>
      </div>
      <span class="badge bg-warning text-dark">{{ $myAssignments->count() }} Pending</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Account No.</th>
            <th>EMI #</th>
            <th>Due Date</th>
            <th>Pending Amount</th>
            <th>Assigned On</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach($myAssignments as $i => $assignment)
          @php
            $emi    = $assignment->emi;
            $client = $emi?->loanAccount?->client;
            $loan   = $emi?->loanAccount;
            $pending = $emi ? max(0, (float)($emi->pending_amount ?? $emi->total_amount)) : 0;
          @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>
              <span class="fw-semibold">{{ $client?->client_name ?? 'N/A' }}</span><br>
              <small class="text-muted">{{ $client?->client_phone ?? '' }}</small>
            </td>
            <td><span class="badge bg-label-secondary">{{ $loan?->account_number ?? 'N/A' }}</span></td>
            <td><span class="badge bg-label-primary">EMI #{{ $emi?->instalment_number ?? '?' }}</span></td>
            <td>
              @if($emi?->due_date)
                @php $due = \Carbon\Carbon::parse($emi->due_date); @endphp
                <span class="{{ $due->isPast() ? 'text-danger fw-bold' : '' }}">
                  {{ $due->format('d-m-Y') }}
                </span>
                @if($due->isPast())
                  <span class="badge bg-label-danger ms-1">Overdue</span>
                @endif
              @else
                <span class="text-muted">-</span>
              @endif
            </td>
            <td class="fw-bold text-danger">₹{{ number_format($pending, 2) }}</td>
            <td><small class="text-muted">{{ $assignment->assigned_at?->format('d-m-Y') ?? '-' }}</small></td>
            <td>
              <button type="button"
                class="btn btn-sm btn-warning btn-collect-assigned"
                data-emi-id="{{ $emi?->id }}"
                data-emi-no="{{ $emi?->instalment_number }}"
                data-client="{{ $client?->client_name ?? 'N/A' }}"
                data-amount="{{ $pending }}"
                data-account="{{ $loan?->account_number ?? '' }}"
                {{ $pending <= 0 ? 'disabled' : '' }}>
                <i class="ri-hand-coin-line me-1"></i> Collect
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif 

   Collections Table -->

  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
      <h5 class="card-title mb-0 d-flex align-items-center  gap-2">
        <span>Collections Information</span>
        <span class="badge bg-label-primary fs-7">Agent: {{ $agentCollectedCount }}</span>
        <span class="badge bg-label-success fs-7">Admin: {{ $adminCollectedCount }}</span>
      </h5>
      <div class="d-flex flex-column flex-sm-row   align-items-stretch align-items-sm-center gap-2 w-100 w-md-auto justify-content-md-end">
        <select id="filterStatus" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="verified">Verified (Paid)</option>
          <option value="rejected">Rejected</option>
        </select>

        <select id="filterCollector" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Collectors</option>
          <option value="agent">Agent Collected</option>
          <option value="admin">Admin Collected</option>
        </select>

        <select id="filterMethod" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Methods</option>
          <optgroup label="Agent Methods">
            <option value="agent_in_hand">Agent In-Hand</option>
            <option value="agent_upi">Agent UPI</option>
            <option value="agent_bank_transfer">Agent Bank Transfer</option>
          </optgroup>
          <optgroup label="Admin Methods">
            <option value="admin_in_hand">Admin In-Hand</option>
            <option value="admin_upi">Admin UPI</option>
            <option value="admin_bank_transfer">Admin Bank Transfer</option>
          </optgroup>
          <option value="payment_link">Payment Link</option>
        </select>

        <button class="btn btn-primary btn-sm w-100 w-sm-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollectionModal">
          <i class="ri-add-line me-1"></i> Add Collection
        </button>

        @if(!$isAgent)
        <!-- Bulk Verify Button (visible when rows selected) -->
        <div id="bulkVerifyBar" class="d-none flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 w-100 w-sm-auto justify-content-center">
          <span class="badge bg-label-primary py-2 px-3 text-center" id="selectedCountBadge">0 selected</span>
          <button class="btn btn-success btn-sm shadow-sm w-100 w-sm-auto" id="btnBulkVerify">
            <i class="ri-check-double-line me-1"></i> Bulk Verify
          </button>
          <button class="btn btn-outline-secondary btn-sm w-100 w-sm-auto" id="btnClearSelection">
            <i class="ri-close-line"></i> Clear
          </button>
        </div>
        @endif
      </div>
    </div>
    <div class="card-datatable table-responsive">
      <table class="datatables-collections table text-nowrap">
        <thead>
          <tr>
            <th style="width:50px"><input type="checkbox" id="selectAllCollections" title="Select All Pending" class="me-1"> S.No</th>
            <th>Client</th>
            <th>Collected By</th>
            <th>EMI ID</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Type</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>

        </thead>
      </table>
    </div>
  </div>
  
  <!-- View Collection Modal -->
  <div class="modal fade" id="viewCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Collection Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="collectionDetailsContent">
          <!-- Content loaded dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Collection Modal -->
  <div class="modal fade" id="addCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Record Manual Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="addCollectionForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Agent <span class="text-danger">*</span></label>
              @if($isAgent && $currentAgentId)
                <select class="form-select select2" disabled data-dropdown-parent="#addCollectionModal">
                  @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ $agent->id == $currentAgentId ? 'selected' : '' }}>
                      {{ $agent->agent_name }} ({{ $agent->agent_code }})
                    </option>
                  @endforeach
                </select>
                <input type="hidden" name="agent_id" value="{{ $currentAgentId }}">
              @else
                <select class="form-select select2" name="agent_id" required data-dropdown-parent="#addCollectionModal">
                  <option value="">Choose Agent</option>
                  @foreach($agents as $agent)
                    <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                  @endforeach
                </select>
              @endif
            </div>
            <div class="mb-3">
              <label class="form-label">Search EMI (Client/Acc No/Phone) <span class="text-danger">*</span></label>
              <select class="form-select" name="emi_id" id="emiSearchSelect" required data-dropdown-parent="#addCollectionModal">
                <option value="">Start typing to search...</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Type <span class="text-danger">*</span></label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input payment-type-radio" type="radio" name="payment_type" id="type_full" value="full" checked>
                  <label class="form-check-label" for="type_full">Full Payment</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input payment-type-radio" type="radio" name="payment_type" id="type_partial" value="partial">
                  <label class="form-check-label" for="type_partial">Partial Payment</label>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" name="amount" id="collectionAmount" required min="0.01" step="0.01" readonly>
                <small id="partialCollectionHelp" class="text-muted d-none"></small>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Collection Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="collected_at" value="{{ date('Y-m-d') }}" required>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                <select id="payment_method" name="payment_method" class="form-select" required>
                  <option value="in_hand">Agent In-Hand</option>
                  <option value="upi">UPI</option>
                  <option value="bank_transfer">Bank Transfer</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Reference No.</label>
                <input type="text" id="payment_reference" name="payment_reference" class="form-control" placeholder="TXN ID, Cheque No. etc">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveCollectionBtn">Save Collection</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Verify Collection Modal (for in-hand) -->
  <div class="modal fade" id="verifyCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Verify Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="verifyCollectionForm">
          <div class="modal-body">
            <input type="hidden" id="verifyCollectionId" name="collection_id">
            <div class="mb-3">
              <label class="form-label">Verification Status</label>
              <select class="form-select" name="status" required>
                <option value="">Select Status</option>
                <option value="verified">Approve</option>
                <option value="rejected">Reject</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Add verification remarks..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Assign Agent Modal -->
  @if(!$isAgent)

  <!-- Bulk Verify Remarks Modal -->
  <div class="modal fade" id="bulkVerifyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ri-check-double-line me-2 text-success"></i>Bulk Verify Collections</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info d-flex align-items-center mb-3">
            <i class="ri-information-line me-2"></i>
            <span>You are about to verify <strong id="bulkVerifyCount">0</strong> collection(s). This will update the EMI remaining balance immediately.</span>
          </div>
          <div class="mb-3">
            <label class="form-label">Remarks (optional)</label>
            <textarea class="form-control" id="bulkVerifyRemarks" rows="2" placeholder="Add verification remarks for all selected collections..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-success" id="btnConfirmBulkVerify">
            <span class="spinner-border spinner-border-sm d-none me-1" id="bulkVerifySpinner" role="status"></span>
            <i class="ri-check-double-line me-1"></i> Confirm Verify All
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="assignAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Agent for Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="assignAgentForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Agent <span class="text-danger">*</span></label>
              <select class="form-select select2" name="agent_id" required data-dropdown-parent="#assignAgentModal">
                <option value="">Choose Agent</option>
                @foreach($agents as $agent)
                  <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Search EMI (Client/Acc No/Phone) <span class="text-danger">*</span></label>
              <select class="form-select" name="emi_id" id="emiAssignSelect" required data-dropdown-parent="#assignAgentModal">
                <option value="">Start typing to search...</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="2" placeholder="Optional notes for the agent..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveAssignBtn">Assign EMI</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endif

  <!-- Payment History Modal -->
  <div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title">Payment History Breakdown</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Amount</th>
                  <th>Method</th>
                  <th>Ref. No.</th>
                  <th>Remarks</th>
                  <th>Collected By</th>
                </tr>
              </thead>
              <tbody id="paymentHistoryContent">
                <!-- Loaded dynamically -->
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('pricing-script')
<script>
  window.userRole = "{{ auth()->user()->roles->first()->name ?? '' }}";
</script>
@endpush
