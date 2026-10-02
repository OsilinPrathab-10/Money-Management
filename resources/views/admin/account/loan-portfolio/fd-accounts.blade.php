@extends('layouts/layoutMaster')

@section('title', __('Fixed Deposits — Accounting'))

@section('content')
<div class="account-module">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Fixed Deposits') }}</h4>
      <p class="text-muted mb-0">{{ __('FD accounts linked to cashbook deposits & payouts') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="{{ route('fd.deposits.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Full FD module') }}</a>
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" action="{{ route('account.fd-accounts.index') }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">{{ __('Search') }}</label>
          <input type="text" name="search" value="{{ request('search') }}" class="form-control"
            placeholder="{{ __('FD number, client, scheme…') }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('Status') }}</label>
          <select name="status" class="form-select no-search">
            <option value="">{{ __('All') }}</option>
            @foreach (['active', 'matured', 'premature_closed', 'closed', 'renewed', 'cancelled'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Per page') }}</label>
          <select name="per_page" class="form-select no-search">
            @foreach ([10, 15, 20, 25, 50] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-primary me-2">{{ __('Filter') }}</button>
          <a href="{{ route('account.fd-accounts.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('FD #') }}</th>
            <th>{{ __('Client') }}</th>
            <th>{{ __('Scheme') }}</th>
            <th class="text-end">{{ __('Deposit') }}</th>
            <th class="text-end">{{ __('Maturity amt') }}</th>
            <th>{{ __('Maturity date') }}</th>
            <th>{{ __('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($fdAccounts as $fd)
            <tr>
              <td>
                <a href="{{ route('fd.deposits.show', $fd) }}"><code>{{ $fd->fd_number }}</code></a>
              </td>
              <td>
                {{ $fd->client?->client_name ?? '—' }}
                @if($fd->client?->client_phone)
                  <small class="text-muted d-block">{{ $fd->client->client_phone }}</small>
                @endif
              </td>
              <td>{{ $fd->scheme?->name ?? '—' }}</td>
              <td class="text-end">₹{{ number_format((float) $fd->deposit_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $fd->maturity_amount, 2) }}</td>
              <td>{{ $fd->maturity_date ? $fd->maturity_date->format('Y-m-d') : '—' }}</td>
              <td><span class="badge bg-label-secondary">{{ $fd->status_label ?? ucfirst(str_replace('_', ' ', (string) $fd->status)) }}</span></td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-5">{{ __('No fixed deposits found.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($fdAccounts->hasPages())
      <div class="card-footer">{{ $fdAccounts->links() }}</div>
    @endif
  </div>
</div>
@endsection
