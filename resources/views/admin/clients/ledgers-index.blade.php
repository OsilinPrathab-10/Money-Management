@extends('layouts/layoutMaster')

@section('title', 'Client Ledgers')

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
  .card-datatable.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
  }

  .datatables-ledgers {
    width: max-content;
    min-width: 100%;
    white-space: nowrap;
  }
  
  .card-border-shadow-primary {
    border-left: 4px solid var(--bs-primary) !important;
  }
  .card-border-shadow-success {
    border-left: 4px solid var(--bs-success) !important;
  }
  .card-border-shadow-warning {
    border-left: 4px solid var(--bs-warning) !important;
  }
  .card-border-shadow-info {
    border-left: 4px solid var(--bs-info) !important;
  }
</style>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const dt_ledger_table = document.querySelector('.datatables-ledgers');
    const ledgerShowBase = "{{ url('admin/client-ledger') }}/";

    if (dt_ledger_table) {
      const dt_ledger = new DataTable(dt_ledger_table, {
        processing: true,
        serverSide: true,
        scrollX: true,
        scrollCollapse: true,
        autoWidth: false,
        ajax: {
          url: "{{ route('admin-client-ledgers.data') }}",
          data: function (d) {
            d.location_id = $('#FilterLocation').val();
          },
          dataSrc: function (json) {
            json.recordsTotal = typeof json.recordsTotal === 'number' ? json.recordsTotal : 0;
            json.recordsFiltered = typeof json.recordsFiltered === 'number' ? json.recordsFiltered : 0;
            json.data = Array.isArray(json.data) ? json.data : [];
            return json.data;
          }
        },
        columns: [
          { data: 'id' }, // Control column for responsive
          { 
            render: function (data, type, full, meta) {
              return meta.row + meta.settings._iDisplayStart + 1;
            }
          },
          { data: 'customer_id' },
          { data: 'name' },
          { data: 'email' },
          { data: 'mobile' },
          { data: 'zone' },
          { data: 'active_loans' },
          { data: 'active_chits' },
          { data: 'total_outstanding' },
          { data: 'action' }
        ],
        columnDefs: [
          {
            className: 'control',
            searchable: false,
            orderable: false,
            targets: 0,
            render: function () { return ''; }
          },
          {
            searchable: false,
            orderable: true,
            targets: 1 // S.No
          },
          {
            targets: 2, // Customer ID
            render: function (data) {
              return `<span class="fw-semibold text-heading">${data || ''}</span>`;
            }
          },
          {
            targets: 3, // Name
            render: function (data, type, full) {
              return `<a href="${ledgerShowBase}${full.id}" class="text-heading fw-bold text-primary">${data}</a>`;
            }
          },
          {
            targets: 4, // Email
            render: function (data) {
              return `<span>${data}</span>`;
            }
          },
          {
            targets: 5, // Mobile
            render: function (data) {
              return `<span>${data}</span>`;
            }
          },
          {
            targets: 6, // Area
            render: function (data) {
              return `<span class="badge bg-label-secondary">${data}</span>`;
            }
          },
          {
            targets: 7, // Active Loans
            className: 'text-center',
            render: function (data) {
              const badgeClass = data > 0 ? 'bg-label-primary' : 'bg-label-secondary';
              return `<span class="badge ${badgeClass} rounded-pill fw-bold">${data}</span>`;
            }
          },
          {
            targets: 8, // Active Chits
            className: 'text-center',
            render: function (data) {
              const badgeClass = data > 0 ? 'bg-label-info' : 'bg-label-secondary';
              return `<span class="badge ${badgeClass} rounded-pill fw-bold">${data}</span>`;
            }
          },
          {
            targets: 9, // Outstanding
            className: 'text-end',
            render: function (data) {
              const numericVal = parseFloat(data.replace(/,/g, ''));
              const colorClass = numericVal > 0 ? 'text-danger fw-bold' : 'text-success';
              return `<span class="${colorClass}">₹${data}</span>`;
            }
          },
          {
            targets: -1, // Action
            searchable: false,
            orderable: false,
            render: function (data, type, full) {
              return `
                <a href="${ledgerShowBase}${full.id}" class="btn btn-sm btn-primary py-1 px-2 shadow-sm d-inline-flex align-items-center">
                  <i class="ri-book-open-line me-1"></i> View Ledger
                </a>
              `;
            }
          }
        ],
        order: [[2, 'asc']],
        displayLength: 10,
        layout: {
          topStart: {
            rowClass: 'row m-3 my-0 justify-content-between',
            features: [
              {
                pageLength: {
                  menu: [10, 25, 50, 100],
                  text: '_MENU_'
                }
              }
            ]
          },
          topEnd: {
            features: [
              {
                search: {
                  placeholder: 'Search Client...',
                  text: '_INPUT_'
                }
              }
            ]
          },
          bottomStart: 'info',
          bottomEnd: 'paging'
        },
        language: {
          paginate: {
            next: '<i class="ri-arrow-right-s-line icon-22px"></i>',
            previous: '<i class="ri-arrow-left-s-line icon-22px"></i>'
          }
        }
      });

      // Filter trigger
      $('#FilterLocation').on('change', function () {
        dt_ledger.draw();
      });
    }
  });
