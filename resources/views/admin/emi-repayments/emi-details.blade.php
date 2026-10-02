@extends('layouts/layoutMaster')

@section('title', 'EMI Details - ' . $loanApplication->application_number)

@section('vendor-style')
@vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
    -webkit-overflow-scrolling: touch !important;
    margin-bottom: 0;
    padding-bottom: 0 !important;
  }
  .dataTables_wrapper .row:last-child {
    margin-top: 0;
    margin-bottom: 0;
    padding-bottom: 0 !important;
  }
  #emiScheduleTable {
    width: max-content !important;
    min-width: 100% !important;
    white-space: nowrap !important;
  }
</style>
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/bank-payment-fields.js', 'resources/assets/custom-js/emi-details.js'])
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
    const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');

    document.addEventListener('click', function(e) {
        const replayBtn = e.target.closest('.btn-replay-pay');
        if (replayBtn) {
            e.preventDefault();
            const collectionId = replayBtn.dataset.id;
            const rejectedAmount = parseFloat(replayBtn.dataset.amount);
            const emiPending = parseFloat(replayBtn.dataset.emiPending);
            const emiNo = replayBtn.dataset.emiNo;

            // The reprocessable amount is the lesser of rejected amount and EMI pending
            const reprocessAmount = Math.min(rejectedAmount, emiPending);
            const formattedReprocess = reprocessAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });
            const formattedRejected = rejectedAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 });

            let confirmHtml = '';
            if (reprocessAmount < rejectedAmount) {
                confirmHtml = '<div class="text-start"><p class="mb-2">Only <strong>₹' + formattedReprocess + '</strong> can be reprocessed for EMI #' + emiNo + '.</p>' +
                    '<p class="text-muted small mb-0">Original rejected amount was ₹' + formattedRejected + ', but the remaining EMI balance is ₹' + formattedReprocess + '.</p></div>';
            } else {
                confirmHtml = 'Are you sure you want to re-process and verify this rejected collection of <strong>₹' + formattedReprocess + '</strong> for EMI #' + emiNo + '?';
            }

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Confirm Re-Pay Payment',
                    html: confirmHtml,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Re-Pay ₹' + formattedReprocess,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-warning me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (result.value || result.isConfirmed) {
                        Swal.showLoading();
                        fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/repay', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                'Accept': 'application/json',
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({})
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    customClass: {
                                        confirmButton: 'btn btn-success'
                                    }
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.message,
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    }
                                });
                            }
                        })
                        .catch(() => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while re-paying the payment.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                }
                            });
                        });
                    }
                });
            } else {
                if (confirm('Re-process ₹' + formattedReprocess + ' for EMI #' + emiNo + '?')) {
                    fetch(baseUrl + 'app/agents/agent-collections/' + collectionId + '/repay', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({})
                    })
                    .then(r => r.json())
                    .then(data => {
                        alert(data.message);
                        if (data.success) {
                            location.reload();
                        }
                    })
                    .catch(() => {
                        alert('An error occurred.');
                    });
                }
            }
        }
    });
});
</script>
@endsection

@section('content')

<!-- Back Button -->
<div class="mb-4">
  <a href="{{ url('emi/repayments') }}" class="btn btn-xs btn-outline-secondary px-2.5 py-1 d-inline-flex align-items-center" style="font-size: 0.78rem;">
    <i class="icon-base ri ri-arrow-left-line me-1" style="font-size: 0.85rem;"></i>
    <span>Back To Repayments</span>
  </a>
</div>

@if(($walletBalance ?? 0) > 0)
<div class="alert alert-primary d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <i class="ri-wallet-3-line me-2"></i>
    <strong>Customer Wallet Balance:</strong> ₹{{ number_format($walletBalance, 2) }}
    <span class="text-muted ms-2">— Can be used for EMI payments</span>
  </div>
  @if($loanAccount?->client_id)
  <a href="{{ route('fd.wallets.client', $loanAccount->client_id) }}" class="btn btn-sm btn-primary">View Wallet</a>
  @endif
</div>
@endif

