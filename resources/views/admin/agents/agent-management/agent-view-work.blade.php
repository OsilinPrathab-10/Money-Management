@extends('layouts/layoutMaster')

@section('title', 'Agent View - Work Information')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
'resources/assets/vendor/libs/select2/select2.scss',
'resources/assets/vendor/libs/@form-validation/form-validation.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
'resources/assets/vendor/libs/select2/select2.js',
'resources/assets/vendor/libs/@form-validation/popular.js',
'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch;
  }
  .datatables-assigned-clients {
    width: 100% !important;
    min-width: 1000px;
    white-space: nowrap;
  }
</style>
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/agent-work-info.js'])
@endsection

@section('content')

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div class="nav-align-top">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view', $agent->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-briefcase-line me-1_5"></i>Work Information</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view-visits', $agent->id) }}"><i class="icon-base ri ri-map-pin-line me-1_5"></i>Visits</a></li>
    </ul>
  </div>
  <a href="{{ route('agent-management.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-4 ms-md-auto">
    <i class="icon-base ri ri-arrow-left-line"></i>
    <span>Back to Agents</span>
  </a>
</div>

<div class="row">
  <!-- Work Information Content -->
  <div class="col-12">
    <!-- Work Statistics Cards -->
    <div class="row g-6 mb-6">
      <!-- Assigned Cases Card -->
      <div class="col-md-6 col-lg-4 col-xl-2">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">{{ $workStats['assigned_cases'] ?? 0 }}</h4>
                <small class="text-muted">Assigned Cases</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-primary rounded-3">
                  <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Unresolved Cases Card -->
      <div class="col-md-6 col-lg-4 col-xl-2">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">{{ $workStats['unresolved_cases'] ?? 0 }}</h4>
                <small class="text-muted">Unresolved Cases</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-danger rounded-3">
                  <i class="icon-base ri ri-error-warning-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>


      <!-- Collections Card -->
      <div class="col-md-6 col-lg-4 col-xl-2">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">₹{{ number_format($workStats['total_collections'] ?? 0, 2) }}</h4>
                <small class="text-muted">Collections</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-success rounded-3">
                  <i class="icon-base ri ri-money-rupee-circle-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Followups Card -->
      <div class="col-md-6 col-lg-4 col-xl-2">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">{{ $workStats['followups'] ?? 0 }}</h4>
                <small class="text-muted">Followups</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-warning rounded-3">
                  <i class="icon-base ri ri-phone-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
    <!--/ Work Statistics Cards -->

    <!-- Assigned Clients Table -->
    <div class="card">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Assigned Clients</h5>
      </div>
      <div class="card-datatable table-responsive">
        <table class="datatables-assigned-clients table text-nowrap" data-agent-id="{{ $agent->id }}">
          <thead>
            <tr>
              <th></th>
              <th>S.No</th>
              <th>EMI ID</th>
              <th>Client Name</th>
              <th>Mobile</th>
              <th>Loan Amount</th>
              <th>Outstanding</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
    <!--/ Assigned Clients Table -->
  </div>
  <!--/ Work Information Content -->
</div>

<!-- Client Information Modal -->
<div class="modal fade" id="clientInfoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Client Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="clientInfoContent">
        <!-- Content loaded dynamically -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@endsection

