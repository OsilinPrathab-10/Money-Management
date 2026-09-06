@extends('layouts/layoutMaster')

@section('title', __('Bank transactions'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Bank transactions') }}</h4>
      <p class="text-muted mb-0">{{ __('Posted movements per bank account.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'bank_account_id' => request('bank_account_id'),
          'transaction_type' => request('transaction_type'),
          'search' => request('search'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.bank-transactions.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-md-3">
          <select name="bank_account_id" class="form-select">
            <option value="">{{ __('All Bank Accounts') }}</option>
            @foreach ($bankAccounts as $b)
              <option value="{{ $b->id }}" @selected(request('bank_account_id') == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <input type="text" name="transaction_type" value="{{ request('transaction_type') }}" class="form-control" placeholder="{{ __('Type (e.g. credit)') }}">
        </div>
        <div class="col-md-4">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search transactions...') }}">
          </div>
        </div>
        <div class="col-md-3 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.bank-transactions.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
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
            <th>{{ __('Bank') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th>{{ __('Reconciled') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($transactions as $t)
            <tr>
              <td>{{ $t->transaction_date?->format('Y-m-d') }}</td>
              <td>{{ $t->bankAccount?->account_name ?? '—' }}</td>
              <td>{{ $t->transaction_type }}</td>
              <td>{{ $t->reference_number ?? '—' }}</td>
              <td>{{ \Illuminate\Support\Str::limit($t->description ?? '', 40) }}</td>
              <td class="text-end">₹{{ number_format((float) $t->amount, 2) }}</td>
              <td>{{ $t->reconciliation_status ?? '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No transactions yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($transactions->hasPages())
      <div class="card-footer">{{ $transactions->links() }}</div>
    @endif
  </div>
</div>
@endsection
