<!-- Modal Add Bank Account -->
<div class="modal fade" id="addBankModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add bank account') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.bank-accounts.store') }}" method="POST">
        @csrf
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
                     oninput="this.value=this.value.replace(/[^0-9]/g,''); validateBankAccNo(this);"
                     onblur="validateBankAccNo(this);">
              <div id="bank_acc_error" class="text-danger small mt-1 d-none">
                <i class="ri-error-warning-line me-1"></i>{{ __('Account number cannot be all zeros.') }}
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Account name') }} <span class="text-danger">*</span></label>
              <input type="text" name="account_name" class="form-control" required placeholder="e.g. Main Operations" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Bank name') }} <span class="text-danger">*</span></label>
              <input type="text" name="bank_name" class="form-control" required placeholder="e.g. HDFC Bank" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Branch name') }}</label>
              <input type="text" name="branch_name" class="form-control" placeholder="{{ __('Optional') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Account type') }} <span class="text-danger">*</span></label>
              <select name="account_type" class="form-select" required>
                <option value="Savings">{{ __('Savings') }}</option>
                <option value="Current">{{ __('Current') }}</option>
                <option value="Credit Card">{{ __('Credit Card') }}</option>
                <option value="Other">{{ __('Other') }}</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('GL account') }} <span class="text-danger">*</span></label>
              <select name="gl_account_id" class="form-select" required>
                @foreach ($bankGlAccounts as $g)
                  <option value="{{ $g->id }}">{{ $g->account_code }} — {{ $g->account_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Opening balance') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="opening_balance" id="bank_opening_balance"
                       class="form-control" required value="0"
                       oninput="this.value=this.value.replace(/-/g,''); document.getElementById('bank_current_balance').value=this.value;"
                       onblur="if(parseFloat(this.value)<0||isNaN(parseFloat(this.value))){this.value='0';document.getElementById('bank_current_balance').value='0';}"
                       title="{{ __('Opening balance cannot be negative') }}">
              </div>
              <small class="text-muted">{{ __('Current balance will be set equal to this.') }}</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Current balance') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="current_balance" id="bank_current_balance"
                       class="form-control bg-light" required value="0" readonly
                       title="{{ __('Auto-set equal to opening balance') }}">
                <span class="input-group-text bg-light text-muted" title="{{ __('Locked: must equal opening balance') }}">
                  <i class="ri-lock-line"></i>
                </span>
              </div>
              <small class="text-muted">{{ __('Must equal opening balance at creation.') }}</small>
            </div>
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                <label class="form-check-label">{{ __('Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="button" class="btn btn-primary" onclick="submitBankAccountForm(this)">{{ __('Save bank account') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function validateBankAccNo(input) {
    const val = input.value.replace(/[^0-9]/g, '');
    const errEl = document.getElementById('bank_acc_error');
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

function submitBankAccountForm(btn) {
    const form = btn.closest('form');

    // ── Account number: all-zeros check ─────────────────────────
    const accInput = document.getElementById('bank_account_number');
    const accVal   = (accInput ? accInput.value : '').trim();
    if (/^0+$/.test(accVal)) {
        accInput.classList.add('is-invalid');
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Account Number',
                html: '<p>Account number <strong>' + accVal + '</strong> is not valid.</p><p class="mb-0 text-muted">Account number cannot be all zeros. Please enter a valid bank account number.</p>',
                confirmButtonColor: '#ff3e1d',
                confirmButtonText: 'OK, Fix it'
            }).then(() => { accInput.focus(); });
        } else {
            alert('Account number cannot be all zeros.');
            accInput.focus();
        }
        return;
    }
    if (accVal.length < 9 || accVal.length > 18) {
        accInput.classList.add('is-invalid');
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Invalid Account Number', text: 'Account number must be between 9 and 18 digits.', confirmButtonColor: '#ff3e1d' })
                .then(() => { accInput.focus(); });
        } else { alert('Account number must be between 9 and 18 digits.'); }
        return;
    }

    const opening = parseFloat(document.getElementById('bank_opening_balance').value) || 0;
    const current = parseFloat(document.getElementById('bank_current_balance').value) || 0;

    if (opening < 0 || current < 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Invalid Amount', text: 'Balance values cannot be negative.', confirmButtonColor: '#ff3e1d' });
        } else { alert('Balance values cannot be negative.'); }
        return;
    }

    if (Math.abs(opening - current) > 0.001) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Balance Mismatch',
                html: '<p>Current Balance must be equal to Opening Balance during account creation.</p>' +
                      '<p class="mb-0"><strong>Opening:</strong> ₹' + opening.toFixed(2) + ' &nbsp;|&nbsp; <strong>Current:</strong> ₹' + current.toFixed(2) + '</p>',
                confirmButtonColor: '#ff3e1d',
                confirmButtonText: 'Fix it'
            }).then(() => {
                document.getElementById('bank_current_balance').value = document.getElementById('bank_opening_balance').value;
            });
        } else {
            alert('Current Balance must equal Opening Balance.');
        }
        return;
    }

    btn.disabled = true;
    form.submit();
}
</script>
