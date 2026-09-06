@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Accounting'))

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">{{ $pageTitle ?? __('Accounting') }}</h4>
    <a href="{{ url('/account') }}" class="btn btn-sm btn-outline-secondary">
      <i class="icon-base ri ri-dashboard-line me-1"></i>{{ __('Account dashboard') }}
    </a>
  </div>

  @if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if (!empty($exportRoute))
    @php
      $exportQuery = array_filter(($payload['filters'] ?? []), fn($v) => !is_null($v) && $v !== '');
    @endphp
    <div class="mb-3">
      @include('admin.account.reports._export-toolbar', [
        'exportRoute' => $exportRoute,
        'query' => $exportQuery,
      ])
    </div>
  @endif

  @if (!empty($payload['filters']) && is_array($payload['filters']))
    <div class="card mb-3">
      <div class="card-body">
        <form method="get" action="{{ url()->current() }}" class="row g-3 align-items-end">
          @php
            $filters = $payload['filters'] ?? [];
          @endphp

          @if (array_key_exists('search', $filters))
            <div class="col-md-3">
              <label class="form-label">{{ __('Search') }}</label>
              <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('Keyword / number') }}">
            </div>
          @endif

          @if (array_key_exists('status', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('Status') }}</label>
              <input type="text" name="status" value="{{ request('status') }}" class="form-control" placeholder="{{ __('e.g. posted') }}">
            </div>
          @endif

          @if (array_key_exists('date_from', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('From') }}</label>
              <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
          @endif

          @if (array_key_exists('date_to', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('To') }}</label>
              <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
          @endif

          @if (array_key_exists('bank_account_id', $filters) && !empty($payload['bankAccounts']))
            <div class="col-md-3">
              <label class="form-label">{{ __('Bank account') }}</label>
              <select name="bank_account_id" class="form-select">
                <option value="">{{ __('All') }}</option>
                @foreach ($payload['bankAccounts'] as $b)
                  <option value="{{ $b->id }}" @selected(request('bank_account_id') == $b->id)>{{ $b->account_name }}</option>
                @endforeach
              </select>
            </div>
          @endif

          @if (array_key_exists('vendor_id', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('Vendor ID') }}</label>
              <input type="number" name="vendor_id" value="{{ request('vendor_id') }}" class="form-control" min="1">
            </div>
          @endif

          @if (array_key_exists('customer_id', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('Customer ID') }}</label>
              <input type="number" name="customer_id" value="{{ request('customer_id') }}" class="form-control" min="1">
            </div>
          @endif

          @if (array_key_exists('purchase_return_id', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('Purchase return') }}</label>
              <input type="number" name="purchase_return_id" value="{{ request('purchase_return_id') }}" class="form-control" min="1">
            </div>
          @endif

          @if (array_key_exists('sales_return_id', $filters))
            <div class="col-md-2">
              <label class="form-label">{{ __('Sales return') }}</label>
              <input type="number" name="sales_return_id" value="{{ request('sales_return_id') }}" class="form-control" min="1">
            </div>
          @endif

          <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">{{ __('Filter') }}</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  @php
    $renderType = null;
    if (isset($payload['debitNotes'])) $renderType = 'debit-notes';
    if (isset($payload['creditNotes'])) $renderType = 'credit-notes';
    if (isset($payload['payments']) && isset($payload['customers'])) $renderType = 'customer-payments';
  @endphp

  <div class="card">
    <div class="card-body">
      @if ($renderType === 'debit-notes')
        @php $notes = $payload['debitNotes'] ?? []; @endphp
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Vendor') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($notes as $n)
                <tr>
                  <td><code>{{ $n->debit_note_number }}</code></td>
                  <td>{{ $n->debit_note_date?->format('Y-m-d') }}</td>
                  <td>{{ $n->vendor?->company_name ?? $n->vendor?->name ?? '—' }}</td>
                  <td>
                    <span class="badge bg-label-{{ $n->status === 'approved' ? 'success' : ($n->status === 'draft' ? 'warning' : 'info') }}">
                      {{ $n->status ?? '—' }}
                    </span>
                  </td>
                  <td class="text-end">₹{{ number_format((float) ($n->total_amount ?? 0), 2) }}</td>
                  <td class="text-end">
                    @include('admin.account.shared.table-actions', [
                      'viewUrl' => route('account.debit-notes.show', $n),
                      'approveUrl' => $n->status === 'draft' ? route('account.debit-notes.approve', $n) : null,
                      'deleteRoute' => $n->status === 'draft' ? route('account.debit-notes.destroy', $n) : null,
                      'deleteConfirm' => __('Delete this draft?'),
                    ])
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No debit notes found.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if (is_object($notes) && method_exists($notes, 'hasPages') && $notes->hasPages())
          <div class="mt-3">{{ $notes->links() }}</div>
        @endif

      @elseif ($renderType === 'credit-notes')
        @php $notes = $payload['creditNotes'] ?? []; @endphp
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Number') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($notes as $n)
                <tr>
                  <td><code>{{ $n->credit_note_number }}</code></td>
                  <td>{{ $n->credit_note_date?->format('Y-m-d') }}</td>
                  <td>{{ $n->customer?->company_name ?? $n->customer?->name ?? '—' }}</td>
                  <td>
                    <span class="badge bg-label-{{ $n->status === 'approved' ? 'success' : ($n->status === 'draft' ? 'warning' : 'info') }}">
                      {{ $n->status ?? '—' }}
                    </span>
                  </td>
                  <td class="text-end">₹{{ number_format((float) ($n->total_amount ?? 0), 2) }}</td>
                  <td class="text-end">
                    @include('admin.account.shared.table-actions', [
                      'viewUrl' => route('account.credit-notes.show', $n),
                      'approveUrl' => $n->status === 'draft' ? route('account.credit-notes.approve', $n) : null,
                      'deleteRoute' => $n->status === 'draft' ? route('account.credit-notes.destroy', $n) : null,
                      'deleteConfirm' => __('Delete this draft?'),
                    ])
                  </td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-5">{{ __('No credit notes found.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if (is_object($notes) && method_exists($notes, 'hasPages') && $notes->hasPages())
          <div class="mt-3">{{ $notes->links() }}</div>
        @endif

      @elseif ($renderType === 'customer-payments')
        @php $payments = $payload['payments'] ?? []; @endphp
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Payment #') }}</th>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Bank') }}</th>
                <th>{{ __('Reference') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse ($payments as $p)
                <tr>
                  <td><code>{{ $p->payment_number }}</code></td>
                  <td>{{ $p->payment_date?->format('Y-m-d') }}</td>
                  <td>{{ $p->customer?->company_name ?? $p->customer?->name ?? '—' }}</td>
                  <td>{{ $p->bankAccount?->account_name ?? '—' }}</td>
                  <td>{{ $p->reference_number ?? '—' }}</td>
                  <td class="text-end">₹{{ number_format((float) ($p->payment_amount ?? 0), 2) }}</td>
                  <td>
                    <span class="badge bg-label-{{ $p->status === 'cleared' ? 'success' : ($p->status === 'pending' ? 'warning' : 'info') }}">
                      {{ $p->status ?? '—' }}
                    </span>
                  </td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-5">{{ __('No customer payments found.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        @if (is_object($payments) && method_exists($payments, 'hasPages') && $payments->hasPages())
          <div class="mt-3">{{ $payments->links() }}</div>
        @endif

      @else
        <div class="text-muted">{{ __('Nothing to render for this ERP shared resource.') }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
