{{-- Reusable Add / Edit Bank Account modal --}}
@php
  $bankAccountStoreUrl = route('account.bank-accounts.store');
@endphp
<div class="modal fade" id="bankAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bankAccountModalTitle">{{ __('Add bank account') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="bankAccountForm" action="{{ $bankAccountStoreUrl }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="_method" id="bankAccountMethod" value="POST" disabled>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Account number') }} <span class="text-danger">*</span></label>
              <input type="text" name="account_number" id="bank_account_number"
                     class="form-control" required
                     placeholder="e.g. 1234567890"
                     minlength="9" maxlength="18"
                     pattern="^(?!0+$)\d{9,18}$"
                     title="{{ __('Enter 9–18 digits. Account number cannot be all zeros.') }}"
                     oninput="this.value=this.value.replace(/[^0-9]/g,''); window.BankAccountModal && BankAccountModal.validateAccNo(this);"
                     onblur="window.BankAccountModal && BankAccountModal.validateAccNo(this);">
              <div id="bank_acc_error" class="text-danger small mt-1 d-none">
                <i class="ri-error-warning-line me-1"></i>{{ __('Account number cannot be all zeros.') }}
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Account name') }} <span class="text-danger">*</span></label>
              <input type="text" name="account_name" id="bank_account_name" class="form-control" required placeholder="e.g. Main Operations" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Bank name') }} <span class="text-danger">*</span></label>
              <input type="text" name="bank_name" id="bank_bank_name" class="form-control" required placeholder="e.g. HDFC Bank" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Branch name') }}</label>
              <input type="text" name="branch_name" id="bank_branch_name" class="form-control" placeholder="{{ __('Optional') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Account type') }} <span class="text-danger">*</span></label>
              <select name="account_type" id="bank_account_type" class="form-select" required>
                <option value="Savings">{{ __('Savings') }}</option>
                <option value="Current">{{ __('Current') }}</option>
                <option value="cash">{{ __('Cash') }}</option>
                <option value="Credit Card">{{ __('Credit Card') }}</option>
                <option value="Other">{{ __('Other') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('IFSC Code') }}</label>
              <input type="text" name="ifsc_code" id="bank_ifsc_code" class="form-control" placeholder="{{ __('e.g. SBIN0001234') }}" maxlength="20">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('UPI ID') }}</label>
              <input type="text" name="upi_id" id="bank_upi_id" class="form-control" placeholder="{{ __('e.g. name@upi') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('QR Code (for collection)') }}</label>
              <input type="file" name="qr_code" id="bank_qr_code" class="form-control" accept="image/*">
              <div id="bank_qr_preview" class="mt-2 d-none">
                <a href="#" id="bank_qr_preview_link" target="_blank" class="btn btn-sm btn-outline-info">
                  <i class="ri-eye-line me-1"></i>{{ __('View current QR') }}
                </a>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Opening balance') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="opening_balance" id="bank_opening_balance"
                       class="form-control" required value="0"
                       oninput="window.BankAccountModal && BankAccountModal.onOpeningInput(this);"
                       onblur="window.BankAccountModal && BankAccountModal.onOpeningBlur(this);"
                       title="{{ __('Opening balance cannot be negative') }}">
              </div>
              <small class="text-muted" id="bank_opening_hint">{{ __('Current balance will be set equal to this.') }}</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Current balance') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="current_balance" id="bank_current_balance"
                       class="form-control bg-light" required value="0" readonly
                       title="{{ __('Auto-set equal to opening balance on create') }}">
                <span class="input-group-text bg-light text-muted" id="bank_current_lock" title="{{ __('Locked') }}">
                  <i class="ri-lock-line"></i>
                </span>
              </div>
              <small class="text-muted" id="bank_current_hint">{{ __('Must equal opening balance at creation.') }}</small>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="bank_is_active" checked>
                <label class="form-check-label" for="bank_is_active">{{ __('Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="button" class="btn btn-primary" id="bankAccountSubmitBtn" onclick="BankAccountModal.submit(this)">{{ __('Save bank account') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
window.BankAccountModal = (function () {
  const STORE_URL = @json($bankAccountStoreUrl);
  const I18N = {
    addTitle: @json(__('Add bank account')),
    editTitle: @json(__('Edit bank account')),
    save: @json(__('Save bank account')),
    update: @json(__('Update bank account')),
    openingCreateHint: @json(__('Current balance will be set equal to this.')),
    openingEditHint: @json(__('Changing opening adjusts current balance by the difference.')),
    currentCreateHint: @json(__('Must equal opening balance at creation.')),
    currentEditHint: @json(__('Current balance (read-only). Updated automatically if opening changes.')),
    invalidAccTitle: @json(__('Invalid Account Number')),
    allZerosHtml: @json(__('Account number cannot be all zeros. Please enter a valid bank account number.')),
    lengthText: @json(__('Account number must be between 9 and 18 digits.')),
    invalidAmount: @json(__('Balance values cannot be negative.')),
    mismatchTitle: @json(__('Balance Mismatch')),
    mismatchHtml: @json(__('Current Balance must be equal to Opening Balance during account creation.')),
  };

  let mode = 'create';

  function el(id) {
    return document.getElementById(id);
  }

  function form() {
    return el('bankAccountForm');
  }

  function validateAccNo(input) {
    if (!input) return true;
    const val = input.value.replace(/[^0-9]/g, '');
    const errEl = el('bank_acc_error');
    const isAllZeros = val.length > 0 && /^0+$/.test(val);
    if (isAllZeros) {
      input.classList.add('is-invalid');
      if (errEl) errEl.classList.remove('d-none');
    } else {
      input.classList.remove('is-invalid');
      if (errEl) errEl.classList.add('d-none');
    }
    return !isAllZeros;
  }

  function onOpeningInput(input) {
    input.value = String(input.value || '').replace(/-/g, '');
    if (mode === 'create') {
      const current = el('bank_current_balance');
      if (current) current.value = input.value;
    }
  }

  function onOpeningBlur(input) {
    const n = parseFloat(input.value);
    if (isNaN(n) || n < 0) {
      input.value = '0';
    }
    if (mode === 'create') {
      const current = el('bank_current_balance');
      if (current) current.value = input.value;
    }
  }

  function setAccountType(value) {
    const select = el('bank_account_type');
    if (!select) return;
    const raw = (value || '').toString();
    const lower = raw.toLowerCase();
    let matched = false;
    Array.from(select.options).forEach((opt) => {
      if (opt.value === raw || opt.value.toLowerCase() === lower) {
        select.value = opt.value;
        matched = true;
      }
    });
    if (!matched && raw) {
      // Preserve legacy values (e.g. savings / current lowercase)
      const temp = document.createElement('option');
      temp.value = raw;
      temp.textContent = raw;
      select.appendChild(temp);
      select.value = raw;
    }
  }

  function resetCreate() {
    mode = 'create';
    const f = form();
    if (!f) return;
    f.reset();
    f.action = STORE_URL;
    const method = el('bankAccountMethod');
    if (method) {
      method.value = 'POST';
      method.disabled = true;
    }
    el('bankAccountModalTitle').textContent = I18N.addTitle;
    el('bankAccountSubmitBtn').textContent = I18N.save;
    el('bank_opening_hint').textContent = I18N.openingCreateHint;
    el('bank_current_hint').textContent = I18N.currentCreateHint;
    el('bank_current_balance').value = '0';
    el('bank_opening_balance').value = '0';
    el('bank_is_active').checked = true;
    el('bank_qr_preview').classList.add('d-none');
    el('bank_qr_code').value = '';
    validateAccNo(el('bank_account_number'));
  }

  function fillEdit(data) {
    mode = 'edit';
    const f = form();
    if (!f || !data) return;

    f.action = data.updateUrl;
    const method = el('bankAccountMethod');
    if (method) {
      method.value = 'PUT';
      method.disabled = false;
    }

    el('bankAccountModalTitle').textContent = I18N.editTitle;
    el('bankAccountSubmitBtn').textContent = I18N.update;
    el('bank_opening_hint').textContent = I18N.openingEditHint;
    el('bank_current_hint').textContent = I18N.currentEditHint;

    el('bank_account_number').value = data.account_number || '';
    el('bank_account_name').value = data.account_name || '';
    el('bank_bank_name').value = data.bank_name || '';
    el('bank_branch_name').value = data.branch_name || '';
    setAccountType(data.account_type || 'Savings');
    el('bank_ifsc_code').value = data.ifsc_code || '';
    el('bank_upi_id').value = data.upi_id || '';
    el('bank_opening_balance').value = data.opening_balance != null ? data.opening_balance : '0';
    el('bank_current_balance').value = data.current_balance != null ? data.current_balance : '0';
    el('bank_is_active').checked = !!data.is_active;
    el('bank_qr_code').value = '';

    const preview = el('bank_qr_preview');
    const link = el('bank_qr_preview_link');
    if (data.qr_code_url) {
      link.href = data.qr_code_url;
      preview.classList.remove('d-none');
    } else {
      preview.classList.add('d-none');
    }
    validateAccNo(el('bank_account_number'));
  }

  function openCreate() {
    resetCreate();
    const modalEl = el('bankAccountModal');
    if (modalEl && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function openEdit(data) {
    fillEdit(data);
    const modalEl = el('bankAccountModal');
    if (modalEl && window.bootstrap) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function parseButtonData(btn) {
    if (!btn) return null;
    const raw = btn.getAttribute('data-bank-account');
    if (!raw) return null;
    try {
      return JSON.parse(raw);
    } catch (e) {
      console.error('Invalid bank account edit payload', e);
      return null;
    }
  }

  function submit(btn) {
    const f = form();
    if (!f) return;

    const accInput = el('bank_account_number');
    const accVal = (accInput ? accInput.value : '').trim();
    if (/^0+$/.test(accVal)) {
      accInput.classList.add('is-invalid');
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'error',
          title: I18N.invalidAccTitle,
          html: '<p>' + I18N.allZerosHtml + '</p>',
          confirmButtonColor: '#ff3e1d',
        }).then(() => accInput.focus());
      } else {
        alert(I18N.allZerosHtml);
        accInput.focus();
      }
      return;
    }
    if (accVal.length < 9 || accVal.length > 18) {
      accInput.classList.add('is-invalid');
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: I18N.invalidAccTitle, text: I18N.lengthText, confirmButtonColor: '#ff3e1d' })
          .then(() => accInput.focus());
      } else {
        alert(I18N.lengthText);
      }
      return;
    }

    const opening = parseFloat(el('bank_opening_balance').value) || 0;
    let current = parseFloat(el('bank_current_balance').value) || 0;

    if (opening < 0 || current < 0) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: I18N.invalidAmount, confirmButtonColor: '#ff3e1d' });
      } else {
        alert(I18N.invalidAmount);
      }
      return;
    }

    if (mode === 'create') {
      current = opening;
      el('bank_current_balance').value = String(opening);
      if (Math.abs(opening - current) > 0.001) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'error',
            title: I18N.mismatchTitle,
            html: '<p>' + I18N.mismatchHtml + '</p>',
            confirmButtonColor: '#ff3e1d',
          });
        } else {
          alert(I18N.mismatchHtml);
        }
        return;
      }
    } else {
      // Update request merges current_balance to opening_balance server-side.
      el('bank_current_balance').value = String(opening);
    }

    btn.disabled = true;
    f.submit();
  }

  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = el('bankAccountModal');
    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (trigger && trigger.getAttribute('data-bank-account')) {
          fillEdit(parseButtonData(trigger));
          return;
        }
        resetCreate();
      });
      modalEl.addEventListener('hidden.bs.modal', function () {
        resetCreate();
        const submitBtn = el('bankAccountSubmitBtn');
        if (submitBtn) submitBtn.disabled = false;
      });
    }

    // Legacy #addBankModal targets → reusable modal
    document.querySelectorAll('[data-bs-target="#addBankModal"]').forEach(function (btn) {
      btn.setAttribute('data-bs-target', '#bankAccountModal');
    });
  });

  // Back-compat aliases used by older inline handlers
  window.validateBankAccNo = validateAccNo;
  window.submitBankAccountForm = function (btn) { submit(btn); };

  return {
    validateAccNo,
    onOpeningInput,
    onOpeningBlur,
    openCreate,
    openEdit,
    resetCreate,
    submit,
  };
})();
</script>
