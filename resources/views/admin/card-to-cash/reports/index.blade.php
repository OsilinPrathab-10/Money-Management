@extends('layouts/layoutMaster')

@section('title', 'Card to Cash - Financial & Operational Reports')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Card to Cash Reports</h4>
    <p class="text-muted mb-0">Financial analytics, wallet reconciliation, charges, and staff performance.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('card-cash.reports.export-excel', request()->query()) }}" class="btn btn-outline-success">
      <i class="ri-file-excel-2-line me-1"></i> Export Excel
    </a>
    <a href="{{ route('card-cash.reports.export-csv', request()->query()) }}" class="btn btn-outline-secondary">
      <i class="ri-file-text-line me-1"></i> Export CSV
    </a>
  </div>
</div>

<!-- Report Section Navigation Pills -->
<div class="nav-align-top mb-6">
  <ul class="nav nav-pills gap-2">
    <li class="nav-item">
      <a href="{{ route('card-cash.reports.index') }}" class="nav-link active px-4 py-2">
        <i class="ri-file-chart-line me-1"></i> Reports & Analytics
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('card-cash.processing.index') }}" class="nav-link px-4 py-2">
        <i class="ri-loader-4-line me-1"></i> Processing
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('card-cash.bill-payments.index') }}" class="nav-link px-4 py-2">
        <i class="ri-bill-line me-1"></i> Bill Payments
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('card-cash.swipes.index') }}" class="nav-link px-4 py-2">
        <i class="ri-swap-box-line me-1"></i> Swipe Transactions
      </a>
    </li>
    <li class="nav-item">
      <a href="{{ route('card-cash.returns.index') }}" class="nav-link px-4 py-2">
        <i class="ri-arrow-go-back-line me-1"></i> Returns / Statement
      </a>
    </li>
  </ul>
</div>

