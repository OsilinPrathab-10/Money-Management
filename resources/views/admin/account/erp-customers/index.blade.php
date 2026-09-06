@extends('layouts/layoutMaster')

@section('title', __('ERP customers'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('ERP customers') }}</h4>
      <p class="text-muted mb-0">{{ __('Accounting customers (invoicing / AR).') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'company_name' => request('company_name'),
          'customer_code' => request('customer_code'),
          'tax_number' => request('tax_number'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.customers.export',
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
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="company_name" value="{{ request('company_name') }}" class="form-control" placeholder="{{ __('Company Name') }}">
          </div>
        </div>
        <div class="col-md-3">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-hashtag"></i></span>
            <input type="text" name="customer_code" value="{{ request('customer_code') }}" class="form-control" placeholder="{{ __('Customer Code') }}">
          </div>
        </div>
        <div class="col-md-3">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-id-card-line"></i></span>
            <input type="text" name="tax_number" value="{{ request('tax_number') }}" class="form-control" placeholder="{{ __('Tax #') }}">
          </div>
        </div>
        <div class="col-md-3 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.customers.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
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
            <th>{{ __('Company') }}</th>
            <th>{{ __('Contact') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Tax #') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($customers as $c)
            <tr>
              <td><code>{{ $c->customer_code }}</code></td>
              <td>{{ $c->company_name }}</td>
              <td>{{ $c->contact_person_name ?? '—' }}</td>
              <td>{{ $c->contact_person_email ?? '—' }}</td>
              <td>{{ $c->tax_number ?? '—' }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'deleteRoute' => route('account.customers.destroy', $c),
                  'deleteConfirm' => __('Delete this customer?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No ERP customers yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($customers->hasPages())
      <div class="card-footer">{{ $customers->links() }}</div>
    @endif
  </div>
</div>
@endsection