<!-- Loan Summary Card -->
<div class="card mb-6">
  <div class="card-header border-bottom">
    <div class="d-flex align-items-center justify-content-between">
      <h5 class="card-title mb-0">Loan Summary - <span class="text-primary">{{ $summary['account_number'] }}</span></h5>
      <span class="badge bg-label-{{ $summary['status'] == 'active' ? 'success' : ($summary['status'] == 'closed' ? 'secondary' : 'warning') }} text-uppercase">
        {{ $summary['status'] }}
      </span>
    </div>
  </div>
  <div class="card-body pt-6">
    @php
      $isKandhuvatti = ($loanApplication->loan_mode ?? 'emi') === 'interest_only';
    @endphp
    <div class="row g-6">
      @if($isKandhuvatti)
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded">
                <i class="ri-bank-card-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Loan Amount</p>
              <h5 class="mb-0">₹{{ number_format($summary['loan_amount'], 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-info rounded">
                <i class="ri-percent-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Interest Rate</p>
              <h5 class="mb-0">{{ number_format($summary['interest_rate'], 2) }}% p.a.</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-warning rounded">
                <i class="ri-calendar-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Loan Structure</p>
              <h5 class="mb-0">Open Loan</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded">
                <i class="ri-checkbox-circle-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Interest Collected</p>
              <h5 class="mb-0 text-success">₹{{ number_format($summary['interest_paid'], 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded">
                <i class="ri-checkbox-circle-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Principal Paid</p>
              <h5 class="mb-0 text-info">₹{{ number_format($summary['principal_paid'], 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-danger rounded">
                <i class="ri-error-warning-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Remaining Principal Balance</p>
              <h5 class="mb-0 text-danger">₹{{ number_format($summary['outstanding'], 2) }}</h5>
            </div>
          </div>
        </div>
      @else
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded">
                <i class="ri-bank-card-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Loan Amount</p>
              <h5 class="mb-0">₹{{ number_format($summary['loan_amount'], 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-danger rounded">
                <i class="ri-error-warning-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Principal Outstanding</p>
              <h5 class="mb-0 text-danger">₹{{ number_format($summary['principal_outstanding'] ?? 0, 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-info rounded">
                <i class="ri-checkbox-circle-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Principal Paid</p>
              <h5 class="mb-0 text-info">₹{{ number_format($summary['principal_paid'] ?? 0, 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded">
                <i class="ri-checkbox-circle-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Interest Paid</p>
              <h5 class="mb-0 text-success">₹{{ number_format($summary['interest_paid'] ?? 0, 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-success rounded">
                <i class="ri-money-rupee-circle-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Total Paid</p>
              <h5 class="mb-0 text-success">₹{{ number_format($summary['paid_amount'], 2) }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-warning rounded">
                <i class="ri-time-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Next EMI Due Date</p>
              <h5 class="mb-0 text-warning">{{ $summary['next_emi_due_date'] ?? 'N/A' }}</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-info rounded">
                <i class="ri-percent-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Interest Rate</p>
              <h5 class="mb-0">{{ number_format($summary['interest_rate'], 2) }}% p.a.</h5>
            </div>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="d-flex align-items-center">
            <div class="avatar">
              <div class="avatar-initial bg-label-secondary rounded">
                <i class="ri-calendar-line ri-24px"></i>
              </div>
            </div>
            <div class="ms-3">
              <p class="mb-0 text-muted small">Tenure</p>
              @php
                $unit = $loanApplication->term_unit ?: optional($loanApplication->product)->term_unit ?: 'months';
                $unitLabel = in_array(strtolower($unit), ['days', 'day', 'daily']) ? 'Days' : (in_array(strtolower($unit), ['weeks', 'week', 'weekly']) ? 'Weeks' : 'Months');
              @endphp
              <h5 class="mb-0">{{ $summary['tenure'] ?? $loanApplication->tenure }} {{ $unitLabel }}</h5>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>


<!-- EMI Schedule Table -->
<div class="card" id="emiScheduleCard"
     data-loan-outstanding="{{ $summary['outstanding'] ?? $loanAccount->outstanding_amount }}"
     data-loan-account-id="{{ $loanAccount->id }}">
  <div class="card-header border-bottom pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <h5 class="mb-0">{{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Payment Schedule' }}</h5>
    <div class="d-flex flex-wrap align-items-center gap-2">
      @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
        <span class="badge bg-label-danger fw-bold fs-6">Remaining Principal Balance: ₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
        @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
          <button type="button" class="btn btn-sm btn-primary" id="btnOpenGenerateCyclesModal"
                  data-bs-toggle="modal" data-bs-target="#generateCyclesModal">
            <i class="ri-add-circle-line me-1"></i> Generate Cycles
          </button>
        @endif
      @endif
      <button type="button" class="btn btn-sm btn-success" id="btnPaySelectedEmis" disabled style="display: none;">
        <i class="ri-money-rupee-circle-line me-1"></i> Pay Selected
      </button>
    </div>
  </div>
  <div class="card-datatable text-nowrap">
    <div class="table-responsive">
      <table class="datatables-emi-schedule table table-hover" id="emiScheduleTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>
            <div class="d-flex align-items-center gap-1">
              @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
              <input type="checkbox" class="form-check-input m-0 js-select-all-emis" id="selectAllEmis" title="Select all unpaid">
              @endif
              <span>{{ $isKandhuvatti ? 'Cycle' : 'EMI No.' }}</span>
            </div>
          </th>
          <th>Due Date</th>
          @if(!$isKandhuvatti)
            <th>Opening Balance</th>
          @endif
          <th>{{ $isKandhuvatti ? 'Principal Repayment' : 'Principal' }}</th>
          <th>{{ $isKandhuvatti ? 'Cycle Interest' : 'Interest' }}</th>
          <th>{{ $isKandhuvatti ? 'Total Payment' : 'Total Amount' }}</th>
          @if(!$isKandhuvatti)
            <th>Closing Balance</th>
          @endif
          <th>Penalty</th>
          <th>Total Due</th>
          <th class="text-end">Paid Amount</th>
          <th class="text-end">Remaining</th>
          <th>Paid Date</th>
          <th>Collected By</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @php
          $firstUnpaid = $loanAccount->emis->where('status', '!=', 'paid')->sortBy('instalment_number')->first();
          $firstUnpaidId = $firstUnpaid ? $firstUnpaid->id : null;
          
          $penaltyConfig = \App\Models\LoanConfiguration::getPenaltyConfig();
          $isPenaltyActive = $penaltyConfig && $penaltyConfig->is_active;
        @endphp
        @forelse($emis as $emi)
        @php
           // Calculate dynamic penalty if not saved to DB but applicable
           $appliedPenalty = (float)($emi->penalty_amount ?? 0);
           $dynamicPenalty = 0;
           
           if ($isPenaltyActive && $appliedPenalty <= 0 && ! in_array($emi->status, ['paid', 'closed'], true)) {
               // Resolve penalty settings: use global if set, otherwise fallback to loan account settings
               $penaltyAmount = ($penaltyConfig->charge_value > 0) 
                   ? $penaltyConfig->charge_value 
                   : ($loanAccount->penalty ?? 0);

               $graceDays = ($penaltyConfig->eligibility_days !== null)
                   ? $penaltyConfig->eligibility_days
                   : ($loanAccount->grace_period_days ?? 0);

               if ($penaltyAmount > 0) {
                   $dueDate = \Carbon\Carbon::parse($emi->due_date);
                   $penaltyStartDate = $dueDate->copy()->addDays($graceDays);
                   if (\Carbon\Carbon::today()->gt($penaltyStartDate)) {
                       $dynamicPenalty = $penaltyAmount;
                   }
               }
           }
           $totalPenaltyToShow = $appliedPenalty > 0 ? $appliedPenalty : $dynamicPenalty;

           $inProgressSumEarly = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
           if ($isKandhuvatti) {
               $remainingEarly = max(0, (float)($emi->pending_amount ?? 0) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0) - $inProgressSumEarly);
           } else {
               $currentRemainingEarly = (float)($emi->pending_amount ?? max(0, ($emi->total_due ?: $emi->interest_amount) - (float)($emi->paid_amount ?? 0)))
                   + ($appliedPenalty <= 0 ? $dynamicPenalty : 0);
               $remainingEarly = max(0, $currentRemainingEarly - $inProgressSumEarly);
           }
           $hasRejectedForSelect = $emi->collections && $emi->collections->where('status', 'rejected')->isNotEmpty();
           $isUnpaidStatus = in_array($emi->status, ['pending', 'overdue', 'partial'], true);
           $isOverdueByDate = $emi->due_date
               && $emi->due_date->lt(now()->startOfDay())
               && ! in_array($emi->status, ['paid', 'closed'], true)
               && $remainingEarly > 0.01;
           $canSelectForPay = ($isUnpaidStatus || $isOverdueByDate)
               && $remainingEarly > 0.01
               && !($inProgressSumEarly > 0 && $remainingEarly <= 0.01)
               && !$hasRejectedForSelect;
        @endphp
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>
            <div class="d-flex align-items-center gap-2">
              @if($canSelectForPay && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff')))
                <input type="checkbox" class="form-check-input emi-pay-checkbox m-0"
                       data-emi-id="{{ $emi->id }}"
                       data-emi-no="{{ $emi->instalment_number }}"
                       data-status="{{ $emi->status }}"
                       data-payable="1"
                       data-remaining="{{ number_format($remainingEarly, 2, '.', '') }}">
              @else
                <span style="width: 1.1rem;"></span>
              @endif
              <span class="badge bg-label-secondary">{{ $isKandhuvatti ? 'Cycle #' : 'EMI #' }}{{ $emi->instalment_number }}</span>
            </div>
          </td>
          <td>{{ $emi->due_date->format('d-m-Y') }}</td>
          @if(!$isKandhuvatti)
            <td>₹{{ number_format($emi->opening_balance ?? 0, 2) }}</td>
          @endif
          <td>
            @php
              $displayPrincipal = $emi->principal_amount;
            @endphp
            ₹{{ number_format($displayPrincipal, 2) }}
          </td>
          <td>₹{{ number_format($emi->interest_amount, 2) }}</td>
          <td class="fw-semibold">
            @if($isKandhuvatti)
              ₹{{ number_format((float)$emi->interest_amount, 2) }}
            @else
              ₹{{ number_format($emi->total_amount, 2) }}
            @endif
          </td>
          @if(!$isKandhuvatti)
            <td>₹{{ number_format($emi->closing_balance ?? 0, 2) }}</td>
          @endif
          <td class="text-danger">
            {{ $totalPenaltyToShow > 0 ? '₹' . number_format($totalPenaltyToShow, 2) : '-' }}
          </td>
          <td class="fw-bold">
            @if($isKandhuvatti)
              @php
                $kandhuTotalDue = (float)$emi->interest_amount + ($appliedPenalty <= 0 ? $dynamicPenalty : 0);
              @endphp
              ₹{{ number_format($kandhuTotalDue, 2) }}
            @else
              ₹{{ number_format($emi->total_amount + $totalPenaltyToShow, 2) }}
            @endif
          </td>
          @php
             if ($isKandhuvatti) {
                 $principalPaid = round((float)($emi->principal_amount ?? 0), 2);
                 $interestPaid = max(0.00, round((float)($emi->paid_amount ?? 0) - $principalPaid, 2));
                 
                 $currentPaid = round((float)($emi->paid_amount ?? 0), 2);
                 $inProgressSum = $emi->collections ? round((float) $emi->collections->where('status', 'in_progress')->sum('amount'), 2) : 0;
                 
                 $inProgressPrincipalPaid = $emi->collections ? round((float) $emi->collections->where('status', 'in_progress')->where('payment_type', 'principal')->sum('amount'), 2) : 0;
                 $inProgressInterestPaid = $emi->collections ? round((float) $emi->collections->where('status', 'in_progress')->where('payment_type', '!=', 'principal')->sum('amount'), 2) : 0;
                 if ($inProgressSum > 0 && $inProgressPrincipalPaid == 0 && $inProgressInterestPaid == 0) {
                     $inProgressInterestPaid = $inProgressSum;
                 }

                 $verifiedCollected = $emi->collections
                    ? round((float) $emi->collections->where('status', 'verified')->sum('amount'), 2)
                    : 0;
                 $originalPaid = $verifiedCollected > 0 ? $verifiedCollected : $currentPaid;
                 
                 $totalInterestPaid = round($interestPaid + $inProgressInterestPaid, 2);
                 $totalPrincipalPaid = round($principalPaid + $inProgressPrincipalPaid, 2);
                 $displayPaid = round($totalInterestPaid, 2);
                 $scheduledDue = round((float)$emi->interest_amount + ($appliedPenalty <= 0 ? $dynamicPenalty : 0), 2);
                 $currentRemaining = max(0, round($scheduledDue - $interestPaid, 2));
                 $displayRemaining = max(0, round($scheduledDue - $totalInterestPaid, 2));
                 $paidVsDueDiff = round($totalInterestPaid - $scheduledDue, 2);
             } else {
                 $currentPaid     = round((float)($emi->paid_amount ?? 0), 2);
                 $currentRemaining = round((float)($emi->pending_amount ?? max(0, ($emi->total_due ?: $emi->interest_amount) - $currentPaid)) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0), 2);
                 
                 // Calculate unverified (in-progress) collections
                 $inProgressSum    = $emi->collections ? round((float) $emi->collections->where('status', 'in_progress')->sum('amount'), 2) : 0;
                 $verifiedCollected = $emi->collections
                    ? round((float) $emi->collections->where('status', 'verified')->sum('amount'), 2)
                    : 0;
                 $originalPaid = $verifiedCollected > 0 ? $verifiedCollected : $currentPaid;
                 $displayPaid      = round($originalPaid + $inProgressSum, 2);
                 $displayRemaining = max(0, round($currentRemaining - $inProgressSum, 2));

                 $interestPart = round((float)($emi->interest_amount ?? 0), 2);
                 $interestPaid = min($currentPaid, $interestPart);
                 $principalPaid = max(0.00, round($currentPaid - $interestPart, 2));

                 $remainingInterestPart = max(0.00, round($interestPart - $interestPaid, 2));
                 $inProgressInterestPaid = min((float)$inProgressSum, $remainingInterestPart);
                 $inProgressPrincipalPaid = max(0.00, round((float)$inProgressSum - $remainingInterestPart, 2));

                 $totalInterestPaid = round($interestPaid + $inProgressInterestPaid, 2);
                 $totalPrincipalPaid = round($principalPaid + $inProgressPrincipalPaid, 2);
                 $scheduledDue = round((float)($emi->total_amount + $totalPenaltyToShow), 2);
                 $paidVsDueDiff = round($originalPaid - $scheduledDue, 2);
             }
          @endphp
          <td class="text-end">
            <div class="d-flex flex-column align-items-end">
              @if($isKandhuvatti)
                @if($totalInterestPaid > 0.01)
                  <span class="fw-bold text-dark font-monospace" style="font-size: 0.9rem;">
                    ₹{{ number_format($totalInterestPaid, 2) }}
                    @if($inProgressInterestPaid > 0.01)
                      <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressInterestPaid, 2) }})</span>
                    @endif
                  </span>
                @else
                  <span class="fw-bold">-</span>
                @endif
                @if($totalPrincipalPaid > 0.01)
                  <span class="badge bg-label-info small mt-1" title="Principal Paid">
                    Principal: ₹{{ number_format($totalPrincipalPaid, 2) }}
                  </span>
                @endif
              @else
                @if($displayPaid > 0.01)
                  <div class="d-flex flex-column align-items-end text-end font-monospace" style="font-size: 0.85rem; gap: 2px;">
                    @if($totalPrincipalPaid > 0.01)
                      <span class="text-success small fw-medium">
                        P. Paid: ₹{{ number_format($principalPaid, 2) }}
                        @if($inProgressPrincipalPaid > 0.01)
                          <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressPrincipalPaid, 2) }})</span>
                        @endif
                      </span>
                    @endif
                    @if($totalInterestPaid > 0.01)
                      <span class="text-info small fw-medium">
                        I. Paid: ₹{{ number_format($interestPaid, 2) }}
                        @if($inProgressInterestPaid > 0.01)
                          <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressInterestPaid, 2) }})</span>
                        @endif
                      </span>
                    @endif
                    <span class="fw-bold text-dark border-top pt-1 mt-1">
                      Total: ₹{{ number_format($originalPaid, 2) }}
                      @if($inProgressSum > 0.01)
                        <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressSum, 2) }})</span>
                      @endif
                    </span>
                    @if(abs($paidVsDueDiff) >= 0.01 && $originalPaid > 0.01)
                      <span class="small {{ $paidVsDueDiff > 0 ? 'text-success' : 'text-danger' }}">
                        {{ $paidVsDueDiff > 0 ? 'Excess' : 'Short' }}: ₹{{ number_format(abs($paidVsDueDiff), 2) }}
                      </span>
                    @endif
                  </div>
                @else
                  <span class="fw-bold">-</span>
                @endif
              @endif
              @if($emi->collections && $emi->collections->count() > 0)
                <a href="javascript:void(0)" class="text-muted small btn-view-history mt-1" 
                   data-id="{{ $emi->id }}" 
                   data-emi-no="{{ $emi->instalment_number }}">
                  <i class="ri-history-line"></i> History
                </a>
              @endif
            </div>
          </td>
          <td class="text-end">
            @if($displayRemaining <= 0)
              <span class="text-success fw-bold">-</span>
            @else
              <span class="text-danger fw-bold">
                ₹{{ number_format($displayRemaining, 2) }}
                @if($inProgressSum > 0)
                  <br><small class="text-muted text-decoration-line-through" style="font-size: 0.75rem;">₹{{ number_format($currentRemaining, 2) }}</small>
                @endif
              </span>
            @endif
          </td>
          <td>
            @if($emi->paid_date)
              {{ $emi->paid_date->format('d-m-Y') }}
            @else
              <span class="text-muted">-</span>
            @endif
          </td>
          <td>
            @if($emi->paid_amount > 0)
              @php
                $lastCollection = $emi->collections->sortByDesc('collected_at')->first();
                $collector = $lastCollection ? $lastCollection->getCollectorDisplay() : ['name' => 'System/Admin', 'sub' => ''];
              @endphp
              <span class="small fw-medium text-heading">{{ $collector['name'] }}</span>
              @if(!empty($collector['sub']))
                <br>
                <small class="text-muted">{{ $collector['sub'] }}</small>
              @endif
            @else
              -
            @endif
          </td>
          <td>
            @if($emi->status == 'paid')
              <span class="badge bg-label-success">Paid</span>
            @elseif($emi->status == 'closed')
              <span class="badge bg-label-secondary">Closed</span>
            @elseif($inProgressSum > 0)
              @if($displayRemaining <= 0)
                <span class="badge bg-label-warning text-warning" title="Awaiting Admin Verification">Paid (Unverified)</span>
              @else
                <span class="badge bg-label-info text-info" title="Awaiting Admin Verification">Partial (Unverified)</span>
              @endif
            @elseif($emi->status == 'partial')
              <span class="badge bg-label-info">Partial</span>
            @elseif($emi->status == 'overdue' || ($emi->pending_amount > 0 && $emi->due_date->lt(now()->startOfDay())))
              <span class="badge bg-label-danger">Overdue</span>
            @else
              <span class="badge bg-label-warning">Pending</span>
            @endif
          </td>
          <td>
            <div class="d-flex align-items-center gap-2">
              @if(! in_array($emi->status, ['paid', 'closed'], true))
                @php
                  // Sequential Lock Logic:
                  // Only the GLOBAL first unpaid EMI can be paid. Overdue and Partial are always unlocked.
                  // 3-day window restriction has been removed.
                  
                  $isFirstUnpaidGlobal = (isset($firstUnpaidInstalment) && $emi->instalment_number == $firstUnpaidInstalment);
                  
                  // Allow payment if it's the first unpaid EMI, or if it is overdue/partial
                  $canPay = $isFirstUnpaidGlobal || in_array($emi->status, ['partial', 'overdue']);

                  $hasRejectedCollection = $emi->collections && $emi->collections->where('status', 'rejected')->isNotEmpty();
                  $rejectedCollection = $hasRejectedCollection ? $emi->collections->where('status', 'rejected')->sortByDesc('collected_at')->first() : null;
                  $isAdminOrStaff = auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff');
                  $isAgent = auth()->user()->hasRole('Agent');
                  $canRePay = $isAdminOrStaff || ($isAgent && $rejectedCollection && auth()->user()->agent && $rejectedCollection->agent_id == auth()->user()->agent->id);
                @endphp

                @if($hasRejectedCollection)
                  @if($canRePay)
                    @php
                      // Calculate EMI pending: total due minus verified payments
                      $verifiedPaidForEmi = $emi->collections ? $emi->collections->where('status', 'verified')->sum('amount') : 0;
                      $emiTotalDue = (float)$emi->total_amount + (float)$emi->penalty_amount;
                      $emiPendingForRepay = max(0, $emiTotalDue - $verifiedPaidForEmi);
                    @endphp
                    <button type="button" class="btn btn-sm btn-warning py-1 px-2 btn-replay-pay"
                            data-id="{{ $rejectedCollection->getRouteKey() }}"
                            data-amount="{{ $rejectedCollection->amount }}"
                            data-emi-pending="{{ $emiPendingForRepay }}"
                            data-emi-no="{{ $emi->instalment_number }}">
                      <i class="ri-refresh-line"></i> Re-Pay ₹{{ number_format(min($rejectedCollection->amount, $emiPendingForRepay), 2) }}
                    </button>
                  @else
                    <button type="button" class="btn btn-sm btn-secondary py-1 px-2 disabled" title="Payment was rejected. Only the collecting Agent or Admin can repay.">
                      <i class="ri-lock-line"></i> Locked
                    </button>
                  @endif
                @elseif($inProgressSum > 0 && $displayRemaining <= 0)
                  <button type="button" class="btn btn-sm btn-secondary py-1 px-2 disabled" title="Payment submitted, awaiting admin verification.">
                    <i class="ri-lock-line"></i> Locked
                  </button>
                @elseif($canPay)
                  <button type="button" class="btn btn-sm btn-primary py-1 px-2 btn-pay-now" 
                          data-id="{{ $emi->id }}" 
                          data-emi-no="{{ $emi->instalment_number }}"
                          data-amount="{{ $displayRemaining }}"
                          data-due-date="{{ $emi->due_date->format('Y-m-d') }}"
                          data-is-kandhuvatti="{{ $isKandhuvatti ? 'true' : 'false' }}"
                          data-outstanding-principal="{{ $loanAccount->outstanding_amount }}">
                    <i class="ri-money-rupee-circle-line"></i> Pay
                  </button>
                  @if($partialPaymentConfig && $partialPaymentConfig->is_active)
                    <button type="button" class="btn btn-sm btn-info py-1 px-2 partial-payment-btn"
                            data-emi-id="{{ $emi->id }}"
                            data-emi-number="{{ $emi->instalment_number }}"
                            data-total-amount="{{ $emi->total_amount }}"
                            data-interest-amount="{{ $emi->interest_amount }}"
                            data-paid-amount="{{ $displayPaid }}"
                            data-principal-amount="{{ $emi->principal_amount ?? 0 }}"
                            data-previous-balance="{{ $emi->previous_balance ?? 0 }}"
                            data-penalty-amount="{{ $totalPenaltyToShow ?? 0 }}"
                            data-min-percentage="{{ $partialPaymentConfig->minimum_partial_percentage ?? 10 }}"
                            data-partial-timing="{{ $partialPaymentConfig->partial_payment_timing ?? 'anytime' }}"
                            data-penalty-method="{{ $partialPaymentConfig->penalty_calculation_method ?? 'emi_amount' }}"
                            data-is-kandhuvatti="{{ $isKandhuvatti ? 'true' : 'false' }}"
                            data-outstanding-principal="{{ $loanAccount->outstanding_amount }}">
                      <i class="ri-percent-line"></i>
                    </button>
                  @endif
                @else
                  <button type="button" class="btn btn-sm btn-secondary py-1 px-2 disabled" title="Please pay the previous EMI first before paying this one.">
                    <i class="ri-time-line"></i> Locked
                  </button>
                @endif
              @endif
              @if($emi->paid_amount > 0)
                <div class="btn-group">
                  <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle py-1 px-2" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ri-printer-line me-1"></i> Print
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                      <a href="{{ route('print-receipt', $emi->getRouteKey()) }}" target="_blank" class="dropdown-item py-2">
                        🧾 Payment Receipt
                      </a>
                    </li>
                    <li>
                      <a href="{{ route('print-statement', $emi->getRouteKey()) }}" target="_blank" class="dropdown-item py-2">
                        📊 Account Statement
                      </a>
                    </li>
                  </ul>
                </div>
              @endif
              @if(auth()->user()->hasRole('Admin') && in_array($emi->status, ['paid', 'partial', 'overdue']) && (float)$emi->paid_amount > 0.001 && $emi->instalment_number == $latestPaidInstalment)
                <button type="button" class="btn btn-sm btn-icon btn-outline-danger btn-undo-payment rounded-pill py-1 px-2"
                        data-emi-id="{{ $emi->getRouteKey() }}"
                        data-instalment="{{ $emi->instalment_number }}"
                        title="Undo Payment">
                  <i class="ri-history-line"></i>
                </button>
              @endif
            </div>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="{{ $isKandhuvatti ? 13 : 15 }}" class="text-center py-4">
            <div class="text-muted">
              <i class="icon-base ri ri-information-line fs-4"></i>
              <p class="mb-0 mt-2">No EMI records found for this loan application.</p>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
      @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
      <tfoot class="table-light">
        <tr>
          <td colspan="14" class="text-end fw-bold py-3 text-danger">
            Unallocated Principal: ₹{{ number_format($loanAccount->outstanding_amount, 2) }} (Carry Forward)
          </td>
        </tr>
      </tfoot>
      @endif
    </table>
    </div>
  </div>
</div>

<!-- Pay EMI Modal -->
<div class="modal fade" id="payEmiModal" tabindex="-1" aria-labelledby="payEmiModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <form id="payEmiForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      @csrf
      <input type="hidden" name="emi_id" id="modalEmiId">

      {{-- Header --}}
      <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
        <div class="d-flex align-items-center gap-2_5">
          <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
            <i class="icon-base ri ri-money-dollar-circle-line"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-primary mb-0 fs-6" id="payEmiModalLabel">
              Pay EMI #<span id="modalEmiNo"></span>
            </h5>
            <small class="text-primary opacity-75" style="font-size:0.75rem;">Full EMI payment collection</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-sm-4">
        {{-- Client Info Card --}}
        <div class="card bg-label-secondary border-0 shadow-none mb-3">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.68rem; letter-spacing: 0.5px;">Client</small>
                <span class="fw-bold text-dark fs-6">{{ $loanAccount->client->user->name ?? $loanAccount->client->name ?? 'Client' }}</span>
              </div>
              <span class="badge bg-label-primary px-2_5 py-1">Full Payment</span>
            </div>
          </div>
        </div>

        {{-- Current Principal Balance (Kandhuvatti Only) --}}
        <div class="col-12 d-none mb-3" id="principalBalanceGroup">
          <div class="p-3 bg-label-danger border border-danger border-opacity-20 rounded-3">
            <small class="text-danger d-block mb-1 fw-semibold" style="font-size:0.7rem;">Current Principal Balance</small>
            <h6 class="mb-0 text-danger fw-bold fs-6" id="modalPrincipalBalanceDisplay">₹0.00</h6>
          </div>
        </div>

        {{-- Payment & Principal Amounts --}}
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="paid_amount">Payment Amount (₹) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
              <input type="number" step="0.01" id="paid_amount" name="paid_amount" class="form-control fw-bold text-dark" required>
            </div>
            <small class="text-muted d-block mt-1" id="paidAmountHelp" style="font-size:0.72rem;">Enter payment amount</small>
          </div>

          {{-- Principal Amount (Kandhuvatti Only) --}}
          <div class="col-12 col-sm-6 d-none" id="principalAmountGroup">
            <label class="form-label fw-medium text-dark small mb-1" for="principal_amount">Principal Repayment (Optional)</label>
            <div class="input-group">
              <span class="input-group-text bg-label-info text-info fw-bold">₹</span>
              <input type="number" step="0.01" id="principal_amount" name="principal_amount" class="form-control fw-bold text-dark" placeholder="Enter principal portion">
            </div>
            <small class="text-info d-block mt-1" style="font-size:0.72rem;">Reduces principal & recalculates future interest.</small>
          </div>
        </div>

        {{-- Payment Details Section --}}
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="paid_date">Payment Date <span class="text-danger">*</span></label>
            <input type="date" id="paid_date" name="paid_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="payment_method">Payment Method <span class="text-danger">*</span></label>
            <select id="payment_method" name="payment_method" class="form-select form-select-sm no-search" required>
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet (₹{{ number_format($walletBalance ?? 0, 2) }})</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          @include('admin.partials.bank-collection-fields', [
            'bankAccounts' => $bankAccounts,
            'wrapperClass' => 'mb-0',
          ])
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="payment_reference">Reference / Txn ID</label>
            <input type="text" id="payment_reference" name="payment_reference" class="form-control form-control-sm" placeholder="e.g. UPI ID, Bank UTR">
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="remarks">Remarks</label>
            <input type="text" id="remarks" name="remarks" class="form-control form-control-sm" placeholder="Any additional notes...">
          </div>
        </div>

        <div class="alert alert-primary bg-label-primary border-0 py-2 px-3 d-flex align-items-center mb-0" role="alert">
          <i class="ri-information-line me-2 text-primary"></i>
          <span class="small text-primary">Recorded by: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->hasRole('Agent') ? 'Agent' : 'Admin/Staff' }})</span>
        </div>
      </div>

      <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
        <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary px-4 shadow-xs" id="btnSubmitPayment">
          <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
          Confirm Payment
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Pay Selected EMIs Modal -->
<div class="modal fade" id="paySelectedEmisModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <form id="paySelectedEmisForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      @csrf
      {{-- Header --}}
      <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
        <div class="d-flex align-items-center gap-2_5">
          <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
            <i class="ri-checkbox-multiple-line"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-primary mb-0 fs-6">Pay Selected EMIs</h5>
            <small class="text-primary opacity-75" style="font-size:0.75rem;">Bulk selected EMI payment collection</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-sm-4">
        {{-- Payment Type / Mode Selection --}}
        <div class="mb-3">
          <label class="form-label fw-semibold text-dark small mb-1">Payment Mode</label>
          <div class="d-flex gap-3">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="selected_pay_type" id="selectedPayTypeFull" value="full" checked>
              <label class="form-check-label fw-medium text-dark small" for="selectedPayTypeFull">
                Full Pay (Pay Total Selected Dues)
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="selected_pay_type" id="selectedPayTypePartial" value="partial">
              <label class="form-check-label fw-medium text-dark small" for="selectedPayTypePartial">
                Partial Pay (Custom Amount Allocation)
              </label>
            </div>
          </div>
        </div>

        {{-- Selected Summary --}}
        <div class="mb-3">
          <label class="form-label fw-semibold text-dark small mb-1">Selected EMIs</label>
          <div id="selectedEmisSummary" class="small text-muted border rounded-3 p-3 bg-label-secondary"></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="selected_paid_amount">Total Amount to Pay (₹) <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
              <input type="number" step="0.01" id="selected_paid_amount" name="paid_amount" class="form-control fw-bold text-dark" required>
            </div>
            <small class="text-muted d-block mt-1" id="selectedPaidAmountHelp" style="font-size:0.72rem;">Editable — extra amount applies to upcoming EMIs automatically.</small>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="selected_paid_date">Payment Date       <span class="text-danger">*</span></label>
            <input type="date" id="selected_paid_date" name="paid_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="selected_payment_method">Payment Method <span class="text-danger">*</span></label>
            <select id="selected_payment_method" name="payment_method" class="form-select form-select-sm no-search" required>
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet (₹{{ number_format($walletBalance ?? 0, 2) }})</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
          <div class="col-12 col-sm-6">
            <label class="form-label fw-medium text-dark small mb-1" for="selected_remarks">Remarks</label>
            <input type="text" id="selected_remarks" name="remarks" class="form-control form-control-sm" placeholder="Optional notes...">
          </div>
        </div>

        <div class="mb-0">
          @include('admin.partials.bank-collection-fields', [
            'bankAccounts' => $bankAccounts,
            'bankContainerId' => 'selectedBankAccountContainer',
            'bankSelectId' => 'selected_internal_bank_account_id',
            'bankSelectName' => 'internal_bank_account_id',
            'qrContainerId' => 'selectedQrCodeDisplayContainer',
            'qrBankNameId' => 'selectedQrBankName',
            'qrUpiIdId' => 'selectedQrUpiId',
            'qrImageWrapperId' => 'selectedQrCodeImageWrapper',
            'bankTransferContainerId' => 'selectedBankTransferDetailsContainer',
            'bankTransferContentId' => 'selectedBankTransferDetailsContent',
          ])
        </div>
      </div>

      <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
        <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-success px-4 shadow-xs" id="btnSubmitSelectedPayment">
          <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
          Confirm Payment
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Partial Payment Modal -->
<div class="modal fade" id="partialPaymentModal" tabindex="-1" aria-labelledby="partialPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <form id="partialPaymentForm" class="modal-content border-0 shadow-lg rounded-3 overflow-hidden">
      @csrf
      <input type="hidden" id="partialEmiId" name="emi_id">
      <input type="hidden" id="partialLoanAccountId" name="loan_account_id" value="{{ $loanAccount->id }}">

      {{-- Header --}}
      <div class="modal-header bg-label-primary px-3 px-sm-4 py-3 border-bottom border-primary border-opacity-10">
        <div class="d-flex align-items-center gap-2_5">
          <div class="avatar avatar-xs bg-white text-primary rounded-circle d-flex align-items-center justify-content-center shadow-xs">
            <i class="icon-base ri ri-percent-line"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-primary mb-0 fs-6" id="partialPaymentModalLabel">
              Partial Payment <span id="partialEmiNumber" class="fw-normal opacity-75"></span>
            </h5>
            <small class="text-primary opacity-75" style="font-size:0.75rem;">Custom partial EMI payment collection</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3 p-sm-4">
        {{-- Client Info Card --}}
        <div class="card bg-label-secondary border-0 shadow-none mb-3">
          <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size:0.68rem; letter-spacing: 0.5px;">Client</small>
                <span class="fw-bold text-dark fs-6">{{ $loanAccount->client->user->name ?? $loanAccount->client->name ?? 'Client' }}</span>
              </div>
              <span class="badge bg-label-primary px-2_5 py-1">Partial Payment</span>
            </div>
          </div>
        </div>

        {{-- Mobile Responsive Financial Breakdown Grid --}}
        <div class="row g-2 mb-3">
          <div class="col-12 col-sm-4" id="partialTotalEmiGroup">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;" id="partialTotalEmiLabel">{{ $isKandhuvatti ? 'Cycle Interest' : 'Total EMI Amount' }}</small>
                <div class="fw-bold text-dark fs-6 text-nowrap mt-1" id="partialTotalEmiDisplay">₹0.00</div>
                <input type="hidden" id="partialTotalEmi">
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-4" id="partialPaidAmountGroup">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;">Already Paid</small>
                <div class="fw-bold text-success fs-6 text-nowrap mt-1" id="partialPaidAmountDisplay">₹0.00</div>
                <input type="hidden" id="partialPaidAmount">
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-4" id="partialTotalDueGroup">
            <div class="card bg-label-primary border border-primary border-opacity-20 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-primary fw-semibold d-block" style="font-size:0.7rem;">Remaining Amount</small>
                <div class="fw-bold text-primary fs-6 text-nowrap mt-1" id="partialTotalDueDisplay">₹0.00</div>
                <input type="hidden" id="partialTotalDue">
              </div>
            </div>
          </div>

          {{-- Extra Kandhuvatti breakdown cards if shown dynamically --}}
          <div class="col-12 col-sm-6 d-none" id="partialInterestPortionCard">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;">Interest Portion</small>
                <div class="fw-bold text-dark fs-6 text-nowrap mt-1" id="partialInterestPortionDisplay">₹0.00</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 d-none" id="partialPrincipalPortionCard">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;">Principal Portion</small>
                <div class="fw-bold text-dark fs-6 text-nowrap mt-1" id="partialPrincipalPortionDisplay">₹0.00</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 d-none" id="partialRemainingInterestCard">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;">Remaining Interest Due</small>
                <div class="fw-bold text-danger fs-6 text-nowrap mt-1" id="partialRemainingInterestDisplay">₹0.00</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 d-none" id="partialPrincipalPaidCard">
            <div class="card bg-label-secondary border-0 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-muted d-block" style="font-size:0.7rem;">Principal Already Paid</small>
                <div class="fw-bold text-primary fs-6 text-nowrap mt-1" id="partialPrincipalPaidDisplay">₹0.00</div>
              </div>
            </div>
          </div>
          <div class="col-12 d-none" id="partialPrincipalGroup">
            <div class="card bg-label-danger border border-danger border-opacity-20 shadow-none text-center h-100">
              <div class="card-body p-2_5">
                <small class="text-danger fw-semibold d-block" style="font-size:0.7rem;">Current Principal Balance</small>
                <div class="fw-bold text-danger fs-6 text-nowrap mt-1" id="partialPrincipalDisplay">₹0.00</div>
              </div>
            </div>
          </div>
        </div>

        {{-- Partial Amount Input --}}
        <div class="mb-3">
          <label for="partialPaymentAmount" class="form-label fw-medium text-dark small mb-1">
            Partial Payment Amount <span class="text-danger">*</span>
          </label>
          <div class="input-group">
            <span class="input-group-text bg-label-primary text-primary fw-bold">₹</span>
            <input type="number" step="0.01" class="form-control fw-bold text-dark" id="partialPaymentAmount" name="partial_amount" placeholder="Enter amount" required>
          </div>
          <small class="text-muted d-block mt-1" id="partialMinAmountHelp" style="font-size:0.72rem;">Minimum: ₹0.00</small>
        </div>

        {{-- Payment Details Section --}}
        <div class="row g-3 mb-3">
          <div class="col-12 col-sm-6">
            <label for="partialPaymentDate" class="form-label fw-medium text-dark small mb-1">Payment Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control form-control-sm" id="partialPaymentDate" name="payment_date" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-12 col-sm-6">
            <label for="partialPaymentMethod" class="form-label fw-medium text-dark small mb-1">Payment Method <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm no-search" id="partialPaymentMethod" name="payment_method" required>
              <option value="in_hand" selected>Cash in hand</option>
              <option value="wallet">Customer Wallet (₹{{ number_format($walletBalance ?? 0, 2) }})</option>
              <option value="upi">UPI / GPay / QR</option>
              <option value="bank_transfer">Bank Transfer</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          @include('admin.partials.bank-collection-fields', [
            'bankAccounts' => $bankAccounts,
            'bankContainerId' => 'partialBankAccountContainer',
            'bankSelectId' => 'partial_internal_bank_account_id',
            'bankSelectName' => 'internal_bank_account_id',
            'qrContainerId' => 'partialQrCodeDisplayContainer',
            'qrBankNameId' => 'partialQrBankName',
            'qrUpiIdId' => 'partialQrUpiId',
            'qrImageWrapperId' => 'partialQrCodeImageWrapper',
            'bankTransferContainerId' => 'partialBankTransferDetailsContainer',
            'bankTransferContentId' => 'partialBankTransferDetailsContent',
          ])
        </div>

        <div class="mb-0">
          <label for="partialPaymentReference" class="form-label fw-medium text-dark small mb-1">Reference / Txn ID</label>
          <input type="text" class="form-control form-control-sm" id="partialPaymentReference" name="payment_reference" placeholder="e.g. UPI ID, Bank UTR">
        </div>
      </div>

      <div class="modal-footer bg-light px-3 px-sm-4 py-3 border-top">
        <button type="button" class="btn btn-sm btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-sm btn-primary px-4 shadow-xs" id="submitPartialPaymentBtn">
          Process Payment
        </button>
      </div>
    </form>
  </div>
</div>
@if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
<!-- Floating Bulk Payment Bar for EMI Schedule -->
<div id="emiScheduleBulkPayBar" class="d-none position-fixed bottom-0 start-50 translate-middle-x mb-4 shadow-lg bg-white border rounded-4 p-3" style="width: 90%; max-width: 800px; border-top: 4px solid #28c76f !important; z-index: 1090; transition: all 0.3s ease-in-out;">
  <div class="d-flex flex-column gap-3">
    <!-- Header with total and count -->
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-success rounded-pill fs-6" id="emiScheduleBulkSelectedCount">0</span>
        <h6 class="mb-0 fw-semibold text-dark" id="emiScheduleBulkBarTitle">EMIs Selected for Bulk Payment</h6>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small fw-medium" id="emiScheduleBulkTotalLabel">Total Overdue:</span>
        <span class="fs-5 fw-bold text-success" id="emiScheduleBulkTotalAmount">₹0.00</span>
      </div>
    </div>
    
    <!-- Selected EMIs list -->
    <div id="emiScheduleBulkListContainer" class="overflow-y-auto px-2" style="max-height: 120px;">
      <!-- JavaScript will dynamically render selected EMIs here -->
    </div>
    
    <!-- Action buttons -->
    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
      <button type="button" id="emiScheduleBulkCancelBtn" class="btn btn-sm btn-outline-secondary">
        <i class="ri-close-line me-1"></i> Cancel Selection
      </button>
      
      <div class="d-flex gap-2">
        <button type="button" id="emiScheduleBulkPayBtn" class="btn btn-sm btn-success px-4">
          <i class="ri-wallet-3-line me-1"></i> Pay Selected
        </button>
        <button type="button" id="emiScheduleBulkUndoBtn" class="btn btn-sm btn-danger px-4 d-none">
          <i class="ri-history-line me-1"></i> Undo Selected Payments
        </button>
      </div>
    </div>
  </div>
</div>
@endif

<!-- EMI Collection History Modal -->
<div class="modal fade" id="emiHistoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Payment History - EMI #<span id="historyEmiNumber"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="px-4 py-3 bg-light border-bottom">
          <div class="row text-center">
            <div class="col-6 border-end">
              <small class="text-muted d-block">EMI Amount / Cycle Interest</small>
              <h6 class="mb-0 fw-bold" id="historyTotalAmount">₹0.00</h6>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Original Paid</small>
              <h6 class="mb-0 fw-bold text-success" id="historyPaidAmount">₹0.00</h6>
            </div>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped mb-0">
            <thead>
              <tr>
                <th class="ps-4">Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Reference</th>
                <th>Status</th>
                <th class="pe-4 text-end history-action-header d-none">Action</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
@php
  $openLoanCycleService = app(\App\Services\OpenLoanCycleService::class);
  $openLoanFreq = $openLoanCycleService->frequencyFor($loanAccount);
  $freqTitle = match($openLoanFreq) {
      'daily' => 'Daily',
      'weekly' => 'Weekly',
      default => 'Monthly',
  };
  $unitPlural = match($openLoanFreq) {
      'daily' => 'days',
      'weekly' => 'weeks',
      default => 'months',
  };
  $defaultCycleCount = match($openLoanFreq) {
      'daily' => 10,
      'weekly' => 10,
      default => 5,
  };
  $unitPlaceholder = match($openLoanFreq) {
      'daily' => 'e.g., 10 days',
      'weekly' => 'e.g., 10 weeks',
      default => 'e.g., 5 months',
  };
  $approxCycleInterest = round($loanAccount->outstanding_amount * ((float)$loanAccount->interest_rate / 100));
@endphp
<!-- Generate Interest Cycles Modal (Open Loan Only) -->
<div class="modal fade" id="generateCyclesModal" tabindex="-1" aria-labelledby="generateCyclesModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formGenerateOpenLoanCycles" action="{{ route('emi.generate-open-cycles', $loanAccount->id) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="generateCyclesModalLabel">
            <i class="ri-calendar-event-line me-2 text-primary"></i>Generate Interest Cycles
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-primary d-flex align-items-center mb-3 py-2 px-3" role="alert">
            <i class="ri-information-line me-2 fs-5"></i>
            <div class="small">
              Generate upcoming interest cycles for this Open Loan so you can collect payments or make principal repayments in advance.
            </div>
          </div>

          <div class="row g-2 mb-3">
            <div class="col-6">
              <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Principal Balance</small>
                <span class="fw-bold text-danger fs-6">₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-2 text-center bg-light">
                <small class="text-muted d-block">Interest per Cycle</small>
                <span class="fw-bold text-primary fs-6">₹{{ number_format($approxCycleInterest, 2) }}</span>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label for="generate_cycle_count" class="form-label fw-medium">
              Number of {{ ucfirst($unitPlural) }} to Generate <span class="text-danger">*</span>
            </label>
            <div class="input-group">
              <input type="number"
                     class="form-control"
                     id="generate_cycle_count"
                     name="cycle_count"
                     min="1"
                     max="365"
                     step="1"
                     value="{{ $defaultCycleCount }}"
                     placeholder="{{ $unitPlaceholder }}"
                     required>
              <span class="input-group-text">{{ ucfirst($unitPlural) }}</span>
            </div>
            <div class="form-text mt-1 text-muted">
              Frequency: <span class="badge bg-label-info">{{ $freqTitle }}</span>. Enter number of {{ $unitPlural }} (e.g., {{ $defaultCycleCount }} {{ $unitPlural }}).
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitGenerateCycles">
            <i class="ri-add-circle-line me-1"></i> Generate Cycles
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@endsection