<!-- Summary Metrics on Filtered Data -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card bg-primary text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-white opacity-75 small d-block">Requested Volume</span>
            <h3 class="text-white fw-bold mb-0">₹{{ number_format($totalRequested, 2) }}</h3>
          </div>
          <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
            <i class="ri-file-list-3-line text-white fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card bg-info text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-white opacity-75 small d-block">Completed Volume</span>
            <h3 class="text-white fw-bold mb-0">₹{{ number_format($totalCompleted, 2) }}</h3>
          </div>
          <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
            <i class="ri-checkbox-circle-line text-white fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card bg-success text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-white opacity-75 small d-block">Total Settled / Returned</span>
            <h3 class="text-white fw-bold mb-0">₹{{ number_format($totalReturns, 2) }}</h3>
          </div>
          <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
            <i class="ri-arrow-go-back-line text-white fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card bg-warning text-white">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-white opacity-75 small d-block">Net Charges / Commission</span>
            <h3 class="text-white fw-bold mb-0">₹{{ number_format($totalCharges, 2) }}</h3>
          </div>
          <div class="avatar avatar-md bg-white bg-opacity-25 rounded d-flex align-items-center justify-content-center">
            <i class="ri-percent-line text-white fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Wallet reconciliation metrics -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small d-block">Wallet Total</span>
            <h3 class="fw-bold mb-0 text-heading">₹{{ number_format($walletTotal, 2) }}</h3>
            <small class="text-muted">Current balance across wallets</small>
          </div>
          <div class="avatar avatar-md bg-label-primary rounded d-flex align-items-center justify-content-center">
            <i class="ri-wallet-3-line text-primary fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small d-block">In/Out Difference</span>
            <h3 class="fw-bold mb-0 {{ $inOutDifference >= 0 ? 'text-success' : 'text-danger' }}">₹{{ number_format($inOutDifference, 2) }}</h3>
            <small class="text-muted">Credits ₹{{ number_format($walletIn, 2) }} − Debits ₹{{ number_format($walletOut, 2) }}</small>
          </div>
          <div class="avatar avatar-md bg-label-info rounded d-flex align-items-center justify-content-center">
            <i class="ri-arrow-left-right-line text-info fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small d-block">Total Difference Amount</span>
            <h3 class="fw-bold mb-0 text-heading">₹{{ number_format($totalDifferenceAmount, 2) }}</h3>
            <small class="text-muted">Requested − Settled / Returned</small>
          </div>
          <div class="avatar avatar-md bg-label-warning rounded d-flex align-items-center justify-content-center">
            <i class="ri-scales-3-line text-warning fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <span class="text-muted small d-block">Card Charges</span>
            <h3 class="fw-bold mb-0 text-danger">₹{{ number_format($cardCharges, 2) }}</h3>
            <small class="text-muted">Swipe charges + return charges</small>
          </div>
          <div class="avatar avatar-md bg-label-danger rounded d-flex align-items-center justify-content-center">
            <i class="ri-bank-card-line text-danger fs-4"></i>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Multi-filter Card -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.reports.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Lead #, Customer, Card, CSR..." value="{{ request('search') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Customer</label>
        <select name="customer_id" class="form-select">
          <option value="">All Customers</option>
          @foreach ($customers as $c)
            <option value="{{ $c->id }}" {{ request('customer_id') == $c->id ? 'selected' : '' }}>
              {{ $c->customer_name }} ({{ $c->customer_number }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Type</label>
        <select name="transaction_type" class="form-select">
          <option value="">All Types</option>
          <option value="bill_payment" {{ request('transaction_type') === 'bill_payment' ? 'selected' : '' }}>Bill Payment</option>
          <option value="swipe" {{ request('transaction_type') === 'swipe' ? 'selected' : '' }}>Swipe</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Status</label>
        <select name="status" class="form-select">
          <option value="">All Statuses</option>
          <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
          <option value="under_process" {{ request('status') === 'under_process' ? 'selected' : '' }}>Under Process</option>
          <option value="payment_success" {{ request('status') === 'payment_success' ? 'selected' : '' }}>Payment Success</option>
          <option value="swipe_success" {{ request('status') === 'swipe_success' ? 'selected' : '' }}>Swipe Success</option>
          <option value="return_pending" {{ request('status') === 'return_pending' ? 'selected' : '' }}>Return Pending</option>
          <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
          <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-medium">Return Method</label>
        <select name="return_method" class="form-select">
          <option value="">All Methods</option>
          <option value="wallet" {{ request('return_method') === 'wallet' ? 'selected' : '' }}>Wallet</option>
          <option value="card" {{ request('return_method') === 'card' ? 'selected' : '' }}>Card</option>
          <option value="split" {{ request('return_method') === 'split' ? 'selected' : '' }}>Split</option>
          <option value="upi" {{ request('return_method') === 'upi' ? 'selected' : '' }}>UPI</option>
          <option value="imps" {{ request('return_method') === 'imps' ? 'selected' : '' }}>IMPS</option>
          <option value="other" {{ request('return_method') === 'other' ? 'selected' : '' }}>Other / Cash</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Assigned Staff</label>
        <select name="assigned_user_id" class="form-select">
          <option value="">All Staff</option>
          @foreach ($staffUsers as $su)
            <option value="{{ $su->id }}" {{ request('assigned_user_id') == $su->id ? 'selected' : '' }}>
              {{ $su->name }}
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
      <div class="col-md-5 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-grow-1"><i class="ri-filter-3-line me-1"></i> Apply Filters</button>
        <a href="{{ route('card-cash.reports.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i> Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Table Card -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Report Records ({{ $leads->total() }})</h5>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Date</th>
          <th>Due Date</th>
          <th>Customer</th>
          <th>Card / CSR</th>
          <th>Type</th>
          <th>Requested Amount</th>
          <th>Source / Terminal</th>
          <th>Settled (Return)</th>
          <th>Difference Amount</th>
          <th>Card Charges</th>
          <th>Staff</th>
          <th>Status</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($leads as $l)
          <tr>
            <td>
              <a href="{{ route('card-cash.leads.show', $l->id) }}" class="fw-bold text-primary">
                {{ $l->lead_number }}
              </a>
            </td>
            <td>
              <small class="text-muted">{{ $l->lead_date ? $l->lead_date->format('d M Y') : '-' }}</small>
            </td>
            <td>
              <small class="text-muted">{{ $l->due_date ? $l->due_date->format('d M Y') : '—' }}</small>
            </td>
            <td>
              <span class="fw-medium text-heading">{{ optional($l->customer)->customer_name ?? 'N/A' }}</span>
              <br><small class="text-muted">{{ $l->phone_number }}</small>
            </td>
            <td>
              <span class="fw-medium">{{ $l->card_name }}</span>
              <br><small class="text-muted">{{ $l->csr_bank_name }}</small>
            </td>
            <td>
              <span class="badge bg-label-{{ $l->transaction_type === 'bill_payment' ? 'primary' : 'warning' }}">
                {{ $l->transaction_type === 'bill_payment' ? 'Bill Payment' : 'Swipe' }}
              </span>
            </td>
            <td>
              <span class="fw-bold fs-6 text-heading">₹{{ number_format($l->requested_amount, 2) }}</span>
            </td>
            <td>
              @if ($l->transaction_type === 'bill_payment')
                <span class="small text-muted">{{ optional(optional($l->billPayment)->paymentSource)->source_name ?? '-' }}</span>
              @else
                <span class="small text-muted">{{ optional(optional($l->swipeTransaction)->gateway)->gateway_name ?? '-' }}</span>
              @endif
            </td>
            <td>
              @if ($l->returnSettlement)
                <span class="fw-bold text-success">₹{{ number_format($l->returnSettlement->return_amount, 2) }}</span>
                <br><small class="text-primary">{{ $l->returnSettlement->settlement_summary }}</small>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
            @php
              $rowReturn = (float) optional($l->returnSettlement)->return_amount;
              $rowDiff = (float) $l->requested_amount - $rowReturn;
              $rowCardCharges = (float) optional($l->swipeTransaction)->charges + (float) optional($l->returnSettlement)->charges;
            @endphp
            <td>
              <span class="fw-medium {{ $rowDiff >= 0 ? 'text-heading' : 'text-danger' }}">₹{{ number_format($rowDiff, 2) }}</span>
            </td>
            <td>
              @if ($rowCardCharges > 0)
                <span class="fw-medium text-warning">₹{{ number_format($rowCardCharges, 2) }}</span>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
            <td>
              <small class="text-muted">{{ optional($l->assignedStaff)->name ?? 'Unassigned' }}</small>
            </td>
            <td>
              <span class="badge bg-label-{{ $l->status_badge_class }}">
                {{ $l->status_label }}
              </span>
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.processing.process', $l->id) }}" class="btn btn-xs btn-outline-primary">
                View
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="14" class="text-center py-5 text-muted">
              No records found matching the filter parameters.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($leads->hasPages())
    <div class="card-footer py-3">
      {{ $leads->links() }}
    </div>
  @endif
</div>
@endsection
