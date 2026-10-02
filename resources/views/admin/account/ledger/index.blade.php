@extends('layouts/layoutMaster')

@section('title', __('Ledger'))

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/select2/select2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/select2/select2.js'])
@endsection

@section('content')
@php
  use App\Services\Account\AccountingTags;
  $perPage = $perPage ?? 25;
  $perPageOptions = $perPageOptions ?? [25, 50, 100, 250, 500];
  $exportQuery = array_filter([
    'from_date' => $fromDate ?? null,
    'to_date' => $toDate ?? null,
    'status' => $status ?? null,
    'module_tag' => ($moduleTag ?? 'all') !== 'all' ? $moduleTag : null,
    'entry_tag' => $entryTag ?? null,
    'bank_account_id' => $bankAccountId ?? null,
    'client_id' => $clientId ?? null,
    'source' => ($source ?? 'all') !== 'all' ? $source : null,
    'search' => $search ?? null,
  ], fn($v) => !is_null($v) && $v !== '');
@endphp
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Operational Ledger Management'),
    'subtitle' => __('Tagged Chit / Loan / FD bank, revenue, expense & dividend movements.'),
  ])

  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.ledger.export',
      'query' => $exportQuery,
    ])
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-danger h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Total debit') }}</span>
          <h4 class="mb-0 acc-debit">₹{{ number_format((float) ($totals['total_debit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card card-border-shadow-success h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Total credit') }}</span>
          <h4 class="mb-0 acc-credit">₹{{ number_format((float) ($totals['total_credit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Chit fees') }}</span>
          <h5 class="mb-0">₹{{ number_format((float) ($totals['chit_fees'] ?? 0), 2) }}</h5>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Loan fees') }}</span>
          <h5 class="mb-0">₹{{ number_format((float) ($totals['loan_fees'] ?? 0), 2) }}</h5>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-2">
      <div class="card h-100 shadow-sm border-0">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('FD fees') }}</span>
          <h5 class="mb-0">₹{{ number_format((float) ($totals['fd_fees'] ?? 0), 2) }}</h5>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Operational Ledger Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Filter by date, module, client, bank, source and more') }}</p>
      </div>
    </div>

    <div class="card-body border-top py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-12">
          @include('partials.date-range-filter', [
            'fromId' => 'ledgerDateFrom',
            'toId' => 'ledgerDateTo',
            'presetId' => 'ledgerDatePreset',
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
          <select name="client_id" id="ledgerClientFilter" class="form-select select2">
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
          <label class="form-label small fw-bold">{{ __('Entry tag') }}</label>
          <select name="entry_tag" class="form-select">
            <option value="">{{ __('All entry tags') }}</option>
            @foreach ($entryOptions ?? [] as $e)
              <option value="{{ $e }}" @selected(($entryTag ?? null) === $e)>{{ AccountingTags::entryLabel($e) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Source') }}</label>
          <select name="source" class="form-select">
            <option value="all">{{ __('All sources') }}</option>
            @foreach ($sourceOptions ?? [] as $s)
              <option value="{{ $s }}" @selected(($source ?? 'all') === $s)>{{ $s }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
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
          <select name="status" class="form-select">
            <option value="all">{{ __('All Status') }}</option>
            @foreach (['posted' => __('Posted'), 'approved' => __('Approved'), 'draft' => __('Draft')] as $key => $label)
              <option value="{{ $key }}" @selected(($status ?? 'posted') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Search') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Ref, description, bank...') }}">
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select">
            @foreach ($perPageOptions as $n)
              <option value="{{ $n }}" @selected((int) $perPage === (int) $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.ledger.index') }}" class="btn btn-outline-secondary" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Tags') }}</th>
            <th>{{ __('Bank') }}</th>
            <th>{{ __('Ref') }}</th>
            <th>{{ __('Source') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="text-end acc-debit">{{ __('Debit') }}</th>
            <th class="text-end acc-credit">{{ __('Credit') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($ledger ?? [] as $line)
            <tr>
              <td>{{ $line['date'] ?? '—' }}</td>
              <td>
                @if (!empty($line['module_tag']))
                  <span class="badge {{ AccountingTags::moduleBadgeClass($line['module_tag']) }}">{{ $line['module_tag'] }}</span>
                @endif
                @if (!empty($line['entry_tag']))
                  <span class="badge bg-label-secondary">{{ AccountingTags::entryLabel($line['entry_tag']) }}</span>
                @endif
              </td>
              <td>
                @if (!empty($line['bank']))
                  <div class="acc-name">{{ $line['bank'] }}</div>
                  @if (!empty($line['bank_name']))
                    <small class="text-muted">{{ $line['bank_name'] }}</small>
                  @endif
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td><code>{{ $line['ref'] ?? '—' }}</code></td>
              <td><span class="badge bg-label-secondary">{{ $line['source'] ?? '—' }}</span></td>
              <td>
                <div class="text-break" style="white-space: normal; max-width: 360px;">{{ $line['description'] ?? '' }}</div>
              </td>
              <td class="text-end acc-debit {{ ((float) ($line['debit'] ?? 0)) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) ($line['debit'] ?? 0), 2) }}</td>
              <td class="text-end acc-credit {{ ((float) ($line['credit'] ?? 0)) <= 0 ? 'is-zero' : '' }}">₹{{ number_format((float) ($line['credit'] ?? 0), 2) }}</td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-5">{{ __('No ledger data found.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($ledger)
      <div class="card-footer border-top">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <form method="get" action="{{ route('account.ledger.index') }}" class="d-flex align-items-center gap-2">
            @foreach (request()->except(['per_page', 'page']) as $key => $value)
              @if (is_array($value))
                @continue
              @endif
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <label class="mb-0 small text-muted">{{ __('Show') }}</label>
            <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
              @foreach ($perPageOptions as $n)
                <option value="{{ $n }}" @selected((int) $perPage === (int) $n)>{{ $n }}</option>
              @endforeach
            </select>
            <span class="small text-muted">{{ __('entries') }}</span>
          </form>

          <div class="d-flex flex-wrap align-items-center gap-2">
            <small class="text-muted">
              {{ __('Showing') }}
              {{ $ledger->firstItem() ?? 0 }}–{{ $ledger->lastItem() ?? 0 }}
              {{ __('of') }}
              {{ number_format($ledger->total()) }}
            </small>
            @if ($ledger->hasPages())
              <div>{{ $ledger->onEachSide(1)->links() }}</div>
            @endif
          </div>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.jQuery && jQuery.fn.select2) {
    jQuery('#ledgerClientFilter').select2({
      placeholder: @json(__('All clients')),
      allowClear: true,
      width: '100%'
    });
  }
});
</script>
@endsection
