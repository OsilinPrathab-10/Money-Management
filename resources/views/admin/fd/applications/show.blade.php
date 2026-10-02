@extends('layouts/layoutMaster')

@section('title', 'FD Application View')

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
@vite(['resources/assets/custom-js/fd-applications.js'])
@endsection

@php
  $statusColor = $application->status_color;
  $clientName = optional($application->client)->client_name ?? 'N/A';
  $payoutOptions = $payoutOptions ?? \App\Models\FixedDepositScheme::payoutOptions();
@endphp

@section('content')

<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1 overflow-hidden">
          <div class="avatar me-2 flex-shrink-0">
            <span class="avatar-initial rounded bg-label-primary"><i class="ri-file-list-3-line ri-20px"></i></span>
          </div>
          <h4 class="ms-1 mb-0 text-truncate w-100" title="{{ $application->application_number }}">{{ $application->application_number }}</h4>
        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Application Number</p>
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
          <h4 class="ms-1 mb-0 text-truncate text-{{ $statusColor }} w-100" title="{{ $application->status_label }}">{{ $application->status_label }}</h4>
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
          <h4 class="ms-1 mb-0 text-truncate w-100">{{ optional($application->applied_at ?? $application->created_at)->format('d-m-Y') }}</h4>
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

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
  <a href="{{ route('fd.applications.index') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Back To List
  </a>
  <div class="d-flex flex-wrap gap-2">
    @php
      $canApproveReject = auth()->user()->hasAnyRole(['Admin', 'Staff', 'Super Admin', 'admin', 'staff']);
    @endphp
    @if($canApproveReject && $application->status === 'pending')
      <button type="button" class="btn btn-success btn-approve-fd-app" data-id="{{ $application->id }}" data-name="{{ $clientName }}">
        <i class="ri-checkbox-circle-line me-1"></i> Approve Application
      </button>
      <button type="button" class="btn btn-danger btn-reject-fd-app" data-id="{{ $application->id }}" data-name="{{ $clientName }}">
        <i class="ri-close-circle-line me-1"></i> Reject Application
      </button>
    @elseif($canApproveReject && $application->status === 'approved')
      <button type="button" class="btn btn-primary btn-book-fd-app" data-id="{{ $application->id }}" data-name="{{ $clientName }}">
        <i class="ri-safe-2-line me-1"></i> Book FD
      </button>
      <button type="button" class="btn btn-danger btn-reject-fd-app" data-id="{{ $application->id }}" data-name="{{ $clientName }}">
        <i class="ri-close-circle-line me-1"></i> Reject Application
      </button>
    @endif

    @if($application->status === 'booked' && $application->fixed_deposit_id)
      <a href="{{ route('fd.deposits.show', $application->fixed_deposit_id) }}" class="btn btn-primary">
        <i class="ri-safe-2-line me-1"></i> View Fixed Deposit
      </a>
    @endif
  </div>
</div>

<div class="card shadow-sm mb-6">
  <div class="card-header border-bottom py-4 px-5">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h5 class="card-title mb-0">FD Application Overview</h5>
        <small class="text-muted">Detailed breakdown of client request and scheme terms</small>
      </div>
      <span class="badge bg-label-{{ $statusColor }} fs-6 px-3 py-2">
        <i class="ri-checkbox-circle-line me-1"></i> {{ $application->status_label }}
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
          <h6 class="mb-0">{{ optional($application->client)->client_name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Phone Number</small>
          <h6 class="mb-0">{{ optional($application->client)->client_phone ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Zone / Area</small>
          <h6 class="mb-0 text-primary fw-bold">{{ optional(optional($application->client)->location)->name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">City/State</small>
          <h6 class="mb-0">{{ optional($application->client)->city ?? 'N/A' }}, {{ optional($application->client)->state ?? 'N/A' }}</h6>
        </div>
      </div>
    </div>

    <div class="mb-6 pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-success"><i class="ri-safe-2-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-success">FD Scheme Parameters</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Selected Scheme</small>
          <h6 class="mb-0">{{ optional($application->scheme)->name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Scheme Code</small>
          <h6 class="mb-0">{{ optional($application->scheme)->scheme_code ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Interest Type</small>
          <h6 class="mb-0">{{ optional($application->scheme)->deposit_type_label ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Interest Rate</small>
          <h6 class="mb-0">{{ number_format((float) $application->interest_rate, 2) }}% p.a.</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Interest Frequency</small>
          <h6 class="mb-0">
            <span class="badge bg-label-primary">{{ optional($application->scheme)->interest_frequency_label ?? 'N/A' }}</span>
          </h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Deposit Amount</small>
          <h6 class="mb-0 text-primary">₹{{ number_format((float) $application->deposit_amount, 0) }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Tenure</small>
          <h6 class="mb-0">{{ $application->tenure }} {{ $application->tenure_type }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Interest Amount</small>
          <h6 class="mb-0">₹{{ number_format((float) $application->interest_amount, 2) }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Maturity Amount</small>
          <h6 class="mb-0 text-success">₹{{ number_format((float) $application->maturity_amount, 2) }}</h6>
        </div>
      </div>
    </div>

    <div class="mb-6 pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-info"><i class="ri-calendar-check-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-info">Dates &amp; Payout</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Deposit Date</small>
          <h6 class="mb-0">{{ optional($application->deposit_date)->format('d-m-Y') ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Start Date</small>
          <h6 class="mb-0">{{ optional($application->start_date)->format('d-m-Y') ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Maturity Date</small>
          <h6 class="mb-0">{{ optional($application->maturity_date)->format('d-m-Y') ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Payout Option</small>
          <h6 class="mb-0">{{ $payoutOptions[$application->payout_option] ?? ($application->payout_option ?: 'N/A') }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Nominee Name</small>
          <h6 class="mb-0">{{ $application->nominee_name ?: 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Nominee Relation</small>
          <h6 class="mb-0">{{ $application->nominee_relation ?: 'N/A' }}</h6>
        </div>
        <div class="col-md-6">
          <small class="text-muted text-uppercase d-block mb-1">Remarks</small>
          <h6 class="mb-0">{{ $application->remarks ?: '—' }}</h6>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
