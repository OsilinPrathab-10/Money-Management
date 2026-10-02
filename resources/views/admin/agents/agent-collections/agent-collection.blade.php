@extends('layouts/layoutMaster')

@section('title', $isAgent ? 'My Collections' : 'Agent Collections')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-style')
<style>
  table.datatables-collections td.emi-id-cell {
    white-space: normal !important;
    min-width: 210px;
    max-width: 280px;
    vertical-align: top;
  }
  table.datatables-collections td.emi-id-cell .emi-id-wrap {
    white-space: normal !important;
    line-height: 1.4;
  }
</style>
@endsection

@section('page-script')
  <script>
    window.isAgentUser = {{ $isAgent ? 'true' : 'false' }};
    window.partialPaymentGlobal = @json($partialPaymentGlobal ?? []);
  </script>
  @vite(['resources/assets/custom-js/bank-payment-fields.js', 'resources/assets/custom-js/agent-collections.js'])
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
  @if($isAgent)
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="mb-0 h6 fw-normal">My Loan Collections</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-2" id="stat-agent-count">{{ $agentCollectedCount }}</h4>
              </div>
              <small class="mb-0">Total: <span id="stat-agent-amount">₹{{ number_format($agentCollectedAmount, 0) }}</span></small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <div class="icon-base ri ri-bank-line icon-26px"></div>
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
              <p class="text-heading mb-1">My Chit Collections</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1" id="stat-chit-count">{{ $chitCollectedCount }}</h4>
              </div>
              <small class="mb-0">Total: ₹{{ number_format($chitCollectedAmount, 0) }}</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-info rounded-3">
                <div class="icon-base ri ri-group-line icon-26px"></div>
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
              <p class="text-heading mb-1">Pending Follow-ups</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1 text-warning">{{ $pendingTasksCount }}</h4>
              </div>
              <small class="mb-0">Assigned EMIs to collect</small>
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
  </div>
  @else
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
  </div>
  @endif

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
    <div class="card-header border-bottom py-3">
      <ul class="nav nav-tabs card-header-tabs nav-fill" role="tablist" id="collectionTypeTabs">
        <li class="nav-item" role="presentation">
          <button class="nav-link active fw-semibold" id="loan-collections-tab" data-bs-toggle="tab" data-bs-target="#loanCollectionsPane" type="button" role="tab" aria-controls="loanCollectionsPane" aria-selected="true">
            <i class="ri-bank-line me-1"></i> {{ $isAgent ? 'My Loan Collections' : 'Loan Collections' }}
            <span class="badge bg-primary text-white ms-1">{{ $agentCollectedCount + $adminCollectedCount }}</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link fw-semibold" id="chit-collections-tab" data-bs-toggle="tab" data-bs-target="#chitCollectionsPane" type="button" role="tab" aria-controls="chitCollectionsPane" aria-selected="false">
            <i class="ri-group-line me-1"></i> {{ $isAgent ? 'My Chit Collections' : 'Chit Collections' }}
            <span class="badge bg-info text-white ms-1" id="chit-tab-count">{{ $chitCollectedCount }}</span>
          </button>
        </li>
      </ul>
    </div>

    <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
      <h5 class="card-title mb-0 d-flex align-items-center gap-2">
        <span id="activeCollectionTitle">{{ $isAgent ? 'My Loan Collections' : 'Loan Collections' }}</span>
        @if(!$isAgent)
        <span class="badge bg-label-primary fs-7 loan-only-stat">Agent: {{ $agentCollectedCount }}</span>
        <span class="badge bg-label-success fs-7 loan-only-stat">Admin: {{ $adminCollectedCount }}</span>
        @endif
        <span class="badge bg-label-info fs-7 chit-only-stat d-none">Total: ₹{{ number_format($chitCollectedAmount, 0) }}</span>
      </h5>
      <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-md-auto justify-content-md-end">
        <div class="d-flex align-items-center gap-1">
          @include('partials.date-range-filter', [
            'fromId' => 'filterStartDate',
            'toId' => 'filterEndDate',
            'presetId' => 'agentCollectionDatePreset',
          ])
          <button type="button" id="btnClearDateFilter" class="btn btn-label-secondary btn-sm d-none" title="Clear date filter">
            <i class="ri-close-line"></i>
          </button>
        </div>

        @if(!$isAgent)
        <select id="filterAgent" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Agents</option>
          @foreach($agents as $agent)
            <option value="{{ $agent->id }}">{{ $agent->agent_name }}</option>
          @endforeach
        </select>
        @endif

        <select id="filterStatus" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="verified">Verified (Paid)</option>
          @if(!$isAgent)
          <option value="rejected">Rejected</option>
          @endif
        </select>

        @if(!$isAgent)
        <select id="filterCollector" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Collectors</option>
          <option value="agent">Agent Collected</option>
          <option value="admin">Admin Collected</option>
        </select>
        @endif

        <select id="filterMethod" class="form-select form-select-sm w-100 w-sm-auto">
          <option value="">All Methods</option>
          @if($isAgent)
            <option value="agent_in_hand">Cash in hand</option>
            <option value="agent_upi">UPI / GPay / QR</option>
            <option value="agent_bank_transfer">Bank Transfer</option>
          @else
          <optgroup label="Agent Methods">
            <option value="agent_in_hand">Agent Cash in hand</option>
            <option value="agent_upi">Agent UPI / GPay / QR</option>
            <option value="agent_bank_transfer">Agent Bank Transfer</option>
          </optgroup>
          <optgroup label="Admin Methods">
            <option value="admin_in_hand">Admin Cash in hand</option>
            <option value="admin_upi">Admin UPI / GPay / QR</option>
            <option value="admin_bank_transfer">Admin Bank Transfer</option>
          </optgroup>
          <option value="payment_link">Payment Link</option>
          @endif
        </select>

        <button class="btn btn-primary btn-sm w-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#addCollectionModal">
          <i class="ri-add-line me-1"></i> Add Collection
        </button>

        @if($isAgent)
        <button type="button" class="btn btn-success btn-sm w-auto shadow-sm" id="btnOpenBulkCollect">
          <i class="ri-stack-line me-1"></i> Bulk Collect
        </button>
        @endif

        @if(!$isAgent)
        <!-- Bulk Verify Button (visible when rows selected) -->
        <div id="bulkVerifyBar" class="d-none flex-row align-items-center gap-1 w-auto justify-content-end">
          <span class="badge bg-label-primary py-1 px-2 text-center fs-7" id="selectedCountBadge">0 selected</span>
          <button type="button" class="btn btn-success btn-sm py-1 px-2 fs-7 shadow-sm" id="btnBulkVerify">
            <i class="ri-check-double-line me-1"></i> Bulk Verify
          </button>
          <button type="button" class="btn btn-outline-secondary btn-sm py-1 px-2 fs-7" id="btnClearSelection" title="Clear Selection">
            <i class="ri-close-line me-1"></i> Clear
          </button>
        </div>
        @endif
      </div>
    </div>
    <div class="tab-content">
      <div class="tab-pane fade show active" id="loanCollectionsPane" role="tabpanel" aria-labelledby="loan-collections-tab">
        <div class="card-datatable table-responsive border-top">
          <table class="datatables-collections table">
            <thead>
              <tr>
                <th style="width:50px">@if(!$isAgent)<input type="checkbox" id="selectAllCollections" title="Select All Pending" class="me-1">@endif S.No</th>
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

      <div class="tab-pane fade" id="chitCollectionsPane" role="tabpanel" aria-labelledby="chit-collections-tab">
        <div class="card-datatable table-responsive border-top">
          <table class="datatables-chit-collections table text-nowrap">
            <thead>
              <tr>
                <th style="width:50px">@if(!$isAgent)<input type="checkbox" id="selectAllChitCollections" title="Select All Pending" class="me-1">@endif S.No</th>
                <th>Client</th>
                <th>Collected By</th>
                <th>Group / Installment</th>
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
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
      <form id="addCollectionForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
        @csrf
        {{-- Header --}}
        <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
          <div class="d-flex align-items-center gap-2_5">
            <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
              <i class="icon-base ri ri-add-circle-line"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-primary mb-0 fs-6">Record Manual Collection</h5>
              <small class="text-primary opacity-75" style="font-size:0.75rem;">Record payment for EMI or Loan installment</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-3 p-sm-4">
          <div class="mb-3">
            @if($isAgent && $currentAgent)
              <label class="form-label fw-medium text-dark small mb-1">Collected By</label>
              <input type="text" class="form-control form-control-sm fw-bold text-dark" value="{{ $currentAgent->agent_name }} ({{ $currentAgent->agent_code }})" readonly>
              <input type="hidden" name="agent_id" value="{{ $currentAgentId }}">
            @else
              <label class="form-label fw-medium text-dark small mb-1">Collected By</label>
              <input type="text" class="form-control form-control-sm fw-bold text-dark" value="{{ auth()->user()->name }}" readonly>
            @endif
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium text-dark small mb-1">Search EMI (Client/Acc No/Phone) <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" name="emi_id" id="emiSearchSelect" required data-dropdown-parent="#addCollectionModal">
              <option value="">Start typing to search...</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium text-dark small mb-1">Payment Type <span class="text-danger">*</span></label>
            <div class="d-flex gap-3 mt-1">
              <div class="form-check">
                <input class="form-check-input payment-type-radio" type="radio" name="payment_type" id="type_full" value="full" checked>
                <label class="form-check-label fw-medium text-dark small" for="type_full">Full Payment</label>
              </div>
              <div class="form-check">
                <input class="form-check-input payment-type-radio" type="radio" name="payment_type" id="type_partial" value="partial">
                <label class="form-check-label fw-medium text-dark small" for="type_partial">Partial Payment</label>
              </div>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label fw-medium text-dark small mb-1">Amount (₹) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
                <input type="number" class="form-control fw-bold text-dark" name="amount" id="collectionAmount" required min="0.01" step="0.01" readonly>
              </div>
              <small id="partialCollectionHelp" class="text-muted d-block mt-1 d-none" style="font-size:0.72rem;"></small>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label fw-medium text-dark small mb-1">Collection Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control form-control-sm" name="collected_at" value="{{ date('Y-m-d') }}" required>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-12 col-sm-6">
              <label class="form-label fw-medium text-dark small mb-1">Payment Method <span class="text-danger">*</span></label>
              <select id="payment_method" name="payment_method" class="form-select form-select-sm" required>
                <option value="in_hand">Cash in hand</option>
                <option value="upi">UPI / GPay / QR</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label fw-medium text-dark small mb-1">Reference No.</label>
              <input type="text" id="payment_reference" name="payment_reference" class="form-control form-control-sm" placeholder="TXN ID, UTR, etc">
            </div>
          </div>
          <div class="mb-3">
            @include('admin.partials.bank-collection-fields', [
              'bankAccounts' => $bankAccounts,
              'bankContainerId' => 'addBankContainer',
              'bankSelectId' => 'add_internal_bank_account_id',
              'bankSelectName' => 'internal_bank_account_id',
              'qrContainerId' => 'addQrContainer',
              'qrBankNameId' => 'addQrBankName',
              'qrUpiIdId' => 'addQrUpiId',
              'qrImageWrapperId' => 'addQrImageWrapper',
              'bankTransferContainerId' => 'addBankTransferContainer',
              'bankTransferContentId' => 'addBankTransferContent',
            ])
          </div>
          <div class="mb-0">
            <label class="form-label fw-medium text-dark small mb-1">Remarks</label>
            <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Optional notes..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
          <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary px-4 shadow-xs" id="saveCollectionBtn">Save Collection</button>
        </div>
      </form>
    </div>
  </div>

  @if($isAgent)
  <!-- Agent Bulk Collection Modal -->
  <div class="modal fade" id="bulkCollectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
      <form id="bulkCollectForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
        @csrf
        {{-- Header --}}
        <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
          <div class="d-flex align-items-center gap-2_5">
            <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
              <i class="ri-checkbox-multiple-line"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-primary mb-0 fs-6">Bulk Collect Assigned Dues</h5>
              <small class="text-primary opacity-75" style="font-size:0.75rem;">All selected collections will be sent for admin verification.</small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-3 p-sm-4">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label fw-medium text-dark small mb-1" for="bulkDueType">Due Type</label>
              <select class="form-select form-select-sm" id="bulkDueType">
                <option value="all">Loan + Chit</option>
                <option value="loan">Loan EMIs</option>
                <option value="chit">Chit Installments</option>
              </select>
            </div>
            <div class="col-md-7">
              <label class="form-label fw-medium text-dark small mb-1" for="bulkDueSearch">Search</label>
              <input type="search" class="form-control form-control-sm" id="bulkDueSearch" placeholder="Client, phone, account or group">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btnSearchBulkDues">
                <i class="ri-search-line me-1"></i> Search
              </button>
            </div>
          </div>

          <div class="table-responsive border rounded-3 mb-3" style="max-height: 360px;">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light sticky-top">
                <tr>
                  <th style="width: 45px;"><input class="form-check-input" type="checkbox" id="bulkSelectAll"></th>
                  <th>Type / Due</th>
                  <th>Client</th>
                  <th>Account / Group</th>
                  <th>Due Date</th>
                  <th style="min-width: 150px;">Collect Amount (₹)</th>
                </tr>
              </thead>
              <tbody id="bulkDuesBody">
                <tr><td colspan="6" class="text-center text-muted py-4">Open Bulk Collect to load assigned dues.</td></tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div id="bulkDuePagination" class="small text-muted"></div>
            <div class="fw-semibold">
              <span id="bulkSelectedCount">0 selected</span>
              <span class="mx-2">|</span>
              Total: <span class="text-success" id="bulkSelectedTotal">₹0.00</span>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label fw-medium text-dark small mb-1">Collection Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control form-control-sm" id="bulkCollectedAt" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium text-dark small mb-1">Payment Method <span class="text-danger">*</span></label>
              <select class="form-select form-select-sm" id="bulkPaymentMethod" required>
                <option value="in_hand">Cash in hand</option>
                <option value="upi">UPI / GPay / QR</option>
                <option value="bank_transfer">Bank Transfer</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium text-dark small mb-1">Reference No.</label>
              <input type="text" class="form-control form-control-sm" id="bulkPaymentReference" maxlength="100" placeholder="Shared transaction/reference">
            </div>
          </div>
          <div class="mb-3">
            @include('admin.partials.bank-collection-fields', [
              'bankAccounts' => $bankAccounts,
              'bankContainerId' => 'bulkBankContainer',
              'bankSelectId' => 'bulk_internal_bank_account_id',
              'bankSelectName' => 'internal_bank_account_id',
              'qrContainerId' => 'bulkQrContainer',
              'qrBankNameId' => 'bulkQrBankName',
              'qrUpiIdId' => 'bulkQrUpiId',
              'qrImageWrapperId' => 'bulkQrImageWrapper',
              'bankTransferContainerId' => 'bulkBankTransferContainer',
              'bankTransferContentId' => 'bulkBankTransferContent',
            ])
          </div>
          <div class="mb-0">
            <label class="form-label fw-medium text-dark small mb-1">Remarks</label>
            <textarea class="form-control form-control-sm" id="bulkRemarks" rows="2" maxlength="1000" placeholder="Shared remarks for all selected items"></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
          <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-success px-4 shadow-xs" id="btnSubmitBulkCollect" disabled>
            <i class="ri-send-plane-line me-1"></i> Send for Verification
          </button>
        </div>
      </form>
    </div>
  </div>
  @endif

  <!-- Verify Collection Modal (for in-hand) -->
  <div class="modal fade" id="verifyCollectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Verify Collection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="verifyCollectionForm" action="#" method="post">
          @csrf
          <div class="modal-body">
            <input type="hidden" id="verifyCollectionId" name="collection_id">
            <input type="hidden" id="verifyCollectionType" name="collection_type" value="">
            <input type="hidden" id="verifyCollectionStatus" name="status" value="verified">
            <div class="mb-3">
              <label class="form-label">Remarks</label>
              <textarea class="form-control" name="remarks" rows="3" placeholder="Add verification remarks..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-danger" id="verifyRejectBtn" data-verify-status="rejected">Reject</button>
            <button type="button" class="btn btn-success" id="verifyApproveBtn" data-verify-status="verified">Approve</button>
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

  <div class="modal fade" id="bulkEmiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="bulkEmiModalTitle">EMI details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th id="bulkEmiModalLabel">EMI</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody id="bulkEmiModalBody"></tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('pricing-script')
