@extends('layouts/layoutMaster')

@section('title', 'Payment Receipts')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/repayment-receipts.js'])
@endsection

@section('page-style')
<style>
  .payment-method-card {
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .payment-method-card:hover {
    border-color: var(--bs-primary) !important;
    box-shadow: 0 0.125rem 0.5rem rgba(var(--bs-primary-rgb), 0.15);
    transform: translateY(-2px);
  }
  
  .payment-method-card.border-primary {
    background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
    border-width: 2px !important;
  }
  
  .payment-method-card .form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
  }
  .card-datatable.table-responsive {
    overflow-x: auto !important;
  }
  .datatables-receipts {
    width: 100% !important;
    margin: 0 !important;
  }
</style>
@endsection

@section('content')
<!-- Stats Cards -->
<div class="row g-6 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="ri-file-list-3-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['total_receipts']) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Total Receipts</p>
        <p class="mb-0 small text-muted">All-time collection count</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-success">
              <i class="ri-money-rupee-circle-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['total_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Total Collected</p>
        <p class="mb-0 small text-muted">Lifetime collection amount</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-info">
              <i class="ri-calendar-event-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['month_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Monthly Collection</p>
        <p class="mb-0 small text-muted">Current month performance</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-warning">
              <i class="ri-time-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['today_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Today's Collection</p>
        <p class="mb-0 small text-muted">Daily collection summary</p>
      </div>
    </div>
  </div>
</div>

<!-- Payment Receipts Table -->
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-0">Payment Receipts</h5>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-receipts table table-hover text-nowrap" id="receiptsTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Receipt No.</th>
          <th>Client Name</th>
          <th>Zone</th>
          <th>Application No.</th>
          <th>Amount</th>
          <th>Payment Method</th>
          <th>Payment Date</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- View Receipt Modal -->
<div class="modal fade" id="viewReceiptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment Receipt Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="receipt-container p-4" id="receiptContent">
          <!-- Receipt content will be loaded here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">
          <i class="icon-base ri ri-printer-line me-1"></i> Print Receipt
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
