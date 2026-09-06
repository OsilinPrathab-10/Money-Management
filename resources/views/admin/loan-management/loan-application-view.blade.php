@extends('layouts/layoutMaster')

@section('title', 'Loan Application View')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-style')
<style>
  /* Modernized Video Container */
  .video-container {
    position: relative;
    overflow: hidden;
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
    border: 2px dashed #dbdade;
    background: #f8f7fa;
    border-radius: 0.75rem;
    transition: all 0.2s ease-in-out;
  }

  .video-container video, .video-container canvas {
    display: block;
    width: 100%;
    height: 240px;
    object-fit: cover;
    border-radius: 0.5rem;
  }

  .video-container:hover {
    border-color: var(--bs-primary);
    background: #f1f0f2;
  }

  .webcam-placeholder-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 240px;
  }

  /* Status Timeline dots for summary */
  .status-dot { height: 12px; width: 12px; border-radius: 50%; display: inline-block; }
</style>
@endsection

@php
$statusColors = [
    'pending' => 'warning',
    'approved' => 'success',
    'process' => 'primary',
    'in_progress' => 'primary',
    'rejected' => 'danger',
    'disbursed' => 'success',
];
$statusColor = $statusColors[$application->status] ?? 'secondary';

$loanAmountValue = $application->loan_amount ?? 0;

// Get applied charges if disbursed, otherwise use product defaults for preview
$appliedProcessingFee = 0;
$appliedDocumentCharges = 0;
$appliedOtherCharges = 0;

if ($application->applicationDetail) {
    $details = $application->applicationDetail->details ?? [];
    if (is_string($details)) {
        $details = json_decode($details, true) ?? [];
    }
    
    $appliedProcessingFee = (float)($details['applied_processing_fee'] ?? 0);
    $appliedDocumentCharges = (float)($details['applied_document_charges'] ?? 0);
    $appliedOtherCharges = (float)($details['applied_other_charges'] ?? 0);
}

// Fallback to product defaults if no charges are recorded in details (usually for non-disbursed preview)
if ($application->status !== 'disbursed' && $appliedProcessingFee == 0 && $appliedDocumentCharges == 0 && $appliedOtherCharges == 0) {
    $appliedProcessingFee = (float)(optional($application->product)->processing_fee ?? 0);
    $appliedDocumentCharges = (float)(optional($application->product)->document_charges ?? 0);
    $appliedOtherCharges = (float)(optional($application->product)->other_charges ?? 0);
}

$totalCharges = $appliedProcessingFee + $appliedDocumentCharges + $appliedOtherCharges;
$netDisbursedAmount = optional($application->loanAccount)->disbursed_amount ?? max($loanAmountValue - $totalCharges, 0);

$interestRate = (float)($application->interest_rate ?? (optional($application->product)->interest_rate ?? 0));
$isKandhuvatti = ($application->loan_mode ?? 'emi') === 'interest_only';
$totalInterest = $loanAmountValue * ($interestRate / 100);
$totalPayable = $loanAmountValue + $totalInterest;

$displayUnit = $application->term_unit ?: optional($application->product)->term_unit ?: 'months';
$displayUnit = in_array(strtolower($displayUnit), ['days', 'day', 'daily']) ? 'Days' : (in_array(strtolower($displayUnit), ['weeks', 'week', 'weekly']) ? 'Weeks' : 'Months');

$displayUnitVal = strtolower($application->term_unit ?: (optional($application->product)->term_unit ?? 'months'));
$defaultOffset = '+1 month';
if (in_array($displayUnitVal, ['weeks', 'week', 'weekly'])) {
    $defaultOffset = '+1 week';
} elseif (in_array($displayUnitVal, ['days', 'day', 'daily'])) {
    $defaultOffset = '+1 day';
}

$defaultStart = $application->emi_start_year ? 
    \Carbon\Carbon::create($application->emi_start_year, $application->emi_start_month, $application->emi_start_day)->format('d-m-Y') : 
    date('d-m-Y', strtotime($defaultOffset));

$frequency = 'monthly';
if (in_array($displayUnitVal, ['week', 'weeks', 'weekly'], true)) {
    $frequency = 'weekly';
} elseif (in_array($displayUnitVal, ['day', 'days', 'daily'], true)) {
    $frequency = 'daily';
}
@endphp

@section('content')

<!-- Statistics Header Row -->
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
          <h4 class="ms-1 mb-0 text-truncate text-{{ $statusColor }} w-100" title="{{ in_array($application->status, ['process','in_progress']) ? 'IN PROGRESS' : ucfirst($application->status) }}">{{ in_array($application->status, ['process','in_progress']) ? 'IN PROGRESS' : ucfirst($application->status) }}</h4>
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
          <h4 class="ms-1 mb-0 text-truncate w-100">{{ $application->created_at->format('d-m-Y') }}</h4>
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
          <h4 class="ms-1 mb-0 text-truncate w-100" title="{{ optional($application->client)->client_name ?? 'N/A' }}">{{ optional($application->client)->client_name ?? 'N/A' }}</h4>

        </div>
        <p class="mb-0 text-muted small text-uppercase fw-medium">Borrower Name</p>
      </div>
    </div>
  </div>
</div>

<!-- Back Navigation -->
<div class="mb-4">
  <a href="{{ url('loan/loan-applications') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> Back To List
  </a>
</div>

