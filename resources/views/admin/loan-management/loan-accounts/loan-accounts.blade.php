@extends('layouts/layoutMaster')

@section('title', 'Loan Accounts')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('page-style')
<style>
  #loanAccountsTable_wrapper > .row {
    margin: 0;
    padding: 0 1.5rem;
  }

  #loanAccountsTable_wrapper > .row:first-of-type {
    padding-top: 1.25rem;
  }

  #loanAccountsTable_wrapper > .row:last-of-type {
    padding-bottom: 1.25rem;
  }

  #loanAccountsTable_wrapper .dataTables_filter {
    margin-right: 1rem;
    display: flex;
    align-items: center;
  }

  #loanAccountsTable_wrapper .dataTables_filter input[type="search"] {
    min-width: 220px;
    border-radius: 0.65rem;
  }

  .card-datatable.table-responsive {
    overflow-x: auto !important;
    display: block;
    width: 100%;
    -webkit-overflow-scrolling: touch;
  }
  #loanAccountsTable {
    width: 100% !important;
    margin: 0 !important;
  }
  #loanAccountsTable th,
  #loanAccountsTable td {
    white-space: nowrap !important;
    padding-left: 15px !important;
    padding-right: 15px !important;
  }

  #loanAccountsTable_wrapper .dt-action-buttons .dt-buttons {
    display: flex;
    align-items: center;
  }

  #loanAccountsTable_wrapper .dt-action-buttons .dt-button {
    border-radius: 0.65rem;
  }

  .loan-account-link {
    text-decoration: none;
    transition: text-decoration 0.2s ease;
  }

  .loan-account-link:hover {
    text-decoration: underline;
  }
</style>
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/loan-accounts.js'])
@endsection

@section('content')

<div class="row g-6 mb-6">
  <!-- Statistics Cards -->
  <div class="col-sm-6 col-xl-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">Active Loans</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2">{{ $activeLoans }}</h4>
            </div>
            <small class="mb-0">Currently active</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success">
              <i class="icon-base ri ri-file-list-3-line icon-26px"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="col-sm-6 col-xl-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">Closed Loans</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2">{{ $closedLoans }}</h4>
            </div>
            <small class="mb-0">Successfully closed</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger">
              <i class="icon-base ri ri-check-double-line icon-26px"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Loan Accounts Table -->
<div class="card">
  <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
    <h5 class="mb-0">Loan Accounts</h5>
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-2">
        <label for="fromDate" class="form-label mb-0 text-nowrap fw-medium">From Date:</label>
        <input type="date" id="fromDate" class="form-control form-control-sm" style="min-width: 150px;">
      </div>
      <div class="d-flex align-items-center gap-2">
        <label for="toDate" class="form-label mb-0 text-nowrap fw-medium">To Date:</label>
        <input type="date" id="toDate" class="form-control form-control-sm" style="min-width: 150px;">
      </div>
      <div class="d-flex align-items-center gap-2">
        <label class="mb-0">Filter by Status:</label>
        <select id="statusFilter" class="form-select form-select-sm" style="width: 150px;">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="closed">Closed</option>
          <option value="foreclosed">Foreclosed</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table id="loanAccountsTable" class="table text-nowrap">
        <thead>
          <tr>
            <th>S.No</th>
            <th>Account Number</th>
            <th>Client Name</th>
            <th>Zone</th>
            <th>Loan Type</th>
            <th>Loan Amount</th>
            <th>Tenure</th>
            <th>EMI Amt/Cycle Inst</th>
            <th>Outstanding</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- DataTables will populate this via AJAX -->
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection
