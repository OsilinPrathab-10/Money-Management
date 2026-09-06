@extends('layouts/layoutMaster')

@section('title', 'Loan Account Details - ' . $loanAccount->account_number)

@section('page-script')
@vite([
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/custom-js/client-view-loans.js',
  'resources/assets/custom-js/loan-account-view.js',
  'resources/assets/custom-js/loan-account-prepayment.js'
])
@endsection

@section('page-style')
@vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
<style>
.nav-tabs .nav-link.active {
    background-color: #696cff !important;
    color: white !important;
    border-color: #696cff #696cff #fff !important;
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.375rem;
    border-top-right-radius: 0.375rem;
    color: #6c757d;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
    color: #696cff;
}

.card-header-tabs {
    margin-bottom: -1px;
}
</style>
@endsection

@section('content')
<!-- Page Header -->
<div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-6">
  <div> 
    <h4 class="mb-1">Loan Details - {{ $loanAccount->account_number }}</h4>
    <p class="text-heading mb-0">{{ $client->client_name ?? 'N/A' }}</p>
  </div>
  <div class="w-100 w-sm-auto">
    <a href="{{ route('loan-accounts') }}" class="btn btn-outline-secondary w-100 w-sm-auto justify-content-center d-inline-flex align-items-center">
      <i class="icon-base ri ri-arrow-left-line me-1"></i>
      Back To Loan Accounts
    </a>
  </div>
</div>

