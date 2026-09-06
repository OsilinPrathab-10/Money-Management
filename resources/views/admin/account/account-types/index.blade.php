@extends('layouts/layoutMaster')

@section('title', __('Account types'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Account types') }}</h4>
      <p class="text-muted mb-0">{{ __('Used when building the chart of accounts.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'is_active' => request('is_active'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountTypeModal">
        <i class="ri-add-line me-1"></i> {{ __('Add account type') }}
      </button>
      <button type="button" class="btn btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#addChartOfAccountModal">
        <i class="ri-node-tree me-1"></i> {{ __('Add account') }}
      </button>
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.account-types.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-3 align-items-center">
        <div class="col-md-5">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search code or name...') }}">
          </div>
        </div>
        <div class="col-md-3">
          <select name="is_active" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.account-types.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Normal') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($accounttypes as $t)
            <tr>
              <td><code>{{ $t->code }}</code></td>
              <td>{{ $t->name }}</td>
              <td>{{ $t->category?->name ?? '—' }}</td>
              <td>{{ $t->normal_balance }}</td>
              <td>{{ $t->is_active ? __('Yes') : __('No') }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'deleteRoute' => route('account.account-types.destroy', $t),
                  'deleteConfirm' => __('Delete?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No account types yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
