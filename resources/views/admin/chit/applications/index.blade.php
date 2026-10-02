@extends('layouts/layoutMaster')

@section('title', 'Chit Applications')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/chit-applications.js', 'resources/assets/custom-js/chit-need-month.js'])
@endsection

@section('content')
<script>
  window.isAdmin = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Staff'));
</script>

{{-- Summary cards — same pattern as Loan Applications --}}
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-2">
    <div class="card cursor-pointer" id="card-total-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Total</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1" id="countTotal">{{ $counts['total'] ?? 0 }}</h4>
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
              <h4 class="mb-1 me-1" id="countApplied">{{ $counts['applied'] ?? 0 }}</h4>
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
  <div class="col-sm-6 col-xl-2">
    <div class="card cursor-pointer" id="card-approved-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Approved</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1" id="countApproved">{{ $counts['approved'] ?? 0 }}</h4>
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
    <div class="card cursor-pointer" id="card-active-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Active</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1" id="countActive">{{ $counts['active'] ?? 0 }}</h4>
            </div>
          </div>
          <div class="avatar">
            <div class="avatar-initial bg-label-success rounded-3">
              <div class="icon-base ri ri-hand-coin-line icon-26px"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer" id="card-rejected-applications">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div class="me-1 overflow-hidden">
            <p class="text-heading mb-1 text-truncate">Rejected</p>
            <div class="d-flex align-items-center">
              <h4 class="mb-1 me-1" id="countRejected">{{ $counts['rejected'] ?? 0 }}</h4>
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

{{-- List table — same header pattern as Loan Applications --}}
<div class="card">
  <div class="card-header border-bottom pb-3">
    <div class="row g-3 align-items-center">
      <div class="col-12 col-md-4">
        <h5 class="card-title mb-0">Chit Applications</h5>
      </div>
      <div class="col-12 col-md-8">
        <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-3">
          <button type="button" class="btn btn-primary shadow-sm" id="btnApplyChit">
            <i class="icon-base ri ri-add-line me-1"></i> Apply for Chit
          </button>
          @include('partials.date-range-filter', [
            'fromId' => 'fromDate',
            'toId' => 'toDate',
            'presetId' => 'chitAppDatePreset',
          ])
          <div class="d-flex align-items-center gap-2">
            <label for="groupFilter" class="form-label mb-0 text-nowrap small fw-medium">Group:</label>
            <select id="groupFilter" class="form-select form-select-sm" style="min-width: 140px;">
              <option value="">All Groups</option>
              @foreach(($filterGroups ?? $availableGroups) as $g)
                <option value="{{ $g->id }}">{{ $g->group_code }}</option>
              @endforeach
            </select>
          </div>
          <div class="d-flex align-items-center gap-2">
            <label for="statusFilter" class="form-label mb-0 text-nowrap small fw-medium">Status:</label>
            <select id="statusFilter" class="form-select form-select-sm" style="min-width: 120px;">
              <option value="">All Statuses</option>
              <option value="applied">Pending</option>
              <option value="approved">Approved</option>
              <option value="active">Active</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive text-nowrap">
    <table class="datatables-chit-applications table border-top">
      <thead>
        <tr>
          <th class="text-start">S.No</th>
          <th>Member #</th>
          <th>Client Name</th>
          <th>Phone Number</th>
          <th>Group</th>
          <th>Scheme Name</th>
          <th class="text-end">Chit Value</th>
          <th class="text-center">Frequency</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

{{-- Apply for Chit Modal --}}
@include('admin.clients.modals.modal-apply-chit')

{{-- View Chit Application Popup --}}
<div class="modal fade" id="modalViewChitApplication" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <div>
          <h5 class="modal-title mb-0">Chit Application</h5>
          <small class="text-muted" id="viewAppSubtitle">Application details</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
          <span class="badge rounded-pill bg-label-secondary fs-6 px-3 py-2" id="viewAppStatus">—</span>
          <span class="text-muted small">Applied: <strong id="viewAppAppliedAt">—</strong></span>
        </div>

        <div class="mb-4">
          <h6 class="text-primary fw-semibold mb-3"><i class="ri-user-line me-1"></i> Client</h6>
          <div class="row g-3">
            <div class="col-md-6"><small class="text-muted d-block">Name</small><strong id="viewAppClientName">—</strong></div>
            <div class="col-md-6"><small class="text-muted d-block">Phone</small><strong id="viewAppClientPhone">—</strong></div>
            <div class="col-md-6"><small class="text-muted d-block">Email</small><strong id="viewAppClientEmail">—</strong></div>
            <div class="col-md-6"><small class="text-muted d-block">Assigned Agent</small><strong id="viewAppAgent">—</strong></div>
          </div>
        </div>

        <hr>

        <div class="mb-4">
          <h6 class="text-success fw-semibold mb-3"><i class="ri-group-line me-1"></i> Group &amp; Scheme</h6>
          <div class="row g-3">
            <div class="col-md-4"><small class="text-muted d-block">Group</small><strong id="viewAppGroup">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Scheme</small><strong id="viewAppScheme">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Member #</small><strong id="viewAppMemberNo">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Chit Value</small><strong id="viewAppChitValue">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Installment</small><strong id="viewAppInstallment">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Collection Frequency</small><strong id="viewAppFrequency">—</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">Duration</small><strong id="viewAppDuration">—</strong></div>
            <div class="col-md-6"><small class="text-muted d-block">Need Month</small><strong id="viewAppNeedMonth">—</strong></div>
            <div class="col-md-6"><small class="text-muted d-block">Group Status</small><strong id="viewAppGroupStatus">—</strong></div>
          </div>
        </div>

        <div id="viewAppRemarksWrap" class="d-none">
          <hr>
          <h6 class="text-muted fw-semibold mb-2">Remarks</h6>
          <p class="mb-0 bg-light rounded p-3" id="viewAppRemarks"></p>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-danger d-none" id="viewAppBtnReject">
          <i class="ri-close-circle-line me-1"></i> Reject
        </button>
        <button type="button" class="btn btn-success d-none" id="viewAppBtnApprove">
          <i class="ri-checkbox-circle-line me-1"></i> Approve
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
