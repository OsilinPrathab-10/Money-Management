@extends('layouts/layoutMaster')

@section('title', 'EMI/Interest Loan Repayments')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-style')
<style>
  .loan-account-link {
    text-decoration: none;
    transition: text-decoration 0.2s ease;
  }

  .loan-account-link:hover {
    text-decoration: underline;
  }

  .card-datatable.table-responsive {
    overflow-x: auto !important;
  }
  .datatables-repayments {
    width: 100% !important;
    margin: 0 !important;
  }

  /* Premium Tabs Custom Styling */
  #repaymentsTabs {
    border-bottom: none;
  }

  #repaymentsTabs .nav-item {
    margin-bottom: -1px;
  }

  #repaymentsTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1.25rem 1rem;
    font-weight: 500;
    color: #5d596c;
    transition: all 0.3s ease;
    border-radius: 0;
  }

  #repaymentsTabs .nav-link:hover {
    color: #7367f0;
    background-color: rgba(115, 103, 240, 0.04);
  }

  #repaymentsTabs .nav-link.active {
    color: #7367f0;
    border-bottom-color: #7367f0;
    background-color: transparent;
    font-weight: 600;
  }

  #repaymentsTabs .nav-link.active .badge {
    transform: scale(1.05);
  }

  #repaymentsTabs .badge {
    transition: all 0.3s ease;
    font-weight: 600;
    padding: 0.25em 0.6em;
  }

  #repaymentsTabs .nav-link i {
    font-size: 1.2rem;
    vertical-align: middle;
    transition: transform 0.3s ease;
  }

  #repaymentsTabs .nav-link:hover i {
    transform: translateY(-2px);
  }
</style>
@endsection

@section('page-script')
<script>
  window.isAdmin = @json(auth()->user()->hasRole('Admin'));
</script>
@vite(['resources/assets/custom-js/repayments.js'])
@endsection

@section('content')

