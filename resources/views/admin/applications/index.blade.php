@extends('layouts/layoutMaster')

@section('title', 'All Applications')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
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
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
<script>
  window.isAdmin = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Staff'));
</script>
@vite([
  'resources/assets/custom-js/all-applications.js',
  'resources/assets/custom-js/loan-applications.js',
  'resources/assets/custom-js/fd-applications.js'
])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
  }
  .datatables-applications {
    width: 100% !important;
    margin: 0 !important;
  }
  .cursor-pointer { cursor: pointer; }
</style>
@endsection

@section('content')
@php
  $filterQuery = request()->except(['page', 'module', 'type', 'status']);
@endphp
<div class="row mb-4">
  <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h4 class="mb-1 text-primary"><i class="ri-folder-user-line me-2"></i>All Applications</h4>
      <p class="text-muted mb-0">View Loan and Fixed Deposit applications in one place.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      @if($canApply ?? true)
      <button type="button" class="btn btn-primary shadow-sm" id="btnOpenApplyLoanModal" data-bs-toggle="modal" data-bs-target="#modalApplyLoanGeneric">
        <i class="ri-add-line me-1"></i> Apply Loan
      </button>
      <button type="button" class="btn btn-warning shadow-sm" id="btnOpenApplyFdModal" data-bs-toggle="modal" data-bs-target="#modalApplyFdGeneric">
        <i class="ri-add-line me-1"></i> Apply FD
      </button>
      @endif
    </div>
  </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" role="tablist">
  <li class="nav-item">
    <a class="nav-link {{ $module === 'all' ? 'active' : '' }}" href="{{ route('all-applications', $filterQuery) }}">
      <i class="ri-list-check-2 me-1"></i> All
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'loan' ? 'active' : '' }}" href="{{ route('applications.loan', $filterQuery) }}">
      <i class="ri-bank-line me-1"></i> Loan
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'fd' ? 'active' : '' }}" href="{{ route('applications.fd', $filterQuery) }}">
      <i class="ri-safe-2-line me-1"></i> Fixed Deposit
    </a>
  </li>
