<form id="{{ $formId }}" class="row g-5 form-apply-fd">
  @csrf

  @if(!empty($preselectClient))
    <input type="hidden" name="client_id" value="{{ $preselectClient->id }}">
  @else
    <div class="col-12">
      <label class="form-label" for="{{ $formId }}_client_id">Select Verified Client <span class="text-danger">*</span></label>
      <select id="{{ $formId }}_client_id" name="client_id" class="form-select select2 fd-client-select" required data-placeholder="Select Verified Client">
        <option></option>
        @foreach($verifiedClients as $fdClient)
          <option value="{{ $fdClient->id }}">
            {{ $fdClient->client_name }} ({{ $fdClient->client_phone }})
          </option>
        @endforeach
      </select>
    </div>
  @endif

  <div class="col-12">
    <label class="form-label" for="{{ $formId }}_scheme_id">Select FD Scheme <span class="text-danger">*</span></label>
    <select id="{{ $formId }}_scheme_id" name="scheme_id" class="form-select select2 fd-scheme-select" required data-placeholder="Select FD Scheme">
      <option></option>
      @foreach($fdSchemes as $scheme)
        <option
          value="{{ $scheme->id }}"
          data-min-amount="{{ $scheme->min_deposit_amount }}"
          data-max-amount="{{ $scheme->max_deposit_amount }}"
          data-min-tenure="{{ $scheme->min_tenure }}"
          data-max-tenure="{{ $scheme->max_tenure }}"
          data-tenure-type="{{ $scheme->tenure_type }}"
          data-rate="{{ $scheme->interest_rate }}"
          data-payout="{{ $scheme->default_payout_option }}"
          data-deposit-type="{{ $scheme->deposit_type_label }}"
          data-frequency="{{ $scheme->interest_frequency }}"
          data-frequency-label="{{ $scheme->interest_frequency_label }}"
        >
          {{ $scheme->name }} ({{ $scheme->scheme_code }}) · {{ number_format((float) $scheme->interest_rate, 2) }}% · {{ $scheme->interest_frequency_label }}
        </option>
      @endforeach
    </select>
  </div>

  <div class="col-12 fd-scheme-info">
    <div class="alert alert-info border d-flex flex-wrap align-items-center gap-3 mb-0 py-3 px-4">
      <div class="d-flex align-items-center gap-2">
        <i class="ri-percent-line ri-20px"></i>
        <span>Interest: <strong class="fd-info-rate">—</strong> p.a.</span>
      </div>
      <span class="text-muted">·</span>
      <div>Type: <strong class="fd-info-type">—</strong></div>
      <span class="text-muted">·</span>
      <div>
        Interest Frequency:
        <span class="badge bg-label-primary fd-info-frequency">Select a scheme</span>
      </div>
    </div>
  </div>

  <div class="col-md-8">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <label class="form-label mb-0 fw-bold">Deposit Amount (₹) <span class="text-danger">*</span></label>
      <small class="text-info fd-amount-range-info">Select a scheme first</small>
    </div>
    <div class="input-group mb-3">
      <span class="input-group-text bg-light fw-bold">₹</span>
      <input type="number" class="form-control form-control-lg fd-amount-input" name="deposit_amount" placeholder="Enter or adjust amount below" value="" min="0" required step="1">
    </div>
    <label class="form-label small text-muted mb-2">Adjust Amount with Slider</label>
    <input type="range" class="form-range fd-amount-slider" min="0" max="1000000" step="1000" style="height: 8px;">
    <div class="d-flex justify-content-between mt-2">
      <small class="text-muted fw-semibold fd-min-amount-label">Min: ₹-</small>
      <small class="text-muted fw-semibold fd-max-amount-label">Max: ₹-</small>
    </div>
  </div>

  <div class="col-md-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <label class="form-label mb-0">Tenure <span class="text-danger">*</span></label>
      <span class="badge bg-label-primary fd-display-tenure">-</span>
    </div>
    <div class="input-group mb-2">
      <input type="number" class="form-control form-control-sm fd-tenure-input" name="tenure" value="" min="1" step="1" required>
      <span class="input-group-text small fd-tenure-unit">months</span>
    </div>
    <input type="range" class="form-range fd-tenure-slider" min="1" max="60" step="1">
    <div class="d-flex justify-content-between">
      <small class="text-muted fd-min-tenure-label">-</small>
      <small class="text-muted fd-max-tenure-label">-</small>
    </div>
  </div>

  <div class="col-md-6">
    <label class="form-label fw-bold" for="{{ $formId }}_payout_option">Payout Option <span class="text-danger">*</span></label>
    <select id="{{ $formId }}_payout_option" name="payout_option" class="form-select fd-payout-select" required>
      @foreach($payoutOptions as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label fw-bold">Nominee Name</label>
    <input type="text" class="form-control" name="nominee_name" maxlength="255" placeholder="Optional">
  </div>

  <div class="col-md-6">
    <label class="form-label fw-bold">Nominee Relation</label>
    <input type="text" class="form-control" name="nominee_relation" maxlength="100" placeholder="Optional">
  </div>

  <div class="col-12">
    <div class="bg-light p-4 rounded-3">
      <div class="row align-items-end g-3">
        <div class="col-md-4">
          <label class="form-label fw-bold">Application Date <span class="text-danger">*</span></label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-calendar-event-line"></i></span>
            <input type="text" name="applied_at" class="form-control flatpickr-date fd-applied-at" required placeholder="DD-MM-YYYY" value="{{ date('d-m-Y') }}">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">Deposit Date <span class="text-danger">*</span></label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-calendar-line"></i></span>
            <input type="text" name="deposit_date" class="form-control flatpickr-date fd-deposit-date" required placeholder="DD-MM-YYYY" value="{{ date('d-m-Y') }}">
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-calendar-check-line"></i></span>
            <input type="text" name="start_date" class="form-control flatpickr-date fd-start-date" required placeholder="DD-MM-YYYY" value="{{ date('d-m-Y') }}">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card bg-label-dark border-0 shadow-none">
      <div class="card-body p-4">
        <div class="row text-center g-4">
          <div class="col-md-3 col-6 border-end">
            <h4 class="mb-1 fd-preview-interest">₹0.00</h4>
            <small class="text-uppercase fw-medium">Total Interest</small>
          </div>
          <div class="col-md-3 col-6 border-end">
            <h4 class="mb-1 fd-preview-frequency">—</h4>
            <small class="text-uppercase fw-medium">Interest Frequency</small>
          </div>
          <div class="col-md-3 col-6 border-end">
            <h4 class="mb-1 fd-preview-maturity">₹0.00</h4>
            <small class="text-uppercase fw-medium">Maturity Amount</small>
          </div>
          <div class="col-md-3 col-6">
            <h4 class="mb-1 fd-preview-date">—</h4>
            <small class="text-uppercase fw-medium">Maturity Date</small>
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

<style>
  .bg-label-dark {
    background: linear-gradient(135deg, #fdfdfd 0%, #f1f4fb 100%);
    color: #444 !important;
    border: 1px solid rgba(0,0,0,0.05) !important;
  }
  #modalApplyFd .modal-body,
  #modalApplyFdGeneric .modal-body,
  #modalApplyFd .modal-content,
  #modalApplyFdGeneric .modal-content {
    overflow: visible !important;
  }
</style>
<script>
  document.addEventListener('shown.bs.modal', function (e) {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
      return;
    }
    var $modal = jQuery(e.target);
    var $payout = $modal.find('.fd-payout-select');
    if (!$payout.length || $payout.hasClass('select2-hidden-accessible')) {
      return;
    }
    $payout.select2({
      dropdownParent: $modal,
      minimumResultsForSearch: Infinity,
      width: '100%'
    });
  });
</script>
