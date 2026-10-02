@extends('layouts/layoutMaster')

@section('title', 'Chit Application View')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
<script>
  window.isAdmin = @json(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin') || auth()->user()->hasRole('Staff'));
</script>
@vite(['resources/assets/custom-js/chit-applications.js'])
@endsection

@php
$statusColors = [
    'applied' => 'warning',
    'approved' => 'info',
    'active' => 'success',
    'rejected' => 'danger',
    'completed' => 'primary',
];
$statusLabels = [
    'applied' => 'Pending',
    'approved' => 'Approved',
    'active' => 'Active',
    'rejected' => 'Rejected',
    'completed' => 'Completed',
];
$statusColor = $statusColors[$member->status] ?? 'secondary';
$statusLabel = $statusLabels[$member->status] ?? ucfirst($member->status);
$clientName = $member->client->client_name ?? 'N/A';
@endphp

@section('content')

{{-- Statistics header — same pattern as Loan Application View --}}
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-primary"><i class="ri-hashtag ri-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100" title="#{{ $member->member_number }}">#{{ $member->member_number }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Member Number</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-{{ $statusColor }} h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-{{ $statusColor }}"><i class="ri-checkbox-circle-line ri-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate text-{{ $statusColor }} w-100" title="{{ $statusLabel }}">{{ $statusLabel }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Current Status</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-info"><i class="ri-calendar-line ri-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100">{{ optional($member->created_at)->format('d-m-Y') }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Applied On</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-success"><i class="ri-user-line ri-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100" title="{{ $clientName }}">{{ $clientName }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Client Name</p>
      </div>
    </div>
  </div>
</div>

{{-- Back + actions --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <a href="{{ route('chit.applications.index') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Back To List
  </a>
  <div class="d-flex flex-wrap gap-2">
    @php
      $canApproveReject = auth()->user()->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    @endphp
    @if($canApproveReject && $member->status === 'applied')
      <button type="button" class="btn btn-success btn-approve-app" data-id="{{ $member->id }}" data-name="{{ $clientName }}">
        <i class="ri-checkbox-circle-line me-1"></i> Approve Application
      </button>
      <button type="button" class="btn btn-danger btn-reject-app" data-id="{{ $member->id }}" data-name="{{ $clientName }}">
        <i class="ri-close-circle-line me-1"></i> Reject Application
      </button>
    @elseif($canApproveReject && $member->status === 'approved')
      <button type="button" class="btn btn-danger btn-reject-app" data-id="{{ $member->id }}" data-name="{{ $clientName }}">
        <i class="ri-close-circle-line me-1"></i> Reject Application
      </button>
    @endif

    @if(in_array($member->status, ['active', 'completed'], true))
      <a href="{{ route('chit.accounts.show', $member) }}" class="btn btn-primary">
        <i class="ri-bank-line me-1"></i> View Chit Account
      </a>
    @endif
  </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible mb-4" role="alert">
  {{ session('success') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible mb-4" role="alert">
  {{ session('error') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- Main overview card — same structure as Loan Application View --}}
<div class="card shadow-sm mb-6">
  <div class="card-header border-bottom py-4 px-5">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0">Chit Application Overview</h5>
        <small class="text-muted">Detailed breakdown of client application and group enrollment</small>
      </div>
      <span class="badge bg-label-{{ $statusColor }} fs-6 px-3 py-2">
        <i class="ri-checkbox-circle-line me-1"></i> {{ $statusLabel }}
      </span>
    </div>
  </div>
  <div class="card-body p-5">

    <div class="mb-6">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-primary"><i class="ri-user-settings-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-primary">Client Personal Info</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Full Name</small>
          <h6 class="mb-0">{{ $member->client->client_name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Phone Number</small>
          <h6 class="mb-0">{{ $member->client->client_phone ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Alternate Phone</small>
          <h6 class="mb-0">{{ $member->client->alternate_phone ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Email</small>
          <h6 class="mb-0">{{ $member->client->client_email ?? 'N/A' }}</h6>
        </div>
      </div>
    </div>

    <div class="mb-6 pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-success"><i class="ri-group-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-success">Group &amp; Scheme Details</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Group Code</small>
          <h6 class="mb-0"><span class="badge bg-label-secondary">{{ $member->group->group_code ?? 'N/A' }}</span></h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Scheme Name</small>
          <h6 class="mb-0">{{ $member->group->scheme->name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Member Slot</small>
          <h6 class="mb-0">#{{ $member->member_number }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Group Status</small>
          <h6 class="mb-0"><span class="badge bg-label-info">{{ ucfirst($member->group->status ?? 'N/A') }}</span></h6>
        </div>
      </div>
    </div>

    <div class="mb-6 pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-warning"><i class="ri-money-rupee-circle-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-warning">Financial Details</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Chit Value</small>
          <h6 class="mb-0 text-primary">₹{{ number_format((float) ($member->group->chit_value ?? 0), 0) }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Monthly Installment</small>
          <h6 class="mb-0">₹{{ number_format((float) ($member->group->installment_amount ?? 0), 0) }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Collection Frequency</small>
          <h6 class="mb-0">
            <span class="badge bg-label-{{ ($member->collection_frequency ?? 'monthly') === 'daily' ? 'warning' : (($member->collection_frequency ?? 'monthly') === 'weekly' ? 'info' : 'secondary') }}">
              {{ $member->collection_frequency_label ?? 'Monthly' }}
            </span>
          </h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Duration</small>
          <h6 class="mb-0">{{ $member->group->total_months ?? 'N/A' }} Months</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Chit Need Month</small>
          <h6 class="mb-0">{{ $member->chit_need_month_label ?: 'Not set' }}</h6>
        </div>
      </div>
    </div>

    <div class="pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-info"><i class="ri-information-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-info">Referral &amp; Processing History</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Assigned Agent</small>
          <h6 class="mb-0">{{ $member->assigned_agent_name }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Joined Date</small>
          <h6 class="mb-0">{{ optional($member->joined_date)->format('d M Y') ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Approved At</small>
          <h6 class="mb-0">{{ $member->approved_at ? $member->approved_at->format('d M Y, h:i A') : 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Approved By</small>
          <h6 class="mb-0">{{ $member->approvedBy->name ?? 'N/A' }}</h6>
        </div>
        @if($member->remarks)
        <div class="col-12">
          <small class="text-muted text-uppercase d-block mb-1">Remarks</small>
          <p class="mb-0 bg-light p-3 rounded-3">{{ $member->remarks }}</p>
        </div>
        @endif
      </div>
    </div>

  </div>
</div>

@endsection