</ul>

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
          <h4 class="ms-1 mb-0">{{ number_format($stats['total'] ?? 0) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Total Applications</p>
        <p class="mb-0 small text-muted">{{ $module === 'all' ? 'Loan and Fixed Deposit applications' : (ucfirst($module) . ' applications') }}</p>
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
          <h4 class="ms-1 mb-0">{{ number_format($stats['pending'] ?? 0) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Pending</p>
        <p class="mb-0 small text-muted">Awaiting approval</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-success">
              <i class="ri-checkbox-circle-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['approved'] ?? 0) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Approved</p>
        <p class="mb-0 small text-muted">Successfully sanctioned</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-danger h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-danger">
              <i class="ri-close-circle-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['rejected'] ?? 0) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Rejected</p>
        <p class="mb-0 small text-muted">Declined applications</p>
      </div>
    </div>
  </div>
</div>

@php
  $selectedType = request('type', $module === 'all' ? '' : $module);
@endphp
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-4">{{ $moduleTitle }}</h5>
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
      <div class="col-12 col-sm-6 col-xl-2">
        <label for="applicationType" class="form-label mb-1">Type</label>
        <select id="applicationType"
                name="type"
                class="form-select form-select-sm"
                data-all-url="{{ route('all-applications') }}"
                data-loan-url="{{ route('applications.loan') }}"
                data-fd-url="{{ route('applications.fd') }}"
                onchange="var key=this.value||'all'; this.form.action=this.dataset[key+'Url']||this.form.action; this.form.requestSubmit();">
          <option value="" @selected($selectedType === '' || $selectedType === 'all')>All Types</option>
          <option value="loan" @selected($selectedType === 'loan')>Loan</option>
          <option value="fd" @selected($selectedType === 'fd')>Fixed Deposit</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <label for="clientName" class="form-label mb-1">Client Name</label>
        <input type="text"
               id="clientName"
               name="client_name"
               class="form-control form-control-sm"
               value="{{ request('client_name') }}"
               placeholder="Search client name">
      </div>
      <div class="col-12 col-sm-6 col-xl-2">
        <label for="applicationZone" class="form-label mb-1">Zone</label>
        <select id="applicationZone" name="location_id" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
          <option value="">All Zones</option>
          @foreach(($locations ?? []) as $location)
            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-xl-3">
        @include('partials.date-range-filter', [
          'fromId' => 'fromDate',
          'toId' => 'toDate',
          'presetId' => 'datePreset',
          'fromName' => 'from_date',
          'toName' => 'to_date',
          'presetName' => 'date_preset',
          'fromValue' => request('from_date'),
          'toValue' => request('to_date'),
          'presetValue' => request('date_preset', 'all'),
          'autoSubmit' => true,
        ])
      </div>
      <div class="col-12 col-sm-6 col-xl-2 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ri-search-line me-1"></i> Filter
        </button>
        <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-applications table table-hover text-nowrap" id="applicationsTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Type</th>
          <th>Application No.</th>
          <th>Client Name</th>
          <th>Zone</th>
          <th>{{ $productHeading }}</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Applied On</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($rows ?? []) as $row)
          @php
            $typeKey = strtolower((string) ($row['module'] ?? 'loan'));
            $typeClass = $typeKey === 'fd' ? 'warning' : 'primary';
            $source = $row['source'] ?? 'admin';
            $sourceIcon = $source === 'customer' ? 'ri-smartphone-line' : ($source === 'agent' ? 'ri-user-star-line' : 'ri-computer-line');
          @endphp
          <tr>
            <td>{{ $row['sno'] }}</td>
            <td><span class="badge bg-label-{{ $typeClass }}">{{ $row['module_label'] ?? ucfirst($typeKey) }}</span></td>
            <td>{{ $row['application_number'] }}</td>
            <td>
              {{ $row['client_name'] ?? 'N/A' }}
              @if(!empty($row['client_phone']) && $row['client_phone'] !== 'N/A')
                <div class="small text-muted">{{ $row['client_phone'] }}</div>
              @endif
            </td>
            <td><span class="badge bg-label-secondary">{{ $row['zone'] ?? 'N/A' }}</span></td>
            <td>{{ $row['product'] ?? 'N/A' }}</td>
            <td>{{ $row['amount_formatted'] ?? ('₹' . number_format((float) ($row['amount'] ?? 0), 2)) }}</td>
            <td><span class="badge bg-label-{{ $row['status_color'] ?? 'secondary' }}">{{ $row['status_label'] ?? 'N/A' }}</span></td>
            <td>{{ $row['applied_at'] ?? 'N/A' }}</td>
            <td>
              <a href="{{ $row['view_url'] }}" class="btn btn-icon btn-text-secondary btn-sm rounded-pill" title="View Application">
                <i class="icon-base ri ri-eye-line icon-22px"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr class="applications-empty-row">
            <td colspan="10" class="text-center py-5 text-muted">No applications found for this period.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if(method_exists($rows, 'hasPages'))
    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
          Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} applications
        </span>
        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
          @foreach(request()->except(['page', 'per_page']) as $name => $value)
            @if(is_array($value))
              @continue
            @endif
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
          @endforeach
          <label class="small text-muted mb-0" for="perPage">Per page</label>
          <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.requestSubmit()">
            @foreach([10, 25, 50, 100] as $size)
              <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
            @endforeach
          </select>
        </form>
      </div>
      <div>
        {{ $rows->onEachSide(1)->links() }}
      </div>
    </div>
  @endif
</div>

{{-- Apply Loan Modal --}}
@include('admin.clients.modals.modal-apply-loan-generic', [
    'verifiedClients' => $verifiedClients ?? collect(),
    'loanProducts' => $loanProducts ?? collect(),
])

{{-- Apply FD Modal --}}
@include('admin.clients.modals.modal-apply-fd-generic', [
    'verifiedClients' => $verifiedClients ?? collect(),
    'fdSchemes' => $fdSchemes ?? collect(),
    'payoutOptions' => $payoutOptions ?? [],
])

@endsection
