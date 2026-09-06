@extends('layouts/layoutMaster')

@section('title', 'Loan Configuration')

@section('page-script')
@vite(['resources/assets/custom-js/loan-configuration.js'])
@endsection

@section('content')
<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Loan Configuration Cards -->
<div class="row g-4">
  <!-- Foreclosure Configuration Card -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
        <div>
          <h5 class="mb-0">Foreclosure Configuration 
            <span class="badge {{ ($foreclosureConfig->is_active ?? true) ? 'bg-label-success' : 'bg-label-secondary' }}" id="foreclosureStatusBadge">
              {{ ($foreclosureConfig->is_active ?? true) ? 'Enabled' : 'Disabled' }}
            </span>
          </h5>
          <small class="text-muted">Configure foreclosure eligibility and charges</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="foreclosureStatus" {{ ($foreclosureConfig->is_active ?? true) ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          <label class="form-check-label" for="foreclosureStatus"></label>
        </div>
      </div>
      <div class="card-body pt-4">
        <form action="{{ route('loan-configuration.save-foreclosure') }}" method="POST" id="foreclosureConfigForm">
          @csrf
          <input type="hidden" name="is_active" id="foreclosureEnabled" value="{{ ($foreclosureConfig->is_active ?? true) ? '1' : '0' }}" data-has-config="{{ $foreclosureConfig && $foreclosureConfig->id ? 'true' : 'false' }}">

          <div class="row mb-3">
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="eligibilityMonths">ELIGIBILITY (MONTHS)</label>
              <input type="number" id="eligibilityMonths" name="eligibility_months" class="form-control"
                placeholder="Months" min="0" value="{{ $foreclosureConfig->eligibility_months ?? '' }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="eligibilityWeeks">ELIGIBILITY (WEEKS)</label>
              <input type="number" id="eligibilityWeeks" name="eligibility_weeks" class="form-control"
                placeholder="Weeks" min="0" value="{{ $foreclosureConfig->eligibility_weeks ?? '' }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="eligibilityDays">ELIGIBILITY (DAYS)</label>
              <input type="number" id="eligibilityDays" name="eligibility_days" class="form-control"
                placeholder="Days" min="0" value="{{ $foreclosureConfig->eligibility_days ?? '' }}" />
            </div>
          </div>

          <div class="row mb-4">
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargesPercentage">MONTHLY CHARGE (%)</label>
              <input type="number" id="chargesPercentage" name="charges_percentage" class="form-control"
                step="0.01" min="0" max="100" value="{{ $foreclosureConfig->charges_percentage ?? 0 }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargesPercentageWeekly">WEEKLY CHARGE (%)</label>
              <input type="number" id="chargesPercentageWeekly" name="charges_percentage_weekly" class="form-control"
                step="0.01" min="0" max="100" value="{{ $foreclosureConfig->charges_percentage_weekly ?? 0 }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargesPercentageDaily">DAILY CHARGE (%)</label>
              <input type="number" id="chargesPercentageDaily" name="charges_percentage_daily" class="form-control"
                step="0.01" min="0" max="100" value="{{ $foreclosureConfig->charges_percentage_daily ?? 0 }}" />
            </div>
          </div>

          <div class="row">
            <div class="col-sm-12">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-save-line me-1"></i> Save Configuration
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Prepayment Configuration Card -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
        <div>
          <h5 class="mb-0">Prepayment Configuration 
            <span class="badge {{ ($prepaymentConfig->is_active ?? false) ? 'bg-label-success' : 'bg-label-secondary' }}" id="prepaymentStatusBadge">
              {{ ($prepaymentConfig->is_active ?? false) ? 'Enabled' : 'Disabled' }}
            </span>
          </h5>
          <small class="text-muted">Configure prepayment eligibility and charges</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="prepaymentStatus" {{ ($prepaymentConfig->is_active ?? false) ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          <label class="form-check-label" for="prepaymentStatus"></label>
        </div>
      </div>
      <div class="card-body pt-4">
        <form action="{{ route('loan-configuration.save-prepayment') }}" method="POST" id="prepaymentConfigForm">
          @csrf
          <input type="hidden" name="is_active" id="prepaymentEnabled" value="{{ ($prepaymentConfig->is_active ?? false) ? '1' : '0' }}" data-has-config="{{ $prepaymentConfig && $prepaymentConfig->id ? 'true' : 'false' }}">

          <div class="row mb-3">
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="prepaymentEligibilityMonths">ELIGIBILITY (MONTHS)</label>
              <input type="number" id="prepaymentEligibilityMonths" name="eligibility_months" class="form-control"
                placeholder="Months" min="0" value="{{ $prepaymentConfig->eligibility_months ?? '' }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="prepaymentEligibilityWeeks">ELIGIBILITY (WEEKS)</label>
              <input type="number" id="prepaymentEligibilityWeeks" name="eligibility_weeks" class="form-control"
                placeholder="Weeks" min="0" value="{{ $prepaymentConfig->eligibility_weeks ?? '' }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="prepaymentEligibilityDays">ELIGIBILITY (DAYS)</label>
              <input type="number" id="prepaymentEligibilityDays" name="eligibility_days" class="form-control"
                placeholder="Days" min="0" value="{{ $prepaymentConfig->eligibility_days ?? '' }}" />
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargeValue">MONTHLY CHARGE</label>
              <input type="number" id="chargeValue" name="charge_value" class="form-control"
                step="0.01" min="0" placeholder="0.00" value="{{ $prepaymentConfig->charge_value ?? 0 }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargeValueWeekly">WEEKLY CHARGE</label>
              <input type="number" id="chargeValueWeekly" name="charge_value_weekly" class="form-control"
                step="0.01" min="0" placeholder="0.00" value="{{ $prepaymentConfig->charge_value_weekly ?? 0 }}" />
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="chargeValueDaily">DAILY CHARGE</label>
              <input type="number" id="chargeValueDaily" name="charge_value_daily" class="form-control"
                step="0.01" min="0" placeholder="0.00" value="{{ $prepaymentConfig->charge_value_daily ?? 0 }}" />
            </div>
          </div>

          <div class="row mb-4">
            <label class="col-sm-12 col-form-label text-uppercase text-muted small fw-bold" for="chargeType">CHARGE TYPE</label>
            <div class="col-sm-12">
              <div class="input-group">
                <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="chargeTypeDropdown">
                  <span id="chargeTypeLabel">{{ ($prepaymentConfig->charge_type ?? 'percentage') == 'percentage' ? 'Percentage (%)' : 'Flat Amount (₹)' }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end w-100">
                  <li><a class="dropdown-item" href="#" data-value="percentage" data-label="Percentage (%)">Percentage (%)</a></li>
                  <li><a class="dropdown-item" href="#" data-value="flat" data-label="Flat Amount (₹)">Flat Amount (₹)</a></li>
                </ul>
                <input type="hidden" id="chargeType" name="charge_type" value="{{ $prepaymentConfig->charge_type ?? 'percentage' }}">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-sm-12">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-save-line me-1"></i> Save Configuration
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Partial Payment Configuration Card -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
        <div>
          <h5 class="mb-0">Partial Payment Configuration 
            <span class="badge {{ ($partialPaymentConfig->is_active ?? false) ? 'bg-label-success' : 'bg-label-secondary' }}" id="partialPaymentStatusBadge">
              {{ ($partialPaymentConfig->is_active ?? false) ? 'Enabled' : 'Disabled' }}
            </span>
          </h5>
          <small class="text-muted">Configure partial payment rules and penalties</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="partialPaymentStatus" {{ ($partialPaymentConfig->is_active ?? false) ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          <label class="form-check-label" for="partialPaymentStatus"></label>
        </div>
      </div>
      <div class="card-body pt-4">
        <form action="{{ route('loan-configuration.save-partial-payment') }}" method="POST" id="partialPaymentConfigForm">
          @csrf
          <input type="hidden" name="is_active" id="partialPaymentEnabled" value="{{ ($partialPaymentConfig->is_active ?? false) ? '1' : '0' }}" data-has-config="{{ $partialPaymentConfig && $partialPaymentConfig->id ? 'true' : 'false' }}">

          <div class="row mb-4">
            <label class="col-sm-12 col-form-label" for="minimumPartialPercentage">MINIMUM PARTIAL AMOUNT (%)</label>
            <div class="col-sm-12">
              <input type="number" id="minimumPartialPercentage" name="minimum_partial_percentage" class="form-control"
                step="0.01" min="0" max="100" placeholder="10"
                value="{{ old('minimum_partial_percentage', $partialPaymentConfig->minimum_partial_percentage ?? 10) }}" />
              <small class="text-muted">Minimum percentage of EMI amount required for partial payment eligibility</small>
            </div>
          </div>

          <div class="row mb-4">
            <label class="col-sm-12 col-form-label" for="partialPaymentTiming">PARTIAL PAYMENT TIMING</label>
            <div class="col-sm-12">
              <select id="partialPaymentTiming" name="partial_payment_timing" class="form-select">
                <option value="anytime" {{ ($partialPaymentConfig->partial_payment_timing ?? 'anytime') == 'anytime' ? 'selected' : '' }}>Anytime</option>
                <option value="before_due" {{ ($partialPaymentConfig->partial_payment_timing ?? 'anytime') == 'before_due' ? 'selected' : '' }}>Before Due Date</option>
                <option value="after_due" {{ ($partialPaymentConfig->partial_payment_timing ?? 'anytime') == 'after_due' ? 'selected' : '' }}>After Due Date</option>
              </select>
              <small class="text-muted">When customers can make partial payments</small>
            </div>
          </div>

          <div class="row mb-4">
            <label class="col-sm-12 col-form-label" for="penaltyCalculationMethod">PENALTY CALCULATION</label>
            <div class="col-sm-12">
              <select id="penaltyCalculationMethod" name="penalty_calculation_method" class="form-select">
                <option value="emi_amount" {{ ($partialPaymentConfig->penalty_calculation_method ?? 'emi_amount') == 'emi_amount' ? 'selected' : '' }}>Based on Original EMI Amount</option>
                <option value="emi_plus_partial_remaining" {{ ($partialPaymentConfig->penalty_calculation_method ?? 'emi_amount') == 'emi_plus_partial_remaining' ? 'selected' : '' }}>Based on EMI Amount + Outstanding Balance</option>
              </select>
              <small class="text-muted">Method for calculating late payment penalties on partial payments</small>
            </div>
          </div>

          <div class="row">
            <div class="col-sm-12">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-save-line me-1"></i> Save Configuration
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Penalty Configuration Card -->
  <div class="col-12 col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
        <div>
          <h5 class="mb-0">Penalty Configuration 
            <span class="badge {{ ($penaltyConfig->is_active ?? false) ? 'bg-label-success' : 'bg-label-secondary' }}" id="penaltyStatusBadge">
              {{ ($penaltyConfig->is_active ?? false) ? 'Enabled' : 'Disabled' }}
            </span>
          </h5>
          <small class="text-muted">Configure default penalties and grace periods globally</small>
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="penaltyStatus" {{ ($penaltyConfig->is_active ?? false) ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
          <label class="form-check-label" for="penaltyStatus"></label>
        </div>
      </div>
      <div class="card-body pt-4">
        <form action="{{ route('loan-configuration.save-penalty') }}" method="POST" id="penaltyConfigForm">
          @csrf
          <input type="hidden" name="is_active" id="penaltyEnabled" value="{{ ($penaltyConfig->is_active ?? false) ? '1' : '0' }}" data-has-config="{{ $penaltyConfig && $penaltyConfig->id ? 'true' : 'false' }}">

          <div class="row mb-4">
            <div class="col-md-6 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="penaltyChargeValue">DEFAULT PENALTY AMOUNT (₹)</label>
              <input type="number" id="penaltyChargeValue" name="charge_value" class="form-control"
                step="0.01" min="0" placeholder="0.00" value="{{ $penaltyConfig->charge_value ?? 0 }}" />
              <small class="text-muted">Fixed amount applied on overdue EMIs</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label text-uppercase text-muted small fw-bold" for="penaltyEligibilityDays">DEFAULT GRACE PERIOD (DAYS)</label>
              <input type="number" id="penaltyEligibilityDays" name="eligibility_days" class="form-control"
                min="0" placeholder="0" value="{{ $penaltyConfig->eligibility_days ?? 0 }}" />
              <small class="text-muted">Days before penalties start applying</small>
            </div>
          </div>

          <div class="row">
            <div class="col-sm-12">
              <button type="submit" class="btn btn-primary w-100">
                <i class="ri-save-line me-1"></i> Save Configuration
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
