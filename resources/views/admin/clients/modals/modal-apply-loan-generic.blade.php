<div class="modal fade" id="modalApplyLoanGeneric" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body p-0">
        <!-- Header with Glassmorphism -->
        <div class="p-6 text-center bg-primary position-relative overflow-hidden" style="border-radius: 0.5rem 0.5rem 0 0;">
          <div class="position-absolute w-100 h-100 top-0 start-0" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); z-index: 1;"></div>
          <div class="position-relative" style="z-index: 2;">
            <h3 class="text-white mb-2">Apply for Quick Loan</h3>
            <p class="text-white opacity-75 mb-0">Select a product and configure loan terms</p>
          </div>
        </div>

        <div class="p-6">
          <form id="formApplyLoanGeneric" class="row g-5">
            @csrf
            
            <!-- Loan Mode Toggle -->
            <div class="col-12" id="loan_mode_container">
              <div class="bg-label-primary p-4 rounded-3 d-flex justify-content-between align-items-center border border-primary border-opacity-10">
                <div class="d-flex align-items-center">
                    <div class="avatar avatar-md bg-white text-primary rounded-3 me-3 shadow-sm">
                      <i class="ri-equalizer-line fs-4"></i>
                    </div>
                    <div>
                        <h6 class="mb-0 fw-bold">Loan Calculation Mode</h6>
                        <small class="text-muted" id="mode_description">Standard EMI (Principal + Interest)</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3">
                  <span class="fw-bold small text-primary" id="emi_label_text">EMI</span>
                  <div class="form-check form-switch form-check-lg mb-0">
                    <input class="form-check-input loan-mode-toggle" type="checkbox" id="loan_mode_toggle" name="loan_mode" value="interest_only" style="width: 3.5em; height: 1.75em;">
                  </div>
                  <span class="fw-bold small text-muted" id="kandhuvatti_label_text">Open Loan</span>
                </div>
              </div>
            </div>
            
            <!-- Client Selection -->
            <div class="col-12">
              <label class="form-label" for="apply_client_id">Select Verified Client <span class="text-danger">*</span></label>
              <select id="apply_client_id" name="client_id" class="form-select select2" required data-placeholder="Select Verified Client">
                <option></option>
                @foreach($verifiedClients as $client)
                  <option value="{{ $client->id }}">
                    {{ $client->client_name }} ({{ $client->client_phone }})
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Loan Product Selection -->
            <div class="col-12">
              <label class="form-label" for="loan_product">Select Loan Product <span class="text-danger">*</span></label>
              <select id="loan_product" name="loan_code" class="form-select select2" required data-placeholder="Select Loan Product">
                <option></option>
                @foreach($loanProducts as $product)
                  @php
                    $displayInterestType = 'Flat';
                    if (($product->interest_type ?? '') === 'reducing' || ($product->interest_type ?? '') === 'declining_balance') {
                        $displayInterestType = 'Reducing Balance';
                    } elseif (($product->interest_type ?? '') === 'fixed') {
                        $displayInterestType = 'Fixed';
                    } elseif (($product->interest_type ?? '') === 'flat') {
                        $displayInterestType = 'Flat';
                    } else {
                        $displayInterestType = ucfirst($product->interest_type ?? 'Flat');
                    }
                  @endphp
                  <option value="{{ $product->loan_code }}" 
                          data-min-amount="{{ $product->loan_amount_min }}" 
                          data-max-amount="{{ $product->loan_amount_max }}"
                          data-min-tenure="{{ $product->min_tenture }}"
                          data-max-tenure="{{ $product->max_tenture }}"
                          data-rate="{{ $product->interest_rate }}"
                          data-term-unit="{{ $product->term_unit }}"
                          data-interest-type="{{ $product->interest_type ?? 'flat' }}">
                    {{ $product->loan_name }} ({{ $product->interest_rate }}% - {{ $displayInterestType }})
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Amount Configuration -->
            <div class="col-md-8">
               <div class="d-flex justify-content-between align-items-center mb-3">
                 <label class="form-label mb-0 fw-bold" for="loan_amount">Loan Amount (₹) <span class="text-danger">*</span></label>
                 <small class="text-info" id="amount_range_info">Select a product first</small>
               </div>
               <div class="input-group mb-3">
                 <span class="input-group-text bg-light fw-bold">₹</span>
                 <input type="number" class="form-control form-control-lg" id="loan_amount_input" name="loan_amount" placeholder="Enter or adjust amount below" value="" min="0" required step="1">
               </div>
               <label class="form-label small text-muted mb-2">Adjust Amount with Slider</label>
               <input type="range" class="form-range" id="loan_amount_slider" min="0" max="1000000" step="1000" style="height: 8px;">
               <div class="d-flex justify-content-between mt-2">
                 <small class="text-muted fw-semibold" id="min_amount_label">Min: ₹-</small>
                 <small class="text-muted fw-semibold" id="max_amount_label">Max: ₹-</small>
               </div>

            </div>

            <!-- Tenure & Frequency Configuration -->
            <div class="col-md-4">
              <label class="form-label" for="repayment_frequency">Repayment Frequency <span class="text-danger">*</span></label>
              <select class="form-select select2" id="repayment_frequency" name="repayment_frequency" required data-placeholder="Select Frequency">
                <option value="monthly">Monthly</option>
                <option value="weekly">Weekly</option>
                <option value="daily">Daily</option>
              </select>
            </div>

            <div class="col-md-4">
               <div class="d-flex justify-content-between align-items-center mb-2">
                 <label class="form-label mb-0" for="tenure">Tenure <span class="text-danger">*</span></label>
                 <span class="badge bg-label-primary" id="display_tenure">12 Months</span>
               </div>
               <div class="input-group mb-2">
                 <input type="number" class="form-control form-control-sm" id="tenure_input" name="tenure" value="12" min="1" step="1">
                 <span class="input-group-text small">months</span>
               </div>
               <input type="range" class="form-range" id="tenure_slider" min="1" max="60" step="1">
               <div class="d-flex justify-content-between">
                 <small class="text-muted" id="min_tenure_label">1 m</small>
                 <small class="text-muted" id="max_tenure_label">60 m</small>
               </div>
            </div>

            <!-- Repayment Flow Configuration -->
            <div class="col-md-4" id="emi_day_wrapper">
              <label class="form-label" for="emi_day" id="emi_day_label">EMI Repayment Day <span class="text-danger">*</span></label>
              <select class="form-select select2" id="emi_day" name="emi_day" required data-placeholder="Select Day">
                <option></option>
                @for ($i = 1; $i <= 28; $i++)
                  <option value="{{ $i }}">{{ $i }}</option>
                @endfor
              </select>
            </div>

            <!-- EMI Start Configuration -->
                   <div class="col-12 mt-4">
              <div class="bg-light p-4 rounded-3">
                <div class="row align-items-end g-3">
                  <div class="col-md-6">
                    <label class="form-label fw-bold" for="emi_start_date">EMI Start Date <span class="text-danger">*</span></label>
                    <div class="input-group input-group-merge">
                      <span class="input-group-text"><i class="ri-calendar-line"></i></span>
                      <input type="text" id="emi_start_date" name="emi_start_date" class="form-control flatpickr-date" required placeholder="DD-MM-YYYY" value="{{ date('d-m-Y', strtotime('+1 month')) }}">
                    </div>
                    <div id="emi_weekday_display" class="mt-1 small fw-medium text-primary">Weekday: {{ date('l', strtotime('+1 month')) }}</div>
                    <small class="text-muted">Select the exact date for the first EMI payment.</small>
                  </div>
                  <div class="col-md-6">
                    <div class="alert alert-info py-2 px-3 mb-0 border-0" style="font-size: 0.85rem;">
                      <i class="ri-information-line me-1"></i>
                      The first EMI will land exactly on this date.
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-4">
              <label class="form-label" for="payment_method">Payment Method <span class="text-danger">*</span></label>
              @php
                $defaultPaymentMethods = [
                  ['method' => 'manual_payment', 'name' => 'Manual Payment'],
                  // ['method' => 'autopay_enach', 'name' => 'E-NACH (Auto-debit)'],
                ];
                $paymentMethods = (collect($activePaymentMethods ?? [])->isEmpty() ? collect($defaultPaymentMethods) : collect($activePaymentMethods))
                    ->reject(fn($m) => (is_array($m) ? $m['method'] : $m->method) === 'autopay_enach');
              @endphp
              <select class="form-select select2" id="payment_method" name="payment_method" required data-placeholder="Select Method">
                <option></option>
                @foreach($paymentMethods as $method)
                  @php
                    $methodKey = is_array($method) ? $method['method'] : $method->method;
                    $methodLabel = is_array($method) ? $method['name'] : $method->name;
                  @endphp
                  <option value="{{ $methodKey === 'manual_payment' ? 'manual' : ($methodKey === 'autopay_enach' ? 'e-nach' : $methodKey) }}">
                    {{ $methodLabel }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="col-md-4" id="gateway_wrapper" style="display: none;">
              <label class="form-label" for="payment_gateway">Select Payment Gateway <span class="text-danger">*</span></label>
              <select id="payment_gateway" name="payment_gateway" class="form-select select2" data-placeholder="Select Gateway">
                <option></option>
                @forelse($activeGateways ?? [] as $gateway)
                  <option value="{{ $gateway->gateway === 'razor-pay' ? 'razorpay' : ($gateway->gateway === 'cash-free' ? 'cashfree' : ($gateway->gateway === 'pay-U' ? 'payu' : $gateway->gateway)) }}">
                    {{ $gateway->name }}
                  </option>
                @empty
                  <option value="" disabled>No active payment gateways configured</option>
                @endforelse
              </select>
            </div>

            <!-- Summary / EMI Preview Card -->
            <div class="col-12">
              <div class="card bg-label-dark border-0 shadow-none">
                <div class="card-body p-4">
                  <div class="row text-center g-4">
                    <div class="col-4 border-end">
                      <h4 class="mb-1" id="preview_emi">₹0.00</h4>
                      <small class="text-uppercase fw-medium preview-label">Monthly EMI</small>
                    </div>
                    <div class="col-4 border-end">
                      <h4 class="mb-1" id="preview_interest">₹0.00</h4>
                      <small class="text-uppercase fw-medium preview-label">Total Interest</small>
                    </div>
                    <div class="col-4">
                      <h4 class="mb-1" id="preview_total">₹0.00</h4>
                      <small class="text-uppercase fw-medium preview-label">Total Payable</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 text-center mt-6">
              <button type="submit" class="btn btn-primary me-3">Submit Application</button>
              <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .modal-body {
    overflow-y: visible !important;
  }
  .bg-label-dark {
    background: linear-gradient(135deg, #fdfdfd 0%, #f1f4fb 100%);
    color: #444 !important;
    border: 1px solid rgba(0,0,0,0.05) !important;
  }
  .form-range::-webkit-slider-thumb {
    background: #5a8dee;
    border: 3px solid #fff;
    box-shadow: 0 0.25rem 0.5rem rgba(90, 141, 238, 0.4);
  }
  .form-range::-moz-range-thumb {
    background: #5a8dee;
    border: 3px solid #fff;
    box-shadow: 0 0.25rem 0.5rem rgba(90, 141, 238, 0.4);
  }
  .preview-label {
    font-size: 0.7rem;
    letter-spacing: 0.5px;
    color: #888;
  }
</style>

