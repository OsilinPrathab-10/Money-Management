<!-- Modal Add Expense Draft -->
<div class="modal fade" id="addExpenseDraftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom bg-light">
        <h5 class="modal-title fw-bold text-primary">{{ __('New Expense (Draft)') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.expenses.store') }}" method="POST" onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\' role=\'status\' aria-hidden=\'true\'></span>Saving...';">
        @csrf
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="expense_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('Amount') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text fw-bold">₹</span>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="0.00" required>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('Category') }} <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">{{ __('Select Category') }}</option>
                @foreach (($allExpenseCategories ?? collect()) as $c)
                  <option value="{{ $c->id }}">{{ $c->category_name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">{{ __('Bank Account') }} <span class="text-danger">*</span></label>
              <select name="bank_account_id" id="expenseModalBankSelect" class="form-select" required>
                <option value="">{{ __('Select Bank Account') }}</option>
                @foreach (($allBankAccounts ?? collect()) as $b)
                  <option value="{{ $b->id }}"
                          data-bank-name="{{ $b->bank_name ?? '—' }}"
                          data-account-number="{{ $b->account_number ?? '—' }}"
                          data-ifsc="{{ $b->effective_ifsc ?? $b->ifsc_code ?? '—' }}"
                          data-branch="{{ $b->branch_name ?? '—' }}"
                          data-upi="{{ $b->upi_id ?? '—' }}"
                          data-qr="{{ $b->qr_code ? (str_starts_with($b->qr_code, 'http') || str_starts_with($b->qr_code, 'data:') ? $b->qr_code : asset('storage/' . $b->qr_code)) : '' }}"
                          data-balance="₹{{ number_format((float) $b->current_balance, 2) }}">
                    {{ $b->account_name }} (₹{{ number_format((float) $b->current_balance, 2) }})
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Dynamic Selected Bank Account Details, Current Balance & QR Code Card -->
            <div id="expenseModalBankCard" class="col-12 d-none">
              <div class="p-3 bg-light border rounded-3 shadow-xs">
                <div class="row align-items-center g-3">
                  <!-- QR Code Display -->
                  <div id="expenseModalQrCol" class="col-auto text-center d-none">
                    <div class="p-2 bg-white border rounded shadow-xs d-inline-block">
                      <img id="expenseModalQrImg" src="" alt="QR Code" style="width: 100px; height: 100px; object-fit: contain;">
                    </div>
                    <small class="d-block text-muted mt-1 fw-semibold" style="font-size: 11px;">Scan QR Code</small>
                  </div>

                  <!-- Details Display -->
                  <div class="col">
                    <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom pb-2 mb-2 gap-2">
                      <div>
                        <h6 id="expenseModalBankTitle" class="mb-0 fw-bold text-primary">Bank Account</h6>
                        <small id="expenseModalBranchText" class="text-muted">—</small>
                      </div>
                      <div class="text-end">
                        <span class="text-muted small d-block mb-0"><i class="ri-wallet-3-line me-1 text-success"></i>Current Balance</span>
                        <strong id="expenseModalBalanceText" class="text-success fs-5 fw-bold">₹0.00</strong>
                      </div>
                    </div>

                    <div class="row g-2 small text-dark">
                      <div class="col-md-6 col-sm-6">
                        <span class="text-muted me-1">Account No:</span>
                        <strong id="expenseModalAccNoText" class="fw-semibold">—</strong>
                      </div>
                      <div class="col-md-6 col-sm-6">
                        <span class="text-muted me-1">IFSC Code:</span>
                        <strong id="expenseModalIfscText" class="fw-semibold">—</strong>
                      </div>
                      <div id="expenseModalUpiRow" class="col-12 d-none">
                        <span class="text-muted me-1">UPI ID:</span>
                        <strong id="expenseModalUpiText" class="text-primary fw-semibold">—</strong>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-md-12">
              <label class="form-label fw-semibold">{{ __('Reference #') }}</label>
              <input type="text" name="reference_number" class="form-control" placeholder="{{ __('Transaction ref / cheque #') }}">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Describe this expense...') }}"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top bg-light">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i>{{ __('Save Expense Draft') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const bankSelect = document.getElementById('expenseModalBankSelect');
  if (!bankSelect) return;

  bankSelect.addEventListener('change', function() {
    const card = document.getElementById('expenseModalBankCard');
    const selected = this.options[this.selectedIndex];

    if (!selected || !this.value) {
      if (card) card.classList.add('d-none');
      return;
    }

    const bankName = selected.getAttribute('data-bank-name') || 'Bank Details';
    const accNo = selected.getAttribute('data-account-number') || '—';
    const ifsc = selected.getAttribute('data-ifsc') || '—';
    const branch = selected.getAttribute('data-branch') || '—';
    const upi = selected.getAttribute('data-upi') || '';
    const balance = selected.getAttribute('data-balance') || '₹0.00';
    let qr = selected.getAttribute('data-qr') || '';

    document.getElementById('expenseModalBankTitle').textContent = bankName;
    document.getElementById('expenseModalBranchText').textContent = branch !== '—' ? ('Branch: ' + branch) : '';
    document.getElementById('expenseModalBalanceText').textContent = balance;
    document.getElementById('expenseModalAccNoText').textContent = accNo;
    document.getElementById('expenseModalIfscText').textContent = ifsc;

    const upiRow = document.getElementById('expenseModalUpiRow');
    if (upi && upi !== '—') {
      document.getElementById('expenseModalUpiText').textContent = upi;
      upiRow.classList.remove('d-none');
      if (!qr) {
        qr = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent('upi://pay?pa=' + upi + '&pn=' + encodeURIComponent(bankName));
      }
    } else {
      upiRow.classList.add('d-none');
    }

    const qrCol = document.getElementById('expenseModalQrCol');
    const qrImg = document.getElementById('expenseModalQrImg');
    if (qr) {
      qrImg.src = qr;
      qrCol.classList.remove('d-none');
    } else {
      qrCol.classList.add('d-none');
    }

    if (card) card.classList.remove('d-none');
  });
});
</script>
