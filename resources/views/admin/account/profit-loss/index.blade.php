@extends('layouts/layoutMaster')

@section('title', __('Profit & Loss'))

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('content')
@php
  use App\Services\Account\AccountingTags;
  $exportQuery = array_filter([
    'from_date' => $fromDate ?? null,
    'to_date' => $toDate ?? null,
    'status_mode' => $statusMode ?? null,
    'module_tag' => ($moduleTag ?? 'all') !== 'all' ? $moduleTag : null,
    'bank_account_id' => $bankAccountId ?? null,
    'client_id' => $clientId ?? null,
    'search' => $search ?? null,
  ], fn($v) => !is_null($v) && $v !== '');
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Profit & Loss Management'),
    'subtitle' => __('Revenue minus expense for the selected period — overall Loan, Chit & FD.'),
  ])

  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.profit-loss.export',
      'query' => $exportQuery,
    ])
  </div>

  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header border-bottom py-3">
      <h5 class="card-title mb-0 fw-bold">{{ __('Filters') }}</h5>
      <p class="text-muted mb-0 small">{{ __('Filter by date, module, client, bank and status') }}</p>
    </div>
    <div class="card-body py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-12">
          @include('partials.date-range-filter', [
            'fromId' => 'pnlDateFrom',
            'toId' => 'pnlDateTo',
            'presetId' => 'pnlDatePreset',
            'fromName' => 'from_date',
            'toName' => 'to_date',
            'fromValue' => $fromDate,
            'toValue' => $toDate,
            'presetValue' => request('date_preset', 'all'),
            'autoSubmit' => true,
            'size' => 'sm',
          ])
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Client') }}</label>
          <select name="client_id" id="pnlClientFilter" class="form-select select2">
            <option value="">{{ __('All clients') }}</option>
            @foreach ($clients ?? [] as $c)
              <option value="{{ $c->id }}" @selected(($clientId ?? null) == $c->id)>{{ $c->client_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Module') }}</label>
          <select name="module_tag" class="form-select">
            <option value="all">{{ __('All modules') }}</option>
            @foreach ($moduleOptions ?? [] as $m)
              <option value="{{ $m }}" @selected(($moduleTag ?? 'all') === $m)>{{ $m }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Bank') }}</label>
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Banks') }}</option>
            @foreach ($bankAccounts ?? [] as $b)
              <option value="{{ $b->id }}" @selected(($bankAccountId ?? null) == $b->id)>
                {{ $b->account_name }}@if($b->bank_name) ({{ $b->bank_name }})@endif
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Status') }}</label>
          <select name="status_mode" class="form-select">
            <option value="posted" @selected(($statusMode ?? 'posted') === 'posted')>{{ __('Posted only') }}</option>
            <option value="all" @selected(($statusMode ?? 'posted') === 'all')>{{ __('All (draft/approved/posted)') }}</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label small fw-bold">{{ __('Search') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search #, ref, description...') }}">
          </div>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter Report') }}</button>
          <a href="{{ route('account.profit-loss.index') }}" class="btn btn-outline-secondary" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card card-border-shadow-success h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Revenue') }}</span>
          <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-border-shadow-danger h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Expense') }}</span>
          <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-border-shadow-info h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Net profit') }}</span>
          <h4 class="mb-0 {{ ((float) ($totals['net_profit'] ?? 0)) >= 0 ? 'acc-credit' : 'acc-debit' }}">
            ₹{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}
          </h4>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    @foreach (($moduleBreakdown ?? []) as $row)
      @php
        $badge = AccountingTags::moduleBadgeClass($row['module']);
      @endphp
      <div class="col-md-6 col-xl-3">
        <div class="card h-100 shadow-sm border-0">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="badge {{ $badge }}">{{ $row['module'] }}</span>
              <small class="text-muted">{{ __('Net') }}</small>
            </div>
            <h4 class="mb-3 {{ ((float) $row['net']) >= 0 ? 'acc-credit' : 'acc-debit' }}">
              ₹{{ number_format((float) $row['net'], 2) }}
            </h4>
            <div class="d-flex justify-content-between small mb-1">
              <span class="text-muted">{{ __('Revenue') }}</span>
              <span class="acc-credit">₹{{ number_format((float) $row['revenue'], 2) }}</span>
            </div>
            <div class="d-flex justify-content-between small">
              <span class="text-muted">{{ __('Expense') }}</span>
              <span class="acc-debit">₹{{ number_format((float) $row['expense'], 2) }}</span>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header border-bottom py-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-0 fw-bold">{{ __('Profit & Loss summary') }}</h5>
          <p class="text-muted mb-0 small">{{ __('Overall and module-wise revenue vs expense') }}</p>
        </div>
        <div class="text-muted small">
          {{ __('From') }}: <strong>{{ $fromDate }}</strong>
          ·
          {{ __('To') }}: <strong>{{ $toDate }}</strong>
        </div>
      </div>
    </div>
    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Module') }}</th>
            <th class="text-end acc-credit">{{ __('Revenue') }}</th>
            <th class="text-end acc-debit">{{ __('Expense') }}</th>
            <th class="text-end">{{ __('Net (Rev − Exp)') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse (($moduleBreakdown ?? []) as $row)
            <tr>
              <td>
                <span class="badge {{ AccountingTags::moduleBadgeClass($row['module']) }}">{{ $row['module'] }}</span>
              </td>
              <td class="text-end acc-credit">₹{{ number_format((float) $row['revenue'], 2) }}</td>
              <td class="text-end acc-debit">₹{{ number_format((float) $row['expense'], 2) }}</td>
              <td class="text-end fw-semibold {{ ((float) $row['net']) >= 0 ? 'acc-credit' : 'acc-debit' }}">
                ₹{{ number_format((float) $row['net'], 2) }}
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-muted py-4">{{ __('No module data found.') }}</td>
            </tr>
          @endforelse
        </tbody>
        <tfoot>
          <tr class="fw-semibold">
            <td>{{ __('Overall total') }}</td>
            <td class="text-end acc-credit">₹{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</td>
            <td class="text-end acc-debit">₹{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</td>
            <td class="text-end {{ ((float) ($totals['net_profit'] ?? 0)) >= 0 ? 'acc-credit' : 'acc-debit' }}">
              ₹{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn.select2) {
    jQuery('#pnlClientFilter').select2({
      placeholder: @json(__('All clients')),
      allowClear: true,
      width: '100%'
    });
  }
});
</script>
@endsection
