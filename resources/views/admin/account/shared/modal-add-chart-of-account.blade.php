<div class="modal fade" id="addChartOfAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add Chart of Account') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.chart-of-accounts.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Account Code') }} <span class="text-danger">*</span></label>
              <input type="text" name="account_code" class="form-control" placeholder="{{ __('e.g. 1101') }}" required maxlength="20" oninput="this.value=this.value.replace(/[^0-9]/g,'');">
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Account Name') }} <span class="text-danger">*</span></label>
              <input type="text" name="account_name" class="form-control" placeholder="{{ __('e.g. Cash in Hand') }}" required pattern="^(?=.*[A-Za-z])[A-Za-z0-9&().,\-\s]+$" title="{{ __('Use letters with valid symbols only') }}">
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Account Type') }} <span class="text-danger">*</span></label>
              <select name="account_type_id" class="form-select" required onchange="enforceNormalBalance(this)">
                <option value="">{{ __('Select Type') }}</option>
                @foreach($allAccountTypes as $type)
                  <option value="{{ $type->id }}" data-normal="{{ $type->normal_balance ?? '' }}">{{ $type->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Normal Balance') }} <span class="text-danger">*</span></label>
              <select name="normal_balance" id="coa_normal_balance" class="form-select" required>
                <option value="debit">{{ __('Debit') }}</option>
                <option value="credit">{{ __('Credit') }}</option>
              </select>
              <small class="text-muted" id="coa_normal_balance_help">{{ __('Auto-mapped from Account Type') }}</small>
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Parent Account') }} ({{ __('Optional') }})</label>
              <select name="parent_account_id" class="form-select">
                <option value="">{{ __('No Parent') }}</option>
                @forelse($allChartOfAccounts as $account)
                  <option value="{{ $account->id }}">{{ $account->account_code }} — {{ $account->account_name }}</option>
                @empty
                  <option disabled>{{ __('No parent accounts available') }}</option>
                @endforelse
              </select>
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Opening Balance') }}</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="opening_balance" id="coa_opening_balance"
                       class="form-control" value="0.00"
                       oninput="if(parseFloat(this.value)<0||this.value.includes('-')){this.value=this.value.replace(/-/g,'');} document.getElementById('coa_current_balance').value=this.value;"  
                       onblur="if(parseFloat(this.value)<0||isNaN(parseFloat(this.value))){this.value='0.00';document.getElementById('coa_current_balance').value='0.00';}"
                       title="{{ __('Opening balance cannot be negative') }}">
              </div>
              <small class="text-muted">{{ __('Current balance will be set equal to this.') }}</small>
            </div>
            <div class="col-md-6 text-start">
              <label class="form-label">{{ __('Current Balance') }}</label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="current_balance" id="coa_current_balance"
                       class="form-control bg-light" value="0.00" readonly
                       title="{{ __('Auto-set equal to opening balance') }}">
                <span class="input-group-text bg-light text-muted" title="{{ __('Locked: must equal opening balance') }}">
                  <i class="ri-lock-line"></i>
                </span>
              </div>
              <small class="text-muted">{{ __('Must equal opening balance at creation.') }}</small>
            </div>
            <div class="col-12 text-start">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12 text-start">
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="coa_is_active" checked>
                <label class="form-check-label" for="coa_is_active">{{ __('Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="button" class="btn btn-primary" onclick="submitChartOfAccountForm(this)">{{ __('Save Account') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function enforceNormalBalance(select) {
    const selectedOption = select.options[select.selectedIndex];
    const normalBalance = selectedOption.getAttribute('data-normal');
    const balanceSelect = document.getElementById('coa_normal_balance');
    const helpText = document.getElementById('coa_normal_balance_help');
    
    if (normalBalance === 'debit' || normalBalance === 'credit') {
        balanceSelect.value = normalBalance;
        balanceSelect.setAttribute('style', 'pointer-events: none; background-color: #f3f4f6;');
        helpText.innerHTML = '<span class="text-success"><i class="ri-check-line me-1"></i> Locked to correct mapping</span>';
    } else {
        balanceSelect.removeAttribute('style');
        helpText.innerHTML = '{{ __('Auto-mapped from Account Type') }}';
    }
}

function submitChartOfAccountForm(btn) {
    const form = btn.closest('form');
    
    const openingInput = form.querySelector('[name="opening_balance"]');
    const currentInput = form.querySelector('[name="current_balance"]');
    
    if (openingInput.value.trim() === '') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Validation Failed', text: 'Opening Balance is required', confirmButtonColor: '#ff3e1d', target: document.getElementById('addChartOfAccountModal') });
        } else {
            alert('Opening Balance is required');
        }
        return;
    }
    
    if (currentInput.value.trim() === '') {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Validation Failed', text: 'Current Balance is required', confirmButtonColor: '#ff3e1d', target: document.getElementById('addChartOfAccountModal') });
        } else {
            alert('Current Balance is required');
        }
        return;
    }

    const opening = parseFloat(openingInput.value);
    const current = parseFloat(currentInput.value);

    if (opening < 0 || current < 0) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Invalid Amount',
                text: 'Opening Balance and Current Balance cannot be negative values.',
                confirmButtonColor: '#ff3e1d',
                target: document.getElementById('addChartOfAccountModal')
            });
        } else {
            alert('Opening Balance and Current Balance cannot be negative values.');
        }
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
                confirmButtonText: 'Fix it',
                target: document.getElementById('addChartOfAccountModal')
            }).then(() => {
                document.getElementById('coa_current_balance').value = document.getElementById('coa_opening_balance').value;
            });
        } else {
            alert('Current Balance must equal Opening Balance.');
        }
        return;
    }

    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(async response => {
        if (!response.ok) {
            if (response.status === 422) {
                const data = await response.json();
                let errorHtml = '<ul class="text-start mb-0 ps-3">';
                for (const field in data.errors) {
                    errorHtml += `<li>${data.errors[field][0]}</li>`;
                    const input = form.querySelector(`[name="${field}"]`);
                    if (input) {
                        input.classList.add('is-invalid');
                        input.addEventListener('input', () => input.classList.remove('is-invalid'), {once: true});
                    }
                }
                errorHtml += '</ul>';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Failed',
                    html: errorHtml,
                    confirmButtonColor: '#ff3e1d',
                    target: document.getElementById('addChartOfAccountModal')
                });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong.', confirmButtonColor: '#ff3e1d' });
            }
            btn.disabled = false;
            btn.innerHTML = originalText;
        } else {
            window.location.reload();
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.innerHTML = originalText;
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Please check your connection and try again.', confirmButtonColor: '#ff3e1d' });
    });
}
</script>
