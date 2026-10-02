@extends('layouts/layoutMaster')

@section('title', __('Bank accounts'))

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
@php
  $exportQuery = array_filter([
    'account_number' => request('account_number'),
    'bank_name' => request('bank_name'),
    'is_active' => request('is_active'),
  ], fn($v) => !is_null($v) && $v !== '');
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Bank Accounts Management'),
    'subtitle' => __('Company bank and cash accounts for collections and payouts.'),
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#bankAccountModal"><i class="ri-add-line me-1"></i>' . e(__('Add bank account')) . '</button>',
  ])

  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.bank-accounts.export',
      'query' => $exportQuery,
      'disablePdf' => true,
    ])
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Bank Accounts Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('All operating bank and cash accounts with balances') }}</p>
      </div>
    </div>

    <div class="card-body border-top py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label small fw-bold">{{ __('Search') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="account_number" value="{{ request('account_number') }}" class="form-control" placeholder="{{ __('Search number, name, bank...') }}">
          </div>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Status') }}</label>
          <select name="is_active" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select">
            @foreach ([10, 25, 50, 100] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.bank-accounts.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Account #') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Bank') }}</th>
            <th>{{ __('Branch') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('UPI ID') }}</th>
            <th>{{ __('QR Code') }}</th>
            <th class="text-end">{{ __('Opening') }}</th>
            <th class="text-end">{{ __('Current') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bankaccounts as $ba)
            <tr>
              <td><code class="acc-number">{{ $ba->account_number }}</code></td>
              <td><span class="acc-name">{{ $ba->account_name }}</span></td>
              <td>{{ $ba->bank_name }}</td>
              <td>{{ $ba->branch_name ?? '—' }}</td>
              <td><span class="badge bg-label-secondary">{{ $ba->account_type }}</span></td>
              <td>
                @if($ba->upi_id)
                  <span class="text-heading fw-medium">{{ $ba->upi_id }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>
                @if($ba->qr_code)
                  <a href="{{ asset('storage/' . $ba->qr_code) }}" target="_blank" class="d-inline-block">
                    <img src="{{ asset('storage/' . $ba->qr_code) }}" alt="QR Code" class="rounded border shadow-sm img-thumbnail" style="width: 42px; height: 42px; object-fit: contain;">
                  </a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end">₹{{ number_format((float) $ba->opening_balance, 2) }}</td>
              <td class="text-end fw-semibold">₹{{ number_format((float) $ba->current_balance, 2) }}</td>
              <td>
                @if ($ba->is_active)
                  <span class="badge bg-label-success">{{ __('Yes') }}</span>
                @else
                  <span class="badge bg-label-secondary">{{ __('No') }}</span>
                @endif
              </td>
              <td class="text-end">
                @php
                  $bankEditPayload = [
                    'updateUrl' => route('account.bank-accounts.update', $ba),
                    'account_number' => $ba->account_number,
                    'account_name' => $ba->account_name,
                    'bank_name' => $ba->bank_name,
                    'branch_name' => $ba->branch_name,
                    'account_type' => $ba->account_type,
                    'ifsc_code' => $ba->ifsc_code,
                    'upi_id' => $ba->upi_id,
                    'opening_balance' => (float) $ba->opening_balance,
                    'current_balance' => (float) $ba->current_balance,
                    'is_active' => (bool) $ba->is_active,
                    'qr_code_url' => $ba->qr_code ? asset('storage/' . $ba->qr_code) : null,
                  ];
                @endphp
                <div class="d-inline-flex align-items-center flex-wrap gap-1">
                  <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill js-transaction-bank-account" title="{{ __('Deposit / Withdraw') }}" aria-label="{{ __('Transaction') }}" data-bs-toggle="modal" data-bs-target="#bankTransactionModal" data-bank-account="{{ json_encode(['id' => $ba->id, 'name' => $ba->bank_name . ' - ' . $ba->account_number, 'transactionUrl' => route('account.bank-accounts.transaction', $ba), 'balance' => (float) $ba->current_balance]) }}">
                    <i class="icon-base ri ri-exchange-dollar-line icon-18px text-success"></i>
                  </button>
                  @include('admin.account.shared.table-actions', [
                    'editModalTarget' => '#bankAccountModal',
                    'editModalClass' => 'js-edit-bank-account',
                    'editModalData' => [
                      'data-bank-account' => json_encode($bankEditPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE),
                    ],
                    'deleteRoute' => route('account.bank-accounts.destroy', $ba),
                    'deleteConfirm' => __('Delete this bank account?'),
                  ])
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="11" class="text-center text-muted py-5">{{ __('No bank accounts yet. Add one above.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($bankaccounts->hasPages())
      <div class="card-footer border-top">{{ $bankaccounts->links() }}</div>
    @endif
  </div>
</div>

<!-- Bank Transaction Modal -->
<div class="modal fade" id="bankTransactionModal" tabindex="-1" aria-labelledby="bankTransactionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bankTransactionModalLabel">{{ __('Deposit / Withdraw') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="bankTransactionForm" method="POST" action="">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">{{ __('Account') }}</label>
            <div class="input-group">
              <input type="text" id="transactAccountName" class="form-control" readonly disabled>
              <span class="input-group-text bg-light text-primary fw-bold" id="transactAccountBalance"></span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ __('Transaction Type') }} <span class="text-danger">*</span></label>
            <select name="transaction_type" class="form-select" required>
              <option value="deposit">{{ __('Deposit') }}</option>
              <option value="withdrawal">{{ __('Withdrawal') }}</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ __('Amount') }} <span class="text-danger">*</span></label>
            <div class="input-group">
              <span class="input-group-text">₹</span>
              <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold" id="personNameLabel">{{ __('Depositor Name') }} <span class="text-danger">*</span></label>
            <input type="text" name="person_name" class="form-control" placeholder="{{ __('Name of person depositing/withdrawing') }}" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Optional remarks') }}"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">{{ __('Reference Number') }}</label>
            <input type="text" name="reference_number" class="form-control" placeholder="{{ __('e.g., TXN12345') }}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('bankTransactionModal');
    if (modalEl) {
      modalEl.addEventListener('show.bs.modal', function (event) {
        const triggerBtn = event.relatedTarget;
        if (!triggerBtn) return;
        
        const raw = triggerBtn.getAttribute('data-bank-account');
        if (!raw) return;
        
        try {
          const data = JSON.parse(raw);
          const transactionForm = document.getElementById('bankTransactionForm');
          const transactAccountName = document.getElementById('transactAccountName');
          const transactAccountBalance = document.getElementById('transactAccountBalance');
          
          if (transactionForm) {
            transactionForm.action = data.transactionUrl;
            transactionForm.reset();
            if (transactAccountName) {
              transactAccountName.value = data.name;
            }
            if (transactAccountBalance) {
              transactAccountBalance.textContent = '₹' + parseFloat(data.balance).toFixed(2);
            }
            const typeSelect = transactionForm.querySelector('select[name="transaction_type"]');
            const personLabel = document.getElementById('personNameLabel');
            const amountInput = transactionForm.querySelector('input[name="amount"]');
            
            if (typeSelect && personLabel && amountInput) {
              typeSelect.value = 'deposit';
              personLabel.innerHTML = '{{ __('Depositor Name') }} <span class="text-danger">*</span>';
              amountInput.removeAttribute('max');
              
              typeSelect.addEventListener('change', function() {
                if (this.value === 'deposit') {
                  personLabel.innerHTML = '{{ __('Depositor Name') }} <span class="text-danger">*</span>';
                  amountInput.removeAttribute('max');
                } else {
                  personLabel.innerHTML = '{{ __('Withdrawer Name') }} <span class="text-danger">*</span>';
                  amountInput.setAttribute('max', data.balance);
                  
                  const maxAmount = parseFloat(data.balance);
                  if (parseFloat(amountInput.value) > maxAmount) {
                    amountInput.value = maxAmount.toFixed(2);
                  }
                }
              });

              amountInput.addEventListener('input', function() {
                if (typeSelect.value === 'withdrawal') {
                  const maxAmount = parseFloat(data.balance);
                  const currentAmount = parseFloat(this.value);
                  if (currentAmount > maxAmount) {
                    this.value = maxAmount.toFixed(2);
                  }
                }
              });
            }
          }
        } catch (e) {
          console.error("Failed to parse data-bank-account payload:", e);
        }
      });
    }
  });
</script>

@endsection
