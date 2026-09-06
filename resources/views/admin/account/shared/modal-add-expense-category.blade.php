<!-- Modal Add Expense Category -->
<div class="modal fade" id="addExpenseCategoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Add expense category') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.expense-categories.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Category name') }} <span class="text-danger">*</span></label>
              <input type="text" name="category_name" class="form-control" required placeholder="e.g. Office Supplies" oninput="this.value=this.value.replace(/[0-9]/g,'');">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Category code') }} <span class="text-danger">*</span></label>
              <input type="text" name="category_code" class="form-control" required placeholder="e.g. EXP-001">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('GL account') }} <span class="text-danger">*</span></label>
              <select name="gl_account_id" class="form-select" required>
                @foreach ($expenseGlAccounts as $g)
                  <option value="{{ $g->id }}">{{ $g->account_code }} — {{ $g->account_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Optional') }}"></textarea>
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
          <button type="submit" class="btn btn-primary">{{ __('Save category') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
