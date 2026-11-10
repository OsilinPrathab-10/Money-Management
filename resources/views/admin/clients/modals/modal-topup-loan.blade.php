@php
  $isKandhuvatti = ($loanAccount->loan_mode ?? 'emi') === 'interest_only';
  $frequency = $loanAccount->frequency;
  $frequencyLabel = $loanAccount->frequency_label;
  $unit = $loanAccount->loanApplication->term_unit ?: optional($loanAccount->loanApplication->product)->term_unit ?: 'months';
  $unitLabel = in_array(strtolower($unit), ['days', 'day', 'daily']) ? 'Days' : (in_array(strtolower($unit), ['weeks', 'week', 'weekly']) ? 'Weeks' : 'Months');
  
  $paidCount = $loanAccount->emis->where('status', 'paid')->count();
  $lastPaidNo = $loanAccount->emis->where('status', 'paid')->max('instalment_number') ?? 0;
  $remainingCount = $loanAccount->emis->where('instalment_number', '>', $lastPaidNo)->count();
  $currentPrincipal = (float) $loanAccount->remaining_principal_balance;
  $currentEmi = (float) $loanAccount->emi_amount;
  $currentRate = (float) $loanAccount->interest_rate;
  $emiDay = (int) ($loanAccount->emi_day ?: 1);

  $nextEmiRow = $loanAccount->emis->whereIn('status', ['pending', 'partial', 'overdue'])->sortBy('instalment_number')->first();
  $defaultNextDueDate = $nextEmiRow && $nextEmiRow->due_date ? \Carbon\Carbon::parse($nextEmiRow->due_date)->format('Y-m-d') : now()->addMonth()->format('Y-m-d');
@endphp

