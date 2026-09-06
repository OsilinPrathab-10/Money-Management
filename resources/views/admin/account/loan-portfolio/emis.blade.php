@extends('layouts/layoutMaster')

@section('title', __('EMIs — Accounting'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('EMI schedule') }}</h4>
      <p class="text-muted mb-0">{{ __('Data from `emis` (linked to loan accounts)') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'status' => request('status'),
          'loan_account_id' => request('loan_account_id'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.emis.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('account.emis.index') }}" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('Search') }}</label>
          <input type="text" name="search" value="{{ request('search') }}" class="form-control"
            placeholder="{{ __('Instalment #, ref, account #…') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All') }}</option>
            @foreach (['pending', 'paid', 'partial', 'overdue', 'waived'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Loan account ID') }}</label>
          <input type="number" name="loan_account_id" value="{{ request('loan_account_id') }}" class="form-control"
            placeholder="{{ __('Optional') }}" min="1">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select">
            @foreach ([15, 20, 50, 100] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary me-2">{{ __('Filter') }}</button>
          <a href="{{ route('account.emis.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>{{ __('Loan account') }}</th>
            <th>{{ __('Client') }}</th>
            <th>{{ __('Instalment') }}</th>
            <th class="text-end">{{ __('Total due') }}</th>
            <th class="text-end">{{ __('Paid') }}</th>
            <th class="text-end">{{ __('Pending') }}</th>
            <th>{{ __('Due date') }}</th>
            <th>{{ __('Status') }}</th>
            <th>{{ __('Risk') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($emis as $emi)
            <tr>
              <td>{{ $emi->id }}</td>
              <td>
                @if ($emi->loanAccount)
                  <code>{{ $emi->loanAccount->account_number }}</code>
                @else
                  —
                @endif
              </td>
              <td>{{ $emi->loanAccount?->client?->client_name ?? '—' }}</td>
              <td>{{ $emi->instalment_number }}</td>
              <td class="text-end">₹{{ number_format((float) $emi->total_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $emi->paid_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $emi->pending_amount, 2) }}</td>
              <td>{{ $emi->due_date ? $emi->due_date->format('Y-m-d') : '—' }}</td>
              <td><span class="badge bg-label-info">{{ $emi->status ?? '—' }}</span></td>
              <td><span class="badge bg-label-secondary">{{ $emi->risk_level_label }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="text-center text-muted py-5">{{ __('No EMIs found.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($emis->hasPages())
      <div class="card-footer">{{ $emis->links() }}</div>
    @endif
  </div>
</div>
@endsection
