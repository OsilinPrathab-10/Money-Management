@extends('layouts/layoutMaster')

@section('title', 'Client Management')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss', 'resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js', 'resources/assets/vendor/libs/cleave-zen/cleave-zen.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/custom-js/loan-applications.js', 'resources/assets/custom-js/chit-applications.js', 'resources/assets/custom-js/chit-need-month.js', 'resources/assets/custom-js/fd-applications.js'])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
  }

  .datatables-users {
    width: max-content;
    min-width: 100%;
    white-space: nowrap;
  }

  .datatables-users .dropdown-menu {
    z-index: 1070 !important;
  }
</style>
@endsection

<!-- Page Scripts -->
@section('page-script')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    window.userRole = "{{ auth()->user()->roles->first()->name ?? 'Guest' }}";
  </script>
  @vite(['resources/assets/custom-js/client-management.js'])
@endsection

@section('content')
  <!-- Success/Error Alerts -->
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

  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-lg-3 col-xl">
      <div class="card card-border-shadow-primary h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2 pb-1">
            <div class="avatar me-2">
              <span class="avatar-initial rounded bg-label-primary">
                <i class="ri-group-line ri-24px"></i>
              </span>
            </div>
            <h4 class="ms-1 mb-0">{{ $totalUser }}</h4>
          </div>
          <p class="mb-1 fw-medium text-heading">Total Clients</p>
          <p class="mb-0 small text-muted">All registered</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3 col-xl">
      <div class="card card-border-shadow-success h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2 pb-1">
            <div class="avatar me-2">
              <span class="avatar-initial rounded bg-label-success">
                <i class="ri-user-follow-line ri-24px"></i>
              </span>
            </div>
            <h4 class="ms-1 mb-0">{{ $activeClients }}</h4>
          </div>
          <p class="mb-1 fw-medium text-heading">Active</p>
          <p class="mb-0 small text-muted">Currently active</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3 col-xl">
      <div class="card card-border-shadow-secondary h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2 pb-1">
            <div class="avatar me-2">
              <span class="avatar-initial rounded bg-label-secondary">
                <i class="ri-user-unfollow-line ri-24px"></i>
              </span>
            </div>
            <h4 class="ms-1 mb-0">{{ $inactiveClients }}</h4>
          </div>
          <p class="mb-1 fw-medium text-heading">Inactive</p>
          <p class="mb-0 small text-muted">Not active</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3 col-xl">
      <div class="card card-border-shadow-warning h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2 pb-1">
            <div class="avatar me-2">
              <span class="avatar-initial rounded bg-label-warning">
                <i class="ri-user-search-line ri-24px"></i>
              </span>
            </div>
            <h4 class="ms-1 mb-0">{{ $pendingClients }}</h4>
          </div>
          <p class="mb-1 fw-medium text-heading">Pending</p>
          <p class="mb-0 small text-muted">Awaiting KYC</p>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3 col-xl">
      <div class="card card-border-shadow-danger h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2 pb-1">
            <div class="avatar me-2">
              <span class="avatar-initial rounded bg-label-danger">
                <i class="ri-user-forbid-line ri-24px"></i>
              </span>
            </div>
            <h4 class="ms-1 mb-0">{{ $blacklistedClients }}</h4>
          </div>
          <p class="mb-1 fw-medium text-heading">Blacklisted</p>
          <p class="mb-0 small text-muted">Restricted</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Users List Table -->
  <div class="card">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <h5 class="card-title mb-0">Clients Overview</h5>
      <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-sm-end">
        @if(!auth()->user()->hasRole('Agent'))
        <button type="button" id="btnBulkAssignClients" class="btn btn-primary btn-sm w-auto d-none me-1">
          <i class="icon-base ri ri-user-add-line me-1"></i> Assign Agent
        </button>
        <button type="button" id="btnBulkAssignZone" class="btn btn-outline-primary btn-sm w-auto d-none me-1">
          <i class="icon-base ri ri-map-pin-line me-1"></i> Assign Zone
        </button>
        <button type="button" id="btnBulkDeleteClients" class="btn btn-danger btn-sm w-auto d-none">
          <i class="ri-delete-bin-line me-1"></i> Delete Selected
        </button>
        @endif
        @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
        <a href="{{ route('client-management-recycle-bin') }}" class="btn btn-outline-secondary btn-sm w-auto shadow-sm">
          <i class="icon-base ri ri-delete-bin-6-line me-1"></i> Recycle Bin
        </a>
        @endif
        <button type="button" class="btn btn-outline-secondary btn-sm w-auto shadow-sm" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
          <i class="icon-base ri ri-file-upload-line me-1"></i> Bulk Import
        </button>
        <a href="{{ route('client-management-add') }}" class="btn btn-outline-primary btn-sm w-auto shadow-sm">
          <i class="icon-base ri ri-user-add-line me-1"></i> Add New Client
        </a>
      </div>
    </div>
    <div class="card-body border-top">
      <div class="row g-3 pt-2 align-items-end">
        <div class="col-md-3 client_account_type">
          <label class="form-label small fw-bold">Filter by Account</label>
          <select id="FilterAccountType" class="form-select no-search">
            <option value="">All Accounts</option>
            <option value="emi">Standard EMI Loan</option>
            <option value="interest_only">Open Loan</option>
            <option value="chit">Chit Fund</option>
            <option value="any_loan">Any Loan Account</option>
            <option value="no_accounts">Without Accounts</option>
          </select>
        </div>
        <div class="col-md-3 client_location">
          <label class="form-label small fw-bold">Filter by Area</label>
          <select id="FilterLocation" class="form-select no-search text-capitalize">
            <option value="">All Areas</option>
            @foreach($locations as $location)
              <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->city }}, {{ $location->state }})</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 client_status">
          <label class="form-label small fw-bold">Filter by Status</label>
          <select id="FilterStatus" class="form-select no-search text-capitalize">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="inactive">Inactive</option>
            <option value="blacklist">Blacklisted</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="button" id="btnResetClientFilters" class="btn btn-outline-secondary w-100">
            <i class="icon-base ri ri-refresh-line me-1"></i> Reset Filters
          </button>
        </div>
      </div>
    </div>
    <div class="card-datatable table-responsive">
      <table class="datatables-users table table-hover">
        <thead>
          <tr>
            <th></th>
            <th class="sorting_disabled">
              @if(!auth()->user()->hasRole('Agent'))
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="selectAllClients">
              </div>
              @endif
            </th>
            <th>Client ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Area</th>
            <th>Loans</th>
            <th>Chits</th>
            <th>Assigned Agent</th>
            <th>Added By</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-body text-center p-4">
          <div class="mb-4 text-warning">
             <i class="ri-error-warning-line display-4"></i>
          </div>
          <h5 class="mb-2">Move to Recycle Bin?</h5>
          <p class="text-muted mb-0">
            The client and their loan, chit and deposit accounts will be archived.
            You can restore them from the Recycle Bin.
          </p>
        </div>
        <div class="modal-footer justify-content-center border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Yes, Move It!</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-4">
          <div class="mb-4 text-success">
             <i class="ri-checkbox-circle-line display-4"></i>
          </div>
          <h5 class="mb-2">Moved to Recycle Bin</h5>
          <p class="text-muted mb-0">The client can be restored from the Recycle Bin.</p>
          <button type="button" class="btn btn-primary btn-sm mt-4 w-100" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>

  <!-- Bulk Assign Agent Modal -->
  <div class="modal fade" id="assignAgentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title">Assign <span id="assignCount">0</span> Clients to Agent</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="assignAgentForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="agentSelect">Select Agent <span class="text-danger">*</span></label>
              <select id="agentSelect" name="agent_id" class="form-select select2" required data-placeholder="Choose an agent">
                <option></option>
                @foreach($agents as $agent)
                  <option value="{{ $agent->id }}">{{ $agent->agent_name }} ({{ $agent->agent_code }})</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="assignRemarks">Remarks (Optional)</label>
              <textarea id="assignRemarks" name="remarks" class="form-control" rows="2" placeholder="Enter any specific instructions..."></textarea>
            </div>
            <div class="alert alert-info d-flex align-items-center" role="alert">
              <span class="alert-icon text-info me-2">
                <i class="ri-information-line"></i>
              </span>
              <span>This will also assign all <strong>active EMIs</strong> of these clients to the selected agent.</span>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnConfirmAssign">
              <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
              Confirm Assignment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bulk Assign Zone Modal -->
  <div class="modal fade" id="assignZoneModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title">Assign Zone to <span id="assignZoneCount">0</span> Clients</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="assignZoneForm">
          @csrf
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label" for="zoneSelect">Select Zone <span class="text-danger">*</span></label>
              <select id="zoneSelect" name="location_id" class="form-select select2-zone" required data-placeholder="Choose a zone">
                <option></option>
                @foreach($locations as $location)
                  <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->city }}, {{ $location->state }})</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnConfirmAssignZone">
              <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
              Confirm Assignment
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bulk Import Modal -->
  <div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <h5 class="modal-title">Bulk Import Clients</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="bulkImportForm" enctype="multipart/form-data">
          @csrf
          <div class="modal-body">
            <div class="mb-4 text-center">
              <p class="text-muted small">Download the template, fill in client data, and upload the file to register clients in bulk.</p>
              <a href="{{ route('client-management-download-template') }}" class="btn btn-sm btn-outline-primary">
                <i class="ri-download-line me-1"></i> Download Template
              </a>
            </div>
            <div class="mb-3">
              <label class="form-label" for="importFile">Choose Excel/CSV File <span class="text-danger">*</span></label>
              <input type="file" id="importFile" name="import_file" class="form-control" accept=".xlsx,.xls,.csv" required>
            </div>
            
            <!-- Error message container -->
            <div id="importErrorsContainer" class="alert alert-danger d-none mt-3" style="max-height: 250px; overflow-y: auto;">
              <h6 class="alert-heading fw-bold mb-1"><i class="ri-error-warning-line me-1"></i> Import Errors:</h6>
              <ul id="importErrorsList" class="mb-0 ps-3 small"></ul>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnConfirmImport">
              <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
              Upload & Import
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Selfie / profile photo preview --}}
  <div class="modal fade" id="clientSelfieModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-bottom py-3">
          <h5 class="modal-title mb-0" id="clientSelfieModalTitle">Profile Photo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center p-4">
          <img id="clientSelfieModalImg" src="" alt="Client selfie"
               class="img-fluid rounded shadow-sm"
               style="max-height: 70vh; width: auto; object-fit: contain;">
        </div>
      </div>
    </div>
  </div>

  <!-- Per-account Loan / Chit Penalty Modal -->
  <div class="modal fade" id="clientPenaltyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header border-bottom">
          <div>
            <h5 class="modal-title mb-0" id="clientPenaltyModalTitle">Apply Penalty</h5>
            <small class="text-muted" id="clientPenaltyClientName"></small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="clientPenaltyLoading" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 mb-0">Loading accounts…</p>
          </div>
          <div id="clientPenaltyEmpty" class="text-center py-5 d-none">
            <i class="ri-inbox-line display-6 text-muted"></i>
            <p class="text-muted mt-2 mb-0">No accounts found for this client.</p>
          </div>
          <div id="clientPenaltyList" class="d-flex flex-column gap-3"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  @include('admin.clients.modals.modal-apply-loan-generic')
  @include('admin.clients.modals.modal-apply-chit', [
      'verifiedClients' => $verifiedClients ?? collect(),
      'availableGroups' => $availableGroups ?? collect(),
      'agents' => $agents ?? collect(),
  ])
  @include('admin.clients.modals.modal-apply-fd-generic', [
      'verifiedClients' => $verifiedClients ?? collect(),
      'fdSchemes' => $fdSchemes ?? collect(),
      'payoutOptions' => $payoutOptions ?? [],
  ])
@endsection
