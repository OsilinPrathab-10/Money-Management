@extends('layouts/layoutMaster')

@section('title', 'EMI/Interest Loan Repayments')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/select2/select2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/custom-js/bank-payment-fields.js'
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
  .client-emi-details {
    background-color: #f8f9fa;
    padding: 1rem 1.25rem;
  }
  .client-emi-details .emi-section-title {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.5rem;
  }
  .client-emi-details table {
    margin-bottom: 1rem;
    background: #fff;
  }
  td.details-control {
    cursor: pointer;
    text-align: center;
    vertical-align: middle;
  }
  td.details-control i {
    font-size: 1.25rem;
    color: #7367f0;
    transition: transform 0.2s ease;
  }
  tr.shown td.details-control i {
    transform: rotate(90deg);
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
  window.isAdminOrStaff = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'));
  window.isAdmin = window.isAdminOrStaff;
  window._bankPaymentGroupsQueue = window._bankPaymentGroupsQueue || [];
  window._bankPaymentGroupsQueue.push({
    methodSelectId: 'bulk_payment_method',
    bankSelectId: 'repaymentBulkBankAccount',
    bankContainerId: 'repaymentBulkBankWrap',
    bankDetailsCardId: 'repaymentBulkBankDetailsCard',
    qrContainerId: 'repaymentBulkQrContainer',
    qrBankNameId: 'repaymentBulkQrBankName',
    qrUpiIdId: 'repaymentBulkQrUpiId',
    qrImageWrapperId: 'repaymentBulkQrImageWrapper',
    bankTransferContainerId: 'repaymentBulkBankTransferContainer',
    bankTransferContentId: 'repaymentBulkBankTransferContent'
  });
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
            <p class="mb-1">Pending EMIs (This Month)</p>
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
          <button type="button" class="nav-link active" role="tab" data-status="overdue" data-bs-toggle="tab">
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
          <button type="button" class="nav-link" role="tab" data-status="upcoming" data-bs-toggle="tab">
            <i class="icon-base ri ri-calendar-schedule-line me-1_5 text-secondary"></i>
            Upcoming
            <span class="badge rounded-pill bg-secondary ms-1" id="tab-count-upcoming">{{ number_format($stats['upcoming_emis'] ?? 0) }}</span>
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
      <h5 class="mb-0 fw-semibold text-primary" id="tableTitle">Overdue Clients</h5>
      <div class="d-flex flex-wrap align-items-center gap-3">
        <!-- Hidden Status Filter for backward compatibility with repayments.js -->
        <input type="hidden" id="statusFilter" value="overdue" />
        <input type="hidden" id="termUnitFilter" value="" />

        <!-- Loan Mode Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="loanModeFilter" class="form-label mb-0 text-nowrap small fw-medium">Mode:</label>
          <select id="loanModeFilter" class="form-select form-select-sm no-search" style="min-width: 120px; width: 130px;">
            <option value="">All Modes</option>
            <option value="emi">Standard EMI</option>
            <option value="interest_only">Open Loan</option>
          </select>
        </div>

        <!-- Loan Type Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="loanTypeFilter" class="form-label mb-0 text-nowrap small fw-medium">Loan Type:</label>
          <select id="loanTypeFilter" class="form-select form-select-sm no-search" style="min-width: 160px; max-width: 220px;">
            <option value="">All Types</option>
            @if(isset($loanTypes))
              @foreach($loanTypes as $lt)
                <option value="{{ $lt->id }}">{{ trim($lt->name) }}</option>
              @endforeach
            @endif
          </select>
        </div>

        <!-- Account Number Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="accountNumberFilter" class="form-label mb-0 text-nowrap small fw-medium">A/C No:</label>
          <input type="text" id="accountNumberFilter" class="form-control form-control-sm" placeholder="Search A/C No" style="width: 130px;" />
        </div>

        <!-- Area Filter -->
        <div class="d-flex align-items-center gap-2">
          <label for="areaFilter" class="form-label mb-0 text-nowrap small fw-medium">Area:</label>
          <select id="areaFilter" class="form-select form-select-sm no-search" style="width: 130px;">
            <option value="">All Areas</option>
            @foreach($locations as $loc)
              <option value="{{ $loc->id }}">{{ trim($loc->name) }}</option>
            @endforeach
          </select>
        </div>

        @include('partials.date-range-filter', [
          'fromId' => 'fromDateFilter',
          'toId' => 'toDateFilter',
          'presetId' => 'repaymentsDatePreset',
        ])

        <button type="button" id="resetFilters" class="btn btn-sm btn-outline-secondary">
          <i class="icon-base ri ri-refresh-line me-1"></i>
          Reset
        </button>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <div class="px-4 pt-3 pb-1 text-muted small d-flex justify-content-between align-items-center gap-3">
      <span>Clients are listed by account. Expand a row to view EMI details. Use <strong>Show</strong> (25–300) to load more accounts per page, or <strong>Select all matching EMIs (all pages)</strong> for bulk pay across the full filtered list.</span>
      @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      <button type="button" id="selectAllMatchingEmis" class="btn btn-sm btn-outline-primary text-nowrap d-none">
        <i class="icon-base ri ri-checkbox-multiple-line me-1"></i>
        Select all matching EMIs
      </button>
      @endif
    </div>
    <table class="datatables-repayments table table-hover" id="repaymentsTable">
      <thead>
        <tr>
          <th style="width: 30px;"></th>
          <th>S.No</th>
          <th>Account No</th>
          <th>Borrower Name</th>
          <th>Agent</th>
          <th>Phone Number</th>
          <th>Zone</th>
          <th>EMI Summary</th>
          <th>Total Due</th>
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

@if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
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

<!-- Bulk Pay Modal (Repayments List) -->
<div class="modal fade" id="bulkPayModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <form id="bulkPayForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      @csrf
      <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
        <div class="d-flex align-items-center gap-2_5">
          <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
            <i class="ri-checkbox-multiple-line"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-primary mb-0 fs-6">Bulk EMI Payment</h5>
            <small class="text-primary opacity-75" style="font-size:0.75rem;">Collect payment for selected EMIs</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-sm-4">
        {{-- Payment Type / Mode Selection --}}
        <div class="mb-3">
          <label class="form-label fw-semibold text-dark small mb-1">Payment Mode</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bulk_pay_type" id="bulkPayTypeFull" value="full" checked>
              <label class="form-check-label fw-medium text-dark small" for="bulkPayTypeFull">
                Full Pay (Pay Total Selected Dues)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="bulk_pay_type" id="bulkPayTypePartial" value="partial">
              <label class="form-check-label fw-medium text-dark small" for="bulkPayTypePartial">
                Partial Pay (Custom Amount Allocation)
              </label>
            </div>
          </div>
        </div>

        {{-- Selected Summary --}}
        <div class="mb-3">
          <label class="form-label fw-semibold text-dark small mb-1">Selected EMIs Summary</label>
          <div id="bulkSelectedEmisSummary" class="small text-muted border rounded-3 p-3 bg-label-secondary" style="max-height: 120px; overflow-y: auto;"></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="bulk_paid_amount">Total Amount to Pay (₹) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
              <input type="number" step="0.01" id="bulk_paid_amount" name="paid_amount" class="form-control fw-bold text-dark" required readonly>
            </div>
            <small class="text-muted d-block mt-1" id="bulkPaidAmountHelp" style="font-size:0.72rem;">Full payment selected.</small>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="bulk_paid_date">Payment Date <span class="text-danger">*</span></label>
            <input type="date" id="bulk_paid_date" name="paid_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="bulk_payment_method">Payment Method <span class="text-danger">*</span></label>
            <select id="bulk_payment_method" name="payment_method" class="form-select form-select-sm no-search" required>
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="bulk_remarks">Remarks</label>
            <input type="text" id="bulk_remarks" name="remarks" class="form-control form-control-sm" placeholder="Optional notes...">
          </div>
        </div>

        <div class="mb-0">
          @include('admin.partials.bank-collection-fields', [
            'bankAccounts' => $bankAccounts ?? [],
            'bankContainerId' => 'repaymentBulkBankWrap',
            'bankSelectId' => 'repaymentBulkBankAccount',
            'bankSelectName' => 'internal_bank_account_id',
            'bankDetailsCardId' => 'repaymentBulkBankDetailsCard',
            'qrContainerId' => 'repaymentBulkQrContainer',
            'qrBankNameId' => 'repaymentBulkQrBankName',
            'qrUpiIdId' => 'repaymentBulkQrUpiId',
            'qrImageWrapperId' => 'repaymentBulkQrImageWrapper',
            'bankTransferContainerId' => 'repaymentBulkBankTransferContainer',
            'bankTransferContentId' => 'repaymentBulkBankTransferContent',
            'wrapperClass' => 'mb-3',
          ])
        </div>
      </div>

      <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
        <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success px-4 shadow-xs" id="btnSubmitRepaymentBulkPay">
          <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
          Confirm Bulk Payment
        </button>
      </div>
    </form>
  </div>
</div>
@endif

@endsection
