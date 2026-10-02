@extends('layouts/layoutMaster')

@section('title', 'Chit Accounts')

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
  #chitAccountsTable_wrapper > .row { margin: 0; padding: 0 1.5rem; }
  #chitAccountsTable_wrapper > .row:first-of-type { padding-top: 1.25rem; }
  #chitAccountsTable_wrapper > .row:last-of-type { padding-bottom: 1.25rem; }
  #chitAccountsTable_wrapper .dataTables_filter input[type="search"] { min-width: 220px; border-radius: 0.65rem; }
  .card-datatable.table-responsive { overflow-x: auto !important; display: block; width: 100%; }
  #chitAccountsTable { width: 100% !important; margin: 0 !important; }
  #chitAccountsTable th, #chitAccountsTable td { white-space: nowrap !important; padding-left: 15px !important; padding-right: 15px !important; }
  .chit-account-link { text-decoration: none; }
  .chit-account-link:hover { text-decoration: underline; }
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
@vite(['resources/assets/custom-js/chit-accounts.js'])
@endsection

@section('content')

<div class="row g-6 mb-6">
  <div class="col-sm-6 col-xl-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
          <div class="content-left">
            <span class="text-heading">Active Chit Accounts</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2">{{ $activeAccounts }}</h4>
            </div>
            <small class="mb-0">Currently active</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success">
              <i class="icon-base ri ri-group-2-line icon-26px"></i>
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
            <span class="text-heading">Completed Accounts</span>
            <div class="d-flex align-items-center my-1">
              <h4 class="mb-0 me-2">{{ $completedAccounts }}</h4>
            </div>
            <small class="mb-0">Successfully completed</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="icon-base ri ri-check-double-line icon-26px"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
    <h5 class="mb-0">Chit Accounts</h5>
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="d-flex align-items-center gap-2">
        <label for="accountNumberFilter" class="form-label mb-0 text-nowrap fw-medium">Chit A/C No:</label>
        <input type="text" id="accountNumberFilter" class="form-control form-control-sm" placeholder="Search A/C No" style="min-width: 140px;">
      </div>
      @include('partials.date-range-filter', [
        'fromId' => 'fromDate',
        'toId' => 'toDate',
        'presetId' => 'chitAccountsDatePreset',
      ])
      <div class="d-flex align-items-center gap-2">
        <label class="mb-0">Filter by Status:</label>
        <select id="statusFilter" class="form-select form-select-sm" style="width: 150px;">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
          <option value="defaulted">Defaulted</option>
        </select>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table id="chitAccountsTable" class="table text-nowrap">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Chit A/C No</th>
          <th>Client Name</th>
          <th>Zone</th>
          <th>Group / Scheme</th>
          <th>Chit Value</th>
          <th>Installment</th>
          <th>Progress</th>
          <th>Outstanding</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

@endsection
