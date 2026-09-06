@extends('layouts/layoutMaster')

@section('title', __('Bank accounts'))

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  @include('admin.account.shared.page-header', [
    'title' => __('Bank accounts'),
    'subtitle' => __('Operating accounts linked to GL codes 1000–1099.'),
    'icon' => 'ri-bank-line',
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addBankModal"><i class="ri-add-line me-1"></i>' . e(__('Add bank account')) . '</button>
    
    <!-- <button type="button" class="btn btn-sm btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#addRevenueDraftModal"><i class="ri-add-circle-line me-1"></i>' . e(__('New revenue')) . '
  </button>
  <button type="button" class="btn btn-sm btn-outline-danger me-2" data-bs-toggle="modal" data-bs-target="#addExpenseDraftModal"><i class="ri-add-circle-line me-1"></i>' . e(__('New expense')) . '
</button> -->

<a href="' . e(route('account.index')) . '" class="btn btn-sm btn-outline-primary"><i class="icon-base ri ri-dashboard-3-line me-1"></i>' . e(__('Account dashboard')) . '</a>',
  ])

  @php
    $exportQuery = array_filter([
      'account_number' => request('account_number'),
      'bank_name' => request('bank_name'),
      'is_active' => request('is_active'),
    ], fn($v) => !is_null($v) && $v !== '');
  @endphp
  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.bank-accounts.export',
      'query' => $exportQuery,
      'disablePdf' => true,
    ])
  </div>

  {{-- Flash alerts handled globally by SweetAlert2 in scripts.blade.php --}}

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-3 align-items-center">
        <div class="col-md-5">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="account_number" value="{{ request('account_number') }}" class="form-control" placeholder="{{ __('Search number, name, bank...') }}">
          </div>
        </div>
        <div class="col-md-3">
          <select name="is_active" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="per_page" class="form-select">
            @foreach ([10, 25, 50, 100] as $n)
              <option value="{{ $n }}" @selected((int) request('per_page', 20) === $n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.bank-accounts.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Account #') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Bank') }}</th>
            <th>{{ __('Branch') }}</th>
            <th>{{ __('Type') }}</th>
            <th>{{ __('GL') }}</th>
            <th class="text-end">{{ __('Opening') }}</th>
            <th class="text-end">{{ __('Current') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($bankaccounts as $ba)
            <tr>
              <td><code>{{ $ba->account_number }}</code></td>
              <td>{{ $ba->account_name }}</td>
              <td>{{ $ba->bank_name }}</td>
              <td>{{ $ba->branch_name ?? '—' }}</td>
              <td>{{ $ba->account_type }}</td>
              <td>
                @if ($ba->gl_account)
                  <small>{{ $ba->gl_account->account_code }}</small><br>
                  <span class="text-muted">{{ $ba->gl_account->account_name }}</span>
                @else
                  —
                @endif
              </td>
              <td class="text-end">₹{{ number_format((float) $ba->opening_balance, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $ba->current_balance, 2) }}</td>
              <td>{{ $ba->is_active ? __('Yes') : __('No') }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'editUrl' => route('account.bank-accounts.edit', $ba),
                  'deleteRoute' => route('account.bank-accounts.destroy', $ba),
                  'deleteConfirm' => __('Delete this bank account?'),
                ])
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="text-center text-muted py-5">{{ __('No bank accounts yet. Add one above or run AccountModuleDemoSeeder.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($bankaccounts->hasPages())
      <div class="card-footer">{{ $bankaccounts->links() }}</div>
    @endif
  </div>
</div>
@endsection
