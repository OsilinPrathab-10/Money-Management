@extends('layouts/layoutMaster')

@section('title', 'Agent Attendance')

<!-- Vendor Styles -->
@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/select2/select2.scss', 'resources/assets/vendor/libs/flatpickr/flatpickr.scss', 'resources/assets/vendor/libs/animate-css/animate.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

<!-- Vendor Scripts -->
@section('vendor-script')
  @vite(['resources/assets/vendor/libs/moment/moment.js', 'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/select2/select2.js', 'resources/assets/vendor/libs/flatpickr/flatpickr.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

<!-- Page Scripts -->
@section('page-script')
  @vite(['resources/assets/custom-js/agent-attendance.js'])
@endsection

@section('content')
  <!-- Success Alert -->
  @if(session('success'))
    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="alert alert-success alert-dismissible" role="alert">
          <strong>Success!</strong> {{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    </div>
  @endif

  @if(session('error'))
    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="alert alert-danger alert-dismissible" role="alert">
          <strong>Error!</strong> {{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      </div>
    </div>
  @endif

  <!-- Stats Cards -->
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="mb-0 h6 fw-normal">Present Today</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-2">{{ $presentToday }}</h4>
              </div>
              <small class="mb-0">Agents checked in</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded-3">
                <div class="icon-base ri ri-user-check-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Absent Today</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1">{{ $absentToday }}</h4>
              </div>
              <small class="mb-0">Not checked in</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-danger rounded">
                <div class="icon-base ri ri-user-unfollow-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between">
            <div class="me-1">
              <p class="text-heading mb-1">Total Agents</p>
              <div class="d-flex align-items-center">
                <h4 class="mb-1 me-1">{{ $totalAgents }}</h4>
              </div>
              <small class="mb-0">All agents</small>
            </div>
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <div class="icon-base ri ri-team-line icon-26px"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Attendance Table -->
  <div class="card">
    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0">Attendance Records</h5>
      <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#filterModal">
        <i class="icon-base ri ri-filter-line me-1"></i>Filter
      </button>
    </div>

    <div class="card-datatable table-responsive">
      <table class="datatables-attendance table">
        <thead>
          <tr>
            <th></th>
            <th>S.No</th>
            <th>Agent Name</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Total Hours</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>

  <!-- Filter Modal -->
  <div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Attendance</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Start Date</label>
            <input type="text" class="form-control" id="startDate" placeholder="Select start date">
          </div>
          <div class="mb-3">
            <label class="form-label">End Date</label>
            <input type="text" class="form-control" id="endDate" placeholder="Select end date">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="resetFilter">Reset</button>
          <button type="button" class="btn btn-primary" id="applyFilter">Apply Filter</button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Attendance Modal -->
  <div class="modal fade" id="viewAttendanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Attendance Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="attendanceDetailsContent">
          <!-- Content loaded dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

@endsection
