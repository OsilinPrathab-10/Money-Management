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
  'resources/assets/custom-js/chit-applications.js',
  'resources/assets/custom-js/chit-need-month.js',
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
  $settlementTab = $settlementTab ?? request('status', 'pending');
  $settlementTabQuery = array_merge($filterQuery, ['status' => $settlementTab]);
@endphp
<div class="row mb-4">
  <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div>
      <h4 class="mb-1 text-primary"><i class="ri-folder-user-line me-2"></i>All Applications</h4>
      <p class="text-muted mb-0">View Loan, Chit, Fixed Deposit, and Settlement applications in one place.</p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      @if($canApply ?? true)
      <button type="button" class="btn btn-primary shadow-sm" id="btnOpenApplyLoanModal" data-bs-toggle="modal" data-bs-target="#modalApplyLoanGeneric">
        <i class="ri-add-line me-1"></i> Apply Loan
      </button>
      <button type="button" class="btn btn-info text-white shadow-sm" id="btnApplyChit" data-bs-toggle="modal" data-bs-target="#modalApplyChit">
        <i class="ri-add-line me-1"></i> Apply Chit
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
    <a class="nav-link {{ $module === 'chit' ? 'active' : '' }}" href="{{ route('applications.chit', $filterQuery) }}">
      <i class="ri-group-line me-1"></i> Chit
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'fd' ? 'active' : '' }}" href="{{ route('applications.fd', $filterQuery) }}">
      <i class="ri-safe-2-line me-1"></i> Fixed Deposit
    </a>
  </li>
  @if($canListSettlements ?? false)
  <li class="nav-item">
    <a class="nav-link {{ $module === 'settlement' ? 'active' : '' }}" href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'pending'])) }}">
      <i class="ri-hand-coin-line me-1"></i> Settlement
    </a>
  </li>
  @endif
</ul>

@if($module === 'settlement')
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <a href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'pending'])) }}" class="text-decoration-none">
      <div class="card h-100 cursor-pointer {{ $settlementTab === 'pending' ? 'border-warning' : '' }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-heading mb-1">Applied</p>
              <h4 class="mb-0">{{ number_format($stats['pending'] ?? 0) }}</h4>
            </div>
            <div class="avatar">
              <span class="avatar-initial rounded bg-label-warning"><i class="ri-time-line ri-24px"></i></span>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'approved'])) }}" class="text-decoration-none">
      <div class="card h-100 cursor-pointer {{ $settlementTab === 'approved' ? 'border-success' : '' }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-heading mb-1">Approved</p>
              <h4 class="mb-0">{{ number_format($stats['approved'] ?? 0) }}</h4>
            </div>
            <div class="avatar">
              <span class="avatar-initial rounded bg-label-success"><i class="ri-checkbox-circle-line ri-24px"></i></span>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <a href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'rejected'])) }}" class="text-decoration-none">
      <div class="card h-100 cursor-pointer {{ $settlementTab === 'rejected' ? 'border-danger' : '' }}">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-heading mb-1">Rejected</p>
              <h4 class="mb-0">{{ number_format($stats['rejected'] ?? 0) }}</h4>
            </div>
            <div class="avatar">
              <span class="avatar-initial rounded bg-label-danger"><i class="ri-close-circle-line ri-24px"></i></span>
            </div>
          </div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body d-flex align-items-center justify-content-center">
        <a href="{{ route('chit.settlement-applications.index') }}" class="btn btn-primary d-flex align-items-center gap-2">
          <i class="ri-hand-coin-line ri-20px"></i> Apply for Settlement
        </a>
      </div>
    </div>
  </div>
</div>
@else
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
        <p class="mb-0 small text-muted">{{ $module === 'all' ? 'Loan, Chit, FD and Settlement applications' : ($module === 'settlement' ? 'Settlement applications' : (ucfirst($module) . ' applications')) }}</p>
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
        <p class="mb-1 fw-medium text-heading">Approved / Active</p>
        <p class="mb-0 small text-muted">Approved, booked, disbursed or active</p>
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
@endif

@php
  $selectedType = request('type', $module === 'all' ? '' : $module);
  $settlementTitles = [
    'pending' => 'Applied Applications',
    'approved' => 'Approved Applications',
    'rejected' => 'Rejected Applications',
  ];
