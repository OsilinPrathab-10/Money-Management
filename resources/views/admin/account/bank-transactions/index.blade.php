@extends('layouts/layoutMaster')

@section('title', __('Bank transactions'))

@section('content')
@php
  use App\Services\Account\AccountingTags;
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Bank Transactions Management'),
    'subtitle' => __('Posted movements per bank account — shows bank name and Chit / Loan / FD tags.'),
  ])

  @php
    $exportQuery = array_filter([
      'bank_account_id' => request('bank_account_id'),
      'transaction_type' => request('transaction_type'),
      'module_tag' => (($moduleTag ?? 'all') !== 'all') ? $moduleTag : null,
      'entry_tag' => $entryTag ?? null,
      'search' => request('search'),
      'date_preset' => request('date_preset'),
      'date_from' => request('date_from'),
      'date_to' => request('date_to'),
      'sort' => request('sort'),
      'direction' => request('direction'),
    ], fn($v) => !is_null($v) && $v !== '');
  @endphp

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md bg-label-primary rounded p-2">
              <i class="ri-bank-card-line ri-24px"></i>
            </div>
            <div>
              <small class="text-muted d-block">{{ $selectedBankAccount ? __('Selected Bank Balance') : __('Total Bank Balance') }}</small>
              <h4 class="mb-0 fw-bold text-primary">₹{{ number_format((float) ($selectedBankAccount ? $selectedBankAccount->current_balance : ($totalBankBalance ?? 0)), 2) }}</h4>
              @if ($selectedBankAccount)
                <small class="text-muted">{{ $selectedBankAccount->account_name }} @if($selectedBankAccount->bank_name)({{ $selectedBankAccount->bank_name }})@endif</small>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md bg-label-success rounded p-2">
              <i class="ri-arrow-down-line ri-24px"></i>
            </div>
            <div>
              <small class="text-muted d-block">{{ __('Total Credits') }}</small>
              <h4 class="mb-0 fw-bold text-success">₹{{ number_format((float) ($totalCredits ?? 0), 2) }}</h4>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-3">
          <div class="d-flex align-items-center gap-3">
            <div class="avatar avatar-md bg-label-danger rounded p-2">
              <i class="ri-arrow-up-line ri-24px"></i>
            </div>
            <div>
              <small class="text-muted d-block">{{ __('Total Debits') }}</small>
              <h4 class="mb-0 fw-bold text-danger">₹{{ number_format((float) ($totalDebits ?? 0), 2) }}</h4>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Bank Transactions Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Credit and debit movements across bank and cash accounts') }}</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        @include('admin.account.reports._export-toolbar', [
          'exportRoute' => 'account.bank-transactions.export',
          'query' => $exportQuery,
        ])
      </div>
    </div>

    <div class="card-body border-top py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Bank account') }}</label>
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Bank Accounts') }}</option>
            @foreach ($bankAccounts as $b)
              <option value="{{ $b->id }}" @selected(request('bank_account_id') == $b->id)>
                {{ $b->account_name }}@if($b->bank_name) ({{ $b->bank_name }})@endif — ₹{{ number_format((float) $b->current_balance, 2) }}
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Module') }}</label>
          <select name="module_tag" class="form-select">
            <option value="all">{{ __('All modules') }}</option>
            @foreach ($moduleOptions ?? [] as $m)
              <option value="{{ $m }}" @selected(($moduleTag ?? 'all') === $m)>{{ $m === 'TRANSFER' ? __('TRANSFER (Internal Transfer)') : $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Entry tag') }}</label>
          <select name="entry_tag" class="form-select">
            <option value="">{{ __('All entry tags') }}</option>
            @foreach ($entryOptions ?? [] as $e)
              <option value="{{ $e }}" @selected(($entryTag ?? null) === $e)>{{ AccountingTags::entryLabel($e) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Type') }}</label>
          <select name="transaction_type" class="form-select">
            <option value="">{{ __('All types') }}</option>
            <option value="credit" @selected(request('transaction_type') === 'credit')>{{ __('Credit') }}</option>
            <option value="debit" @selected(request('transaction_type') === 'debit')>{{ __('Debit') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Search') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search bank, ref, description...') }}">
          </div>
        </div>

        <div class="col-12 pt-2 border-top">
          @include('partials.date-range-filter', [
            'fromId' => 'bankTxDateFrom',
            'toId' => 'bankTxDateTo',
            'presetId' => 'bankTxDatePreset',
            'fromName' => 'date_from',
            'toName' => 'date_to',
            'fromValue' => request('date_from'),
            'toValue' => request('date_to'),
            'presetValue' => request('date_preset', 'all'),
            'autoSubmit' => true,
            'size' => 'sm',
          ])
        </div>
        <div class="col-12 d-flex justify-content-end gap-2">
          <button type="submit" class="btn btn-primary flex-fill px-3"><i class="ri-filter-3-line me-1"></i> {{ __('Filter') }}</button>
          <a href="{{ route('account.bank-transactions.index') }}" class="btn btn-outline-secondary" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
          @include('admin.account.reports._export-toolbar', [
            'exportRoute' => 'account.bank-transactions.export',
            'query' => $exportQuery,
          ])
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Bank') }}</th>
            <th>{{ __('Module') }}</th>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="text-end acc-credit">{{ __('Credit') }}</th>
            <th class="text-end acc-debit">{{ __('Debit') }}</th>
            <th class="text-end fw-bold">{{ __('Balance') }}</th>
            <th>{{ __('Reconciled') }}</th>
            <th class="text-end">{{ __('Action') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($transactions as $t)
            @php
              $fullDesc = $t->description ?: '—';
              $bankLabel = $t->bankAccount?->account_name ?? '—';
              $bankName = $t->bankAccount?->bank_name ?? '';
              $entryLabel = $t->entry_tag ? AccountingTags::entryLabel($t->entry_tag) : '—';
            @endphp
            <tr>
              <td>{{ $t->transaction_date?->format('Y-m-d') }}</td>
              <td>
                <div class="acc-name">{{ $bankLabel }}</div>
                @if ($bankName)
                  <small class="text-muted">{{ $bankName }}</small>
                @endif
              </td>
              <td>
                @if ($t->module_tag)
                  <span class="badge {{ AccountingTags::moduleBadgeClass($t->module_tag) }}">{{ $t->module_tag }}</span>
                @else
                  <span class="text-muted">—</span>
                @endif
                @if ($t->entry_tag)
                  <div><span class="badge bg-label-secondary">{{ $entryLabel }}</span></div>
                @endif
              </td>
              <td><code>{{ $t->reference_number ?? '—' }}</code></td>
              <td>
                <div class="text-break" style="white-space: normal; max-width: 420px;">{{ $fullDesc }}</div>
              </td>
              <td class="text-end acc-credit">
                @if ($t->transaction_type === 'credit')
                  ₹{{ number_format((float) $t->amount, 2) }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end acc-debit">
                @if ($t->transaction_type === 'debit')
                  ₹{{ number_format((float) $t->amount, 2) }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end fw-semibold text-dark">
                ₹{{ number_format((float) ($t->running_balance ?? $t->bankAccount?->current_balance ?? 0), 2) }}
              </td>
              <td>{{ $t->reconciliation_status ?? '—' }}</td>
              <td class="text-end">
                <button
                  type="button"
                  class="btn btn-sm btn-icon btn-outline-primary btn-view-bank-tx"
                  title="{{ __('View full details') }}"
                  data-bs-toggle="modal"
                  data-bs-target="#viewBankTransactionModal"
                  data-date="{{ $t->transaction_date?->format('Y-m-d') }}"
                  data-bank="{{ $bankLabel }}"
                  data-bank-name="{{ $bankName }}"
                  data-module="{{ $t->module_tag ?? '' }}"
                  data-entry="{{ $entryLabel }}"
                  data-type="{{ strtoupper($t->transaction_type ?? '') }}"
                  data-reference="{{ $t->reference_number ?? '—' }}"
                  data-description="{{ $fullDesc }}"
                  data-amount="₹{{ number_format((float) $t->amount, 2) }}"
                  data-balance="₹{{ number_format((float) ($t->running_balance ?? 0), 2) }}"
                  data-status="{{ $t->transaction_status ?? '—' }}"
                  data-reconciled="{{ $t->reconciliation_status ?? '—' }}"
                >
                  <i class="ri-eye-line"></i>
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted py-5">{{ __('No transactions yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($transactions->hasPages())
      <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
  </div>
</div>

{{-- View transaction popup --}}
<div class="modal fade" id="viewBankTransactionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Bank transaction details') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <span class="text-muted d-block small">{{ __('Date') }}</span>
            <strong id="vtx-date">—</strong>
          </div>
          <div class="col-md-4">
            <span class="text-muted d-block small">{{ __('Type') }}</span>
            <strong id="vtx-type">—</strong>
          </div>
          <div class="col-md-4">
            <span class="text-muted d-block small">{{ __('Amount') }}</span>
            <strong id="vtx-amount" class="fs-5">—</strong>
          </div>
          <div class="col-md-6">
            <span class="text-muted d-block small">{{ __('Bank account') }}</span>
            <strong id="vtx-bank" class="acc-name">—</strong>
            <div class="text-muted small" id="vtx-bank-name"></div>
          </div>
          <div class="col-md-3">
            <span class="text-muted d-block small">{{ __('Module') }}</span>
            <strong id="vtx-module">—</strong>
          </div>
          <div class="col-md-3">
            <span class="text-muted d-block small">{{ __('Entry') }}</span>
            <strong id="vtx-entry">—</strong>
          </div>
          <div class="col-md-6">
            <span class="text-muted d-block small">{{ __('Reference') }}</span>
            <code id="vtx-reference">—</code>
          </div>
          <div class="col-md-3">
            <span class="text-muted d-block small">{{ __('Status') }}</span>
            <strong id="vtx-status">—</strong>
          </div>
          <div class="col-md-3">
            <span class="text-muted d-block small">{{ __('Reconciled') }}</span>
            <strong id="vtx-reconciled">—</strong>
          </div>
          <div class="col-md-6">
            <span class="text-muted d-block small">{{ __('Running balance') }}</span>
            <strong id="vtx-balance">—</strong>
          </div>
          <div class="col-12">
            <span class="text-muted d-block small mb-1">{{ __('Description') }}</span>
            <div id="vtx-description" class="border rounded p-3 bg-lighter text-break" style="white-space: pre-wrap;">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  var modal = document.getElementById('viewBankTransactionModal');
  if (!modal) return;

  modal.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;

    var setText = function (id, value) {
      var el = document.getElementById(id);
      if (el) el.textContent = value || '—';
    };

    setText('vtx-date', btn.getAttribute('data-date'));
    setText('vtx-type', btn.getAttribute('data-type'));
    setText('vtx-amount', btn.getAttribute('data-amount'));
    setText('vtx-bank', btn.getAttribute('data-bank'));
    setText('vtx-bank-name', btn.getAttribute('data-bank-name'));
    setText('vtx-module', btn.getAttribute('data-module') || '—');
    setText('vtx-entry', btn.getAttribute('data-entry'));
    setText('vtx-reference', btn.getAttribute('data-reference'));
    setText('vtx-status', btn.getAttribute('data-status'));
    setText('vtx-reconciled', btn.getAttribute('data-reconciled'));
    setText('vtx-balance', btn.getAttribute('data-balance'));
    setText('vtx-description', btn.getAttribute('data-description'));

    var amountEl = document.getElementById('vtx-amount');
    if (amountEl) {
      var type = (btn.getAttribute('data-type') || '').toLowerCase();
      amountEl.classList.remove('acc-credit', 'acc-debit');
      amountEl.classList.add(type === 'credit' ? 'acc-credit' : 'acc-debit');
    }
  });
});
</script>
@endsection
