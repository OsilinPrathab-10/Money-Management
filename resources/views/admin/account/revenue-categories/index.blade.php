@extends('layouts/layoutMaster')

@section('title', __('Revenue categories'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Revenue categories') }}</h4>
      <p class="text-muted mb-0">{{ __('Mapped to revenue GL accounts (typically 4000–4999).') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'is_active' => request('is_active'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRevenueCategoryModal">
        <i class="ri-add-line me-1"></i> {{ __('Add category') }}
      </button>
      <button type="button" class="btn btn-outline-success ms-2" data-bs-toggle="modal" data-bs-target="#addRevenueDraftModal">
        <i class="ri-add-circle-line me-1"></i> {{ __('New revenue') }}
      </button>
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.revenue-categories.export',
        'query' => $exportQuery,
      ])
      <a href="{{ route('account.index') }}" class="btn btn-outline-secondary">{{ __('Account dashboard') }}</a>
    </div>
  </div>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-body p-3">
      <form method="get" class="row g-3 align-items-center">
        <div class="col-md-5">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search name or code...') }}">
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
          <a href="{{ route('account.revenue-categories.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
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
            <th>{{ __('GL') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($revenuecategories as $r)
            <tr>
              <td><code>{{ $r->category_code }}</code></td>
              <td>{{ $r->category_name }}</td>
              <td>{{ $r->gl_account?->account_name ?? '—' }}</td>
              <td>{{ $r->is_active ? __('Yes') : __('No') }}</td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'deleteRoute' => route('account.revenue-categories.destroy', $r),
                  'deleteConfirm' => __('Delete?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No categories yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
