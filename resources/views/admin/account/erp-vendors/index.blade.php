@extends('layouts/layoutMaster')

@section('title', __('ERP vendors'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('ERP vendors') }}</h4>
      <p class="text-muted mb-0">{{ __('Accounting vendors (bills / AP).') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'company_name' => request('company_name'),
          'vendor_code' => request('vendor_code'),
          'tax_number' => request('tax_number'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.vendors.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('Company') }}</label>
          <input type="text" name="company_name" value="{{ request('company_name') }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Vendor code') }}</label>
          <input type="text" name="vendor_code" value="{{ request('vendor_code') }}" class="form-control">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Tax #') }}</label>
          <input type="text" name="tax_number" value="{{ request('tax_number') }}" class="form-control">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
          <a href="{{ route('account.vendors.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
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
          @forelse ($vendors as $v)
            <tr>
              <td><code>{{ $v->vendor_code }}</code></td>
              <td>{{ $v->company_name }}</td>
              <td>{{ $v->contact_person_name ?? '—' }}</td>
              <td>{{ $v->contact_person_email ?? $v->primary_email ?? '—' }}</td>
              <td>{{ $v->tax_number ?? '—' }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'deleteRoute' => route('account.vendors.destroy', $v),
                  'deleteConfirm' => __('Delete this vendor?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No ERP vendors yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($vendors->hasPages())
      <div class="card-footer">{{ $vendors->links() }}</div>
    @endif
  </div>
</div>
@endsection
