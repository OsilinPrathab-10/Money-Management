@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Swipe Transactions')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Card Swipe Transactions</h4>
    <p class="text-muted mb-0">Track all card swipe withdrawals, gateway terminals, and wallet deductions.</p>
  </div>
  <a href="{{ route('card-cash.leads.create') }}" class="btn btn-primary">
    <i class="ri-add-line me-1"></i> New Lead
  </a>
</div>

<!-- Filter Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.swipes.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Ref, Customer, Lead #..." value="{{ request('search') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Gateway</label>
        <select name="withdrawal_gateway_id" class="form-select">
          <option value="">All Gateways</option>
          @foreach ($gateways as $gw)
            <option value="{{ $gw->id }}" {{ request('withdrawal_gateway_id') == $gw->id ? 'selected' : '' }}>
              {{ $gw->gateway_name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Wallet</label>
        <select name="wallet_id" class="form-select">
          <option value="">All Wallets</option>
          @foreach ($wallets as $w)
            <option value="{{ $w->id }}" {{ request('wallet_id') == $w->id ? 'selected' : '' }}>
              {{ $w->wallet_name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">From Date</label>
        <input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}">
      </div>
      <div class="col-md-1">
        <label class="form-label small fw-medium">To Date</label>
        <input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="ri-filter-3-line"></i> Filter</button>
        <a href="{{ route('card-cash.swipes.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Swipe Transactions ({{ $swipes->total() }})</h5>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Customer</th>
          <th>Gross Swipe</th>
          <th>Charges / Fees</th>
          <th>Net Debited</th>
          <th>Gateway</th>
          <th>Deducted From Wallet</th>
          <th>Reference / Slip</th>
          <th>Date</th>
          <th>Proof</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($swipes as $s)
          <tr>
            <td>
              <a href="{{ route('card-cash.leads.show', $s->lead_id) }}" class="fw-bold text-primary">
                {{ optional($s->lead)->lead_number ?? "#{$s->lead_id}" }}
              </a>
            </td>
            <td>
              <span class="fw-medium text-heading">{{ optional($s->customer)->customer_name ?? 'N/A' }}</span>
              <br><small class="text-muted">{{ optional($s->customer)->phone_number ?? '' }}</small>
            </td>
            <td>
              <span class="fw-bold fs-6 text-heading">₹{{ number_format($s->swipe_amount, 2) }}</span>
            </td>
            <td>
              <span class="text-danger fw-semibold">₹{{ number_format($s->charges, 2) }}</span>
              @if ($s->charges_percentage > 0)
                <br><span class="badge bg-label-danger fs-tiny">{{ $s->charges_percentage }}%</span>
              @endif
            </td>
            <td>
              <span class="fw-bold text-primary">₹{{ number_format($s->net_amount, 2) }}</span>
            </td>
            <td>
              <span class="badge bg-label-info">{{ optional($s->gateway)->gateway_name ?? 'POS Machine' }}</span>
            </td>
            <td>
              <span class="badge bg-label-secondary">
                <i class="ri-wallet-3-line me-1"></i>{{ optional($s->wallet)->wallet_name ?? 'Direct' }}
              </span>
            </td>
            <td>
              <span class="font-monospace fw-medium">{{ $s->transaction_reference ?: ($s->gateway_reference ?: '-') }}</span>
            </td>
            <td>
              <small class="text-muted">{{ $s->processed_at ? $s->processed_at->format('d M Y, h:i A') : '-' }}</small>
            </td>
            <td>
              @if ($s->proof_url)
                <a href="{{ $s->proof_url }}" target="_blank" class="btn btn-xs btn-label-primary">
                  <i class="ri-attachment-line me-1"></i> Slip
                </a>
              @else
                <span class="text-muted small">No slip</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.processing.process', $s->lead_id) }}" class="btn btn-sm btn-outline-primary">
                View / Process
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="11" class="text-center py-5 text-muted">
              No swipe transaction records found.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($swipes->hasPages())
    <div class="card-footer py-3">
      {{ $swipes->links() }}
    </div>
  @endif
</div>
@endsection
