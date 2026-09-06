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
@vite(['resources/assets/custom-js/emi-details.js'])
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
  <a href="{{ url('emi/repayments') }}" class="btn btn-outline-secondary">
    <i class="icon-base ri ri-arrow-left-line me-1"></i>
    Back To Repayments
  </a>
</div>

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
<div class="card">
  <div class="card-header border-bottom pb-3 d-flex justify-content-between align-items-center">
    <h5 class="mb-0">{{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Payment Schedule' }}</h5>
    @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
      <span class="badge bg-label-danger fw-bold fs-6">Remaining Principal Balance: ₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
    @endif
  </div>
  <div class="card-datatable text-nowrap">
    <div class="table-responsive">
      <table class="datatables-emi-schedule table table-hover" id="emiScheduleTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>{{ $isKandhuvatti ? 'Cycle' : 'EMI No.' }}</th>
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
           
           if ($isPenaltyActive && $appliedPenalty <= 0 && $emi->status !== 'paid') {
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
        @endphp
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>
            <span class="badge bg-label-secondary">{{ $isKandhuvatti ? 'Cycle #' : 'EMI #' }}{{ $emi->instalment_number }}</span>
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
          <td class="fw-semibold">₹{{ number_format($isKandhuvatti ? $emi->interest_amount : $emi->total_amount, 2) }}</td>
          @if(!$isKandhuvatti)
            <td>₹{{ number_format($emi->closing_balance ?? 0, 2) }}</td>
          @endif
          <td class="text-danger">
            {{ $totalPenaltyToShow > 0 ? '₹' . number_format($totalPenaltyToShow, 2) : '-' }}
          </td>
          <td class="fw-bold">
            @if($isKandhuvatti)
              ₹{{ number_format(($emi->total_due > 0 ? $emi->total_due : $emi->interest_amount) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0), 2) }}
            @else
              ₹{{ number_format($emi->total_amount + $totalPenaltyToShow, 2) }}
            @endif
          </td>
          @php
             if ($isKandhuvatti) {
                 $principalPaid = (float)($emi->principal_amount ?? 0);
                 $interestPaid = max(0.00, (float)($emi->paid_amount ?? 0) - $principalPaid);
                 
                 $currentPaid = (float)($emi->paid_amount ?? 0);
                 $currentRemaining = (float)($emi->pending_amount ?? 0) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0);
                 $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                 
                 $inProgressPrincipalPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', 'principal')->sum('amount') : 0;
                 $inProgressInterestPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', '!=', 'principal')->sum('amount') : 0;
                 if ($inProgressSum > 0 && $inProgressPrincipalPaid == 0 && $inProgressInterestPaid == 0) {
                     $inProgressInterestPaid = $inProgressSum;
                 }

                 $displayPaid = $currentPaid + $inProgressSum;
                 $displayRemaining = max(0, $currentRemaining - $inProgressSum);
                 
                 $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                 $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
             } else {
                 $currentPaid     = (float)($emi->paid_amount ?? 0);
                 // Use DB-stored pending_amount (set by syncEmiBalances) as the source of truth.
                 // For Kandhuvatti the interest cycle amount lives in interest_amount / total_due,
                 // not in total_amount (which may be 0). Using pending_amount avoids that trap.
                 $currentRemaining = (float)($emi->pending_amount ?? max(0, ($emi->total_due ?: $emi->interest_amount) - $currentPaid)) + ($appliedPenalty <= 0 ? $dynamicPenalty : 0);
                 
                 // Calculate unverified (in-progress) collections
                 $inProgressSum    = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                 $displayPaid      = $currentPaid + $inProgressSum;
                 $displayRemaining = max(0, $currentRemaining - $inProgressSum);

                 $interestPart = (float)($emi->interest_amount ?? 0);
                 $interestPaid = min($currentPaid, $interestPart);
                 $principalPaid = max(0.00, $currentPaid - $interestPart);

                 $remainingInterestPart = max(0.00, $interestPart - $interestPaid);
                 $inProgressInterestPaid = min((float)$inProgressSum, $remainingInterestPart);
                 $inProgressPrincipalPaid = max(0.00, (float)$inProgressSum - $remainingInterestPart);

                 $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                 $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
             }
          @endphp
          <td class="text-end">
            <div class="d-flex flex-column align-items-end">
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
                    Total: ₹{{ number_format($currentPaid, 2) }}
                    @if($inProgressSum > 0.01)
                      <span class="text-warning small" title="Unverified Agent Collection">(+₹{{ number_format($inProgressSum, 2) }})</span>
                    @endif
                  </span>
                </div>
              @else
                <span class="fw-bold">-</span>
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
                $agent = $lastCollection ? $lastCollection->agent : null;
                $verifier = $lastCollection ? $lastCollection->verifiedBy : null;
              @endphp
              @if($agent)
                <span class="small fw-medium text-heading">{{ $agent->agent_name }}</span>
                <br>
                <small class="text-muted">Agent: {{ $agent->agent_code }}</small>
              @elseif($verifier)
                <span class="small fw-medium text-heading">{{ $verifier->name }}</span>
                <br>
                <small class="text-muted">Admin/Staff</small>
              @else
                <span class="text-muted">System/Admin</span>
              @endif
            @else
              -
            @endif
          </td>
          <td>
            @if($emi->status == 'paid')
              <span class="badge bg-label-success">Paid</span>
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
              @if($emi->status != 'paid')
                @php
                  // New Sequential Lock Logic:
                  // 1. Only the GLOBAL first unpaid EMI can be paid.
                  // 2. If it's Pending, it's unlocked if it's NOT the first-ever EMI (because previous is paid) 
                  //    OR if it's within the 3-day window.
                  // 3. If it's Overdue or Partial, it's always unlocked.
                  
                  $isFirstUnpaidGlobal = (isset($firstUnpaidInstalment) && $emi->instalment_number == $firstUnpaidInstalment);
                  
                  // Allow payment if it's the first unpaid EMI (Sequential Lock)
                  // We removed the 3-day window and instalment > 1 restrictions as per user request
                  $canPay = $isFirstUnpaidGlobal;

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
                @else
                  <button type="button" class="btn btn-sm btn-secondary py-1 px-2 disabled" title="Please pay the previous EMI first or wait until 3 days before the due date.">
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
<div class="modal fade" id="payEmiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title">Record EMI Payment - EMI #<span id="modalEmiNo"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="payEmiForm">
        @csrf
        <input type="hidden" name="emi_id" id="modalEmiId">
        <div class="modal-body">
          <div class="row g-4">
            <!-- Current Principal Balance (Kandhuvatti Only) -->
            <div class="col-12 d-none" id="principalBalanceGroup">
              <div class="p-3" style="background-color: #ffebee; border-radius: 8px; border-left: 4px solid #f44336;">
                <small class="text-muted d-block mb-1">Current Principal Balance</small>
                <h6 class="mb-0 text-danger" id="modalPrincipalBalanceDisplay">₹0.00</h6>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="paid_amount">Total Payment Amount (₹) <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" id="paid_amount" name="paid_amount" class="form-control" required>
              </div>
              <small class="text-muted" id="paidAmountHelp">Enter total amount</small>
            </div>
            <!-- Principal Amount (Kandhuvatti Only) -->
            <div class="col-md-6 d-none" id="principalAmountGroup">
              <label class="form-label" for="principal_amount">Principal Repayment (Optional)</label>
              <div class="input-group border border-info rounded">
                <span class="input-group-text bg-info text-white">₹</span>
                <input type="number" id="principal_amount" name="principal_amount" class="form-control"  placeholder="Enter principal portion">
              </div>
              <small class="text-info">Reduces principal & recalculates future interest.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="paid_date">Payment Date <span class="text-danger">*</span></label>
              <input type="date" id="paid_date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="payment_method">Payment Method <span class="text-danger">*</span></label>
              <select id="payment_method" name="payment_method" class="form-select" required>
                @if(auth()->user()->hasRole('Agent'))
                  <option value="in_hand" selected>Agent In-Hand</option>
                  <option value="upi">UPI</option>
                  <option value="bank_transfer">Bank Transfer</option>
                @else
                  <option value="in_hand" selected>Admin In-Hand</option>
                  <option value="upi">Admin UPI</option>
                  <option value="bank_transfer">Admin Bank Transfer</option>
                @endif
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="payment_reference">Reference No.</label>
              <input type="text" id="payment_reference" name="payment_reference" class="form-control" placeholder="TXN ID, Cheque No. etc">
            </div>
            <div class="col-12">
              <label class="form-label" for="remarks">Remarks</label>
              <textarea id="remarks" name="remarks" class="form-control" rows="2" placeholder="Any additional notes..."></textarea>
            </div>
          </div>
          <div class="alert alert-info d-flex align-items-center mt-4 mb-0" role="alert">
            <span class="alert-icon text-info me-2">
              <i class="ri-information-line"></i>
            </span>
            <span>Recorded by: <strong>{{ auth()->user()->name }}</strong> ({{ auth()->user()->hasRole('Agent') ? 'Agent' : 'Admin/Staff' }})</span>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-{{ auth()->user()->hasRole('Agent') ? 'warning' : 'primary' }}" id="btnSubmitPayment">
            <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
            {{ auth()->user()->hasRole('Agent') ? 'Submit for Approval' : 'Confirm Payment' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@endsection
