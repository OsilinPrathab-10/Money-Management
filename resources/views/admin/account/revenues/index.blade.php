@extends('layouts/layoutMaster')

@section('title', __('Revenues'))

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
      <h4 class="mb-1">{{ __('Revenues') }}</h4>
      <p class="text-muted mb-0">{{ __('Record income; approve then post to the general ledger.') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'category_id' => request('category_id'),
          'status' => request('status'),
          'date_from' => request('date_from'),
          'date_to' => request('date_to'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRevenueDraftModal">
        <i class="ri-add-line me-1"></i> {{ __('New revenue') }}
      </button>
      <button type="button" class="btn btn-outline-secondary ms-2" data-bs-toggle="modal" data-bs-target="#addRevenueCategoryModal">
        <i class="ri-bookmark-2-line me-1 text-success"></i> {{ __('Add category') }}
      </button>
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.revenues.export',
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
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search # or ref...') }}">
          </div>
        </div>
        <div class="col-md-2">
          <select name="category_id" class="form-select">
            <option value="">{{ __('All Categories') }}</option>
            @foreach ($categories as $c)
              <option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->category_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            @foreach (['draft', 'approved', 'posted'] as $st)
              <option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <div class="input-group">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            <span class="input-group-text">to</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
          </div>
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.revenues.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
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
            <th>{{ __('Category') }}</th>
            <th>{{ __('Bank') }}</th>
            <th>{{ __('GL') }}</th>
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
              <td>{{ $r->bankAccount?->account_name ?? '—' }}</td>
              <td><small>{{ $r->chartOfAccount?->account_code ?? '—' }}</small></td>
              <td class="text-end">₹{{ number_format((float) $r->amount, 2) }}</td>
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
            <tr><td colspan="8" class="text-center text-muted py-5">{{ __('No revenues yet.') }}</td></tr>
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
