@extends('layouts/layoutMaster')

@section('title', __('Chart of accounts'))

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Chart of accounts') }}</h4>
      <p class="text-muted mb-0">{{ __('GL accounts for your company (filtered by your user scope).') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'account_code' => request('account_code'),
          'account_type_id' => request('account_type_id'),
          'normal_balance' => request('normal_balance'),
          'is_active' => request('is_active'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChartOfAccountModal">
        <i class="ri-add-line me-1"></i> {{ __('Add account') }}
      </button>
      <button type="button" class="btn btn-outline-warning ms-2" data-bs-toggle="modal" data-bs-target="#addAccountTypeModal">
        <i class="ri-shapes-line me-1"></i> {{ __('Add type') }}
      </button>
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.chart-of-accounts.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  {{-- Flash alerts handled globally by SweetAlert2 in scripts.blade.php --}}

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-2 align-items-center">
        <div class="col-md-3">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="account_code" value="{{ request('account_code') }}" class="form-control" placeholder="{{ __('Search code or name...') }}">
          </div>
        </div>
        <div class="col-md-2">
          <select name="account_type_id" class="form-select">
            <option value="all">{{ __('All Types') }}</option>
            @foreach ($accounttypes ?? [] as $t)
              <option value="{{ $t->id }}" @selected(request('account_type_id') == $t->id)>{{ $t->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="normal_balance" class="form-select">
            <option value="all">{{ __('All Balances') }}</option>
            <option value="debit" @selected(request('normal_balance') === 'debit')>{{ __('Debit') }}</option>
            <option value="credit" @selected(request('normal_balance') === 'credit')>{{ __('Credit') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="is_active" class="form-select">
            <option value="all">{{ __('All Status') }}</option>
            <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="per_page" class="form-select">
            @foreach ([10, 25, 50, 100] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }} {{ __('per page') }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.chart-of-accounts.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th style="width:50px">{{ __('S.No') }}</th>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('Normal') }}</th>
            <th class="text-end">{{ __('Opening') }}</th>
            <th class="text-end">{{ __('Current') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($chartofaccounts as $row)
            <tr>
              <td class="text-muted small">{{ $loop->iteration + ($chartofaccounts->currentPage() - 1) * $chartofaccounts->perPage() }}</td>
              <td><code>{{ $row->account_code }}</code></td>
              <td>{{ $row->account_name }}</td>
              <td>{{ $row->account_type?->name ?? '—' }}</td>
              <td><span class="badge bg-label-secondary">{{ $row->normal_balance }}</span></td>
              <td class="text-end">₹{{ number_format((float) $row->opening_balance, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $row->current_balance, 2) }}</td>
              <td>{{ $row->is_active ? __('Yes') : __('No') }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'viewUrl' => route('account.chart-of-accounts.show', $row),
                ])
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">{{ __('No chart of accounts yet. Run demo seeder or create accounts from the ERP forms / API.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($chartofaccounts->hasPages())
      <div class="card-footer">{{ $chartofaccounts->links() }}</div>
    @endif
  </div>
  </div>
  @include('admin.account.shared.modal-add-chart-of-account')
@endsection
