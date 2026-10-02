@extends('layouts/layoutMaster')

@section('title', 'FD Applications')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
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
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/fd-applications.js'])
@endsection

@section('content')
<script>
  window.isAdmin = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Staff'));
</script>

<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-2">
    <div class="card cursor-pointer" id="card-total-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Total</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1">{{ $stats['total'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-primary rounded-3">
              <div class="icon-base ri ri-file-list-3-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card cursor-pointer" id="card-pending-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Pending</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1">{{ $stats['pending'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-warning rounded">
              <div class="icon-base ri ri-time-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer" id="card-approved-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Approved</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1">{{ $stats['approved'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-info rounded">
              <div class="icon-base ri ri-checkbox-circle-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer" id="card-booked-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Booked</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1">{{ $stats['booked'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-success rounded-3">
              <div class="icon-base ri ri-safe-2-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-2">
    <div class="card cursor-pointer" id="card-rejected-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Rejected</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1">{{ $stats['rejected'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-danger rounded-3">
              <div class="icon-base ri ri-close-circle-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom pb-3">
    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-4">
        <h5 class="card-title mb-0">FD Applications</h5>
      </div>
      <div class="col-12 col-md-8">
        <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-3">
          <button type="button" class="btn btn-primary shadow-sm" id="btnOpenApplyFdModal">
            <i class="icon-base ri ri-add-line me-1"></i> Apply for FD
          </button>
          @include('partials.date-range-filter', [
            'fromId' => 'fromDate',
            'toId' => 'toDate',
            'presetId' => 'fdAppDatePreset',
          ])
          <div class="d-flex align-items-center gap-2">
            <label for="statusFilter" class="form-label mb-0 text-nowrap small fw-medium">Status:</label>
            <select id="statusFilter" class="form-select form-select-sm" style="min-width: 120px;">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="booked">Booked</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive text-nowrap">
    <table class="datatables-fd-applications table border-top">
      <thead>
        <tr>
          <th class="text-start">S.No</th>
          <th>Application Number</th>
          <th>Client Name</th>
          <th>Phone Number</th>
          <th>Zone</th>
          <th>FD Scheme</th>
          <th class="text-end">Deposit Amount</th>
          <th class="text-center">Status</th>
          <th class="text-center">Applied On</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

@include('admin.clients.modals.modal-apply-fd-generic', [
    'verifiedClients' => $verifiedClients,
    'fdSchemes' => $fdSchemes,
    'payoutOptions' => $payoutOptions,
])
@endsection
