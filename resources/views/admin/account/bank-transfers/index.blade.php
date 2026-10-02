@extends('layouts/layoutMaster')

@section('title', __('Bank transfers'))

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/sweetalert2/sweetalert2.js'])
@endsection

@section('content')
<div class="account-module">
  @include('admin.account.shared.page-header', [
    'title' => __('Bank Transfers Management'),
    'subtitle' => __('Manage and authorize transfers between operating bank accounts.'),
    'toolbar' => '<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTransferModal"><i class="ri-add-line me-1"></i>' . e(__('Create bank transfer')) . '</button>',
  ])

  @php
    $exportQuery = array_filter([
      'transfer_number' => request('transfer_number'),
      'status' => request('status'),
      'from_account_id' => request('from_account_id'),
      'to_account_id' => request('to_account_id'),
    ], fn($v) => !is_null($v) && $v !== '');
  @endphp
  <div class="mb-4">
    @include('admin.account.reports._export-toolbar', [
      'exportRoute' => 'account.bank-transfers.export',
      'query' => $exportQuery,
      'disablePdf' => true,
    ])
  </div>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <div class="card border-0 shadow-sm">
    <div class="card-header d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 border-bottom py-3">
      <div>
        <h5 class="card-title mb-0 fw-bold">{{ __('Bank Transfers Directory') }}</h5>
        <p class="text-muted mb-0 small">{{ __('Transfers between company bank accounts') }}</p>
      </div>
    </div>

    <div class="card-body border-top py-3">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Transfer #') }}</label>
          <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="text" name="transfer_number" value="{{ request('transfer_number') }}" class="form-control" placeholder="{{ __('Transfer #') }}">
          </div>
        </div>
        <div class="col-md-2">
          <label class="form-label small fw-bold">{{ __('Status') }}</label>
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
            <option value="completed" @selected(request('status') === 'completed')>{{ __('Completed') }}</option>
            <option value="failed" @selected(request('status') === 'failed')>{{ __('Failed') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('From') }}</label>
          <select name="from_account_id" class="form-select">
            <option value="">{{ __('From Account') }}</option>
            @foreach ($bankaccounts as $b)
              <option value="{{ $b->id }}" @selected(request('from_account_id') == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold">{{ __('To') }}</label>
          <select name="to_account_id" class="form-select">
            <option value="">{{ __('To Account') }}</option>
            @foreach ($bankaccounts as $b)
              <option value="{{ $b->id }}" @selected(request('to_account_id') == $b->id)>{{ $b->account_name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2 d-flex gap-1">
          <button type="submit" class="btn btn-primary flex-grow-1">{{ __('Filter') }}</button>
          <a href="{{ route('account.bank-transfers.index') }}" class="btn btn-outline-secondary px-2" title="{{ __('Reset') }}"><i class="ri-refresh-line"></i></a>
        </div>
      </form>
    </div>

    <div class="card-datatable table-responsive">
      <table class="account-datatable table table-hover">
        <thead class="table-light">
          <tr>
            <th>{{ __('Number') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('From') }}</th>
            <th>{{ __('To') }}</th>
            <th class="text-end">{{ __('Amount') }}</th>
            <th class="text-end">{{ __('Charges') }}</th>
            <th class="text-end">{{ __('Total Debit') }}</th>
            <th>{{ __('Reference #') }}</th>
            <th>{{ __('Status') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($banktransfers as $tr)
            <tr>
              <td><code>{{ $tr->transfer_number }}</code></td>
              <td>{{ $tr->transfer_date?->format('Y-m-d') }}</td>
              <td><span class="acc-name">{{ $tr->fromAccount?->account_name ?? '—' }}</span></td>
              <td><span class="acc-name">{{ $tr->toAccount?->account_name ?? '—' }}</span></td>
              <td class="text-end">₹{{ number_format((float) $tr->transfer_amount, 2) }}</td>
              <td class="text-end">₹{{ number_format((float) $tr->transfer_charges, 2) }}</td>
              <td class="text-end acc-debit">₹{{ number_format((float) $tr->total_debit, 2) }}</td>
              <td><small>{{ $tr->reference_number ?? '—' }}</small></td>
              <td>
                @if ($tr->status === 'completed')
                  <span class="badge bg-label-success">{{ __('Completed') }}</span>
                @elseif ($tr->status === 'failed')
                  <span class="badge bg-label-danger">{{ __('Failed') }}</span>
                @else
                  <span class="badge bg-label-warning">{{ __('Pending') }}</span>
                @endif
              </td>
              <td class="text-end">
                @if ($tr->status === 'pending')
                  <div class="d-inline-flex gap-1 align-items-center">
                    <form action="{{ route('account.bank-transfers.process', $tr) }}" method="POST" class="process-transfer-form">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-success px-2 py-1" style="font-size: 0.75rem;" title="{{ __('Process Transfer') }}">
                        <i class="ri-check-line me-1"></i>{{ __('Process') }}
                      </button>
                    </form>
                    
                    <button type="button" class="btn btn-sm btn-icon btn-outline-primary edit-transfer-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#editTransferModal"
                            data-id="{{ $tr->id }}"
                            data-action="{{ route('account.bank-transfers.update', $tr) }}"
                            data-date="{{ $tr->transfer_date?->format('Y-m-d') }}"
                            data-from="{{ $tr->from_account_id }}"
                            data-to="{{ $tr->to_account_id }}"
                            data-amount="{{ $tr->transfer_amount }}"
                            data-charges="{{ $tr->transfer_charges }}"
                            data-ref="{{ $tr->reference_number }}"
                            data-desc="{{ $tr->description }}">
                      <i class="ri-edit-line"></i>
                    </button>

                    <form action="{{ route('account.bank-transfers.destroy', $tr) }}" method="POST" class="delete-transfer-form">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" title="{{ __('Delete') }}">
                        <i class="ri-delete-bin-7-line"></i>
                      </button>
                    </form>
                  </div>
                @else
                  <span class="text-muted"><small>—</small></span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="10" class="text-center text-muted py-5">{{ __('No transfers yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($banktransfers->hasPages())
      <div class="card-footer">{{ $banktransfers->links() }}</div>
    @endif
  </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="addTransferModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Create Bank Transfer') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="{{ route('account.bank-transfers.store') }}" method="POST">
        @csrf
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Transfer Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('From Account (Source)') }} <span class="text-danger">*</span></label>
              <select name="from_account_id" class="form-select" required>
                <option value="">{{ __('Select Source Account') }}</option>
                @foreach ($bankaccounts as $b)
                  <option value="{{ $b->id }}">{{ $b->account_name }} (₹{{ number_format($b->current_balance, 2) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('To Account (Destination)') }} <span class="text-danger">*</span></label>
              <select name="to_account_id" class="form-select" required>
                <option value="">{{ __('Select Destination Account') }}</option>
                @foreach ($bankaccounts as $b)
                  <option value="{{ $b->id }}">{{ $b->account_name }} (₹{{ number_format($b->current_balance, 2) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Transfer Amount (₹)') }} <span class="text-danger">*</span></label>
              <input type="number" name="transfer_amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Transfer Charges (₹)') }}</label>
              <input type="number" name="transfer_charges" step="0.01" min="0" class="form-control" placeholder="0.00">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Reference Number') }}</label>
              <input type="text" name="reference_number" class="form-control" placeholder="{{ __('Transaction Reference ID / Receipt') }}">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }} <span class="text-danger">*</span></label>
              <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Purpose of transfer...') }}" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Submit') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editTransferModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Bank Transfer') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editTransferForm" method="POST">
        @csrf
        @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">{{ __('Transfer Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="transfer_date" id="edit_transfer_date" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('From Account (Source)') }} <span class="text-danger">*</span></label>
              <select name="from_account_id" id="edit_from_account_id" class="form-select" required>
                <option value="">{{ __('Select Source Account') }}</option>
                @foreach ($bankaccounts as $b)
                  <option value="{{ $b->id }}">{{ $b->account_name }} (₹{{ number_format($b->current_balance, 2) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('To Account (Destination)') }} <span class="text-danger">*</span></label>
              <select name="to_account_id" id="edit_to_account_id" class="form-select" required>
                <option value="">{{ __('Select Destination Account') }}</option>
                @foreach ($bankaccounts as $b)
                  <option value="{{ $b->id }}">{{ $b->account_name }} (₹{{ number_format($b->current_balance, 2) }})</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Transfer Amount (₹)') }} <span class="text-danger">*</span></label>
              <input type="number" name="transfer_amount" id="edit_transfer_amount" step="0.01" min="0.01" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Transfer Charges (₹)') }}</label>
              <input type="number" name="transfer_charges" id="edit_transfer_charges" step="0.01" min="0" class="form-control" placeholder="0.00">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Reference Number') }}</label>
              <input type="text" name="reference_number" id="edit_reference_number" class="form-control" placeholder="{{ __('Transaction Reference ID / Receipt') }}">
            </div>
            <div class="col-12">
              <label class="form-label">{{ __('Description') }} <span class="text-danger">*</span></label>
              <textarea name="description" id="edit_description" class="form-control" rows="3" placeholder="{{ __('Purpose of transfer...') }}" required></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Populate Edit Modal
  const editButtons = document.querySelectorAll('.edit-transfer-btn');
  editButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      const form = document.getElementById('editTransferForm');
      form.action = this.getAttribute('data-action');
      document.getElementById('edit_transfer_date').value = this.getAttribute('data-date');
      document.getElementById('edit_from_account_id').value = this.getAttribute('data-from');
      document.getElementById('edit_to_account_id').value = this.getAttribute('data-to');
      document.getElementById('edit_transfer_amount').value = this.getAttribute('data-amount');
      document.getElementById('edit_transfer_charges').value = this.getAttribute('data-charges') || 0;
      document.getElementById('edit_reference_number').value = this.getAttribute('data-ref') || '';
      document.getElementById('edit_description').value = this.getAttribute('data-desc') || '';
    });
  });

  // Delete Confirmation
  const deleteForms = document.querySelectorAll('.delete-transfer-form');
  deleteForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      Swal.fire({
        title: '{{ __('Delete transfer?') }}',
        text: '{{ __('This action cannot be undone.') }}',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ea5455',
        cancelButtonColor: '#8592a3',
        confirmButtonText: '{{ __('Yes, delete') }}',
        cancelButtonText: '{{ __('Cancel') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  // Process Confirmation
  const processForms = document.querySelectorAll('.process-transfer-form');
  processForms.forEach(form => {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      Swal.fire({
        title: '{{ __('Process bank transfer?') }}',
        text: '{{ __('This will deduct the transfer amount plus transfer charges (₹) from the debited source bank account and credit the destination account.') }}',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28c76f',
        cancelButtonColor: '#8592a3',
        confirmButtonText: '{{ __('Yes, process') }}',
        cancelButtonText: '{{ __('Cancel') }}'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    });
  });

  // Validate same bank selection on Create & Edit modals
  function bindBankSelectValidation(fromSelect, toSelect, form) {
    if (!fromSelect || !toSelect) return;

    function checkSameAccount() {
      const fromVal = fromSelect.value;
      const toVal = toSelect.value;

      if (fromVal && toVal && fromVal === toVal) {
        toSelect.value = '';
        Swal.fire({
          title: '{{ __('Invalid Bank Selection') }}',
          text: '{{ __('Source and Destination bank accounts cannot be the same account.') }}',
          icon: 'warning',
          confirmButtonColor: '#ea5455'
        });
      }
    }

    fromSelect.addEventListener('change', checkSameAccount);
    toSelect.addEventListener('change', checkSameAccount);

    if (form) {
      form.addEventListener('submit', function(e) {
        const fromVal = fromSelect.value;
        const toVal = toSelect.value;
        if (fromVal && toVal && fromVal === toVal) {
          e.preventDefault();
          Swal.fire({
            title: '{{ __('Invalid Bank Selection') }}',
            text: '{{ __('Source and Destination bank accounts cannot be the same account.') }}',
            icon: 'error',
            confirmButtonColor: '#ea5455'
          });
        }
      });
    }
  }

  const addModalForm = document.querySelector('#addTransferModal form');
  if (addModalForm) {
    const addFrom = addModalForm.querySelector('[name="from_account_id"]');
    const addTo = addModalForm.querySelector('[name="to_account_id"]');
    bindBankSelectValidation(addFrom, addTo, addModalForm);
  }

  const editModalForm = document.getElementById('editTransferForm');
  if (editModalForm) {
    const editFrom = document.getElementById('edit_from_account_id');
    const editTo = document.getElementById('edit_to_account_id');
    bindBankSelectValidation(editFrom, editTo, editModalForm);
  }
});
</script>
@endsection