@endphp
<div class="card">
  <div class="card-header border-bottom {{ $module === 'settlement' ? 'pb-0' : '' }}">
    @if($module === 'settlement')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <h5 class="mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i>Settlement Applications</h5>
    </div>
    <form method="GET" action="{{ route('applications.settlement') }}" class="row g-3 align-items-end mb-3">
      <input type="hidden" name="status" value="{{ $settlementTab }}">
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
      <div class="col-12 col-xl-4">
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
      <div class="col-12 col-sm-6 col-xl-3 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ri-search-line me-1"></i> Filter
        </button>
        <a href="{{ route('applications.settlement', ['status' => $settlementTab]) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
    <ul class="nav nav-tabs nav-fill" role="tablist">
      <li class="nav-item" role="presentation">
        <a class="nav-link {{ $settlementTab === 'pending' ? 'active' : '' }}" href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'pending'])) }}">
          <i class="ri-time-line me-1"></i>Applied
          <span class="badge rounded-pill bg-warning ms-1 text-dark">{{ number_format($stats['pending'] ?? 0) }}</span>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link {{ $settlementTab === 'approved' ? 'active' : '' }}" href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'approved'])) }}">
          <i class="ri-checkbox-circle-line me-1"></i>Approved
          <span class="badge rounded-pill bg-success ms-1">{{ number_format($stats['approved'] ?? 0) }}</span>
        </a>
      </li>
      <li class="nav-item" role="presentation">
        <a class="nav-link {{ $settlementTab === 'rejected' ? 'active' : '' }}" href="{{ route('applications.settlement', array_merge($filterQuery, ['status' => 'rejected'])) }}">
          <i class="ri-close-circle-line me-1"></i>Rejected
          <span class="badge rounded-pill bg-danger ms-1">{{ number_format($stats['rejected'] ?? 0) }}</span>
        </a>
      </li>
    </ul>
    @else
    <h5 class="card-title mb-4">{{ $moduleTitle }}</h5>
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
      <div class="col-12 col-sm-6 col-xl-2">
        <label for="applicationType" class="form-label mb-1">Type</label>
        <select id="applicationType"
                name="type"
                class="form-select form-select-sm"
                data-all-url="{{ route('all-applications') }}"
                data-loan-url="{{ route('applications.loan') }}"
                data-chit-url="{{ route('applications.chit') }}"
                data-fd-url="{{ route('applications.fd') }}"
                @if($canListSettlements ?? false)
                data-settlement-url="{{ route('applications.settlement', ['status' => 'pending']) }}"
                @endif
                onchange="var key=this.value||'all'; this.form.action=this.dataset[key+'Url']||this.form.action; this.form.requestSubmit();">
          <option value="" @selected($selectedType === '' || $selectedType === 'all')>All Types</option>
          <option value="loan" @selected($selectedType === 'loan')>Loan</option>
          <option value="chit" @selected($selectedType === 'chit')>Chit</option>
          <option value="fd" @selected($selectedType === 'fd')>Fixed Deposit</option>
          @if($canListSettlements ?? false)
          <option value="settlement" @selected($selectedType === 'settlement')>Settlement</option>
          @endif
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
    @endif
  </div>
  @if($module === 'settlement')
  <div class="card-body border-bottom py-3">
    <h5 class="mb-0 fw-semibold text-primary">
      <i class="icon-base ri ri-file-list-3-line me-2"></i>{{ $settlementTitles[$settlementTab] ?? 'Settlement Applications' }}
    </h5>
  </div>
  @endif
  <div class="card-datatable table-responsive">
    <table class="datatables-applications table table-hover text-nowrap" id="applicationsTable">
      <thead>
        @if($module === 'settlement')
        <tr>
          <th>#</th>
          <th>Payout Code</th>
          <th>Source</th>
          <th>Client</th>
          <th>Phone</th>
          <th>Group</th>
          <th>Scheme</th>
          <th>Chit Value</th>
          <th>Settlement Amount</th>
          <th>Settlement Month</th>
          <th>Status</th>
          <th>Applied On</th>
          <th>Actions</th>
        </tr>
        @else
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
        @endif
      </thead>
      <tbody>
        @forelse(($rows ?? []) as $row)
          @php
            $typeKey = strtolower((string) ($row['module'] ?? 'loan'));
            $typeClass = $typeKey === 'chit' ? 'info' : ($typeKey === 'fd' ? 'warning' : ($typeKey === 'settlement' ? 'success' : 'primary'));
            $source = $row['source'] ?? 'admin';
            $sourceIcon = $source === 'customer' ? 'ri-smartphone-line' : ($source === 'agent' ? 'ri-user-star-line' : 'ri-computer-line');
          @endphp
          @if($module === 'settlement')
          <tr>
            <td class="text-center">{{ $row['member_number'] ?? $row['sno'] }}</td>
            <td><code>{{ $row['payout_code'] ?? $row['application_number'] }}</code></td>
            <td>
              <span class="badge bg-label-{{ $row['source_badge'] ?? 'primary' }} text-nowrap">
                <i class="{{ $sourceIcon }} me-1"></i>{{ $row['source_label'] ?? 'Admin' }}
              </span>
              @if(!empty($row['applicant_login']))
                <div class="small text-muted mt-1">{{ $row['applicant_login'] }}</div>
              @endif
            </td>
            <td>
              {{ $row['client_name'] ?? 'N/A' }}
              @if(!empty($row['client_phone']) && $row['client_phone'] !== 'N/A')
                <div class="small text-muted">{{ $row['client_phone'] }}</div>
              @endif
            </td>
            <td>{{ $row['client_phone'] ?? '—' }}</td>
            <td>{{ $row['group_code'] ?? '—' }}</td>
            <td>{{ $row['scheme_name'] ?? '—' }}</td>
            <td class="text-end">{{ $row['chit_value_formatted'] ?? '₹0.00' }}</td>
            <td class="text-end"><span class="text-success fw-semibold">{{ $row['amount_formatted'] ?? '₹0.00' }}</span></td>
            <td>
              @if(!empty($row['month_label']))
                <span class="badge bg-label-primary">{{ $row['month_label'] }}</span>
                @if(!empty($row['payout_kind_label']))
                  <span class="badge bg-label-{{ $row['payout_kind_badge'] ?? 'secondary' }} ms-1">{{ $row['payout_kind_label'] }}</span>
                @endif
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td><span class="badge bg-{{ $row['status_color'] ?? 'secondary' }}">{{ $row['status_label'] ?? 'N/A' }}</span></td>
            <td>{{ $row['applied_at'] ?? 'N/A' }}</td>
            <td class="text-nowrap">
              <a href="{{ $row['view_url'] }}" class="btn btn-icon btn-sm btn-label-primary rounded-circle me-1" title="View Review">
                <i class="ri-eye-line"></i>
              </a>
              @if(!empty($row['can_action']))
                @if(!empty($row['approve_url']))
                  <a href="{{ $row['approve_url'] }}" class="btn btn-icon btn-sm btn-success rounded-circle me-1 text-white" title="Approve / Confirm Release" style="background-color:#28a745;border-color:#28a745;">
                    <i class="ri-checkbox-circle-line text-white"></i>
                  </a>
                @endif
                @if(!empty($row['cancel_url']))
                  <form method="POST" action="{{ $row['cancel_url'] }}" class="d-inline form-reject-settlement" onsubmit="if(this.dataset.confirmed==='1') return true; return confirm('Reject this settlement application?');">
                    @csrf
                    <button type="submit" class="btn btn-icon btn-sm btn-label-danger rounded-circle" title="Reject Application">
                      <i class="ri-close-circle-line"></i>
                    </button>
                  </form>
                @endif
              @endif
            </td>
          </tr>
          @else
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
          @endif
        @empty
          <tr class="applications-empty-row">
            <td colspan="{{ $module === 'settlement' ? 13 : 10 }}" class="text-center py-5 text-muted">No {{ $module === 'settlement' ? strtolower($settlementTitles[$settlementTab] ?? 'settlement applications') : 'applications' }} found for this period.</td>
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

{{-- Apply Chit Modal --}}
@include('admin.clients.modals.modal-apply-chit', [
    'verifiedClients' => $verifiedClients ?? collect(),
    'availableGroups' => $availableGroups ?? collect(),
    'agents' => $agents ?? collect(),
])

{{-- Apply FD Modal --}}
@include('admin.clients.modals.modal-apply-fd-generic', [
    'verifiedClients' => $verifiedClients ?? collect(),
    'fdSchemes' => $fdSchemes ?? collect(),
    'payoutOptions' => $payoutOptions ?? [],
])

@endsection

