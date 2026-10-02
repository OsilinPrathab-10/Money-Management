@extends('layouts/layoutMaster')

@section('title', __('Revenues'))

@section('vendor-style')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
    @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Revenues Management'),
    'subtitle' => __('Record income; approve then post to the general ledger.'),
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addRevenueDraftModal"><i class="ri-add-line me-1"></i>' . e(__('New revenue')) . '</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addRevenueCategoryModal"><i class="ri-bookmark-2-line me-1"></i>' . e(__('Add category')) . '</button>',
  ])

  @php
    $exportQuery = array_filter([
      'search' => request('search'),
      'category_id' => request('category_id'),
      'status' => request('status'),
      'date_from' => request('date_from'),
      'date_to' => request('date_to'),
    ], fn($v) => !is_null($v) && $v !== '');
  @endphp
  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.revenues.export',
      'query' => $exportQuery,
    ])
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Revenues Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Income entries by category and bank account') }}</p>
      </div>
    </div>

    <div class="card-body border-top py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('Search') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search # or ref...') }}">
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Category') }}</label>
          <select name="category_id" class="form-select">
            <option value="">{{ __('All Categories') }}</option>
            @foreach ($categories as $c)
              <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->category_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            @foreach (['draft', 'approved', 'posted'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          @include('partials.date-range-filter', [
            'fromId' => 'revenueDateFrom',
            'toId' => 'revenueDateTo',
            'presetId' => 'revenueDatePreset',
            'fromName' => 'date_from',
            'toName' => 'date_to',
            'fromValue' => request('date_from'),
            'toValue' => request('date_to'),
            'presetValue' => request('date_preset', 'all'),
            'autoSubmit' => true,
            'size' => 'sm',
          ])
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.revenues.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Number') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Bank') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($revenues as $r)
            <tr>
              <td><a href="{{ route('account.revenues.show', $r) }}">{{ $r->revenue_number }}</a></td>
              <td>{{ $r->revenue_date?->format('Y-m-d') }}</td>
              <td>{{ $r->category?->category_name ?? '—' }}</td>
              <td><span class="acc-name">{{ $r->bankAccount?->account_name ?? '—' }}</span></td>
              <td class="text-end acc-credit">₹{{ number_format((float) $r->amount, 2) }}</td>
              <td><span class="badge bg-label-{{ $r->status === 'posted' ? 'success' : ($r->status === 'approved' ? 'info' : 'warning') }}">{{ $r->status }}</span></td>
              <td class="text-end">
                @include('admin.account.shared.table-actions', [
                  'viewUrl' => route('account.revenues.show', $r),
                  'approveUrl' => $r->status === 'draft' ? route('account.revenues.approve', $r) : null,
                  'postUrl' => $r->status === 'approved' ? route('account.revenues.post', $r) : null,
                  'deleteRoute' => $r->status === 'draft' ? route('account.revenues.destroy', $r) : null,
                  'deleteConfirm' => __('Delete this draft?'),
                ])
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No revenues yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($revenues->hasPages())
      <div class="card-footer">{{ $revenues->links() }}</div>
    @endif
  </div>
</div>
@endsection