<!-- Statistics Cards -->
<div class="row g-4 mb-4">
  <!-- Total EMIs Card -->
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Total EMIs</p>
            <h4 class="mb-0" id="stat-total-emis">{{ number_format($stats['total_emis']) }}</h4>
            <small class="text-muted" id="stat-total-emis-label">Overdue & Upcoming</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="icon-base ri ri-file-list-3-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Paid EMIs Card -->
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Paid EMIs</p>
            <h4 class="mb-0" id="stat-paid-emis">{{ number_format($stats['paid_emis']) }}</h4>
            <small class="text-muted" id="stat-total-collected">₹{{ number_format($stats['total_collected'], 2) }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success">
              <i class="icon-base ri ri-checkbox-circle-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Pending EMIs Card -->
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Pending EMIs</p>
            <h4 class="mb-0" id="stat-pending-emis">{{ number_format($stats['pending_emis']) }}</h4>
            <small class="text-muted" id="stat-total-pending">₹{{ number_format($stats['total_pending'], 2) }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-warning">
              <i class="icon-base ri ri-time-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overdue EMIs Card -->
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Overdue EMIs</p>
            <h4 class="mb-0" id="stat-overdue-emis">{{ number_format($stats['overdue_emis']) }}</h4>
            <small class="text-muted" id="stat-overdue-emis-label">Needs attention</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger">
              <i class="icon-base ri ri-alarm-warning-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- EMI Repayments Table -->
<div class="card shadow-sm">
  <!-- Card tabs navigation -->
  <div class="card-header p-0 border-bottom">
    <div class="nav-align-top">
      <ul class="nav nav-tabs nav-fill" role="tablist" id="repaymentsTabs">
        <li class="nav-item">
          <button type="button" class="nav-link active" role="tab" data-status="all" data-bs-toggle="tab">
            <i class="icon-base ri ri-layout-grid-line me-1_5 text-primary"></i>
            All
            <span class="badge rounded-pill bg-primary ms-1" id="tab-count-all">{{ number_format($stats['total_emis'] ?? 0) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="overdue" data-bs-toggle="tab">
            <i class="icon-base ri ri-alarm-warning-line me-1_5 text-danger"></i>
            Overdue
            <span class="badge rounded-pill bg-danger ms-1" id="tab-count-overdue">{{ number_format($stats['overdue_emis']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="pending" data-bs-toggle="tab">
            <i class="icon-base ri ri-time-line me-1_5 text-warning"></i>
            Pending
            <span class="badge rounded-pill bg-warning ms-1 text-dark" id="tab-count-pending">{{ number_format($stats['pending_emis']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="partial" data-bs-toggle="tab">
            <i class="icon-base ri ri-pie-chart-line me-1_5 text-info"></i>
            Partial Paid
            <span class="badge rounded-pill bg-info ms-1" id="tab-count-partial">{{ number_format($stats['partial_emis']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="paid" data-bs-toggle="tab">
            <i class="icon-base ri ri-checkbox-circle-line me-1_5 text-success"></i>
            Paid
            <span class="badge rounded-pill bg-success ms-1" id="tab-count-paid">{{ number_format($stats['paid_emis']) }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>

  <!-- Filters row -->
  <div class="card-body border-bottom py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <h5 class="mb-0 fw-semibold text-primary" id="tableTitle">All EMI Repayments</h5>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <!-- Hidden Status Filter for backward compatibility with repayments.js -->
        <input type="hidden" id="statusFilter" value="all" />

        <!-- Area Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="areaFilter" class="form-label mb-0 text-nowrap small fw-medium">Area:</label>
          <select id="areaFilter" class="form-select form-select-sm" style="min-width: 130px;">
            <option value="">All Areas</option>
            @foreach($locations as $loc)
              <option value="{{ $loc->id }}">{{ $loc->name }}</option>
            @endforeach
          </select>
        </div>

        <!-- Loan Type Filter (Category) -->
        <div class="d-flex align-items-center gap-2">
          <label for="loanTypeFilter" class="form-label mb-0 text-nowrap small fw-medium">Loan Type:</label>
          <select id="loanTypeFilter" class="form-select form-select-sm" style="min-width: 140px;">
            <option value="">All Loan Types</option>
            @foreach($loanTypes as $lt)
              <option value="{{ $lt->id }}">{{ $lt->name }}</option>
            @endforeach
          </select>
        </div>

        <!-- Product Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="productFilter" class="form-label mb-0 text-nowrap small fw-medium">Product:</label>
          <select id="productFilter" class="form-select form-select-sm" style="min-width: 150px;">
            <option value="">All Products</option>
            @foreach($products as $prod)
              <option value="{{ $prod->id }}" data-loan-type-id="{{ $prod->loan_type_id }}">{{ $prod->loan_name }}{{ $prod->loanType ? ' ('.$prod->loanType->name.')' : '' }}</option>
            @endforeach
          </select>
        </div>

        <!-- Loan Mode Filter (EMI / Open Loan) -->
        <div class="d-flex align-items-center gap-2">
          <label for="loanModeFilter" class="form-label mb-0 text-nowrap small fw-medium">Mode:</label>
          <select id="loanModeFilter" class="form-select form-select-sm" style="min-width: 140px;">
            <option value="all">All Modes</option>
            <option value="emi">Standard EMI</option>
            <option value="interest_only">Open Loan (Interest-Only)</option>
          </select>
        </div>

        <!-- Date Range Filter (dd/mm/yyyy) -->
        <div class="d-flex align-items-center gap-2">
          <label for="fromDateFilter" class="form-label mb-0 text-nowrap small fw-medium">From:</label>
          <input type="text" id="fromDateFilter" class="form-control form-control-sm flatpickr-date" placeholder="dd/mm/yyyy" style="max-width: 130px;" />
        </div>
        <div class="d-flex align-items-center gap-2">
          <label for="toDateFilter" class="form-label mb-0 text-nowrap small fw-medium">To:</label>
          <input type="text" id="toDateFilter" class="form-control form-control-sm flatpickr-date" placeholder="dd/mm/yyyy" style="max-width: 130px;" />
        </div>

        <button type="button" id="resetFilters" class="btn btn-sm btn-outline-secondary">
          <i class="icon-base ri ri-refresh-line me-1"></i>
          Reset
        </button>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <div class="px-4 pt-3 pb-1 text-muted small d-flex justify-content-between align-items-center">
      <span>Showing <strong>overdue and upcoming 1 payment</strong> per client.</span>
    </div>
    <table class="datatables-repayments table table-hover text-nowrap" id="repaymentsTable">
      <thead>
        <tr>
          @if(auth()->user()->hasRole('Admin'))
          <th style="width: 25px; text-align: center;"><input type="checkbox" class="form-check-input" id="selectAllEmis"></th>
          @else
          <th style="width: 25px;"></th>
          @endif
          <th>S.No</th>
          <th>Account No</th>
          <th>Borrower Name</th>
          <th>Agent</th>
          <th>Phone Number</th>
          <th>Zone</th>
          <th>Due Date</th>
          <th>Amount (EMI / Int / Prin)</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>



<!-- EMI Details Modal -->
<div class="modal fade" id="emiDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="emiDetailsTitle">EMI Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="emiDetailsBody">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex gap-2">
          <a href="#" id="emiScheduleLink" class="btn btn-outline-secondary">
            <i class="icon-base ri ri-file-list-3-line me-1"></i>
            View Full Schedule
          </a>
          <a href="#" id="emiPrintReceiptLink" class="btn btn-outline-primary d-none" target="_blank">
            <i class="icon-base ri ri-printer-line me-1"></i>
            Print Receipt
          </a>
        </div>
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- EMI Collection History Modal -->
<div class="modal fade" id="emiHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment History - EMI #<span id="historyEmiNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="px-4 py-3 bg-light border-bottom">
          <div class="row text-center">
            <div class="col-6 border-end">
              <small class="text-muted d-block">EMI Amount / Cycle Interest</small>
              <h6 class="mb-0 fw-bold" id="historyTotalAmount">₹0.00</h6>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Total Paid</small>
              <h6 class="mb-0 fw-bold text-success" id="historyPaidAmount">₹0.00</h6>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead>
              <tr>
                <th class="ps-4">Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="pe-4 text-end history-action-header d-none">Action</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <!-- History items will be loaded here -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@if(auth()->user()->hasRole('Admin'))
<!-- Floating Bulk Payment Bar -->
<div id="bulkPayBar" class="d-none position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg bg-white border rounded-4 p-3" style="width: 90%; max-width: 800px; border-top: 4px solid #7367f0 !important; z-index: 1090; transition: all 0.3s ease-in-out;">
  <div class="d-flex flex-column gap-3">
    <!-- Header with total and count -->
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary rounded-pill fs-6" id="bulkSelectedCount">0</span>
        <h6 class="mb-0 fw-semibold text-dark" id="bulkBarTitle">EMIs Selected for Bulk Payment</h6>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small fw-medium" id="bulkTotalLabel">Total Overdue:</span>
        <span class="fs-5 fw-bold text-primary" id="bulkTotalAmount">₹0.00</span>
      </div>
    </div>
    
    <!-- Client-wise breakdowns -->
    <div id="bulkClientsContainer" class="overflow-y-auto px-2" style="max-height: 120px;">
      <!-- JavaScript will dynamically render client breakdowns here -->
    </div>
    
    <!-- Action buttons -->
    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
      <button type="button" id="bulkCancelBtn" class="btn btn-sm btn-outline-secondary">
        <i class="ri-close-line me-1"></i> Cancel Selection
      </button>
      
      <div class="d-flex gap-2">
        <button type="button" id="bulkPayBtn" class="btn btn-sm btn-primary px-4">
          <i class="ri-wallet-3-line me-1"></i> Full Pay Selected  
        </button>
        <button type="button" id="bulkUndoBtn" class="btn btn-sm btn-danger px-4 d-none">
          <i class="ri-history-line me-1"></i> Undo Selected Payments
        </button>
      </div>
    </div>
  </div>
</div>
@endif

@endsection