<script>
  window.userRole = @json(auth()->user()->roles->pluck('name')->first() ?? '');
  window.userRoles = @json(auth()->user()->getRoleNames()->values());
  window.isAgentUser = @json((bool) $isAgent);
  window.canVerifyCollections = @json(!$isAgent && auth()->user()->hasAnyRole(['Admin', 'Staff']));

  document.addEventListener('DOMContentLoaded', function () {
    if (window.__agentCollectionVerifyBound) {
      return;
    }
    window.__agentCollectionVerifyBound = true;

    const form = document.getElementById('verifyCollectionForm');
    const approveBtn = document.getElementById('verifyApproveBtn');
    const rejectBtn = document.getElementById('verifyRejectBtn');
    if (!form || !approveBtn || !rejectBtn) {
      return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    let base = document.documentElement.getAttribute('data-base-url') || window.location.origin;
    if (!base.endsWith('/')) base += '/';

    const sendVerify = function (status, btn) {
      const statusInput = form.querySelector('[name="status"]');
      if (statusInput) statusInput.value = status;

      const buttons = [approveBtn, rejectBtn];
      if (btn && btn.disabled) return;
      buttons.forEach(b => { b.disabled = true; });
      const original = btn ? btn.innerHTML : '';
      if (btn) {
        btn.innerHTML = status === 'rejected'
          ? '<span class="spinner-border spinner-border-sm me-1"></span>Rejecting...'
          : '<span class="spinner-border spinner-border-sm me-1"></span>Approving...';
      }

      const restore = function () {
        buttons.forEach(b => { b.disabled = false; });
        if (btn) btn.innerHTML = original || (status === 'rejected' ? 'Reject' : 'Approve');
      };

      let collectionId = String(document.getElementById('verifyCollectionId')?.value || '').trim();
      const collectionType = String(document.getElementById('verifyCollectionType')?.value || '').trim();
      if (collectionType === 'chit' && collectionId && !collectionId.startsWith('chit_')) {
        collectionId = 'chit_' + collectionId;
      }
      if (!collectionId) {
        restore();
        Swal.fire({ icon: 'error', title: 'Verification Failed', text: 'Collection id is missing.' });
        return;
      }

      const body = new FormData(form);
      body.set('collection_id', collectionId);
      body.set('collection_type', collectionType || (collectionId.startsWith('chit_') ? 'chit' : 'loan'));
      body.set('status', status);

      fetch(base + 'app/agents/agent-collections/verify-one', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body
      })
        .then(r => r.json().catch(() => ({ success: false, message: 'Verification failed. Please try again.' })))
        .then(data => {
          restore();
          const modal = bootstrap.Modal.getInstance(document.getElementById('verifyCollectionModal'));
          if (modal) modal.hide();
          if (data.success) {
            Swal.fire({ icon: 'success', title: 'Success!', text: data.message || 'Collection updated successfully' })
              .then(() => window.location.reload());
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Verification Failed',
              text: data.message || 'Failed to verify collection'
            });
          }
        })
        .catch(() => {
          restore();
          Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to verify collection' });
        });
    };

    approveBtn.addEventListener('click', function (e) {
      e.preventDefault();
      sendVerify('verified', this);
    });
    rejectBtn.addEventListener('click', function (e) {
      e.preventDefault();
      sendVerify('rejected', this);
    });
    form.addEventListener('submit', function (e) {
      e.preventDefault();
    });
  });
</script>
@endpush
