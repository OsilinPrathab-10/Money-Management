@extends('layouts/layoutMaster')

@section('title', __('Account categories'))

@section('content')
<div class="account-module">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
      <h4 class="mb-1">{{ __('Account categories') }}</h4>
      <p class="text-muted mb-0">{{ __('System categories used for grouping account types (Assets, Liabilities, Equity, Revenue, Expenses).') }}</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      @php
        $exportQuery = array_filter([
          'search' => request('search'),
          'is_active' => request('is_active'),
          'type' => request('type'),
        ], fn($v) => !is_null($v) && $v !== '');
      @endphp
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
        <i class="ri-add-line me-1"></i> {{ __('Add category') }}
      </button>
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => 'account.account-categories.export',
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
        <div class="col-md-4">
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Search name or code...') }}">
          </div>
        </div>
        <div class="col-md-3">
          <select name="type" class="form-select">
            <option value="">{{ __('All Types') }}</option>
            <option value="assets" @selected(request('type') === 'assets')>{{ __('Assets') }}</option>
            <option value="liabilities" @selected(request('type') === 'liabilities')>{{ __('Liabilities') }}</option>
            <option value="equity" @selected(request('type') === 'equity')>{{ __('Equity') }}</option>
            <option value="revenue" @selected(request('type') === 'revenue')>{{ __('Revenue') }}</option>
            <option value="expenses" @selected(request('type') === 'expenses')>{{ __('Expenses') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="is_active" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="1" @selected(request('is_active') === '1')>{{ __('Active') }}</option>
            <option value="0" @selected(request('is_active') === '0')>{{ __('Inactive') }}</option>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.account-categories.index') }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a>
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
            <th>{{ __('Type') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Active') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($accountcategories as $c)
            <tr>
              <td><code>{{ $c->code }}</code></td>
              <td>{{ $c->name }}</td>
              <td>
                @switch($c->type)
                  @case('assets') <span class="badge bg-label-success">{{ __('Assets') }}</span> @break
                  @case('liabilities') <span class="badge bg-label-danger">{{ __('Liabilities') }}</span> @break
                  @case('equity') <span class="badge bg-label-info">{{ __('Equity') }}</span> @break
                  @case('revenue') <span class="badge bg-label-primary">{{ __('Revenue') }}</span> @break
                  @case('expenses') <span class="badge bg-label-warning">{{ __('Expenses') }}</span> @break
                @endswitch
              </td>
              <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($c->description, 50) }}</small></td>
              <td>{{ $c->is_active ? __('Yes') : __('No') }}</td>
              <td class="text-end">
                <div class="d-inline-flex align-items-center gap-1">
                  <button type="button" 
                          class="btn btn-sm btn-icon btn-text-secondary rounded-pill edit-category-btn"
                          data-id="{{ $c->id }}"
                          data-name="{{ $c->name }}"
                          data-code="{{ $c->code }}"
                          data-type="{{ $c->type }}"
                          data-description="{{ $c->description }}"
                          data-active="{{ $c->is_active ? '1' : '0' }}"
                          data-update-url="{{ route('account.account-categories.update', $c) }}"
                          data-bs-toggle="modal"
                          data-bs-target="#editCategoryModal"
                          title="{{ __('Edit') }}">
                    <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
                  </button>

                  @include('admin.account.shared.table-actions', [
                    'deleteRoute' => route('account.account-categories.destroy', $c),
                    'deleteConfirm' => __('Are you sure you want to delete this category?'),
                  ])
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No categories found.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($accountcategories->hasPages())
      <div class="card-footer p-3 d-flex justify-content-center">
        {{ $accountcategories->links() }}
      </div>
    @endif
  </div>
</div>

{{-- Add Category Modal --}}
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add account category') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.account-categories.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
              <select name="type" class="form-select" required>
                <option value="">{{ __('Select') }}</option>
                <option value="assets">{{ __('Assets') }}</option>
                <option value="liabilities">{{ __('Liabilities') }}</option>
                <option value="equity">{{ __('Equity') }}</option>
                <option value="revenue">{{ __('Revenue') }}</option>
                <option value="expenses">{{ __('Expenses') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Current Assets">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Code') }} <span class="text-danger">*</span></label>
              <input type="text" name="code" class="form-control" required placeholder="e.g. ASSETS_CURR">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check form-switch mt-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="addCategoryActive" checked>
                <label class="form-check-label" for="addCategoryActive">{{ __('Is Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Category') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Edit Category Modal --}}
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit account category') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
              <select name="type" class="form-select" required>
                <option value="assets">{{ __('Assets') }}</option>
                <option value="liabilities">{{ __('Liabilities') }}</option>
                <option value="equity">{{ __('Equity') }}</option>
                <option value="revenue">{{ __('Revenue') }}</option>
                <option value="expenses">{{ __('Expenses') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Current Assets">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Code') }} <span class="text-danger">*</span></label>
              <input type="text" name="code" class="form-control" required placeholder="e.g. ASSETS_CURR">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
            </div>
            <div class="col-12">
              <div class="form-check form-switch mt-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editCategoryActive">
                <label class="form-check-label" for="editCategoryActive">{{ __('Is Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editCategoryModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const name = button.getAttribute('data-name');
        const code = button.getAttribute('data-code');
        const type = button.getAttribute('data-type');
        const description = button.getAttribute('data-description');
        const active = button.getAttribute('data-active') === '1';
        const updateUrl = button.getAttribute('data-update-url');

        const form = editModal.querySelector('form');
        form.action = updateUrl;

        editModal.querySelector('[name="name"]').value = name;
        editModal.querySelector('[name="code"]').value = code;
        editModal.querySelector('[name="type"]').value = type;
        editModal.querySelector('[name="description"]').value = description;
        editModal.querySelector('[name="is_active"]').checked = active;
      });
    }
  });
</script>
@endsection
