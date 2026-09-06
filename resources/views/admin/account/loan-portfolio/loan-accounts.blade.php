@extends('layouts/layoutMaster')

@section('title', __('Loan accounts — Accounting'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Loan accounts') }}</h4>
      <p class="text-muted mb-0">{{ __('Data from `loan_accounts` (core loan app)') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'status' => request('status'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.loan-accounts.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('account.loan-accounts.index') }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">{{ __('Search') }}</label>
          <input type="text" name="search" value="{{ request('search') }}" class="form-control"
            placeholder="{{ __('Account #, loan code, client…') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All') }}</option>
            @foreach (['active', 'closed', 'defaulted', 'foreclosed'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select">
            @foreach ([10, 15, 25, 50] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary me-2">{{ __('Filter') }}</button>
          <a href="{{ route('account.loan-accounts.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Account #') }}</th>
            <th>{{ __('Client') }}</th>
            <th>{{ __('Loan code') }}</th>
            <th class="text-end">{{ __('Loan amount') }}</th>
            <th class="text-end">{{ __('Outstanding') }}</th>
            <th class="text-end">{{ __('EMI') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Disbursed') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($loanAccounts as $la)
            <tr>
              <td><code>{{ $la->account_number }}</code></td>
              <td>{{ $la->client?->client_name ?? '—' }}</td>
              <td>{{ $la->loan_code ?? '—' }}</td>
              <td class="text-end">₹{{ number_format((float) $la->loan_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $la->outstanding_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $la->emi_amount, 2) }}</td>
              <td><span class="badge bg-label-secondary">{{ $la->status ?? '—' }}</span></td>
              <td>{{ $la->disbursed_at ? $la->disbursed_at->format('Y-m-d') : '—' }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">{{ __('No loan accounts found.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($loanAccounts->hasPages())
      <div class="card-footer">{{ $loanAccounts->links() }}</div>
    @endif
  </div>
</div>
@endsection