</script>
@endsection

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="mb-0 fw-bold">Client Ledgers Management</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Client Ledgers</li>
      </ol>
    </nav>
  </div>

  <!-- Summary Statistics Cards -->
  <div class="row g-4 mb-5">
    <div class="col-sm-6 col-lg-3">
      <div class="card card-border-shadow-primary h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-primary p-2">
                <i class="ri-group-line ri-24px"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">{{ $totalClients }}</h4>
              <p class="mb-0 small text-muted">Total Clients</p>
            </div>
          </div>
          <div class="pt-2 border-top">
            <span class="small text-muted">All registered accounts</span>
          </div>
        </div>
      </div>
    </div>
    
    <div class="col-sm-6 col-lg-3">
      <div class="card card-border-shadow-success h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-success p-2">
                <i class="ri-wallet-3-line ri-24px"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">₹{{ number_format($totalOutstandingLoans, 2) }}</h4>
              <p class="mb-0 small text-muted">Total Outstanding</p>
            </div>
          </div>
          <div class="pt-2 border-top">
            <span class="small text-muted">Active loan balances</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card card-border-shadow-warning h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-warning p-2">
                <i class="ri-bank-card-line ri-24px"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">{{ $activeLoanAccountsCount }}</h4>
              <p class="mb-0 small text-muted">Active Loans</p>
            </div>
          </div>
          <div class="pt-2 border-top">
            <span class="small text-muted">Currently active loan products</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card card-border-shadow-info h-100 shadow-sm border-0">
        <div class="card-body">
          <div class="d-flex align-items-center mb-3">
            <div class="avatar me-3">
              <span class="avatar-initial rounded bg-label-info p-2">
                <i class="ri-hand-coin-line ri-24px"></i>
              </span>
            </div>
            <div>
              <h4 class="mb-0 fw-bold text-heading">{{ $activeChitMembersCount }}</h4>
              <p class="mb-0 small text-muted">Chit Subscribers</p>
            </div>
          </div>
          <div class="pt-2 border-top">
            <span class="small text-muted">Active chit group enrollments</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Clients Ledgers Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">Client Ledgers Directory</h5>
        <p class="text-muted mb-0 small">Consolidated ledger reports of client loans, EMIs, and chit accounts</p>
      </div>
    </div>
    
    <div class="card-body border-top py-3">
      <div class="row g-4">
        <div class="col-md-4">
          <label class="form-label small fw-bold">Filter by Area / Zone</label>
          <select id="FilterLocation" class="form-select select2 text-capitalize">
            <option value="">All Areas</option>
            @foreach($locations as $location)
              <option value="{{ $location->id }}">{{ $location->name }} ({{ $location->city }}, {{ $location->state }})</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
    
    <div class="card-datatable table-responsive">
      <table class="datatables-ledgers table table-hover">
        <thead class="table-light">
          <tr>
            <th></th>
            <th>S.No</th>
            <th>Client ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Mobile</th>
            <th>Area / Zone</th>
            <th>Active Loans</th>
            <th>Active Chits</th>
            <th>Outstanding Loans</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
@endsection
