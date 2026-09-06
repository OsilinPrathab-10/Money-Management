@extends('layouts/layoutMaster')

@section('title', __('Bank transfers'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Bank transfers') }}</h4>
      <p class="text-muted mb-0">{{ __('Transfers between your bank accounts.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'transfer_number' => request('transfer_number'),
          'status' => request('status'),
          'from_account_id' => request('from_account_id'),
          'to_account_id' => request('to_account_id'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.bank-transfers.export',
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
        <div class="col-md-2">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="transfer_number" value="{{ request('transfer_number') }}" class="form-control" placeholder="{{ __('Transfer #') }}">
          </div>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="draft" @selected(request('status') === 'draft')>{{ __('Draft') }}</option>
            <option value="posted" @selected(request('status') === 'posted')>{{ __('Posted') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="from_account_id" class="form-select">
            <option value="">{{ __('From Account') }}</option>
            @foreach ($bankaccounts as $b)
              <option value="{{ $b->id }}" @selected(request('from_account_id') == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select name="to_account_id" class="form-select">
            <option value="">{{ __('To Account') }}</option>
            @foreach ($bankaccounts as $b)
              <option value="{{ $b->id }}" @selected(request('to_account_id') == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.bank-transfers.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Number') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('From') }}</th>
            <th>{{ __('To') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($banktransfers as $tr)
            <tr>
              <td><code>{{ $tr->transfer_number }}</code></td>
              <td>{{ $tr->transfer_date?->format('Y-m-d') }}</td>
              <td>{{ $tr->fromAccount?->account_name ?? '—' }}</td>
              <td>{{ $tr->toAccount?->account_name ?? '—' }}</td>
              <td class="text-end">₹{{ number_format((float) $tr->transfer_amount, 2) }}</td>
              <td><span class="badge bg-label-secondary">{{ $tr->status }}</span></td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No transfers yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($banktransfers->hasPages())
      <div class="card-footer">{{ $banktransfers->links() }}</div>
    @endif
  </div>
</div>
@endsection
