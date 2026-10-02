@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Returns / Statement')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Returns / Statement</h4>
    <p class="text-muted mb-0">Record of customer settlements and payout statements returned via Card, Wallet, UPI, IMPS, or Cash.</p>
  </div>
  <a href="{{ route('card-cash.leads.create') }}" class="btn btn-primary">
    <i class="ri-add-line me-1"></i> New Lead
  </a>
</div>

<!-- Filter Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.returns.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Ref, UPI, Account, Customer..." value="{{ request('search') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Method</label>
        <select name="return_method" class="form-select">
          <option value="">All Methods</option>
          <option value="wallet" {{ request('return_method') === 'wallet' ? 'selected' : '' }}>Wallet / Multi-Wallet</option>
          <option value="card" {{ request('return_method') === 'card' ? 'selected' : '' }}>Card Return</option>
          <option value="split" {{ request('return_method') === 'split' ? 'selected' : '' }}>Split (Card + Cash)</option>
          <option value="upi" {{ request('return_method') === 'upi' ? 'selected' : '' }}>UPI</option>
          <option value="imps" {{ request('return_method') === 'imps' ? 'selected' : '' }}>IMPS / Bank Transfer</option>
          <option value="other" {{ request('return_method') === 'other' ? 'selected' : '' }}>Other / Cash</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
          <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
          <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
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
        <a href="{{ route('card-cash.returns.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Customer Returns ({{ $returns->total() }})</h5>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Settlement Details</th>
          <th>Method</th>
          <th>Gross Processed</th>
          <th>Return %</th>
          <th>Net Returned</th>
          <th>Charges Retained</th>
          <th>Beneficiary / Reference</th>
          <th>Date</th>
          <th>Status</th>
          <th>Proof</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($returns as $r)
          <tr>
            <td>
              <a href="{{ route('card-cash.leads.show', $r->lead_id) }}" class="fw-bold text-primary">
                {{ optional($r->lead)->lead_number ?? "#{$r->lead_id}" }}
              </a>
            </td>
            <td>
              @php
                $rows = $r->lead ? $r->lead->settlementDisplay($r) : [
                  'name' => optional($r->customer)->customer_name ?? 'N/A',
                  'bank_name' => '—',
                  'card_number' => '—',
                  'due_date' => '—',
                  'amount' => '₹' . number_format($r->return_amount, 2),
                  'settlement_details' => $r->settlement_summary,
                ];
              @endphp
              <div class="small lh-sm">
                <div><span class="text-muted">Name:</span> <strong>{{ $rows['name'] }}</strong></div>
                <div><span class="text-muted">Bank Name:</span> {{ $rows['bank_name'] }}</div>
                <div><span class="text-muted">Card Number:</span> <span class="font-monospace">{{ $rows['card_number'] }}</span></div>
                <div><span class="text-muted">Due Date:</span> {{ $rows['due_date'] }}</div>
                <div><span class="text-muted">Amount:</span> <strong class="text-success">{{ $rows['amount'] }}</strong></div>
                <div><span class="text-muted">Settlement Details:</span> <span class="text-primary">{{ $rows['settlement_details'] }}</span></div>
              </div>
            </td>
            <td>
              @php
                $badgeClass = match($r->return_method) {
                  'card' => 'bg-label-primary',
                  'wallet' => 'bg-label-info',
                  'upi' => 'bg-label-success',
                  'imps' => 'bg-label-secondary',
                  default => 'bg-label-secondary'
                };
              @endphp
              @if ($r->is_split_wallet)
                <span class="badge bg-label-info"><i class="ri-wallet-3-line me-1"></i> SPLIT WALLET</span>
                <div class="small mt-1 text-muted">{{ count($r->wallet_split_breakdown ?? []) }} Wallets</div>
              @elseif ($r->is_split)
                <span class="badge bg-label-warning"><i class="ri-git-merge-line me-1"></i> SPLIT</span>
                <div class="small mt-1">
                  <span class="badge bg-label-primary px-1">Card: ₹{{ number_format($r->card_amount, 2) }}</span>
                  <span class="badge bg-label-success px-1">Cash: ₹{{ number_format($r->cash_amount, 2) }}</span>
                </div>
              @else
                <span class="badge {{ $badgeClass }} text-uppercase">{{ $r->return_method }}</span>
                @if ($r->wallet)
                  <div class="small mt-1 text-muted">{{ $r->wallet->wallet_name }}</div>
                @endif
              @endif
            </td>
            <td>
              <span class="text-muted">₹{{ number_format($r->gross_amount, 2) }}</span>
            </td>
            <td>
              <span class="fw-semibold">{{ $r->return_percentage ? number_format($r->return_percentage, 2) . '%' : '-' }}</span>
            </td>
            <td>
              <span class="fw-bold fs-6 text-success">₹{{ number_format($r->return_amount, 2) }}</span>
              @if ($r->is_split)
                <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                  <div>Card: ₹{{ number_format($r->card_amount, 2) }}</div>
                  <div>Cash: ₹{{ number_format($r->cash_amount, 2) }}</div>
                </div>
              @elseif ($r->is_split_wallet && is_array($r->wallet_split_breakdown))
                <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                  @foreach ($r->wallet_split_breakdown as $wb)
                    <div>{{ $wb['wallet_name'] ?? 'Wallet' }}: ₹{{ number_format($wb['amount'] ?? 0, 2) }}</div>
                  @endforeach
                </div>
              @else
                <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                  {{ strtoupper($r->return_method) }}: ₹{{ number_format($r->return_amount, 2) }}
                </div>
              @endif
            </td>
            <td>
              <span class="fw-medium text-warning">₹{{ number_format($r->charges, 2) }}</span>
            </td>
            <td>
              @if ($r->return_method === 'wallet' || $r->is_split_wallet)
                @if ($r->is_split_wallet && is_array($r->wallet_split_breakdown))
                  @foreach ($r->wallet_split_breakdown as $wb)
                    <div class="small"><i class="ri-wallet-3-line text-info me-1"></i>{{ $wb['wallet_name'] ?? 'Wallet' }}: ₹{{ number_format($wb['amount'] ?? 0, 2) }}</div>
                  @endforeach
                @elseif ($r->wallet)
                  <span class="small fw-medium"><i class="ri-wallet-3-line text-info me-1"></i>{{ $r->wallet->wallet_name }}</span>
                @endif
                @if ($r->payment_reference)
                  <br><span class="font-monospace small text-muted">{{ $r->payment_reference }}</span>
                @endif
              @elseif ($r->return_method === 'upi')
                <span class="font-monospace small">{{ $r->upi_id }}</span>
              @elseif ($r->return_method === 'imps')
                <span class="small fw-medium">{{ $r->account_holder_name }}</span><br>
                <span class="font-monospace small text-muted">{{ $r->account_number }} ({{ $r->ifsc_code }})</span>
              @elseif ($r->payment_reference)
                <span class="font-monospace small">{{ $r->payment_reference }}</span>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
            <td>
              <small class="text-muted">{{ $r->processed_at ? $r->processed_at->format('d M Y, h:i A') : '-' }}</small>
            </td>
            <td>
              <span class="badge bg-label-{{ $r->status === 'success' ? 'success' : 'warning' }}">
                {{ ucfirst($r->status) }}
              </span>
            </td>
            <td>
              @if ($r->proof_url)
                <a href="{{ $r->proof_url }}" target="_blank" class="btn btn-xs btn-label-primary">
                  <i class="ri-attachment-line me-1"></i> Proof
                </a>
              @else
                <span class="text-muted small">None</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.leads.show', $r->lead_id) }}" class="btn btn-sm btn-outline-primary">
                View Lead
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="12" class="text-center py-5 text-muted">
              No return / settlement records found.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($returns->hasPages())
    <div class="card-footer py-3">
      {{ $returns->links() }}
    </div>
  @endif
</div>
@endsection