<!-- Modal Top-Up Loan -->
<div class="modal fade" id="topupLoanModal" tabindex="-1" aria-labelledby="topupLoanModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content border-0 shadow-lg">
      <form id="topupLoanForm" action="{{ route('loan-account.topup', $loanAccount->id) }}" method="POST">
        @csrf
        
        <!-- Header -->
        <div class="modal-header bg-label-success px-4 py-3 border-bottom border-success border-opacity-10">
          <div class="d-flex align-items-center gap-2">
            <div class="avatar avatar-sm bg-success text-white rounded p-1 d-flex align-items-center justify-content-center">
              <i class="ri-add-circle-fill fs-5"></i>
            </div>
            <div>
              <h5 class="modal-title fw-bold text-success mb-0" id="topupLoanModalLabel">
                Top-Up Loan & Regenerate EMI Schedule
              </h5>
              <small class="text-muted">
                Account: <strong class="text-dark">{{ $loanAccount->customer_loan_account_number ?? $loanAccount->account_number }}</strong> &bull; 
                Client: <strong>{{ optional($loanAccount->client)->client_name }}</strong> &bull;
                Mode: <span class="badge bg-label-info">{{ $isKandhuvatti ? 'Open Loan' : 'Standard EMI' }}</span>
              </small>
            </div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- Loan Current Metrics Strip -->
          <div class="card bg-light border-0 shadow-none mb-4">
            <div class="card-body p-3">
              <div class="row g-3 text-center align-items-center">
                <div class="col-6 col-sm-2 border-end">
                  <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem;">Loan Frequency</small>
                  <span class="badge bg-label-primary mt-1" id="currentFreqBadge">{{ $frequencyLabel }}</span>
                </div>
                <div class="col-6 col-sm-2 border-end">
                  <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem;">Current Principal</small>
                  <h6 class="mb-0 fw-bold text-danger mt-1">₹{{ number_format($currentPrincipal, 2) }}</h6>
                </div>
                <div class="col-6 col-sm-2 border-end">
                  <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem;">Current EMI</small>
                  <h6 class="mb-0 fw-bold text-primary mt-1">₹{{ number_format($currentEmi, 2) }}</h6>
                </div>
                <div class="col-6 col-sm-3 border-end">
                  <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem;">Installments Progress</small>
                  <div class="mt-1">
                    <span class="badge bg-label-success">Paid: {{ $paidCount }}</span>
                    <span class="badge bg-label-secondary ms-1">Remaining: {{ $remainingCount }}</span>
                  </div>
                </div>
                <div class="col-6 col-sm-3">
                  <small class="text-muted d-block text-uppercase" style="font-size: 0.72rem;">Sanctioned Total</small>
                  <h6 class="mb-0 fw-bold text-dark mt-1">₹{{ number_format($loanAccount->loan_amount, 2) }}</h6>
                </div>
              </div>
            </div>
          </div>

          <div class="row g-4">
            <!-- Left Column: Top-Up & Schedule Settings -->
            <div class="col-lg-7">
              <h6 class="fw-bold text-dark d-flex align-items-center gap-1 mb-3">
                <i class="ri-money-dollar-circle-line text-success fs-5"></i>
                <span>1. Top-Up Amount & Charges</span>
              </h6>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="topup_amount" class="form-label fw-semibold">
                    Top-Up Amount (₹) <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text bg-white">₹</span>
                    <input type="number" 
                           class="form-control form-control-lg fw-bold text-success border-success" 
                           id="topup_amount" 
                           name="topup_amount" 
                           placeholder="e.g. 50000" 
                           min="1" 
                           step="1" 
                           required>
                  </div>
                  <small class="text-muted">Additional principal amount to be disbursed.</small>
                </div>

                <div class="col-md-6">
                  <label for="topup_interest_rate" class="form-label fw-semibold">
                    Interest Rate (% p.a.) <span class="text-danger">*</span>
                  </label>
                  <div class="input-group">
                    <input type="number" 
                           class="form-control form-control-lg" 
                           id="topup_interest_rate" 
                           name="interest_rate" 
                           value="{{ $currentRate }}" 
                           min="0" 
                           max="100" 
                           step="0.01" 
                           required>
                    <span class="input-group-text bg-white">%</span>
                  </div>
                  <small class="text-muted">Defaults to current loan interest rate (editable).</small>
                </div>

                <div class="col-md-6">
                  <label for="topup_processing_fee" class="form-label fw-semibold">
                    Processing Fee / Deduction (₹)
                  </label>
                  <div class="input-group">
                    <span class="input-group-text bg-white">₹</span>
                    <input type="number" 
                           class="form-control" 
                           id="topup_processing_fee" 
                           name="processing_fee" 
                           value="0" 
                           min="0" 
                           step="1">
                  </div>
                  <small class="text-muted">Optional fee deducted from client payout.</small>
                </div>

                <div class="col-md-6">
                  <label for="topup_net_disbursed" class="form-label fw-semibold">
                    Net Payout to Client
                  </label>
                  <input type="text" 
                         class="form-control bg-light fw-bold text-success" 
                         id="topup_net_disbursed" 
                         value="₹0.00" 
                         readonly>
                  <small class="text-muted">Amount actually transferred to client.</small>
                </div>
              </div>

              <input type="radio" class="d-none topup-freq-radio" name="frequency" value="{{ $frequency }}" checked>

              @if(!$isKandhuvatti)
              <h6 class="fw-bold text-dark d-flex align-items-center gap-1 mt-4 mb-3">
                <i class="ri-calendar-schedule-line text-primary fs-5"></i>
                <span>2. Tenure Options</span>
                <span class="badge bg-label-primary ms-1">{{ $frequencyLabel }}</span>
              </h6>

              <!-- Tenure Mode Selection (Add, Edit/Set, Keep) -->
              <div class="mb-3">
                <label class="form-label fw-semibold d-block">
                  Tenure Adjustment Mode <span class="text-danger">*</span>
                </label>
                <div class="btn-group w-100 mb-2" role="group" aria-label="Tenure Mode">
                  <input type="radio" class="btn-check topup-tenure-mode" name="tenure_mode" id="tenure_mode_add" value="add" checked>
                  <label class="btn btn-outline-secondary" for="tenure_mode_add">
                    <i class="ri-add-line me-1"></i> Add Extra (+N)
                  </label>

                  <input type="radio" class="btn-check topup-tenure-mode" name="tenure_mode" id="tenure_mode_set" value="set">
                  <label class="btn btn-outline-secondary" for="tenure_mode_set">
                    <i class="ri-edit-line me-1"></i> Set Exact Remaining
                  </label>

                  <input type="radio" class="btn-check topup-tenure-mode" name="tenure_mode" id="tenure_mode_keep" value="keep">
                  <label class="btn btn-outline-secondary" for="tenure_mode_keep">
                    <i class="ri-check-line me-1"></i> Keep Current ({{ $remainingCount }})
                  </label>
                </div>

                <!-- Input when Mode is Add -->
                <div id="tenureInputGroupAdd">
                  <label for="topup_additional_tenure" class="form-label small fw-medium">
                    Additional Installments to Add (<span class="unit-label-text">{{ $unitLabel }}</span>)
                  </label>
                  <div class="input-group">
                    <span class="input-group-text bg-white">+</span>
                    <input type="number" 
                           class="form-control" 
                           id="topup_additional_tenure" 
                           name="additional_tenure" 
                           value="0" 
                           min="0" 
                           step="1">
                    <span class="input-group-text bg-white unit-label-text">{{ $unitLabel }}</span>
                  </div>
                  <small class="text-muted">Example: Enter 5 to extend remaining duration by 5 <span class="unit-label-text-lower">{{ strtolower($unitLabel) }}</span>.</small>
                </div>

                <!-- Input when Mode is Set -->
                <div id="tenureInputGroupSet" style="display: none;">
                  <label for="topup_new_remaining_tenure" class="form-label small fw-medium">
                    New Total Remaining Installments (<span class="unit-label-text">{{ $unitLabel }}</span>)
                  </label>
                  <div class="input-group">
                    <input type="number" 
                           class="form-control fw-bold text-primary" 
                           id="topup_new_remaining_tenure" 
                           name="new_remaining_tenure" 
                           value="{{ max(1, $remainingCount) }}" 
                           min="1" 
                           step="1">
                    <span class="input-group-text bg-white unit-label-text">{{ $unitLabel }}</span>
                  </div>
                  <small class="text-muted">Directly override the remaining installments count (e.g. set to 20 <span class="unit-label-text-lower">{{ strtolower($unitLabel) }}</span>).</small>
                </div>

                <!-- Info when Mode is Keep -->
                <div id="tenureInputGroupKeep" class="alert alert-light py-2 px-3 mb-0" style="display: none;">
                  <i class="ri-information-line me-1 text-info"></i>
                  <span>Maintaining existing <strong>{{ $remainingCount }}</strong> remaining installments. The EMI amount will increase to cover the top-up.</span>
                </div>
              </div>

              <!-- Day of Week / Month & First Due Date -->
              <div class="row g-3">
                <div class="col-md-6" id="colEmiDayWrapper">
                  <label for="topup_emi_day" class="form-label fw-semibold" id="lblEmiDay">
                    Preferred Collection Day
                  </label>
                  <!-- Monthly Day Selector (1-31) -->
                  <div id="containerMonthlyEmiDay" style="{{ $frequency === 'monthly' ? '' : 'display: none;' }}">
                    <div class="input-group">
                      <span class="input-group-text bg-white">Day</span>
                      <input type="number" class="form-control" id="input_monthly_day" value="{{ $emiDay }}" min="1" max="31">
                    </div>
                  </div>
                  <!-- Weekly Day Selector (Monday-Sunday) -->
                  <div id="containerWeeklyEmiDay" style="{{ $frequency === 'weekly' ? '' : 'display: none;' }}">
                    <select class="form-select no-search" id="select_weekly_day">
                      <option value="1" {{ $emiDay == 1 ? 'selected' : '' }}>Monday</option>
                      <option value="2" {{ $emiDay == 2 ? 'selected' : '' }}>Tuesday</option>
                      <option value="3" {{ $emiDay == 3 ? 'selected' : '' }}>Wednesday</option>
                      <option value="4" {{ $emiDay == 4 ? 'selected' : '' }}>Thursday</option>
                      <option value="5" {{ $emiDay == 5 ? 'selected' : '' }}>Friday</option>
                      <option value="6" {{ $emiDay == 6 ? 'selected' : '' }}>Saturday</option>
                      <option value="7" {{ $emiDay == 7 ? 'selected' : '' }}>Sunday</option>
                    </select>
                  </div>
                  <small class="text-muted" id="helpEmiDay">Day on which future EMIs become due.</small>
                </div>

                <div class="col-md-6">
                  <label for="topup_first_due_date" class="form-label fw-semibold">
                    First Revised EMI Due Date
                  </label>
                  <input type="date" 
                         class="form-control" 
                         id="topup_first_due_date" 
                         name="first_due_date" 
                         value="{{ $defaultNextDueDate }}">
                  <small class="text-muted">Start date for the next regenerated cycle.</small>
                </div>
              </div>
              @endif
              <input type="hidden" name="emi_day" id="topup_emi_day" value="{{ $emiDay }}">

              <!-- Disbursement Details -->
              <h6 class="fw-bold text-dark d-flex align-items-center gap-1 mt-4 mb-3">
                <i class="ri-bank-card-line text-warning fs-5"></i>
                <span>{{ $isKandhuvatti ? '2' : '3' }}. Disbursement & Payout Details</span>
              </h6>

              <div class="row g-3">
                <div class="col-md-6">
                  <label for="topup_disbursed_at" class="form-label fw-semibold">
                    Disbursement Date <span class="text-danger">*</span>
                  </label>
                  <input type="date" 
                         class="form-control" 
                         id="topup_disbursed_at" 
                         name="disbursed_at" 
                         value="{{ now()->toDateString() }}" 
                         required>
                </div>

                <div class="col-md-6">
                  <label for="topup_payment_method" class="form-label fw-semibold">
                    Payment Method <span class="text-danger">*</span>
                  </label>
                  <select class="form-select no-search" id="topup_payment_method" name="payment_method" required>
                    <option value="bank_transfer" selected>Bank Transfer (NEFT/RTGS/IMPS)</option>
                    <option value="cash">Cash</option>
                    <option value="cheque">Cheque</option>
                    <option value="upi">UPI / Online</option>
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="topup_internal_bank_account_id" class="form-label fw-semibold">
                    Disbursing Company Bank Account
                  </label>
                  <select class="form-select no-search" id="topup_internal_bank_account_id" name="internal_bank_account_id">
                    <option value="">-- Select Bank Account --</option>
                    @foreach($bankAccounts ?? [] as $bank)
                      <option value="{{ $bank->id }}">
                        {{ $bank->bank_name ?: $bank->account_name }} (₹{{ number_format($bank->current_balance ?? 0, 2) }})
                      </option>
                    @endforeach
                  </select>
                </div>

                <div class="col-md-6">
                  <label for="topup_utr_number" class="form-label fw-semibold">
                    UTR / Transaction Reference
                  </label>
                  <input type="text" 
                         class="form-control" 
                         id="topup_utr_number" 
                         name="utr_number" 
                         placeholder="e.g. UTR123456789">
                </div>

                <div class="col-12">
                  <label for="topup_remarks" class="form-label fw-semibold">
                    Remarks / Purpose
                  </label>
                  <input type="text" 
                         class="form-control" 
                         id="topup_remarks" 
                         name="remarks" 
                         placeholder="e.g. Additional working capital top-up">
                </div>
              </div>
            </div>

            <!-- Right Column: Live Plan Preview & Sample Schedule -->
            <div class="col-lg-5">
              <div class="sticky-top" style="top: 20px;">
                <div class="card border border-success border-opacity-25 bg-success bg-opacity-10 shadow-none">
                  <div class="card-header py-3 px-3 bg-success bg-opacity-20 d-flex justify-content-between align-items-center border-bottom border-success border-opacity-20">
                    <span class="fw-bold text-success d-flex align-items-center gap-1">
                      <i class="ri-calculator-line fs-5"></i>
                      <span>Regenerated Plan Preview</span>
                    </span>
                    <span class="badge bg-success" id="previewBadge">Live Calculation</span>
                  </div>

                  <div class="card-body p-3">
                    <!-- Key Figures Grid -->
                    <div class="row g-2 text-center mb-3">
                      <div class="col-6">
                        <div class="border rounded p-2 bg-white">
                          <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">New Principal</small>
                          <h6 class="mb-0 fw-bold text-dark mt-1" id="previewNewPrincipal">₹0.00</h6>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="border rounded p-2 bg-white border-success">
                          <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Revised Next EMI</small>
                          <h5 class="mb-0 fw-bold text-success mt-1" id="previewNewEmi">₹0.00</h5>
                          <small class="text-muted d-block" id="previewEmiPeriodSub" style="font-size: 0.7rem;">per {{ strtolower($frequencyLabel) }}</small>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="border rounded p-2 bg-white">
                          <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">Next Due Date</small>
                          <h6 class="mb-0 fw-bold text-primary mt-1" id="previewNextDueDate">—</h6>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="border rounded p-2 bg-white">
                          <small class="text-muted d-block text-uppercase" style="font-size: 0.7rem;">New Total Tenure</small>
                          <h6 class="mb-0 fw-bold text-dark mt-1" id="previewNewTenure">—</h6>
                        </div>
                      </div>
                    </div>

                    <!-- Total Payable comparison -->
                    <div class="p-2 border rounded bg-white mb-3 text-start small">
                      <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Total Payable:</span>
                        <strong class="text-dark" id="previewTotalPayable">₹0.00</strong>
                      </div>
                      <div class="d-flex justify-content-between">
                        <span class="text-muted">Frequency:</span>
                        <strong class="text-primary text-uppercase" id="previewFrequencyLabel">{{ $frequencyLabel }}</strong>
                      </div>
                    </div>

                    <!-- Sample Regenerated Installments Preview -->
                    <div id="sampleScheduleContainer" style="display: none;">
                      <h6 class="fw-semibold text-dark mb-2" style="font-size: 0.82rem;">
                        <i class="ri-list-check-2 me-1 text-primary"></i>Upcoming Installments (First 5 Cycles)
                      </h6>
                      <div class="table-responsive bg-white rounded border">
                        <table class="table table-sm table-striped mb-0 text-center" style="font-size: 0.78rem;">
                          <thead class="table-light">
                            <tr>
                              <th>#</th>
                              <th>Due Date</th>
                              <th>Principal</th>
                              <th>Interest</th>
                              <th>EMI Amount</th>
                            </tr>
                          </thead>
                          <tbody id="sampleScheduleTableBody">
                            <!-- Populated dynamically via JS -->
                          </tbody>
                        </table>
                      </div>
                    </div>

                    <!-- Helper Notice -->
                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0 d-flex align-items-start gap-2" role="alert" style="font-size: 0.78rem;">
                      <i class="ri-shield-check-line fs-5 text-warning flex-shrink-0 mt-0.5"></i>
                      <div>
                        <strong>Integrity Guaranteed:</strong> All past completed EMI payments and collections remain permanently preserved. Only future uncollected installments are replaced with this plan. Push notifications will be dispatched to the client mobile app instantly.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="modal-footer bg-light px-4 py-3 border-top d-flex justify-content-between">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            Cancel
          </button>
          <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-1 shadow px-4" id="btnSubmitTopup">
            <i class="ri-check-line fs-5"></i>
            <span>Confirm & Disburse Top-Up</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const topupModalEl = document.getElementById('topupLoanModal');
  if (!topupModalEl) return;

  const topupAmountInput = document.getElementById('topup_amount');
  const interestRateInput = document.getElementById('topup_interest_rate');
  const feeInput = document.getElementById('topup_processing_fee');
  const netDisbursedInput = document.getElementById('topup_net_disbursed');

  const addTenureInput = document.getElementById('topup_additional_tenure');
  const setTenureInput = document.getElementById('topup_new_remaining_tenure');
  const emiDayHidden = document.getElementById('topup_emi_day');
  const inputMonthlyDay = document.getElementById('input_monthly_day');
  const selectWeeklyDay = document.getElementById('select_weekly_day');
  const firstDueDateInput = document.getElementById('topup_first_due_date');

  const tenureGroupAdd = document.getElementById('tenureInputGroupAdd');
  const tenureGroupSet = document.getElementById('tenureInputGroupSet');
  const tenureGroupKeep = document.getElementById('tenureInputGroupKeep');

  const containerMonthlyDay = document.getElementById('containerMonthlyEmiDay');
  const containerWeeklyDay = document.getElementById('containerWeeklyEmiDay');
  const colEmiDayWrapper = document.getElementById('colEmiDayWrapper');

  const previewNewPrincipal = document.getElementById('previewNewPrincipal');
  const previewNewEmi = document.getElementById('previewNewEmi');
  const previewNextDueDate = document.getElementById('previewNextDueDate');
  const previewNewTenure = document.getElementById('previewNewTenure');
  const previewTotalPayable = document.getElementById('previewTotalPayable');
  const previewFrequencyLabel = document.getElementById('previewFrequencyLabel');
  const previewEmiPeriodSub = document.getElementById('previewEmiPeriodSub');

  const sampleContainer = document.getElementById('sampleScheduleContainer');
  const sampleTableBody = document.getElementById('sampleScheduleTableBody');

  const form = document.getElementById('topupLoanForm');
  const submitBtn = document.getElementById('btnSubmitTopup');

  const loanId = '{{ $loanAccount->id }}';
  const remainingCountBase = {{ (int) $remainingCount }};
  const isKandhuvatti = {{ $isKandhuvatti ? 'true' : 'false' }};

  let debounceTimer = null;

  // Sync EMI day hidden input
  function syncEmiDay() {
    if (!emiDayHidden) return;
    const selectedFreq = getSelectedFrequency();
    if (selectedFreq === 'weekly' && selectWeeklyDay) {
      emiDayHidden.value = selectWeeklyDay.value;
    } else if (selectedFreq === 'monthly' && inputMonthlyDay) {
      emiDayHidden.value = inputMonthlyDay.value || 1;
    } else {
      emiDayHidden.value = 1;
    }
  }

  function getSelectedFrequency() {
    const radio = document.querySelector('input[name="frequency"]:checked');
    return radio ? radio.value : 'monthly';
  }

  function getSelectedTenureMode() {
    const radio = document.querySelector('input[name="tenure_mode"]:checked');
    return radio ? radio.value : 'add';
  }

  // Handle Frequency Change
  document.querySelectorAll('.topup-freq-radio').forEach(r => {
    r.addEventListener('change', function () {
      const freq = this.value;
      let unitText = 'Months';
      if (freq === 'daily') unitText = 'Days';
      if (freq === 'weekly') unitText = 'Weeks';

      document.querySelectorAll('.unit-label-text').forEach(el => el.textContent = unitText);
      document.querySelectorAll('.unit-label-text-lower').forEach(el => el.textContent = unitText.toLowerCase());

      if (freq === 'daily') {
        if (colEmiDayWrapper) colEmiDayWrapper.style.display = 'none';
      } else if (freq === 'weekly') {
        if (colEmiDayWrapper) colEmiDayWrapper.style.display = 'block';
        if (containerWeeklyDay) containerWeeklyDay.style.display = 'block';
        if (containerMonthlyDay) containerMonthlyDay.style.display = 'none';
      } else {
        if (colEmiDayWrapper) colEmiDayWrapper.style.display = 'block';
        if (containerWeeklyDay) containerWeeklyDay.style.display = 'none';
        if (containerMonthlyDay) containerMonthlyDay.style.display = 'block';
      }

      syncEmiDay();
      triggerPreview();
    });
  });

  const lockedFreqRadio = document.querySelector('.topup-freq-radio:checked');
  if (lockedFreqRadio) lockedFreqRadio.dispatchEvent(new Event('change'));

  // Handle Tenure Mode Change (Add, Set, Keep)
  document.querySelectorAll('.topup-tenure-mode').forEach(r => {
    r.addEventListener('change', function () {
      const mode = this.value;
      if (tenureGroupAdd) tenureGroupAdd.style.display = mode === 'add' ? 'block' : 'none';
      if (tenureGroupSet) tenureGroupSet.style.display = mode === 'set' ? 'block' : 'none';
      if (tenureGroupKeep) tenureGroupKeep.style.display = mode === 'keep' ? 'block' : 'none';

      triggerPreview();
    });
  });

  if (selectWeeklyDay) selectWeeklyDay.addEventListener('change', () => { syncEmiDay(); triggerPreview(); });
  if (inputMonthlyDay) inputMonthlyDay.addEventListener('input', () => { syncEmiDay(); triggerPreview(); });
  if (firstDueDateInput) firstDueDateInput.addEventListener('change', triggerPreview);

  function updateNetDisbursed() {
    const amt = parseFloat(topupAmountInput.value) || 0;
    const fee = parseFloat(feeInput.value) || 0;
    const net = Math.max(0, amt - fee);
    netDisbursedInput.value = '₹' + net.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function triggerPreview() {
    updateNetDisbursed();
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fetchPreview, 300);
  }

  function fetchPreview() {
    const amt = parseFloat(topupAmountInput.value) || 0;
    if (amt <= 0) {
      if (sampleContainer) sampleContainer.style.display = 'none';
      return;
    }

    const freq = getSelectedFrequency();
    const mode = getSelectedTenureMode();
    const addTenure = parseInt(addTenureInput ? addTenureInput.value : 0) || 0;
    const setTenure = parseInt(setTenureInput ? setTenureInput.value : remainingCountBase) || remainingCountBase;
    const rate = parseFloat(interestRateInput.value) || 0;
    const emiDayVal = emiDayHidden ? emiDayHidden.value : 1;
    const firstDueDateVal = firstDueDateInput ? firstDueDateInput.value : '';

    const dataBaseUrl = document.documentElement.getAttribute('data-base-url');
    const baseUrl = window.baseUrl || (dataBaseUrl ? dataBaseUrl + '/' : '/');
    const params = new URLSearchParams({
      amount: amt,
      tenure_mode: mode,
      additional_tenure: addTenure,
      new_remaining_tenure: setTenure,
      frequency: freq,
      emi_day: emiDayVal,
      first_due_date: firstDueDateVal,
      interest_rate: rate
    });

    const url = `${baseUrl}loan-accounts/${loanId}/topup-info?${params.toString()}`;

    fetch(url, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.preview) {
        const p = res.data.preview;
        previewNewPrincipal.textContent = '₹' + Number(p.new_principal_balance).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        previewNewEmi.textContent = '₹' + Number(p.new_emi_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        previewNextDueDate.textContent = p.first_new_due_date || '—';
        previewNewTenure.textContent = p.new_total_tenure ? `${p.new_total_tenure} ${p.unit_label}` : 'Open Cycle';
        previewTotalPayable.textContent = '₹' + Number(p.total_new_payable).toLocaleString('en-IN', { minimumFractionDigits: 2 });
        previewFrequencyLabel.textContent = p.frequency_label;
        previewEmiPeriodSub.textContent = `per ${p.frequency_label.toLowerCase()}`;

        // Populate sample installments table
        if (p.sample_schedule && p.sample_schedule.length > 0) {
          let rowsHtml = '';
          p.sample_schedule.forEach(s => {
            rowsHtml += `<tr>
              <td class="fw-bold">${s.instalment_number}</td>
              <td>${s.due_date}</td>
              <td>₹${Number(s.principal).toLocaleString('en-IN')}</td>
              <td>₹${Number(s.interest).toLocaleString('en-IN')}</td>
              <td class="fw-bold text-success">₹${Number(s.emi_amount).toLocaleString('en-IN')}</td>
            </tr>`;
          });
          sampleTableBody.innerHTML = rowsHtml;
          sampleContainer.style.display = 'block';
        } else {
          sampleContainer.style.display = 'none';
        }
      }
    })
    .catch(err => {
      console.warn('Topup preview error:', err);
    });
  }

  [topupAmountInput, addTenureInput, setTenureInput, interestRateInput, feeInput].forEach(inp => {
    if (inp) {
      inp.addEventListener('input', triggerPreview);
    }
  });

  // Handle Form Submission
  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const amt = parseFloat(topupAmountInput.value) || 0;
    if (amt <= 0) {
      Swal.fire({
        icon: 'warning',
        title: 'Invalid Amount',
        text: 'Please enter a valid top-up amount greater than zero.'
      });
      return;
    }

    syncEmiDay();
    const formattedAmt = '₹' + amt.toLocaleString('en-IN', { minimumFractionDigits: 2 });
    const selectedFreq = getSelectedFrequency();

    Swal.fire({
      title: 'Confirm Loan Top-Up',
      html: `<div class="text-start">
        <p>Are you sure you want to add a top-up of <strong>${formattedAmt}</strong> to this loan account?</p>
        <ul class="text-muted small mb-0">
          <li>Future schedule will be regenerated based on <strong>${selectedFreq.toUpperCase()}</strong> frequency.</li>
          <li>Past completed payments remain completely intact with audit trails.</li>
          <li>Customer mobile app push notification and revised schedule will be updated automatically.</li>
        </ul>
      </div>`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, Confirm & Disburse',
      cancelButtonText: 'Cancel',
      customClass: {
        confirmButton: 'btn btn-success me-2',
        cancelButton: 'btn btn-outline-secondary'
      },
      buttonsStyling: false
    }).then(result => {
      if (result.isConfirmed) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Processing...';

        const formData = new FormData(form);
        const dataObj = {};
        formData.forEach((value, key) => { dataObj[key] = value; });

        fetch(form.action, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify(dataObj)
        })
        .then(async r => {
          const res = await r.json();
          if (!r.ok) {
            throw new Error(res.message || 'Top-up processing failed.');
          }
          return res;
        })
        .then(res => {
          const topupData = res.data;
          const notif = res.notification_data || {};

          let whatsappBtnHtml = '';
          if (notif.whatsapp_link) {
            whatsappBtnHtml = `
              <div class="mt-3">
                <a href="${notif.whatsapp_link}" target="_blank" class="btn btn-sm btn-success d-inline-flex align-items-center gap-1">
                  <i class="ri-whatsapp-line fs-5"></i>
                  <span>Send WhatsApp Notification to Client</span>
                </a>
              </div>
            `;
          }

          Swal.fire({
            icon: 'success',
            title: 'Top-Up Successful!',
            html: `<div class="text-start">
              <p class="mb-2">${res.message}</p>
              <div class="card bg-light p-2 mb-2 text-start small">
                <div><strong>Revised Next EMI:</strong> ₹${Number(topupData.new_emi_amount).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</div>
                <div><strong>Frequency:</strong> ${topupData.frequency ? topupData.frequency.toUpperCase() : 'MONTHLY'}</div>
                <div><strong>Next Due Date:</strong> ${topupData.next_due_date || '—'}</div>
                <div><strong>Outstanding Balance:</strong> ₹${Number(topupData.new_outstanding).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</div>
              </div>
              <p class="small text-muted mb-0">Customer push notification has been sent to their registered mobile app.</p>
              ${whatsappBtnHtml}
            </div>`,
            confirmButtonText: 'View Updated Schedule',
            customClass: {
              confirmButton: 'btn btn-primary'
            },
            buttonsStyling: false
          }).then(() => {
            window.location.reload();
          });
        })
        .catch(err => {
          Swal.fire({
            icon: 'error',
            title: 'Top-Up Failed',
            text: err.message || 'An error occurred while processing the top-up loan.',
            customClass: {
              confirmButton: 'btn btn-primary'
            }
          });
        })
        .finally(() => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="ri-check-line fs-5"></i> <span>Confirm & Disburse Top-Up</span>';
        });
      }
    });
  });

  @if(session('open_topup_modal'))
  bootstrap.Modal.getOrCreateInstance(topupModalEl).show();
  @endif
});
</script>
