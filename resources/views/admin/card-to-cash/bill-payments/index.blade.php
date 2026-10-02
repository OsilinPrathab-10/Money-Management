@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Bill Payments')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Credit Card Bill Payments</h4>
    <p class="text-muted mb-0">Record and monitor all credit card bill payments processed through gateways and accounts.</p>
  </div>
  <a href="{{ route('card-cash.leads.create') }}" class="btn btn-primary">
    <i class="ri-add-line me-1"></i> New Lead
  </a>
</div>

<!-- Filter Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.bill-payments.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="UTR, Customer, Lead #..." value="{{ request('search') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Payment Source</label>
        <select name="payment_source_id" class="form-select">
          <option value="">All Sources</option>
          @foreach ($paymentSources as $src)
            <option value="{{ $src->id }}" {{ request('payment_source_id') == $src->id ? 'selected' : '' }}>
              {{ $src->source_name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">From Date</label>
        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">To Date</label>
        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="ri-filter-3-line"></i> Filter</button>
        <a href="{{ route('card-cash.bill-payments.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Bill Payment Transactions ({{ $payments->total() }})</h5>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Customer</th>
          <th>Card Details</th>
          <th>Amount Paid</th>
          <th>Debit Wallet / Source</th>
          <th>UTR / Reference</th>
          <th>Payment Date</th>
          <th>Proof</th>
          <th>Processed By</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($payments as $p)
          <tr>
            <td>
              <a href="{{ route('card-cash.leads.show', $p->lead_id) }}" class="fw-bold text-primary">
                {{ optional($p->lead)->lead_number ?? "#{$p->lead_id}" }}
              </a>
            </td>
            <td>
              <span class="fw-medium text-heading">{{ optional($p->customer)->customer_name ?? 'N/A' }}</span>
              <br><small class="text-muted">{{ optional($p->customer)->phone_number ?? '' }}</small>
            </td>
            <td>
              <span class="fw-medium">{{ optional($p->lead)->card_name ?? 'N/A' }}</span>
              <br><small class="text-muted">{{ optional($p->lead)->csr_bank_name ?? '' }}</small>
            </td>
            <td>
              <span class="fw-bold fs-6 text-success">₹{{ number_format($p->amount, 2) }}</span>
            </td>
            <td>
              @if ($p->is_split_wallet)
                <span class="badge bg-label-info" title="{{ $p->wallet_summary }}">
                  <i class="ri-git-merge-line me-1"></i> Split Wallets
                </span>
                @if (!empty($p->wallet_split_breakdown))
                  <div class="mt-1" style="max-width: 220px;">
                    @foreach ($p->wallet_split_breakdown as $item)
                      <span class="badge bg-white text-dark border shadow-xs fs-tiny mb-1">
                        {{ $item['wallet_name'] ?? 'Wallet' }}: ₹{{ number_format($item['amount'] ?? 0, 2) }}
                      </span>
                    @endforeach
                  </div>
                @endif
                @if ($p->paymentSource)
                  <div class="small text-primary mt-1">Source: {{ $p->paymentSource->source_name }}</div>
                @endif
              @else
                @if ($p->wallet)
                  <span class="badge bg-label-success">
                    <i class="ri-wallet-3-line me-1"></i> {{ $p->wallet->wallet_name }}
                  </span>
                  <br><small class="text-muted font-monospace">{{ $p->wallet->wallet_code }}</small>
                @endif
                @if ($p->paymentSource)
                  <div class="{{ $p->wallet ? 'mt-1' : '' }}">
                    <span class="badge bg-label-primary">{{ $p->paymentSource->source_name }}</span>
                    @if ($p->paymentSource->source_type)
                      <small class="text-muted text-capitalize d-block">{{ $p->paymentSource->source_type }}</small>
                    @endif
                  </div>
                @elseif (!$p->wallet)
                  <span class="badge bg-label-secondary">Default</span>
                @endif
              @endif
            </td>
            <td>
              <span class="font-monospace fw-medium">{{ $p->transaction_reference ?: ($p->gateway_reference ?: '-') }}</span>
            </td>
            <td>
              <small class="text-muted">{{ $p->payment_date ? $p->payment_date->format('d M Y, h:i A') : '-' }}</small>
            </td>
            <td>
              @if ($p->proof_url)
                <a href="{{ $p->proof_url }}" target="_blank" class="btn btn-xs btn-label-primary">
                  <i class="ri-attachment-line me-1"></i> Screenshot
                </a>
              @else
                <span class="text-muted small">No proof</span>
              @endif
            </td>
            <td>
              <small class="text-muted">{{ optional($p->processor)->name ?? 'System' }}</small>
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.processing.process', $p->lead_id) }}" class="btn btn-sm btn-outline-primary">
                View / Process
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-center py-5 text-muted">
              No bill payment records found.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($payments->hasPages())
    <div class="card-footer py-3">
      {{ $payments->links() }}
    </div>
  @endif
</div>
@endsection