<!-- Loan Summary Card -->
<div class="card mb-6">
  <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
    <h5 class="mb-0">Loan Summary</h5>
    <div class="d-flex flex-wrap gap-2 w-100 w-sm-auto justify-content-sm-end">
      @php
        $foreclosureConfig = \App\Models\LoanConfiguration::where('type', 'foreclosure')->where('is_active', true)->first();
        $prepaymentConfig = \App\Models\LoanConfiguration::where('type', 'prepayment')->where('is_active', true)->first();
      @endphp
      <!-- @if($loanAccount->status === 'active' && $prepaymentConfig)
      <button type="button" class="btn btn-primary btn-sm flex-grow-1 w-sm-auto" id="prepaymentBtn" 
              data-account-id="{{ $loanAccount->id }}"
              data-account-number="{{ $loanAccount->account_number }}">
        <i class="icon-base ri ri-money-dollar-circle-line me-1"></i>
        Prepayment
      </button>
      @endif -->
      @if($loanAccount->status === 'active' && $foreclosureConfig)
      <button type="button" class="btn btn-warning btn-sm flex-grow-1 w-sm-auto" id="forecloseBtn" 
              data-account-id="{{ $loanAccount->id }}"
              data-account-number="{{ $loanAccount->account_number }}">
        <i class="icon-base ri ri-close-circle-line me-1"></i>
        Foreclose Loan
      </button>
      @endif
    </div>
  </div>
  <div class="card-body">
    @php
      $statusKey = strtolower($loanAccount->status ?? 'pending');
      $statusStyles = [
        'pending' => ['label' => 'Pending', 'color' => 'warning'],
        'active' => ['label' => 'Active', 'color' => 'success'],
        'process' => ['label' => 'In Process', 'color' => 'primary'],
        'closed' => ['label' => 'Closed', 'color' => 'danger'],
        'foreclosed' => ['label' => 'Foreclosed', 'color' => 'danger'],
        'defaulted' => ['label' => 'Defaulted', 'color' => 'danger'],
      ];
      $statusConfig = $statusStyles[$statusKey] ?? ['label' => ucfirst($loanAccount->status ?? 'Unknown'), 'color' => 'secondary'];
    @endphp
    <div class="row g-4">
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Account Number</small>
          <h6 class="mb-0">{{ $loanAccount->account_number }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Sanctioned Amount</small>
          <h6 class="mb-0 text-primary">₹{{ number_format($loanAccount->loan_amount, 0) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Interest Rate</small>
          <h6 class="mb-0">{{ $loanAccount->interest_rate }}% p.a.</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Tenure</small>
          @php
            $unit = optional($loanAccount->loanApplication)->term_unit ?? 'months';
            $displayUnit = match(strtolower($unit)) {
              'daily', 'day', 'days' => 'Days',
              'weekly', 'week', 'weeks' => 'Weeks',
              'monthly', 'month', 'months' => 'Months',
              default => ucfirst($unit)
            };
            $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
          @endphp
          <h6 class="mb-0">
            @if($isKandhuvatti)
              <span class="text-secondary">Flexible (Kandhuvatti)</span>
            @else
              {{ $loanAccount->tenure }} {{ $displayUnit }}
            @endif
          </h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Disbursed Amount</small>
          <h6 class="mb-0 text-primary">₹{{ number_format($loanAccount->disbursed_amount ?? $loanAccount->loan_amount, 0) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Disbursed Date</small>
          <h6 class="mb-0">{{ $loanAccount->disbursed_at ? $loanAccount->disbursed_at->format('d-m-Y') : 'Not disbursed yet' }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">{{ $isKandhuvatti ? 'Loan Structure' : 'Total Payable' }}</small>
          <h6 class="mb-0">{{ $isKandhuvatti ? 'Open Loan' : '₹' . number_format($loanAccount->total_payable, 2) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Paid Amount</small>
          <h6 class="mb-0 text-success">₹{{ number_format($loanAccount->paid_amount, 2) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">{{ $isKandhuvatti ? 'Remaining Principal Balance' : 'Outstanding' }}</small>
          <h6 class="mb-0 text-danger">₹{{ number_format($loanAccount->outstanding_amount, 2) }}</h6>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="d-flex flex-column">
          <small class="text-muted text-uppercase mb-1">Status</small>
          <span class="badge rounded-pill bg-label-{{ $statusConfig['color'] }} px-3 py-2 align-self-start">
            {{ $statusConfig['label'] }}
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tabs Card -->
<div class="card">
  <div class="card-header">
    <ul class="nav nav-tabs card-header-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="emi-schedule-tab" data-bs-toggle="tab" data-bs-target="#emi-schedule" type="button" role="tab" aria-controls="emi-schedule" aria-selected="true">
          <i class="icon-base ri ri-calendar-line me-1"></i>
          {{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Schedules' }}
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
          <i class="icon-base ri ri-file-text-line me-1"></i>
          Documents
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="safety-tab" data-bs-toggle="tab" data-bs-target="#safety" type="button" role="tab" aria-controls="safety" aria-selected="false">
          <i class="icon-base ri ri-shield-user-line me-1"></i>
          Safety Verification
        </button>
      </li>
    </ul>
  </div>
  <div class="card-body">
    <div class="tab-content">
      <div class="tab-pane fade show active" id="emi-schedule" role="tabpanel" aria-labelledby="emi-schedule-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">{{ $isKandhuvatti ? 'Interest Cycle Schedule' : 'EMI Schedule' }}</h6>
          <div class="text-muted d-flex align-items-center gap-3">
            @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
              <span class="badge bg-label-danger fw-bold fs-6">Remaining Principal Balance: ₹{{ number_format($loanAccount->outstanding_amount, 2) }}</span>
            @endif
            <small>{{ $isKandhuvatti ? 'Total Cycles' : 'Total EMIs' }}: {{ $emis->count() }}</small>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th class="text-center">{{ $isKandhuvatti ? 'Cycle' : 'EMI No.' }}</th>
                <th>{{ $isKandhuvatti ? 'Collection Date' : 'Due Date' }}</th>
                <th class="text-end">{{ $isKandhuvatti ? 'Principal Repayment' : 'Principal' }}</th>
                <th class="text-end">{{ $isKandhuvatti ? 'Cycle Interest' : 'Interest' }}</th>
                <th class="text-end">{{ $isKandhuvatti ? 'Total Payment' : 'Total EMI' }}</th>
                <th class="text-end">Paid Amount</th>
                <th>Paid Date</th>
                <th class="text-end">Penalty</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              @php
                $firstUnpaid = $loanAccount->emis->where('status', '!=', 'paid')->sortBy('instalment_number')->first();
                $firstUnpaidId = $firstUnpaid ? $firstUnpaid->id : null;
              @endphp
              @forelse($emis as $emi)
              <tr>
                <td class="text-center">
                  <strong>{{ $emi->instalment_number }}</strong>
                </td>
                <td>
                  {{ $emi->due_date ? $emi->due_date->format('d-m-Y') : '-' }}
                </td>
                <td class="text-end">
                  @php
                    $displayPrincipal = $emi->principal_amount;
                    if ($isKandhuvatti && $emi->id === $firstUnpaidId && $loanAccount->outstanding_amount > 0) {
                      $displayPrincipal = $loanAccount->outstanding_amount;
                    }
                  @endphp
                  ₹{{ number_format($displayPrincipal, 2) }}
                </td>
                <td class="text-end">
                  ₹{{ number_format($emi->interest_amount, 2) }}
                </td>
                <td class="text-end">
                  <strong>₹{{ number_format($emi->total_amount, 2) }}</strong>
                </td>
                <td class="text-end">
                  @php
                    $currentPaid = (float)($emi->paid_amount ?? 0);
                    $inProgressSum = $emi->collections ? $emi->collections->where('status', 'in_progress')->sum('amount') : 0;
                    $displayPaid = $currentPaid + $inProgressSum;

                    if ($isKandhuvatti) {
                      $principalPaid = (float)($emi->principal_amount ?? 0);
                      $interestPaid = max(0.00, $currentPaid - $principalPaid);

                      $inProgressPrincipalPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', 'principal')->sum('amount') : 0;
                      $inProgressInterestPaid = $emi->collections ? $emi->collections->where('status', 'in_progress')->where('payment_type', '!=', 'principal')->sum('amount') : 0;
                      if ($inProgressSum > 0 && $inProgressPrincipalPaid == 0 && $inProgressInterestPaid == 0) {
                          $inProgressInterestPaid = $inProgressSum;
                      }

                      $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                      $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
                    } else {
                      $interestPart = (float)($emi->interest_amount ?? 0);
                      $interestPaid = min($currentPaid, $interestPart);
                      $principalPaid = max(0.00, $currentPaid - $interestPart);

                      $remainingInterestPart = max(0.00, $interestPart - $interestPaid);
                      $inProgressInterestPaid = min((float)$inProgressSum, $remainingInterestPart);
                      $inProgressPrincipalPaid = max(0.00, (float)$inProgressSum - $remainingInterestPart);

                      $totalInterestPaid = $interestPaid + $inProgressInterestPaid;
                      $totalPrincipalPaid = $principalPaid + $inProgressPrincipalPaid;
                    }

                    $displayRemaining = max(0, ($emi->total_amount + ($emi->penalty_amount ?? 0)) - $currentPaid - $inProgressSum);
                  @endphp
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
                    -
                  @endif
                </td>
                <td>
                  {{ $emi->paid_date ? $emi->paid_date->format('d-m-Y') : '-' }}
                </td>
                <td class="text-end">
                  {{ $emi->penalty_amount > 0 ? '₹' . number_format($emi->penalty_amount, 2) : '-' }}
                </td>
                <td class="text-center">
                  @php
                    $statusColors = [
                      'pending' => 'warning',
                      'paid' => 'success',
                      'overdue' => 'danger',
                      'partial' => 'info'
                    ];
                    $statusColor = $statusColors[$emi->status] ?? 'secondary';
                  @endphp
                  @if($emi->status === 'paid')
                    <span class="badge bg-label-success">Paid</span>
                  @elseif($inProgressSum > 0)
                    @if($displayRemaining <= 0.01)
                      <span class="badge bg-label-warning text-warning" title="Awaiting Admin Verification">Paid (Unverified)</span>
                    @else
                      <span class="badge bg-label-info text-info" title="Awaiting Admin Verification">Partial (Unverified)</span>
                    @endif
                  @else
                    <span class="badge bg-label-{{ $statusColor }}">
                      {{ ucfirst($emi->status) }}
                    </span>
                  @endif
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="9" class="text-center text-muted py-4">
                  No EMI records found
                </td>
              </tr>
              @endforelse
            </tbody>
            @if($isKandhuvatti && $loanAccount->outstanding_amount > 0)
            <tfoot class="table-light">
              <tr>
                <td colspan="9" class="text-end fw-bold py-3 text-danger">
                  Unallocated Principal: ₹{{ number_format($loanAccount->outstanding_amount, 2) }} (Carry Forward)
                </td>
              </tr>
            </tfoot>
            @endif
          </table>
        </div>
      </div>

      <!-- Documents Tab -->
      <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3">
          <h6 class="mb-0">Loan Documents</h6>
          <div class="d-flex flex-wrap align-items-center gap-2 w-100 w-sm-auto justify-content-sm-end">
            <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 w-sm-auto" id="regenerateDocsBtn" data-loan-id="{{ $loanAccount->id }}">
              <i class="icon-base ri ri-refresh-line me-1"></i> Regenerate All
            </button>
            <div class="text-muted flex-grow-1 w-sm-auto text-end">
              @php
                $docStatusColor = match($loanAccount->status) {
                  'active' => 'success',
                  'closed' => 'danger',
                  'foreclosed' => 'warning',
                  default => 'secondary'
                };
              @endphp
              <small>Status: <span class="badge bg-label-{{ $docStatusColor }}">{{ ucfirst($loanAccount->status) }}</span></small>
            </div>
          </div>
        </div>
        @if($loanAccount->status === 'pending')
        <div class="alert alert-info mb-3">
          <i class="icon-base ri ri-information-line me-2"></i>
          <strong>Note:</strong> Additional documents will become available as the loan progresses through different stages.
        </div>
        @endif
        <div class="table-responsive">
          <table class="table table-bordered table-hover">
            <thead class="table-light">
              <tr>
                <th class="text-center" style="width: 80px;">S.No</th>
                <th>Document Name</th>
                <th class="text-center" style="width: 200px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($savedDocuments as $index => $document)
              <tr>
                <td class="text-center">
                  <strong>{{ $index + 1 }}</strong>
                </td>
                <td>
                  <div class="d-flex align-items-center">
                    <i class="icon-base ri ri-file-text-line text-primary me-2"></i>
                    {{ $document->document_title ?? ucfirst(str_replace('_', ' ', $document->document_type)) }}
                  </div>
                </td>
                <td class="text-center">
                  <div class="d-flex justify-content-center gap-2">
                    <a href="{{ route('client-loan-document-view', ['loanId' => $loanAccount->id, 'documentType' => $document->document_type]) }}"
                       target="_blank"
                       class="btn btn-sm btn-outline-primary">
                       <i class="icon-base ri ri-eye-line me-1"></i>
                       View
                    </a>
                    <a href="{{ route('client-loan-document-download', ['loanId' => $loanAccount->id, 'documentType' => $document->document_type]) }}"
                       class="btn btn-sm btn-primary">
                       <i class="icon-base ri ri-download-line me-1"></i>
                       Download
                    </a>
                  </div>
                </td>
              </tr>
              @empty
              <tr>
                <td colspan="3" class="text-center text-muted py-4">
                  <div class="d-flex flex-column align-items-center">
                    <i class="icon-base ri ri-file-forbid-line text-muted mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-0">No documents found for this loan</p>
                  </div>
                </td>
              </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Safety Verification Tab -->
      <div class="tab-pane fade" id="safety" role="tabpanel" aria-labelledby="safety-tab">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h6 class="mb-0 fw-bold"><i class="ri-shield-check-line me-2"></i>Safety Verification Proofs</h6>
        </div>
        <div class="row g-4">
          <div class="col-md-6">
            <div class="card border shadow-none bg-light h-100">
              <div class="card-body p-4 text-center">
                <p class="small fw-bold text-muted text-uppercase mb-3">Live Identity Verification</p>
                @if($loanAccount->loanApplication && $loanAccount->loanApplication->live_photo)
                  <img src="{{ asset('storage/' . $loanAccount->loanApplication->live_photo) }}" class="img-fluid rounded-3 border shadow-sm" style="max-height: 400px; object-fit: contain;">
                @else
                  <div class="py-5 text-muted bg-white rounded-3 border"><i class="ri-image-line ri-4x d-block mb-3 opacity-25"></i>No Identity Photo Recorded</div>
                @endif
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card border shadow-none bg-light h-100">
              <div class="card-body p-4 text-center">
                <p class="small fw-bold text-muted text-uppercase mb-3">Cash Handover Proof</p>
                @if($loanAccount->loanApplication && $loanAccount->loanApplication->cash_photo)
                  <img src="{{ asset('storage/' . $loanAccount->loanApplication->cash_photo) }}" class="img-fluid rounded-3 border shadow-sm" style="max-height: 400px; object-fit: contain;">
                @else
                  <div class="py-5 text-muted bg-white rounded-3 border"><i class="ri-money-rupee-circle-line ri-4x d-block mb-3 opacity-25"></i>No Cash Proof Recorded</div>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Foreclosure Modal -->
<div class="modal fade" id="foreclosureModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="foreclosureModalTitle">Foreclose Loan Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body p-4">
        <!-- Eligibility Status -->
        <div id="eligibilityAlert" class="alert mb-4 d-flex align-items-center" role="alert"></div>
        
        <div class="row g-4">
          <!-- Configuration Section (Hidden by default, shown on Override) -->
          <div class="col-md-12 d-none" id="overrideSection">
            <h6 class="fw-bold mb-3 d-none" id="overrideTitle">Configuration Override</h6>
            <p class="text-muted small mb-4 d-none" id="overrideDesc">
              Customize foreclosure settings for this specific transaction.
            </p>
            
            <form id="foreclosureOverrideForm">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label text-uppercase text-muted small fw-bold">Eligibility (<span class="eligibility-unit-text">Months</span>)</label>
                  <div class="input-group">
                    <input type="text" class="form-control bg-body-secondary" id="modalEligibilityMonths" readonly>
                    <span class="input-group-text text-muted">Paid</span>
                  </div>
                  <small class="text-muted">Threshold: <span id="currentEligibility"></span> <span class="eligibility-unit-text">months</span></small>
                </div>

                <div class="col-md-6 mb-3">
                  <label class="form-label text-uppercase text-muted small fw-bold">Foreclosure Charges (%)</label>
                  <div class="input-group">
                    <input type="text" class="form-control bg-body-secondary text-center" id="modalChargesPercentage" readonly>
                  </div>
                </div>

                <div class="col-md-12 mb-3">
                  <label class="form-label text-uppercase text-muted small fw-bold">Extra Charges (%)</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="modalExtraCharge" step="0.01" min="0" max="100" placeholder="0.00">
                    <span class="input-group-text">%</span>
                  </div>
                  <small class="text-muted">Additional percentage charge for this foreclosure (optional)</small>
                </div>
              </div>
            </form>
          </div>
          
          <!-- Amount Breakdown Section -->
          <div class="col-md-12" id="breakdownSection">
            <h6 class="fw-bold mb-3" id="breakdownTitle">Payment Breakdown</h6>
            <div class="bg-light p-3 rounded-3">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Principal outstanding</span>
                <span class="fw-medium" id="outstandingAmt"></span>
              </div>


              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted" id="interestOutstandingLabel">Interest outstanding</span>
                <span class="fw-medium text-info" id="interestOutstandingAmt">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Foreclosure charges (<span id="chargesPercent"></span>%)</span>
                <span class="fw-medium text-danger" id="foreclosureCharges"></span>
              </div>
              <div class="d-flex justify-content-between mb-3">
                <span class="text-muted">Extra Charges</span>
                <span class="fw-medium text-warning" id="extraChargeAmt"></span>
              </div>
              <div class="border-top my-2"></div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="fw-bold text-dark">Total Payable</span>
                <span class="fw-bold text-primary fs-5" id="totalForeclosureAmt"></span>
              </div>
            </div>
            
          </div>
        </div>

        <!-- Common Confirmation Checkbox -->
        <div class="mt-4 d-none" id="confirmationSection">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="confirmOverrideCheck">
            <label class="form-check-label text-muted small fw-bold" for="confirmOverrideCheck" id="confirmationLabel">
              I confirm that I want to foreclose this loan account.
            </label>
          </div>
          <div class="mt-3 d-none" id="foreclosureReasonGroup">
            <label class="form-label text-uppercase text-muted small fw-bold" for="foreclosureReason">Reason for Foreclosure</label>
            <textarea class="form-control" id="foreclosureReason" rows="3" placeholder="Provide the foreclosure reason"></textarea>
            <small class="text-muted">This note will be saved to the loan account.</small>
          </div>
        </div>
      </div>
      
      <div class="modal-footer border-top p-3 d-flex justify-content-between">
        <!-- Left Side Buttons -->
        <div>
          <button type="button" class="btn btn-danger d-none" id="overrideBtn">
            Override Foreclose
          </button>
        </div>

        <!-- Right Side Buttons -->
        <div>
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal" id="closeBtn">Close</button>
          <button type="button" class="btn btn-label-secondary d-none" id="cancelBtn">Cancel</button>
          <button type="button" class="btn btn-danger d-none" id="confirmForeclosureBtn">
            <i class="icon-base ri ri-check-line me-1"></i>
            Confirm Foreclosure
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Prepayment Modal -->
<div class="modal fade" id="prepaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-bottom">
        <h5 class="modal-title fw-bold" id="prepaymentModalTitle">Prepayment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body p-4">
        <!-- Eligibility Status -->
        <div id="prepaymentEligibilityAlert" class="alert mb-4 d-flex align-items-center" role="alert"></div>
        
        <div class="row g-4">
          <!-- Prepayment Amount Input -->
          <div class="col-md-12">
            <h6 class="fw-bold mb-3">Prepayment Details</h6>
            <div class="mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold">Prepayment Amount</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" class="form-control" id="prepaymentAmount" 
                       placeholder="Enter prepayment amount" step="0.01" min="0">
              </div>
              <small class="text-muted">
                Min: <span id="minPrepaymentAmount">-</span> | Max: <span id="maxPrepaymentAmount">-</span>
              </small>
            </div>
          </div>
          
          <!-- Payment Breakdown Section -->
          <div class="col-md-12" id="prepaymentBreakdownSection">
            <h6 class="fw-bold mb-3">Payment Breakdown</h6>
            <div class="bg-light p-3 rounded-3">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Outstanding Principal</span>
                <span class="fw-medium" id="prepaymentOutstanding">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Prepayment Amount</span>
                <span class="fw-medium text-primary" id="prepaymentAmountDisplay">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Interest Portion</span>
                <span class="fw-medium text-info" id="prepaymentInterest">₹0.00</span>
              </div>
              <div class="d-flex justify-content-between mb-3">
                <span class="text-muted">Prepayment Charges (<span id="prepaymentChargesPercent">0</span>%)</span>
                <span class="fw-medium text-danger" id="prepaymentCharges">₹0.00</span>
              </div>
              <div class="border-top my-2"></div>
              <div class="d-flex justify-content-between align-items-center mt-2 mb-3">
                <span class="fw-bold text-dark">Total Payable</span>
                <span class="fw-bold text-primary fs-5" id="prepaymentTotalPayable">₹0.00</span>
              </div>
              <div class="border-top my-2"></div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="fw-bold text-success">New Outstanding Principal</span>
                <span class="fw-bold text-success fs-6" id="prepaymentNewOutstanding">₹0.00</span>
              </div>
            </div>
          </div>

          <!-- Payment Method -->
          <div class="col-md-12">
            <label class="form-label text-uppercase text-muted small fw-bold">Payment Method</label>
            <select class="form-select" id="prepaymentPaymentMethod">
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="online">Online Payment</option>
              <option value="upi">UPI</option>
            </select>
          </div>

          <!-- Payment Reference -->
          <div class="col-md-12">
            <label class="form-label text-uppercase text-muted small fw-bold">Payment Reference (Optional)</label>
            <input type="text" class="form-control" id="prepaymentReference" 
                   placeholder="Transaction ID, Cheque Number, etc.">
          </div>

          <!-- Remarks -->
          <div class="col-md-12">
            <label class="form-label text-uppercase text-muted small fw-bold">Remarks (Optional)</label>
            <textarea class="form-control" id="prepaymentRemarks" rows="2" 
                      placeholder="Add any additional notes"></textarea>
          </div>
        </div>

        <!-- Confirmation Checkbox -->
        <div class="mt-4" id="prepaymentConfirmationSection">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="confirmPrepaymentCheck">
            <label class="form-check-label text-muted small fw-bold" for="confirmPrepaymentCheck">
              I confirm that I want to process this prepayment and understand that the EMI schedule will be regenerated with reduced tenure.
            </label>
          </div>
        </div>
      </div>
      
      <div class="modal-footer border-top p-3 d-flex justify-content-between">
        <div>
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
        </div>
        <div>
          <button type="button" class="btn btn-primary" id="confirmPrepaymentBtn" disabled>
            <i class="icon-base ri ri-check-line me-1"></i>
            Process Prepayment
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
