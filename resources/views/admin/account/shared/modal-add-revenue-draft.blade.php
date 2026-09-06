<!-- Modal Add Revenue Draft -->
<div class="modal fade" id="addRevenueDraftModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('New revenue (draft)') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.revenues.store') }}" method="POST" onsubmit="const btn = this.querySelector('button[type=submit]'); btn.disabled = true; btn.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\' role=\'status\' aria-hidden=\'true\'></span>Saving...';">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="revenue_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Amount') }} <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">₹</span>
                <input type="number" step="0.01" min="0" name="amount" class="form-control" required>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Category') }} <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">{{ __('Select') }}</option>
                @foreach ($allRevenueCategories as $c)
                  <option value="{{ $c->id }}">{{ $c->category_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Bank account') }} <span class="text-danger">*</span></label>
              <select name="bank_account_id" class="form-select" required>
                <option value="">{{ __('Select') }}</option>
                @foreach ($allBankAccounts as $b)
                  <option value="{{ $b->id }}">{{ $b->account_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Revenue GL') }} <span class="text-danger">*</span></label>
              <select name="chart_of_account_id" class="form-select" required>
                <option value="">{{ __('Select') }}</option>
                @foreach ($revenueGlAccounts as $g)
                  <option value="{{ $g->id }}">{{ $g->account_code }} — {{ $g->account_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Reference #') }}</label>
              <input type="text" name="reference_number" class="form-control" placeholder="{{ __('Optional') }}">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }}</label>
              <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Describe this revenue...') }}"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save revenue draft') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
