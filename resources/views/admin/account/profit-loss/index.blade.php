@extends('layouts/layoutMaster')

@section('title', __('Profit & Loss'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Profit & Loss') }}</h4>
      <p class="text-muted mb-0">{{ __('Revenue minus expense for the selected period.') }}</p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'from_date' => $fromDate ?? null,
          'to_date' => $toDate ?? null,
          'status_mode' => $statusMode ?? null,
          'search' => $search ?? null,
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.profit-loss.export',
        'query' => $exportQuery,
      ])

      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">
        {{ __('Account dashboard') }}
      </a>
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
        <div class="col-md-3">
          <select name="status_mode" class="form-select">
            <option value="posted" @selected(($statusMode ?? 'posted') === 'posted')>{{ __('Posted Transactions Only') }}</option>
            <option value="all" @selected(($statusMode ?? 'posted') === 'all')>{{ __('All (Incl. Draft/Approved)') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search...') }}">
          </div>
        </div>
        <div class="col-md-3 d-flex gap-1 mt-2 mt-md-0">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter Report') }}</button>
          <a href="{{ route('account.profit-loss.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Revenue') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Expense') }}</span>
          <h4 class="mb-0">₹{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</h4>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card h-100 border-info">
        <div class="card-body">
          <span class="text-muted d-block">{{ __('Net profit') }}</span>
          <h4 class="mb-0 {{ ((float) ($totals['net_profit'] ?? 0)) >= 0 ? 'text-success' : 'text-danger' }}">
            ₹{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}
          </h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h5 class="mb-0">{{ __('Totals') }}</h5>
        <div class="text-muted">
          {{ __('From') }}: <strong>{{ $fromDate }}</strong> {{ __('To') }}: <strong>{{ $toDate }}</strong>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th>{{ __('Metric') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>{{ __('Revenue') }}</td>
              <td class="text-end">₹{{ number_format((float) ($totals['total_revenue'] ?? 0), 2) }}</td>
            </tr>
            <tr>
              <td>{{ __('Expense') }}</td>
              <td class="text-end">₹{{ number_format((float) ($totals['total_expense'] ?? 0), 2) }}</td>
            </tr>
            <tr>
              <td><strong>{{ __('Net profit (Revenue - Expense)') }}</strong></td>
              <td class="text-end">
                <strong class="{{ ((float) ($totals['net_profit'] ?? 0)) >= 0 ? 'text-success' : 'text-danger' }}">
                  ₹{{ number_format((float) ($totals['net_profit'] ?? 0), 2) }}
                </strong>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

   
</div>
@endsection

