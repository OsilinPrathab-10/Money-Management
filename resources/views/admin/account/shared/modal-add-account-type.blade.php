<!-- Modal Add Account Type -->
<div class="modal fade" id="addAccountTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add account type') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.account-types.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Category') }} <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">{{ __('Select') }}</option>
                @foreach ($accountCategories as $c)
                  <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Savings" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Code') }} <span class="text-danger">*</span></label>
              <input type="text" name="code" class="form-control" required placeholder="e.g. SAV" oninput="this.value=this.value.replace(/[^A-Za-z]/g,'');">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Normal balance') }} <span class="text-danger">*</span></label>
              <select name="normal_balance" class="form-select" required>
                <option value="0">{{ __('Debit') }}</option>
                <option value="1">{{ __('Credit') }}</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <input type="text" name="description" class="form-control" placeholder="{{ __('Optional') }}">
            </div>
            <div class="col-12 text-end">
              <div class="form-check form-switch d-inline-block">
                <input class="form-check-input" type="checkbox" name="is_active" value="1" checked>
                <label class="form-check-label">{{ __('Active') }}</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save account type') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
