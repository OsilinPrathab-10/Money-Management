@extends('layouts/layoutMaster')

@section('title', __('Ledger'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Ledger') }}</h4>
      <p class="text-muted mb-0">{{ __('General ledger journal lines (debits and credits).') }}</p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'from_date' => $fromDate ?? null,
          'to_date' => $toDate ?? null,
          'status' => $status ?? null,
          'account_id' => $accountId ?? null,
          'search' => $search ?? null,
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.ledger.export',
        'query' => $exportQuery,
      ])

      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">
        {{ __('Account dashboard') }}
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Total debit') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_debit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Total credit') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_credit'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-md-3">
          <div class="input-group">
            <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control" title="{{ __('From Date') }}">
            <span class="input-group-text">to</span>
            <input type="date" name="to_date" value="{{ $toDate }}" class="form-control" title="{{ __('To Date') }}">
          </div>
        </div>

        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="all">{{ __('All Status') }}</option>
            @foreach (['posted' => __('Posted'), 'draft' => __('Draft'), 'reversed' => __('Reversed')] as $key => $label)
              <option value="{{ $key }}" @selected(($status ?? 'posted') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <select name="account_id" class="form-select">
            <option value="">{{ __('All Accounts (GL)') }}</option>
            @foreach ($chartOfAccounts ?? [] as $a)
              <option value="{{ $a->id }}" @selected(($accountId ?? null) == $a->id)>{{ $a->account_code }} — {{ $a->account_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search...') }}">
          </div>
        </div>

        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.ledger.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Journal') }}</th>
            <th>{{ __('Account') }}</th>
            <th class="text-end">{{ __('Debit') }}</th>
            <th class="text-end">{{ __('Credit') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($ledger ?? [] as $line)
            <tr>
              <td>{{ $line->journal_date ?? '—' }}</td>
              <td><code>{{ $line->journal_number ?? '—' }}</code></td>
              <td>
                <small>{{ $line->account_code ?? '—' }}</small><br>
                <span class="text-muted">{{ $line->account_name ?? '' }}</span>
              </td>
              <td class="text-end">₹{{ number_format((float) ($line->debit_amount ?? 0), 2) }}</td>
              <td class="text-end">₹{{ number_format((float) ($line->credit_amount ?? 0), 2) }}</td>
              <td>{{ $line->line_description ?? ($line->journal_description ?? '') }}</td>
              <td>
                <span class="badge bg-label-{{ ($line->journal_status ?? '') === 'posted' ? 'success' : (($line->journal_status ?? '') === 'draft' ? 'warning' : 'secondary') }}">
                  {{ $line->journal_status ?? '—' }}
                </span>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No ledger data found.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($ledger && $ledger->hasPages())
      <div class="card-footer">{{ $ledger->links() }}</div>
    @endif
  </div>
</div>
@endsection

