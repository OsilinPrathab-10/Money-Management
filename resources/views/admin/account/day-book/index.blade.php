@extends('layouts/layoutMaster')

@section('title', __('Day Book'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Day Book') }}</h4>
      <p class="text-muted mb-0">{{ __('Daily revenue and expense summary.') }}</p>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'day' => $day ?? null,
          'status' => $status ?? null,
          'bank_account_id' => $bankAccountId ?? null,
          'revenue_category_id' => $revenueCategoryId ?? null,
          'expense_category_id' => $expenseCategoryId ?? null,
          'search' => $search ?? null,
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.day-book.export',
        'query' => $exportQuery,
      ])

      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">
        {{ __('Account dashboard') }}
      </a>
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
    <div class="card-body p-3">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-md-2">
          <input type="date" name="day" value="{{ $day }}" class="form-control" title="{{ __('Select Day') }}">
        </div>

        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="all">{{ __('All Status') }}</option>
            @foreach (['posted' => __('Posted'), 'approved' => __('Approved'), 'draft' => __('Draft')] as $key => $label)
              <option value="{{ $key }}" @selected(($status ?? 'posted') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Banks') }}</option>
            @foreach ($bankAccounts ?? [] as $b)
              <option value="{{ $b->id }}" @selected(($bankAccountId ?? null) == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <select name="revenue_category_id" class="form-select">
            <option value="">{{ __('All Revenue Cats') }}</option>
            @foreach ($revenueCategories ?? [] as $c)
              <option value="{{ $c->id }}" @selected(($revenueCategoryId ?? null) == $c->id)>{{ $c->category_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <select name="expense_category_id" class="form-select">
            <option value="">{{ __('All Expense Cats') }}</option>
            @foreach ($expenseCategories ?? [] as $c)
              <option value="{{ $c->id }}" @selected(($expenseCategoryId ?? null) == $c->id)>{{ $c->category_name }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-2">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('Search...') }}">
          </div>
        </div>

        <div class="col-md-12 d-flex justify-content-end gap-2 mt-2 mt-md-0">
          <button type="submit" class="btn btn-primary px-4">{{ __('Filter') }}</button>
          <a href="{{ route('account.day-book.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Revenues') }}</strong></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Number') }}</th>
                  <th>{{ __('Category') }}</th>
                  <th>{{ __('Bank') }}</th>
                  <th>{{ __('GL') }}</th>
                  <th class="text-end">{{ __('Amount') }}</th>
                  <th>{{ __('Status') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($revenues ?? [] as $r)
                  <tr>
                    <td><code>{{ $r->revenue_number }}</code></td>
                    <td>{{ $r->category?->category_name ?? '—' }}</td>
                    <td>{{ $r->bankAccount?->account_name ?? '—' }}</td>
                    <td><small>{{ $r->chartOfAccount?->account_code ?? '—' }}</small></td>
                    <td class="text-end">₹{{ number_format((float) ($r->amount ?? 0), 2) }}</td>
                    <td><span class="badge bg-label-{{ $r->status === 'posted' ? 'success' : ($r->status === 'approved' ? 'info' : 'warning') }}">{{ $r->status }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No revenue found for this day.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header"><strong>{{ __('Expenses') }}</strong></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>{{ __('Number') }}</th>
                  <th>{{ __('Category') }}</th>
                  <th>{{ __('Bank') }}</th>
                  <th>{{ __('GL') }}</th>
                  <th class="text-end">{{ __('Amount') }}</th>
                  <th>{{ __('Status') }}</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($expenses ?? [] as $e)
                  <tr>
                    <td><code>{{ $e->expense_number }}</code></td>
                    <td>{{ $e->category?->category_name ?? '—' }}</td>
                    <td>{{ $e->bankAccount?->account_name ?? '—' }}</td>
                    <td><small>{{ $e->chartOfAccount?->account_code ?? '—' }}</small></td>
                    <td class="text-end">₹{{ number_format((float) ($e->amount ?? 0), 2) }}</td>
                    <td><span class="badge bg-label-{{ $e->status === 'posted' ? 'success' : ($e->status === 'approved' ? 'info' : 'warning') }}">{{ $e->status }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No expense found for this day.') }}</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

