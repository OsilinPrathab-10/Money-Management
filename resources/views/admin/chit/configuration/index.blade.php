@extends('layouts/layoutMaster')

@section('title', 'Chit Configuration')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1">Chit configuration</h4>
    <p class="text-muted mb-0">Configure settings, referral bonuses, commission rates, and late penalties for Chit Schemes and Groups.</p>
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger alert-dismissible mb-4" role="alert">
    <ul class="mb-0">
      @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="row g-6">
  {{-- Commission Settings --}}
  <div class="col-md-6 col-12">
    <form action="{{ route('chit.configuration.update') }}" method="POST" class="h-100" id="commissionConfigForm">
      @csrf
      <div class="card border shadow-none h-100">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="card-title mb-0"><i class="ri-settings-4-line me-2 text-primary"></i>Referral & Commission Configuration</h5>
          <div class="d-flex align-items-center gap-3">
            <span class="badge {{ $referralEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="referralStatusBadge">
              {{ $referralEnabled ? 'ON' : 'OFF' }}
            </span>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="referralStatusSwitch" {{ $referralEnabled ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
            </div>
          </div>
        </div>
        <div class="card-body pt-6 d-flex flex-column justify-content-between">
          <input type="hidden" name="referral_enabled" id="referralEnabled" value="{{ $referralEnabled ? '1' : '0' }}">
          <div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Referral Commission Percentage (%)</label>
              <div class="input-group">
                <input type="number" step="0.01" min="0" max="100" name="referral_commission_percentage" class="form-control" value="{{ old('referral_commission_percentage', $referralPercentage) }}" required>
                <span class="input-group-text">%</span>
              </div>
              <small class="text-muted d-block mt-1">The percentage of the calculation base that the referring Agent will receive as a bonus.</small>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Calculation Base</label>
              <select name="referral_calculation_base" class="form-select" required>
                <option value="group_commission" {{ old('referral_calculation_base', $calculationBase) == 'group_commission' ? 'selected' : '' }}>Group Commission (Chit Value × Group Commission %)</option>
                <option value="chit_value" {{ old('referral_calculation_base', $calculationBase) == 'chit_value' ? 'selected' : '' }}>Total Chit Value</option>
              </select>
              <small class="text-muted d-block mt-1">Determine if the referral percentage is calculated based on the group's commission or the overall chit value.</small>
            </div>
          </div>

          <div class="pt-4">
            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Save Configuration</button>
          </div>
        </div>
      </div>
    </form>
  </div>

  {{-- Penalty Settings --}}
  <div class="col-md-6 col-12">
    <form action="{{ route('chit.configuration.update') }}" method="POST" class="h-100" id="penaltyConfigForm">
      @csrf
      <div class="card border shadow-none h-100">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
          <h5 class="card-title mb-0"><i class="ri-error-warning-line me-2 text-warning"></i>Late Penalty Configuration</h5>
          <div class="d-flex align-items-center gap-3">
            <span class="badge {{ $penaltyEnabled ? 'bg-label-success' : 'bg-label-secondary' }}" id="penaltyStatusBadge">
              {{ $penaltyEnabled ? 'ON' : 'OFF' }}
            </span>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="penaltyStatusSwitch" {{ $penaltyEnabled ? 'checked' : '' }} style="cursor:pointer;width:3rem;height:1.5rem;">
            </div>
          </div>
        </div>
        <div class="card-body pt-6 d-flex flex-column justify-content-between">
          <input type="hidden" name="penalty_enabled" id="penaltyEnabled" value="{{ $penaltyEnabled ? '1' : '0' }}">
          <div>
            <div class="mb-4">
              <label class="form-label fw-semibold">Penalty Calculation Type</label>
              <select name="penalty_type" class="form-select" required>
                <option value="fixed" {{ old('penalty_type', $penaltyType ?? 'fixed') == 'fixed' ? 'selected' : '' }}>Fixed Amount (₹)</option>
                <option value="percentage" {{ old('penalty_type', $penaltyType ?? 'fixed') == 'percentage' ? 'selected' : '' }}>Percentage (%)</option>
              </select>
              <small class="text-muted d-block mt-1">Determine if the penalty is a fixed charge or a percentage of the installment amount.</small>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Penalty Value</label>
              <input type="number" step="0.01" min="0" name="penalty_value" class="form-control" value="{{ old('penalty_value', $penaltyValue ?? '0.00') }}" required>
              <small class="text-muted d-block mt-1">The value of the penalty (e.g., ₹50.00 or 2.00%). Applied only when status is ON.</small>
            </div>

            <div class="mb-4">
              <label class="form-label fw-semibold">Grace Period (Days)</label>
              <input type="number" min="0" name="penalty_grace_days" class="form-control" value="{{ old('penalty_grace_days', $penaltyGraceDays ?? 0) }}" required>
              <small class="text-muted d-block mt-1">Number of days after the due date before penalty charges are applied.</small>
            </div>
          </div>

          <div class="pt-4">
            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Save Configuration</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="row g-6 mt-1">
  <div class="col-12">
    <form action="{{ route('chit.configuration.update') }}" method="POST" id="settlementFeesConfigForm">
      @csrf
      <div class="card border shadow-none">
        <div class="card-header border-bottom">
          <h5 class="card-title mb-0"><i class="ri-money-rupee-circle-line me-2 text-success"></i>Default Settlement Charges</h5>
        </div>
        <div class="card-body pt-6">
          <p class="text-muted small mb-4">Default processing fee and document charges applied at chit settlement release (same as loan disbursement charges). Can be adjusted per settlement.</p>
          <div class="row g-4">
            <div class="col-md-3">
              <label class="form-label fw-semibold">Processing Fee (₹)</label>
              <input type="number" step="0.01" min="0" name="settlement_processing_fee" class="form-control" value="{{ old('settlement_processing_fee', $settlementProcessingFee ?? '0') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Document Charges (₹)</label>
              <input type="number" step="0.01" min="0" name="settlement_document_charges" class="form-control" value="{{ old('settlement_document_charges', $settlementDocumentCharges ?? '0') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Other Charges (₹)</label>
              <input type="number" step="0.01" min="0" name="settlement_other_charges" class="form-control" value="{{ old('settlement_other_charges', $settlementOtherCharges ?? '0') }}" required>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Bank Transfer Charges (₹)</label>
              <input type="number" step="0.01" min="0" name="settlement_banking_charges" class="form-control" value="{{ old('settlement_banking_charges', $settlementBankingCharges ?? '0') }}" required>
              <small class="text-muted">Company bank debit only — not deducted from member payout.</small>
            </div>
          </div>
          <div class="pt-4">
            <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Save Settlement Defaults</button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const commissionForm = document.getElementById('commissionConfigForm');
    const penaltyForm = document.getElementById('penaltyConfigForm');

    function bindStatusSwitch(switchId, hiddenId, badgeId) {
      const statusSwitch = document.getElementById(switchId);
      const hiddenInput = document.getElementById(hiddenId);
      const badge = document.getElementById(badgeId);
      if (!statusSwitch || !hiddenInput || !badge) return;

      statusSwitch.addEventListener('change', function () {
        const isOn = this.checked;
        hiddenInput.value = isOn ? '1' : '0';
        badge.textContent = isOn ? 'ON' : 'OFF';
        badge.classList.toggle('bg-label-success', isOn);
        badge.classList.toggle('bg-label-secondary', !isOn);
      });
    }

    bindStatusSwitch('referralStatusSwitch', 'referralEnabled', 'referralStatusBadge');
    bindStatusSwitch('penaltyStatusSwitch', 'penaltyEnabled', 'penaltyStatusBadge');

    function setupFormSubmit(form) {
      if (!form) return;
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

        const saveUrl = this.getAttribute('action');

        fetch(saveUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          },
          body: formData
        })
        .then(response => {
          if (!response.ok) {
            return response.json().then(data => {
              throw new Error(data.message || 'Failed to save configuration');
            });
          }
          return response.json();
        })
        .then(data => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;

          if (data.success) {
            showToast('success', 'Success', data.message || 'Configuration saved successfully');
          } else {
            showToast('danger', 'Error', data.message || 'Failed to save configuration');
          }
        })
        .catch(error => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
          console.error('Error:', error);
          showToast('danger', 'Error', error.message || 'An error occurred while saving configuration');
        });
      });
    }

    setupFormSubmit(commissionForm);
    setupFormSubmit(penaltyForm);
    setupFormSubmit(document.getElementById('settlementFeesConfigForm'));
  });

  function showToast(type, title, message) {
    const toastContainer = document.querySelector('.toast-container') || createToastContainer();

    const toastId = 'toast-' + Date.now();
    let iconClass, bgClass;

    if (type === 'success') {
      iconClass = 'ri-check-line';
      bgClass = 'bg-success';
    } else if (type === 'danger') {
      iconClass = 'ri-close-circle-line';
      bgClass = 'bg-danger';
    } else if (type === 'warning') {
      iconClass = 'ri-error-warning-line';
      bgClass = 'bg-warning';
    } else {
      iconClass = 'ri-information-line';
      bgClass = 'bg-info';
    }

    const toastHTML = message ? `
      <div id="${toastId}" class="bs-toast toast fade rounded-5 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-top-5 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body rounded-bottom-3">
          ${message}
        </div>
      </div>
    ` : `
      <div id="${toastId}" class="bs-toast toast fade show rounded-3 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="border: none;">
        <div class="toast-header ${bgClass} text-white rounded-3 border-0">
          <i class="icon-base ${iconClass} me-2"></i>
          <div class="me-auto fw-medium">${title}</div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHTML);

    const toastElement = document.getElementById(toastId);
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
      const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
      });
      toast.show();
    } else {
      toastElement.classList.add('show');
      setTimeout(() => {
        toastElement.classList.remove('show');
        toastElement.remove();
      }, 3000);
    }

    toastElement.addEventListener('hidden.bs.toast', function () {
      toastElement.remove();
    });
  }

  function createToastContainer() {
    const container = document.createElement('div');
    container.className = 'toast-container position-fixed top-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
  }
</script>
@endsection