<!-- Main Details Card -->
@if(!auth()->user()->hasRole('Agent') || $application->status == 'disbursed')
<div class="card shadow-sm mb-6">
  <div class="card-header border-bottom py-4 px-5">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0">Loan Application Overview</h5>
            <small class="text-muted">Detailed breakdown of client and product request</small>
        </div>
        <span class="badge bg-label-{{ $statusColor }} fs-6 px-3 py-2">
            <i class="ri-checkbox-circle-line me-1"></i> {{ in_array($application->status, ['process','in_progress']) ? 'IN PROGRESS' : ucfirst($application->status) }}
        </span>
    </div>
  </div>
  <div class="card-body p-5">
    
    <!-- Client Section -->
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
        <!-- <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">CIBIL Score</small>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-info" data-bs-toggle="tooltip" title="CIBIL integration is coming soon in future updates">Coming Soon</span>
          </div>
        </div> -->
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

    <!-- Product Section -->
    <div class="mb-6 pt-5 border-top">
      <div class="d-flex align-items-center mb-4">
        <div class="avatar avatar-sm me-3">
          <span class="avatar-initial rounded-3 bg-label-success"><i class="ri-bank-line"></i></span>
        </div>
        <h5 class="mb-0 fw-bold text-success">Loan Product Parameters</h5>
      </div>
      <div class="row g-4 ms-1">
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Selected Product</small>
          <h6 class="mb-0">{{ optional($application->product)->loan_name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Loan Type</small>
          <h6 class="mb-0">{{ optional(optional($application->product)->loanType)->name ?? 'N/A' }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Calculation Mode</small>
          <span class="badge bg-label-{{ ($application->loan_mode ?? 'emi') === 'interest_only' ? 'danger' : 'primary' }} px-3">
            {{ ($application->loan_mode ?? 'emi') === 'interest_only' ? 'Kandhuvatti (Interest-Only)' : 'Standard EMI' }}
          </span>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Interest Rate</small>
          <h6 class="mb-0">{{ optional($application->product)->interest_rate ?? 0 }}% p.a.</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Approved Amount</small>
          <h6 class="mb-0 text-primary">₹{{ number_format($application->loan_amount ?? 0, 0) }}</h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">Tenure</small>
          <h6 class="mb-0">
            @if(($application->loan_mode ?? 'emi') === 'interest_only')
              <span class="text-secondary">Flexible (Interest-Only)</span>
            @else
              {{ $application->tenure ?? ($application->tenure_max ?? 'N/A') }} {{ $displayUnit }}
            @endif
          </h6>
        </div>
        <div class="col-md-3 col-6">
          <small class="text-muted text-uppercase d-block mb-1">
            @php
              $termUnit = strtolower($application->term_unit ?: optional($application->product)->term_unit ?: 'months');
              $isWeekly = in_array($termUnit, ['weeks', 'week', 'weekly']);
              $isDaily = in_array($termUnit, ['days', 'day', 'daily']);
              $isKandhuvatti = ($application->loan_mode ?? 'emi') === 'interest_only';
            @endphp
            @if($isDaily)
              {{ $isKandhuvatti ? 'Daily Interest Cycle' : 'Collection Type' }}
            @elseif($isWeekly)
              {{ $isKandhuvatti ? 'Weekly Interest Day' : 'Weekly Collection Day' }}
            @else
              {{ $isKandhuvatti ? 'Monthly Interest Day' : 'Monthly EMI Day' }}
            @endif
          </small>
          <h6 class="mb-0">
            @if($isDaily)
              Everyday Collection
            @elseif($isWeekly)
              @php
                $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
              @endphp
              {{ $days[$application->emi_day] ?? (optional($application->client)->collection_day ?? 'Not Set') }}
            @else
              {{ $application->emi_day ? 'Day ' . $application->emi_day : 'Not Set' }}
            @endif
          </h6>
        </div>
      </div>
    </div>

    <!-- Application Details (JSON Parsing) -->
    @if($application->applicationDetail)
      <div class="mb-0 pt-5 border-top">
        <div class="d-flex align-items-center mb-4">
          <div class="avatar avatar-sm me-3">
            <span class="avatar-initial rounded-3 bg-label-info"><i class="ri-information-line"></i></span>
          </div>
          <h5 class="mb-0 fw-bold text-info">Dynamic Application Details</h5>
        </div>
        <div class="row g-4 ms-1">
          @php
            $detailsRaw = $application->applicationDetail->details ?? [];
            if (is_string($detailsRaw)) {
                $details = json_decode($detailsRaw, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $fixedJson = str_replace('\n', '', $detailsRaw);
                    $details = json_decode($fixedJson, true);
                }
            } else {
                $details = $detailsRaw;
            }
            $details = is_array($details) ? $details : [];
          @endphp
          @foreach($details as $key => $value)
            <div class="col-md-3 col-6">
              <small class="text-muted text-uppercase d-block mb-1">{{ ucfirst(str_replace('_', ' ', $key)) }}</small>
              <h6 class="mb-0">{{ is_array($value) ? json_encode($value) : ($value ?? 'N/A') }}</h6>
            </div>
          @endforeach

          @if($application->applicationDetail->vehicle_image)
            <div class="col-md-3 col-6">
              <small class="text-muted text-uppercase d-block mb-1">Verification Asset</small>
              <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#vehicleImageModal">
                <i class="ri-image-line me-1"></i> View Photo
              </button>
            </div>
          @endif
        </div>
      </div>
    @endif
  </div>
</div>
@endif


<!-- CASE: APPROVED/PENDING STATUS (Dual Webcam Module) -->
@if(auth()->user()->hasRole('Admin') && ($application->status == 'approved' || $application->status == 'pending'))
<div class="card shadow-sm mb-6 border-success">
  <div class="card-header bg-label-success py-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0 fw-bold"><i class="ri-shield-user-line me-2"></i>Step 1: Processing, Safety Verification & Final Terms</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject Application</button>
        <span class="badge bg-label-success">Mandatory Step</span>
    </div>
  </div>
  <div class="card-body p-4">
    <form id="adminProceedForm">
      <div class="row g-5">
        <!-- Loan Terms Confirmation -->
        <div class="col-lg-5">
            <h6 class="text-uppercase fw-bold text-muted mb-4 border-bottom pb-2">1. Finalize Disbursal Terms</h6>
            <div class="bg-light p-4 rounded-3 border">
                <div class="mb-4">
                    <label class="form-label fw-bold">Approved Loan Amount <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">₹</span>
                        <input type="number" class="form-control bg-light" name="approved_amount" value="{{ $application->loan_amount }}" readonly required min="1" max="{{ optional($application->product)->loan_amount_max ?? 9999999 }}">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">Interest Rate (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" name="interest_rate" class="form-control form-control-lg bg-light" value="{{ $application->interest_rate ?? (optional($application->product)->interest_rate ?? 0) }}" readonly required>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Tenure ({{ $displayUnit }}) <span class="text-danger">*</span></label>
                        <input type="number" name="tenure" class="form-control form-control-lg bg-light" value="{{ $application->tenure ?? ($application->tenure_max ?? (optional($application->product)->min_tenture ?? 12)) }}" readonly required>
                    </div>
                    <div class="col-6">
                        @php
                          $termUnit = strtolower($application->term_unit ?: optional($application->product)->term_unit ?: 'months');
                          $isWeekly = in_array($termUnit, ['weeks', 'week', 'weekly']);
                          $isDaily = in_array($termUnit, ['days', 'day', 'daily']);
                        @endphp
                        <label class="form-label fw-semibold">
                          @if($isDaily)
                            Collection Type
                          @elseif($isWeekly)
                            Collection Day
                          @else
                            Monthly EMI Day
                          @endif
                          <span class="text-danger">*</span>
                        </label>
                        @if($isDaily)
                          <input type="text" class="form-control form-control-lg bg-light" value="Everyday" readonly disabled>
                          <input type="hidden" name="emi_day" value="1">
                        @elseif($isWeekly)
                          @php
                            $dayMap = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
                            $selectedDay = $application->emi_day ?? ($dayMap[optional($application->client)->collection_day] ?? 1);
                          @endphp
                          <select class="form-select form-select-lg bg-light" disabled>
                            @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $val => $name)
                              <option value="{{ $val }}" {{ $selectedDay == $val ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                          </select>
                          <input type="hidden" name="emi_day" value="{{ $selectedDay }}">
                        @else
                          <input type="number" name="emi_day" class="form-control form-control-lg bg-light" value="{{ $application->emi_day ?? 1 }}" readonly min="1" max="31" required placeholder="e.g. 5">
                        @endif
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold">EMI Start Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-label-primary border-primary text-primary"><i class="ri-calendar-event-line"></i></span>
                        <input type="text" name="emi_start_date" id="emi_start_date_step2" class="form-control flatpickr-date" value="{{ $defaultStart }}" required placeholder="DD-MM-YYYY" data-frequency="{{ $frequency }}">
                    </div>
                    <div id="emi_weekday_display_step2" class="mt-2 small fw-medium text-primary">
                        Weekday: {{ \Carbon\Carbon::parse($defaultStart)->format('l') }}
                    </div>
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                    <select class="form-select form-select-lg bg-light" >
                        <option value="" >Select Payment Method</option>
                        <option value="manual" selected>Manual (Cash/Offline)</option>
                    </select>
                    <input type="hidden" name="payment_method" value="manual">
                </div>
                
                <!-- Financial Payout Preview -->
                <div class="mt-4 pt-4 border-top">
                    <h6 class="text-uppercase fw-bold text-muted mb-3 small"><i class="ri-calculator-line me-1"></i> Payout Preview</h6>
                    @if($isKandhuvatti)
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted small">Interest / Cycle ({{ $application->interest_rate }}%):</span>
                          <span class="fw-bold text-warning small">₹{{ number_format(($application->loan_amount * $application->interest_rate / 100), 2) }}</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted small">Loan Structure:</span>
                          <span class="fw-bold text-info small">Open Loan (Flexible)</span>
                      </div>
                    @else
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted small">Total Interest:</span>
                          <span class="fw-bold text-warning small">₹{{ number_format($totalInterest, 2) }}</span>
                      </div>
                      <div class="d-flex justify-content-between mb-2">
                          <span class="text-muted small">Total Payable:</span>
                          <span class="fw-bold text-info small">₹{{ number_format($totalPayable, 2) }}</span>
                      </div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mt-3 bg-label-success p-2 rounded">
                        <span class="fw-bold text-success">NET PAYOUT:</span>
                        <span class="fw-bold text-success fs-5">₹{{ number_format($netDisbursedAmount, 2) }}</span>
                    </div>
                    <small class="text-muted d-block mt-2" style="font-size: 10px;">* Includes deductions for processing fees and charges.</small>
                </div>
            </div>
        </div>

        <!-- Verification Cameras Section -->
        <div class="col-lg-7">
            <h6 class="text-uppercase fw-bold text-muted mb-4 border-bottom pb-2">2. Visual Proof (Required)</h6>
            <div class="row g-4">
                <!-- Identity Verification -->
                <div class="col-md-6">
                    <div class="card border h-100 shadow-none">
                        <div class="card-header py-2 text-center bg-label-primary fw-bold small">IDENTITY VERIFICATION</div>
                        <div class="card-body p-3">
                            <div id="live-webcam-container" class="video-container mb-3">
                                <video id="live-webcam-preview" autoplay playsinline style="display: none;"></video>
                                <canvas id="live-captured-photo" style="display: none;"></canvas>
                                <div id="live-webcam-placeholder" class="webcam-placeholder-content">
                                    <div class="avatar avatar-xl bg-label-secondary rounded mb-2">
                                        <i class="ri-user-received-2-line ri-32px"></i>
                                    </div>
                                    <small class="text-muted fw-medium">Live Identity Photo</small>
                                </div>
                            </div>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="start-live-webcam">Start Camera</button>
                                <button type="button" class="btn btn-sm btn-primary" id="capture-live-btn" style="display:none">Capture</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="retake-live-btn" style="display:none">Retake</button>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="upload-live-btn"><i class="ri-upload-2-line"></i></button>
                            </div>
                            <input type="file" id="manual-live-input" accept="image/*" style="display: none;">
                            <input type="hidden" name="live_photo" id="live_photo_input">
                        </div>
                    </div>
                </div>
                <!-- Cash Disbursal Verification -->
                <div class="col-md-6">
                    <div class="card border h-100 shadow-none">
                        <div class="card-header py-2 text-center bg-label-success fw-bold small">CASH DISBURSAL PROOF</div>
                        <div class="card-body p-3">
                            <div id="cash-webcam-container" class="video-container mb-3">
                                <video id="cash-webcam-preview" autoplay playsinline style="display: none;"></video>
                                <canvas id="cash-captured-photo" style="display: none;"></canvas>
                                <div id="cash-webcam-placeholder" class="webcam-placeholder-content">
                                    <div class="avatar avatar-xl bg-label-secondary rounded mb-2">
                                        <i class="ri-hand-coin-line ri-32px"></i>
                                    </div>
                                    <small class="text-muted fw-medium">Handover Verification</small>
                                </div>
                            </div>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="start-cash-webcam">Start Camera</button>
                                <button type="button" class="btn btn-sm btn-primary" id="capture-cash-btn" style="display:none">Capture</button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="retake-cash-btn" style="display:none">Retake</button>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" id="upload-cash-btn"><i class="ri-upload-2-line"></i></button>
                            </div>
                            <input type="file" id="manual-cash-input" accept="image/*" style="display: none;">
                            <input type="hidden" name="cash_photo" id="cash_photo_input">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 p-4 bg-label-warning rounded-3 border border-warning border-opacity-25">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" required>
                    <label class="form-check-label fw-bold text-dark" for="terms_accepted">
                        I confirm that the client is physically present, has agreed to all T&C, and visual proofs are accurate.
                    </label>
                </div>
            </div>
        </div>
      </div>
      <div class="mt-5 text-end border-top pt-4">
          <button type="submit" class="btn btn-primary btn-lg px-5 shadow" id="proceed-btn" disabled>
            <i class="ri-shield-check-line me-2"></i> Confirm & Proceed to Disbursal
          </button>
      </div>
    </form>
  </div>
</div>
@endif

<!-- CASE: PROCESS STATUS (Simplified Manual Disbursal) -->
@if(auth()->user()->hasRole('Admin') && in_array($application->status, ['process', 'in_progress']))
<!-- Disbursement Actions (Final Step) -->
<div class="card mb-6 border-success shadow-none">
  <div class="card-header border-bottom py-3 d-flex align-items-center">
      <i class="ri-hand-coin-line me-2 fs-5 text-success"></i>
      <h5 class="mb-0 fw-bold">Loan Disbursement Confirmation</h5>
      <div class="ms-auto d-flex align-items-center gap-2 flex-wrap justify-content-end">
        <div class="text-end me-3 mb-2 mb-sm-0 pe-3 border-end">
          <small class="text-muted text-uppercase d-block small">PRINCIPAL AMOUNT</small>
          <h4 class="mb-0 fw-bold text-dark">₹{{ number_format($loanAmountValue, 2) }}</h4>
        </div>
        <div class="text-end me-3 mb-2 mb-sm-0 pe-3 border-end">
          <small class="text-muted text-uppercase d-block small">{{ $isKandhuvatti ? 'Interest / Cycle' : 'TOTAL INTEREST' }}</small>
          <h4 class="mb-0 text-warning fw-bold">₹{{ number_format($totalInterest, 2) }}</h4>
        </div>
        <div class="text-end me-3 mb-2 mb-sm-0 pe-3 border-end">
          <small class="text-muted text-uppercase d-block small">{{ $isKandhuvatti ? 'Loan Structure' : 'TOTAL PAYABLE' }}</small>
          <h4 class="mb-0 text-info fw-bold">{{ $isKandhuvatti ? 'Open Loan' : '₹' . number_format($totalPayable, 2) }}</h4>
        </div>
        <div class="text-end me-3 mb-2 mb-sm-0">
          <small class="text-muted text-uppercase d-block small">NET PAYOUT (HANDOVER)</small>
          <h4 class="mb-0 text-success fw-bold">₹{{ number_format($netDisbursedAmount, 2) }}</h4>
        </div>
        <div class="d-flex gap-2 mb-2 mb-sm-0">
            <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#disbursementBreakdownModal">
              <i class="ri-calculator-line me-1"></i> Breakdown
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#transactionDetailsModal">
              <i class="ri-list-settings-line me-1"></i> Transactions
            </button>
        </div>
      </div>
  </div>
  <div class="card-body p-4">
      <div class="row mb-5">
          <div class="col-lg-6">
              <h6 class="text-uppercase fw-bold text-muted mb-3">Financial Overview</h6>
              <div class="table-responsive border rounded-3 bg-light bg-opacity-50">
                  <table class="table table-sm table-borderless mb-0">
                      <tbody>
                          <tr>
                              <td class="ps-3 py-2">Principal Loan Amount</td>
                              <td class="pe-3 py-2 text-end fw-bold text-dark">₹{{ number_format($loanAmountValue, 2) }}</td>
                          </tr>
                          <tr>
                              <td class="ps-3 py-2 text-muted">{{ $isKandhuvatti ? 'Interest per Cycle (' . $interestRate . '%)' : 'Total Interest Cost' }}</td>
                              <td class="pe-3 py-2 text-end fw-bold text-warning">₹{{ number_format($totalInterest, 2) }}</td>
                          </tr>
                          <tr class="border-top border-info border-opacity-25">
                              <td class="ps-3 py-2 fw-bold text-info">{{ $isKandhuvatti ? 'Loan Structure' : 'Total Payable Amount' }}</td>
                              <td class="pe-3 py-2 text-end fw-bold text-info">{{ $isKandhuvatti ? 'Open Loan' : '₹' . number_format($totalPayable, 2) }}</td>
                          </tr>
                          <tr>
                              <td colspan="2" class="bg-white py-1"></td>
                          </tr>
                          <tr>
                              <td class="ps-3 py-2 text-danger small">Processing Fee (-)</td>
                              <td class="pe-3 py-2 text-end text-danger small" id="summary_processing_fee">₹{{ number_format($appliedProcessingFee, 2) }}</td>
                          </tr>
                          <tr>
                              <td class="ps-3 py-2 text-danger small">Document Charges (-)</td>
                              <td class="pe-3 py-2 text-end text-danger small" id="summary_document_charges">₹{{ number_format($appliedDocumentCharges, 2) }}</td>
                          </tr>
                          <tr>
                              <td class="ps-3 py-2 text-danger small">Other Deductions (-)</td>
                              <td class="pe-3 py-2 text-end text-danger small" id="summary_other_charges">₹{{ number_format($appliedOtherCharges, 2) }}</td>
                          </tr>
                          <tr class="border-top border-success bg-label-success">
                              <td class="ps-3 py-3 fw-bold text-success fs-6">NET PAYOUT (HANDOVER)</td>
                              <td class="pe-3 py-3 text-end fw-bold text-success fs-5" id="summary_net_payout">₹{{ number_format($netDisbursedAmount, 2) }}</td>
                          </tr>
                      </tbody>
                  </table>
              </div>
          </div>
          <div class="col-lg-6 mt-4 mt-lg-0">
              <h6 class="text-uppercase fw-bold text-muted mb-3">Disbursement Schedule</h6>
              <div class="p-3 border rounded-3 bg-white h-100">
                  <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                      <span class="text-muted">{{ $isKandhuvatti ? 'First Interest Date' : 'First EMI Date' }}:</span>
                      <span class="fw-bold text-primary">{{ $application->emi_start_year ? Carbon\Carbon::create($application->emi_start_year, $application->emi_start_month, $application->emi_start_day)->format('d-m-Y') : 'Not Set' }}</span>
                  </div>
                  <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                      <span class="text-muted">Repayment Cycle:</span>
                      <span class="fw-bold">{{ $isKandhuvatti ? 'Open-Ended Interest' : ucfirst($application->tenure_type) }}</span>
                  </div>
                  <div class="d-flex justify-content-between mb-0">
                      <span class="text-muted">Collection Day:</span>
                      @php
                        $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
                        $emiDayVal = (int)($application->emi_day ?? 1);
                      @endphp
                      <span class="fw-bold">{{ $application->tenure_type === 'weekly' ? ($dayNames[$emiDayVal] ?? 'N/A') : 'Day ' . $emiDayVal }}</span>
                  </div>
              </div>
          </div>
      </div>

      <div class="alert alert-soft-info d-flex align-items-center mb-5" role="alert">
          <i class="ri-information-line me-2 fs-5 text-info"></i>
          <div class="small">
            Verify the bank or cash details below before final disbursement. Once disbursed, the loan account will be officially activated and interest will begin to accrue.
          </div>
      </div>
    
    <!-- Bank Details (Shown only for e-nach/electronic) -->
    <div id="bankAccountDetailsContainer" style="{{ $application->payment_method == 'manual' ? 'display: none;' : '' }}">
        <div class="row g-4 mb-4">
          <div class="col-md-6">
            <label for="bankName" class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
            <input type="text" id="bankName" class="form-control form-control-lg" placeholder="Enter bank name" value="{{ optional($application->client)->bank_name }}">
          </div>
          <div class="col-md-6">
            <label for="accountNumber" class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
            <input type="text" id="accountNumber" class="form-control form-control-lg" placeholder="Enter account number" value="{{ optional($application->client)->account_number }}">
          </div>
        </div>
       
        <div class="row g-4 mb-4">
          <div class="col-md-4">
            <label for="holderName" class="form-label fw-semibold">Account Holder Name <span class="text-danger">*</span></label>
            <input type="text" id="holderName" class="form-control form-control-lg" placeholder="Enter holder name" value="{{ optional($application->client)->client_name }}">
          </div>
          <div class="col-md-4">
            <label for="accountType" class="form-label fw-semibold">Account Type <span class="text-danger">*</span></label>
            <select id="accountType" class="form-select form-select-lg">
              <option value="" disabled {{ !optional($application->client)->account_type ? 'selected' : '' }}>Select Type</option>
              <option value="savings" {{ (optional($application->client)->account_type == 'savings') ? 'selected' : '' }}>Savings</option>
              <option value="current" {{ (optional($application->client)->account_type == 'current') ? 'selected' : '' }}>Current</option>
              <option value="overdraft" {{ (optional($application->client)->account_type == 'overdraft') ? 'selected' : '' }}>Overdraft</option>
            </select>
          </div>
          <div class="col-md-4">
            <label for="ifscCode" class="form-label fw-semibold">IFSC Code <span class="text-danger">*</span></label>
            <input type="text" id="ifscCode" class="form-control form-control-lg" placeholder="Enter IFSC code" maxlength="11" value="{{ optional($application->client)->ifsc_code }}">
          </div>
        </div>
    </div>

    <!-- Disbursement Date Selection -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <label for="disbursedAt" class="form-label fw-bold">Disbursement Date <span class="text-danger">*</span></label>
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-label-success border-success text-success"><i class="ri-calendar-event-line"></i></span>
                <input type="text" id="disbursedAt" class="form-control border-success flatpickr-date" value="{{ date('d-m-Y') }}" placeholder="DD-MM-YYYY" required data-frequency="{{ $frequency }}">
            </div>
            <small class="text-muted">Specifies when funds are released.</small>
        </div>
        <div class="col-md-6">
            <label for="emiStartDate" class="form-label fw-bold">EMI Start Date <span class="text-danger">*</span></label>
            <div class="input-group input-group-lg">
                <span class="input-group-text bg-label-primary border-primary text-primary"><i class="ri-calendar-check-line"></i></span>
                <input type="text" id="emiStartDate" class="form-control border-primary flatpickr-date" value="{{ $defaultStart }}" placeholder="DD-MM-YYYY" required data-frequency="{{ $frequency }}">
            </div>
            <div id="emi_weekday_display_step3" class="mt-2 small fw-medium text-primary">
                Weekday: {{ \Carbon\Carbon::parse($defaultStart)->format('l') }}
            </div>
            <small class="text-muted">First EMI repayment date.</small>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-md-12" id="transactionDetailsContainer" style="{{ $application->payment_method == 'manual' ? 'display: none;' : '' }}">
            <label class="form-label fw-bold">Processing Reference / Transaction ID <span class="text-danger">*</span></label>
            <input type="text" id="disbursementReference" class="form-control form-control-lg" placeholder="Enter Transaction Reference ID">
        </div>
    </div>

    <!-- Electronic Transaction Details (Shown only for e-nach/electronic) -->
    <div id="utrDetailsContainer" style="{{ $application->payment_method == 'manual' ? 'display: none;' : '' }}">
        <div class="row g-4 mb-4">
            <div class="col-md-12">
                <label for="utrNumber" class="form-label fw-semibold">UTR Number <span class="text-danger">*</span></label>
                <input type="text" id="utrNumber" class="form-control form-control-lg" placeholder="Enter UTR number">
            </div>
        </div>
    </div>

    <!-- Charges Adjustment (Enter Manually) -->
    <div class="d-flex align-items-center justify-content-between mb-3 mt-4">
        <h6 class="fw-bold mb-0 text-uppercase small text-muted">Disbursement Charges (Adjust Manually if needed)</h6>
        <button type="button" class="btn btn-sm btn-link text-primary p-0" id="resetChargesBtn">
            <i class="ri-refresh-line me-1"></i> Reset to Defaults
        </button>
    </div>
    <div class="row g-4 mb-5 p-4 bg-label-secondary rounded-3 border">
        <div class="col-md-4">
            <label class="form-label small fw-bold">Processing Fee (₹)</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-primary border-end-0 text-primary">₹</span>
                <input type="number" id="input_processing_fee" class="form-control charge-input border-primary border-start-0 ps-0" value="{{ number_format($appliedProcessingFee, 2, '.', '') }}" step="0.01" data-default="{{ $appliedProcessingFee }}" placeholder="0.00">

            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">Doc Charges (₹)</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-primary border-end-0 text-primary">₹</span>
                <input type="number" id="input_document_charges" class="form-control charge-input border-primary border-start-0 ps-0" value="{{ number_format($appliedDocumentCharges, 2, '.', '') }}" step="0.01" data-default="{{ $appliedDocumentCharges }}" placeholder="0.00">
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-bold">Other Charges (₹)</label>
            <div class="input-group">
                <span class="input-group-text bg-white border-primary border-end-0 text-primary">₹</span>
                <input type="number" id="input_other_charges" class="form-control charge-input border-primary border-start-0 ps-0" value="{{ number_format($appliedOtherCharges, 2, '.', '') }}" step="0.01" data-default="{{ $appliedOtherCharges }}" placeholder="0.00">
            </div>
        </div>
    </div>
   
    <div class="d-flex gap-3 border-top pt-4">
      <button type="button" class="btn btn-success btn-lg px-5 shadow" id="disburseBtn">
        <i class="ri-check-double-line me-2"></i> Confirm & Disburse Loan
      </button>
      <button type="button" class="btn btn-outline-danger btn-lg px-4" data-bs-toggle="modal" data-bs-target="#rejectModal">
        <i class="ri-close-circle-line me-2"></i> Reject Instead
      </button>
    </div>
  </div>
</div>
@endif

<!-- CASE: DISBURSED STATUS (Summary & Proof Photos) -->
@if($application->status == 'disbursed')
<div class="card shadow-sm border-success mb-6">
    <div class="card-header bg-label-success py-3"><h5 class="mb-0 fw-bold">Disbursement Completed Successfully</h5></div>
    <div class="card-body p-5">
        <div class="row g-4 text-center border-bottom pb-5 mb-5">
            <div class="col-md-2 col-6">
                <small class="text-muted d-block mb-1 text-uppercase small">Approved Amount</small>
                <h5 class="mb-0 fw-semibold">₹{{ number_format($loanAmountValue, 0) }}</h5>
            </div>
            @php
                $interestRate = $application->interest_rate ?? 0;
                $totalInterest = $loanAmountValue * ($interestRate / 100);
                $totalPayable = $loanAmountValue + $totalInterest;
            @endphp
            <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1 text-uppercase small">Total Payable</small>
                <h4 class="mb-0 fw-bold text-info">₹{{ number_format($totalPayable, 2) }}</h4>
            </div>
            <div class="col-md-2 col-6">
                <small class="text-muted d-block mb-1 text-uppercase small">Total Charges</small>
                <h5 class="mb-0 fw-semibold text-danger">₹{{ number_format($totalCharges, 0) }}</h5>
            </div>
            <div class="col-md-3 col-6">
                <small class="text-muted d-block mb-1 text-uppercase small text-success">Net Disbursed</small>
                <h4 class="text-success fw-bold mb-0">₹{{ number_format($netDisbursedAmount, 0) }}</h4>
            </div>
            <div class="col-md-2 col-6">
                <small class="text-muted d-block mb-1 text-uppercase small">Date Completed</small>
                <h5 class="mb-0 fw-semibold">{{ $application->disbursed_at ? $application->disbursed_at->format('d-m-Y') : 'N/A' }}</h5>
            </div>
        </div>

        <h6 class="fw-bold mb-4">Verification Media Logged</h6>
        <div class="row g-4">
            <div class="col-md-6 text-center">
                <small class="text-muted d-block mb-2">Live Identity Photo</small>
                @if($application->live_photo)
                    <img src="{{ asset('storage/' . $application->live_photo) }}" class="img-fluid rounded-3 border shadow-sm" style="max-height: 250px;">
                @else
                    <div class="p-5 bg-light rounded-3 text-muted"><i class="ri-image-line ri-3x"></i></div>
                @endif
            </div>
            <div class="col-md-6 text-center">
                <small class="text-muted d-block mb-2">Cash Handover Verification</small>
                @if($application->cash_photo)
                    <img src="{{ asset('storage/' . $application->cash_photo) }}" class="img-fluid rounded-3 border shadow-sm" style="max-height: 250px;">
                @else
                    <div class="p-5 bg-light rounded-3 text-muted"><i class="ri-money-rupee-circle-line ri-3x"></i></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Shared Modals (Rejection, Vehicle View, Breakdown) -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom py-3">
        <h5 class="modal-title fw-bold">Confirm Application Rejection</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="avatar avatar-xl bg-label-danger mx-auto mb-4">
            <i class="ri-close-circle-line ri-48px"></i>
        </div>
        <p class="mb-4">Are you sure you want to reject this application? This action will notify the client.</p>
        <div class="mb-3 text-start">
            <label class="form-label fw-semibold">Reason for Rejection (Optional)</label>
            <textarea id="loanRejectReason" class="form-control" rows="3" placeholder="e.g. Low CIBIL, Insufficient proof..."></textarea>
        </div>
      </div>
      <div class="modal-footer border-top py-3">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmRejectBtn">Reject Application</button>
      </div>
    </div>
  </div>
</div>

@if($application->applicationDetail && $application->applicationDetail->vehicle_image)
<div class="modal fade" id="vehicleImageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title">Verification Asset Photo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img src="{{ asset('storage/' . $application->applicationDetail->vehicle_image) }}" class="img-fluid rounded-3 shadow">
      </div>
    </div>
  </div>
</div>
@endif

<div class="modal fade" id="disbursementBreakdownModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Charges Breakdown</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <tbody>
              <tr>
                <td class="ps-4">Principal (Gross Loan Amount)</td>
                <td class="text-end pe-4 fw-bold">₹{{ number_format($loanAmountValue, 2) }}</td>
              </tr>
              @php
                $interestRate = $application->interest_rate ?? 0;
                $totalInterest = $loanAmountValue * ($interestRate / 100);
                $totalPayable = $loanAmountValue + $totalInterest;
              @endphp
              <tr>
                <td class="ps-4">Total Interest (Flat)</td>
                <td class="text-end pe-4 text-primary">+ ₹{{ number_format($totalInterest, 2) }}</td>
              </tr>
              <tr class="table-info fw-bold">
                <td class="ps-4">Total Payable (Repayment Amount)</td>
                <td class="text-end pe-4 text-info">₹{{ number_format($totalPayable, 2) }}</td>
              </tr>
              <tr>
                <td class="ps-4">Processing Fee</td>
                <td class="text-end pe-4 text-danger">- ₹<span id="breakdown_processing_fee">{{ number_format($appliedProcessingFee, 2) }}</span></td>
              </tr>
              <tr>
                <td class="ps-4">Documentation Charges</td>
                <td class="text-end pe-4 text-danger">- ₹<span id="breakdown_document_charges">{{ number_format($appliedDocumentCharges, 2) }}</span></td>
              </tr>
              <tr>
                <td class="ps-4 border-bottom-0">Other Charges</td>
                <td class="text-end pe-4 text-danger border-bottom-0">- ₹<span id="breakdown_other_charges">{{ number_format($appliedOtherCharges, 2) }}</span></td>
              </tr>
              <tr class="table-success fw-bold">
                <td class="ps-4">Net Disbursed Amount</td>
                <td class="text-end pe-4">₹<span id="breakdown_net_amount">{{ number_format($netDisbursedAmount, 2) }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold">Transaction Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-4">
          <div class="col-md-6">
            <h6 class="fw-bold mb-3">Client Information</h6>
            <p class="mb-1"><span class="text-muted">Name:</span> {{ optional($application->client)->client_name }}</p>
            <p class="mb-1"><span class="text-muted">Phone:</span> {{ optional($application->client)->client_phone }}</p>
            <p class="mb-1"><span class="text-muted">Application No:</span> {{ $application->application_number }}</p>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold mb-3">Loan Summary</h6>
            <p class="mb-1"><span class="text-muted">Product:</span> {{ optional($application->product)->loan_name }}</p>
            <p class="mb-1"><span class="text-muted">Amount:</span> ₹{{ number_format($application->loan_amount, 2) }}</p>
            <p class="mb-1"><span class="text-muted">Tenure:</span> {{ $application->tenure }} {{ $displayUnit }}</p>
          </div>
        </div>
        <hr class="my-4">
        <h6 class="fw-bold mb-3">Verification Details</h6>
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label text-muted small">Live Photo Verification</label>
            @if($application->live_photo)
              <div class="p-2 border rounded text-center bg-light">
                <img src="{{ asset('storage/' . $application->live_photo) }}" class="img-fluid rounded" style="max-height: 150px;">
              </div>
            @else
              <p class="text-warning small">Not uploaded</p>
            @endif
          </div>
          <div class="col-6">
            <label class="form-label text-muted small">Cash/Physical Verification</label>
            @if($application->cash_photo)
              <div class="p-2 border rounded text-center bg-light">
                <img src="{{ asset('storage/' . $application->cash_photo) }}" class="img-fluid rounded" style="max-height: 150px;">
              </div>
            @else
              <p class="text-warning small">Not uploaded</p>
            @endif
          </div>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
(function() {
    const initAdminActions = () => {
        // Initialize flatpickr for date fields
        if (typeof flatpickr !== 'undefined') {
            flatpickr('.flatpickr-date', {
                monthSelectorType: 'static',
                dateFormat: 'd-m-Y',
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length > 0) {
                        const date = selectedDates[0];
                        
                        // Automatically update EMI start date when disbursement date changes
                        if (instance.element.id === 'disbursedAt') {
                            const emiStartDateEl = document.getElementById('emiStartDate');
                            if (emiStartDateEl && emiStartDateEl._flatpickr) {
                                const frequency = "{{ strtolower($application->term_unit ?: optional($application->product)->term_unit ?: 'months') }}";
                                const isWeekly = frequency.includes('week');
                                const isDaily = frequency.includes('day') || frequency.includes('daily');
                                
                                let emiDate = new Date(date);
                                if (isDaily) {
                                    emiDate.setDate(emiDate.getDate() + 1);
                                } else if (isWeekly) {
                                    emiDate.setDate(emiDate.getDate() + 7);
                                } else {
                                    emiDate.setMonth(emiDate.getMonth() + 1);
                                }
                                emiStartDateEl._flatpickr.setDate(emiDate, true);
                            }
                        }

                        const emiDayFields = document.querySelectorAll('[name="emi_day"]');
                        const isWeekly = !!document.querySelector('select[name="emi_day"]');
                        
                        // Update Weekday Display
                        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        const weekdayName = days[date.getDay()];
                        ['step2', 'step3'].forEach(step => {
                            const display = document.getElementById(`emi_weekday_display_${step}`);
                            if (display) display.innerText = `Weekday: ${weekdayName}`;
                        });

                        emiDayFields.forEach(field => {
                            if (isWeekly) {
                                let day = date.getDay();
                                if (day === 0) day = 7;
                                field.value = day;
                                if (window.jQuery && jQuery(field).data('select2')) {
                                    jQuery(field).trigger('change');
                                }
                            } else {
                                field.value = date.getDate();
                            }
                        });
                    }
                }
            });
        }
        // 1. Dual Webcam Module Logic
        function setupPhotoHandler(config) {
            const startBtn = document.getElementById(config.startBtnId);
            const captureBtn = document.getElementById(config.captureBtnId);
            const retakeBtn = document.getElementById(config.retakeBtnId);
            const uploadBtn = document.getElementById(config.uploadBtnId);
            const manualInput = document.getElementById(config.manualInputId);
            const preview = document.getElementById(config.previewId);
            const canvas = document.getElementById(config.canvasId);
            const placeholder = document.getElementById(config.placeholderId);
            const inputStore = document.getElementById(config.inputStoreId);
            const termsAccepted = document.getElementById('terms_accepted');
            const proceedBtn = document.getElementById('proceed-btn');
            
            let stream = null;
            if (!startBtn) return;

            const checkValidity = () => {
                const isLive = document.getElementById('live_photo_input').value !== '';
                const isCash = document.getElementById('cash_photo_input').value !== '';
                const isTerms = termsAccepted ? termsAccepted.checked : false;
                if (proceedBtn) proceedBtn.disabled = !( (isLive || isCash) && isTerms );
            };

            const startWebcam = async () => {
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
                    preview.srcObject = stream;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                    canvas.style.display = 'none';
                    startBtn.style.display = 'none';
                    captureBtn.style.display = 'inline-block';
                } catch (err) {
                    Swal.fire('Camera Error', err.message, 'error');
                }
            };

            startBtn.addEventListener('click', startWebcam);

            captureBtn.addEventListener('click', () => {
                const context = canvas.getContext('2d');
                canvas.width = preview.videoWidth;
                canvas.height = preview.videoHeight;
                context.drawImage(preview, 0, 0);
                inputStore.value = canvas.toDataURL('image/jpeg', 0.8);
                canvas.style.display = 'block';
                preview.style.display = 'none';
                captureBtn.style.display = 'none';
                retakeBtn.style.display = 'inline-block';
                if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
                checkValidity();
            });

            uploadBtn.addEventListener('click', () => manualInput.click());
            manualInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        inputStore.value = ev.target.result;
                        const img = new Image();
                        img.onload = () => {
                            canvas.width = img.width;
                            canvas.height = img.height;
                            canvas.getContext('2d').drawImage(img, 0, 0);
                            canvas.style.display = 'block';
                            placeholder.style.display = 'none';
                            checkValidity();
                        };
                        img.src = ev.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
            retakeBtn.addEventListener('click', startWebcam);
            if (termsAccepted) termsAccepted.addEventListener('change', checkValidity);
        }

        setupPhotoHandler({
            startBtnId: 'start-live-webcam', captureBtnId: 'capture-live-btn', retakeBtnId: 'retake-live-btn',
            uploadBtnId: 'upload-live-btn', manualInputId: 'manual-live-input', previewId: 'live-webcam-preview',
            canvasId: 'live-captured-photo', placeholderId: 'live-webcam-placeholder', inputStoreId: 'live_photo_input'
        });

        setupPhotoHandler({
            startBtnId: 'start-cash-webcam', captureBtnId: 'capture-cash-btn', retakeBtnId: 'retake-cash-btn',
            uploadBtnId: 'upload-cash-btn', manualInputId: 'manual-cash-input', previewId: 'cash-webcam-preview',
            canvasId: 'cash-captured-photo', placeholderId: 'cash-webcam-placeholder', inputStoreId: 'cash_photo_input'
        });

        // 2. Admin Proceed (Step 2) logic
        const adminProceedForm = document.getElementById('adminProceedForm');
        if (adminProceedForm) {
            adminProceedForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = document.getElementById('proceed-btn');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
                
                try {
                    const response = await fetch("{{ route('loan-applications.admin-proceed', $application) }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify(Object.fromEntries(new FormData(adminProceedForm)))
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Success', res.message, 'success').then(() => window.location.reload());
                    } else { throw new Error(res.message); }
                } catch (err) {
                    Swal.fire('Error', err.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = 'Confirm & Proceed to Disbursal';
                }
            });
        }


        // 4. Rejection Action
        const confirmRejectBtn = document.getElementById('confirmRejectBtn');
        if (confirmRejectBtn) {
            confirmRejectBtn.addEventListener('click', async () => {
                const reason = document.getElementById('loanRejectReason').value;
                confirmRejectBtn.disabled = true;
                try {
                    const response = await fetch("{{ route('loan-applications.reject', $application) }}", {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        body: JSON.stringify({ reason })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Rejected', 'Application status updated.', 'info').then(() => window.location.reload());
                    } else { throw new Error(res.message); }
                } catch (err) {
                    Swal.fire('Error', err.message, 'error');
                    confirmRejectBtn.disabled = false;
                }
            });
        }

        // 5. Final Disbursement (Step 3)
        const disburseBtn = document.getElementById('disburseBtn');
        if (disburseBtn) {
            const loanAmount = {{ (float)$application->loan_amount }};
            const netDisplays = document.querySelectorAll('h4.text-success.fw-bold');
            const chargeInputs = document.querySelectorAll('.charge-input');

            // Quick check for Swal
            const safeSwal = (config) => {
                if (typeof Swal !== 'undefined') {
                    return Swal.fire(config);
                } else if (window.Swal) {
                    return window.Swal.fire(config);
                } else {
                    alert(config.text || config.title);
                    return Promise.resolve({ isConfirmed: true });
                }
            };

            const updateNet = () => {
                const processingFee = parseFloat(document.getElementById('input_processing_fee').value) || 0;
                const documentCharges = parseFloat(document.getElementById('input_document_charges').value) || 0;
                const otherCharges = parseFloat(document.getElementById('input_other_charges').value) || 0;
                
                const totalCharges = processingFee + documentCharges + otherCharges;
                const netAmount = Math.max(loanAmount - totalCharges, 0);
                
                // Update main displays
                netDisplays.forEach(el => {
                    el.textContent = '₹' + netAmount.toLocaleString('en-IN', { 
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                });
                
                // Update Summary Table (Disbursement Step)
                const sumProcessing = document.getElementById('summary_processing_fee');
                const sumDocument = document.getElementById('summary_document_charges');
                const sumOther = document.getElementById('summary_other_charges');
                const sumNet = document.getElementById('summary_net_payout');
                
                if (sumProcessing) sumProcessing.textContent = '₹' + processingFee.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (sumDocument) sumDocument.textContent = '₹' + documentCharges.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (sumOther) sumOther.textContent = '₹' + otherCharges.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                if (sumNet) sumNet.textContent = '₹' + netAmount.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                
                // Update Breakdown Modal (if exists)
                const bdProcessing = document.getElementById('breakdown_processing_fee');
                const bdDocument = document.getElementById('breakdown_document_charges');
                const bdOther = document.getElementById('breakdown_other_charges');
                const bdNet = document.getElementById('breakdown_net_amount');
                
                if (bdProcessing) bdProcessing.textContent = processingFee.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                if (bdDocument) bdDocument.textContent = documentCharges.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                if (bdOther) bdOther.textContent = otherCharges.toLocaleString('en-IN', { minimumFractionDigits: 2 });
                if (bdNet) bdNet.textContent = netAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            };

            const resetCharges = () => {
                chargeInputs.forEach(i => {
                    i.value = i.dataset.default;
                });
                updateNet();
                Swal.fire({
                    icon: 'info',
                    text: 'Charges reset to product defaults.',
                    timer: 1000,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
            };

            const resetBtn = document.getElementById('resetChargesBtn');
            if (resetBtn) resetBtn.addEventListener('click', resetCharges);

            chargeInputs.forEach(i => i.addEventListener('input', updateNet));
            updateNet(); // Initial sync

            disburseBtn.addEventListener('click', async () => {
                const { isConfirmed } = await safeSwal({ 
                    title: 'Disburse Loan?', 
                    text: 'Confirm final disbursement and EMI generation?', 
                    icon: 'question', 
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Disburse Now'
                });
                
                if (isConfirmed) {
                    disburseBtn.disabled = true;
                    disburseBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Disbursing...';

                    try {
                        const paymentMethod = "{{ $application->payment_method }}";
                        const payload = {
                            disbursement_reference: document.getElementById('disbursementReference')?.value,
                            processing_fee: document.getElementById('input_processing_fee')?.value,
                            document_charges: document.getElementById('input_document_charges')?.value,
                            other_charges: document.getElementById('input_other_charges')?.value,
                            // New Bank fields
                            bank_name: document.getElementById('bankName')?.value,
                            account_number: document.getElementById('accountNumber')?.value,
                            holder_name: document.getElementById('holderName')?.value,
                            account_type: document.getElementById('accountType')?.value,
                            ifsc_code: document.getElementById('ifscCode')?.value,
                            utr_number: document.getElementById('utrNumber')?.value,
                            disbursed_at: document.getElementById('disbursedAt')?.value,
                            emi_start_date: document.getElementById('emiStartDate')?.value
                        };

                        // Basic validation for non-manual payments
                        if (paymentMethod !== 'manual') {
                            if (!payload.bank_name || !payload.account_number || !payload.ifsc_code || !payload.disbursement_reference || !payload.utr_number) {
                                throw new Error('Please fill in all bank and transaction details for electronic disbursement.');
                            }
                        }

                        const response = await fetch("{{ route('loan-applications.disburse', $application) }}", {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                            body: JSON.stringify(payload)
                        });
                        const res = await response.json();
                        if (res.success) {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire('Disbursed', 'Loan account is now active.', 'success').then(() => window.location.reload());
                            } else {
                                alert('Disbursed successfully!');
                                window.location.reload();
                            }
                        } else { throw new Error(res.message); }
                    } catch (err) {
                        safeSwal({ title: 'Error', text: err.message, icon: 'error' });
                        disburseBtn.disabled = false;
                        disburseBtn.innerHTML = '<i class="ri-check-double-line me-2"></i> Confirm & Disburse Loan';
                    }
                }
            });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAdminActions);
    } else {
        initAdminActions();
    }
})();
</script>
@endsection