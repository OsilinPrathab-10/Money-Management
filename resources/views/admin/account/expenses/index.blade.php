@extends('layouts/layoutMaster')

@section('title', __('Expenses'))

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Expenses Management'),
    'subtitle' => __('Record costs; approve then post to the general ledger.'),
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseDraftModal"><i class="ri-add-line me-1"></i>' . e(__('New expense')) . '</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addExpenseCategoryModal"><i class="ri-bookmark-2-line me-1"></i>' . e(__('Add category')) . '</button>',
  ])

  @php
    $exportQuery = array_filter([
      'search' => request('search'),
      'category_id' => request('category_id'),
      'bank_account_id' => request('bank_account_id'),
      'status' => request('status'),
      'date_preset' => request('date_preset'),
      'date_from' => request('date_from'),
      'date_to' => request('date_to'),
    ], fn($v) => !is_null($v) && $v !== '');
  @endphp
  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.expenses.export',
      'query' => $exportQuery,
    ])
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Expenses Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Cost entries by category and bank account') }}</p>
      </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle py-3">
      <form method="get" action="{{ route('account.expenses.index') }}" class="row g-3 align-items-end">
        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-search-line me-1 text-primary"></i>{{ __('Search Expenses') }}
          </label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search #, ref, description...') }}">
          </div>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-bookmark-2-line me-1 text-info"></i>{{ __('Category') }}
          </label>
          <select name="category_id" class="form-select">
            <option value="">{{ __('All Categories') }}</option>
            @foreach ($categories as $c)
              <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->category_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-bank-line me-1 text-secondary"></i>{{ __('Bank Account') }}
          </label>
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Bank Accounts') }}</option>
            @foreach ($bankAccounts as $ba)
              <option value="{{ $ba->id }}" @selected(request('bank_account_id') == $ba->id)>{{ $ba->account_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-checkbox-circle-line me-1 text-success"></i>{{ __('Status') }}
          </label>
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            @foreach (['draft', 'approved', 'posted'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-xl-1 col-md-4 col-sm-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill" title="{{ __('Filter') }}">
            <i class="ri-filter-3-line"></i>
          </button>
          <a href="{{ route('account.expenses.index') }}" class="btn btn-outline-secondary" title="{{ __('Reset') }}">
            <i class="ri-refresh-line"></i>
          </a>
        </div>
        <div class="col-12 pt-2 border-top">
          @include('partials.date-range-filter', [
            'fromId' => 'expenseDateFrom',
            'toId' => 'expenseDateTo',
            'presetId' => 'expenseDatePreset',
            'fromName' => 'date_from',
            'toName' => 'date_to',
            'fromValue' => request('date_from'),
            'toValue' => request('date_to'),
            'presetValue' => request('date_preset', 'all'),
            'autoSubmit' => true,
            'size' => 'sm',
          ])
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Number') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Bank') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($expenses as $e)
            @php
              $vData = [
                'data-expense-number' => $e->expense_number,
                'data-date' => $e->expense_date?->format('Y-m-d'),
                'data-category' => $e->category?->category_name ?? '—',
                'data-bank' => $e->bankAccount?->account_name ?? '—',
                'data-amount' => '₹' . number_format((float) $e->amount, 2),
                'data-status' => ucfirst($e->status),
                'data-status-badge' => $e->status === 'posted' ? 'success' : ($e->status === 'approved' ? 'info' : 'warning'),
                'data-reference' => $e->reference_number ?? '—',
                'data-description' => $e->description ?? '—',
                'data-approve-url' => $e->status === 'draft' ? route('account.expenses.approve', $e) : '',
                'data-post-url' => $e->status === 'approved' ? route('account.expenses.post', $e) : '',
                'data-full-url' => route('account.expenses.show', $e),
              ];
            @endphp
            <tr>
              <td>
                <a href="#"
                   class="fw-bold text-primary view-expense-modal-trigger"
                   data-bs-toggle="modal"
                   data-bs-target="#viewExpenseModal"
                   @foreach($vData as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach>
                  {{ $e->expense_number }}
                </a>
              </td>
              <td>{{ $e->expense_date?->format('Y-m-d') }}</td>
              <td>{{ $e->category?->category_name ?? '—' }}</td>
              <td><span class="acc-name">{{ $e->bankAccount?->account_name ?? '—' }}</span></td>
              <td class="text-end acc-debit">₹{{ number_format((float) $e->amount, 2) }}</td>
              <td><span class="badge bg-label-{{ $e->status === 'posted' ? 'success' : ($e->status === 'approved' ? 'info' : 'warning') }}">{{ ucfirst($e->status) }}</span></td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'viewModalTarget' => '#viewExpenseModal',
                  'viewModalClass' => 'view-expense-modal-trigger',
                  'viewModalData' => $vData,
                  'viewUrl' => route('account.expenses.show', $e),
                  'approveUrl' => $e->status === 'draft' ? route('account.expenses.approve', $e) : null,
                  'postUrl' => $e->status === 'approved' ? route('account.expenses.post', $e) : null,
                  'deleteRoute' => $e->status === 'draft' ? route('account.expenses.destroy', $e) : null,
                  'deleteConfirm' => __('Delete this draft?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No expenses yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($expenses->hasPages())
      <div class="card-footer">{{ $expenses->links() }}</div>
    @endif
  </div>
</div>

<!-- Expense View Popup Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-bottom bg-light">
        <div>
          <h5 class="modal-title fw-bold text-primary mb-0" id="expModalNumber">EXP-0000</h5>
          <small class="text-muted">{{ __('Expense Details') }}</small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-3">
          <div class="col-md-4 col-sm-6">
            <span class="text-muted small d-block mb-1"><i class="ri-calendar-line me-1 text-primary"></i>{{ __('Expense Date') }}</span>
            <strong id="expModalDate" class="text-dark fs-6">—</strong>
          </div>
          <div class="col-md-4 col-sm-6">
            <span class="text-muted small d-block mb-1"><i class="ri-money-rupee-circle-line me-1 text-success"></i>{{ __('Amount') }}</span>
            <strong id="expModalAmount" class="text-success fs-5 fw-bold">₹0.00</strong>
          </div>
          <div class="col-md-4 col-sm-6">
            <span class="text-muted small d-block mb-1"><i class="ri-checkbox-circle-line me-1 text-info"></i>{{ __('Status') }}</span>
            <span id="expModalStatusBadge" class="badge bg-label-info">—</span>
          </div>

          <div class="col-md-6 col-sm-6">
            <span class="text-muted small d-block mb-1"><i class="ri-bookmark-2-line me-1 text-warning"></i>{{ __('Category') }}</span>
            <span id="expModalCategory" class="fw-semibold text-dark">—</span>
          </div>
          <div class="col-md-6 col-sm-6">
            <span class="text-muted small d-block mb-1"><i class="ri-bank-line me-1 text-secondary"></i>{{ __('Bank Account') }}</span>
            <span id="expModalBank" class="fw-semibold text-dark">—</span>
          </div>

          <div class="col-12">
            <span class="text-muted small d-block mb-1"><i class="ri-hashtag me-1 text-muted"></i>{{ __('Reference Number') }}</span>
            <span id="expModalReference" class="text-dark fw-medium">—</span>
          </div>

          <div class="col-12">
            <span class="text-muted small d-block mb-1"><i class="ri-file-text-line me-1 text-muted"></i>{{ __('Description') }}</span>
            <div id="expModalDescription" class="p-3 bg-light rounded-2 text-dark small border">—</div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top bg-light d-flex justify-content-between align-items-center">
        <a id="expModalFullLink" href="#" class="btn btn-outline-primary btn-sm">
          <i class="ri-external-link-line me-1"></i>{{ __('Open Full Page') }}
        </a>
        <div class="d-flex gap-2 align-items-center">
          <form id="expModalApproveForm" method="post" action="" class="d-none">
            @csrf
            <button type="submit" class="btn btn-success btn-sm">
              <i class="ri-checkbox-circle-line me-1"></i>{{ __('Approve') }}
            </button>
          </form>
          <form id="expModalPostForm" method="post" action="" class="d-none">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="ri-send-plane-line me-1"></i>{{ __('Post to Ledger') }}
            </button>
          </form>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">{{ __('Close') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.addEventListener('click', function(e) {
    const trigger = e.target.closest('.view-expense-modal-trigger');
    if (!trigger) return;

    document.getElementById('expModalNumber').textContent = trigger.getAttribute('data-expense-number') || 'Expense';
    document.getElementById('expModalDate').textContent = trigger.getAttribute('data-date') || '—';
    document.getElementById('expModalAmount').textContent = trigger.getAttribute('data-amount') || '₹0.00';

    const badge = document.getElementById('expModalStatusBadge');
    badge.textContent = trigger.getAttribute('data-status') || '—';
    badge.className = 'badge bg-label-' + (trigger.getAttribute('data-status-badge') || 'info');

    document.getElementById('expModalCategory').textContent = trigger.getAttribute('data-category') || '—';
    document.getElementById('expModalBank').textContent = trigger.getAttribute('data-bank') || '—';
    document.getElementById('expModalReference').textContent = trigger.getAttribute('data-reference') || '—';
    document.getElementById('expModalDescription').textContent = trigger.getAttribute('data-description') || '—';

    document.getElementById('expModalFullLink').href = trigger.getAttribute('data-full-url') || '#';

    const approveForm = document.getElementById('expModalApproveForm');
    const approveUrl = trigger.getAttribute('data-approve-url');
    if (approveUrl) {
      approveForm.action = approveUrl;
      approveForm.classList.remove('d-none');
    } else {
      approveForm.classList.add('d-none');
    }

    const postForm = document.getElementById('expModalPostForm');
    const postUrl = trigger.getAttribute('data-post-url');
    if (postUrl) {
      postForm.action = postUrl;
      postForm.classList.remove('d-none');
    } else {
      postForm.classList.add('d-none');
    }
  });
});
</script>
@endsection
