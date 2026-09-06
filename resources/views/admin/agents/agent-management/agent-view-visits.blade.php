@extends('layouts/layoutMaster')

@section('title', 'Agent View - Visits')

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
  .datatables-agent-visits {
    width: 100% !important;
    min-width: 1000px;
    white-space: nowrap;
  }
</style>
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/agent-view-visits.js'])
@endsection

@section('content')

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div class="nav-align-top">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view', $agent->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ route('agent-management.view-work', $agent->id) }}"><i class="icon-base ri ri-briefcase-line me-1_5"></i>Work Information</a></li>
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-map-pin-line me-1_5"></i>Visits</a></li>
    </ul>
  </div>
  <a href="{{ route('agent-management.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-4 ms-md-auto">
    <i class="icon-base ri ri-arrow-left-line"></i>
    <span>Back to Agents</span>
  </a>
</div>

<div class="row">
  <!-- Visits Content -->
  <div class="col-12">
    <!-- Visit Statistics Cards -->
    <div class="row g-6 mb-6">
      <!-- Total Visits Card -->
      <div class="col-md-6 col-lg-6 col-xl-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">{{ $stats['total_visits'] ?? 0 }}</h4>
                <small class="text-muted">Total Visits</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-primary rounded-3">
                  <i class="icon-base ri ri-map-pin-user-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Today Visits Card -->
      <div class="col-md-6 col-lg-6 col-xl-3">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between">
              <div class="content-left">
                <h4 class="mb-1">{{ $stats['today_visits'] ?? 0 }}</h4>
                <small class="text-muted">Today Visits</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-info rounded-3">
                  <i class="icon-base ri ri-calendar-check-line icon-24px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!--/ Visit Statistics Cards -->

    <!-- Visits Table -->
    <div class="card">
      <div class="card-header border-bottom">
        <h5 class="card-title mb-0">Agent Visits</h5>
      </div>
      <div class="card-datatable table-responsive">
        <table class="datatables-agent-visits table text-nowrap" data-agent-id="{{ $agent->id }}">
          <thead>
            <tr>
              <th></th>
              <th>S.No</th>
              <th>Date</th>
              <th>Client Name</th>
              <th>Start Time</th>
              <th>End Time</th>
              <th>Duration</th>
              <th>Location</th>
              <th>Actions</th>
            </tr>
          </thead>
        </table>
      </div>
    </div>
    <!--/ Visits Table -->
  </div>
  <!--/ Visits Content -->
</div>

@endsection
