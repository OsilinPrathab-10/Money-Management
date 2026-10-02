@extends('layouts/layoutMaster')

@section('title', 'Card to Cash Processing Queue')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Active Processing Queue</h4>
    <p class="text-muted mb-0">Active leads requiring verification, bill payment execution, card swipe, or return settlement.</p>
  </div>
  <a href="{{ route('card-cash.leads.create') }}" class="btn btn-primary">
    <i class="ri-add-line me-1"></i> Add Lead
  </a>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Queue Filters -->
<div class="card mb-6">
  <div class="card-body">
    <form action="{{ route('card-cash.processing.index') }}" method="GET" class="row g-4 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-medium">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Lead #, Customer, Phone..." value="{{ request('search') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Transaction Type</label>
        <select name="transaction_type" class="form-select">
          <option value="">All Types</option>
          <option value="bill_payment" {{ request('transaction_type') === 'bill_payment' ? 'selected' : '' }}>Bill Payment</option>
          <option value="swipe" {{ request('transaction_type') === 'swipe' ? 'selected' : '' }}>Card Swipe</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-medium">Stage / Status</label>
        <select name="status" class="form-select">
          <option value="">All Active Stages</option>
          <option value="new" {{ request('status') === 'new' ? 'selected' : '' }}>New</option>
          <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>In Processing</option>
          <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
          <option value="payment_processing" {{ request('status') === 'payment_processing' ? 'selected' : '' }}>Payment Processing</option>
          <option value="payment_success" {{ request('status') === 'payment_success' ? 'selected' : '' }}>Payment Success</option>
          <option value="return_pending" {{ request('status') === 'return_pending' ? 'selected' : '' }}>Return Settlement Pending</option>
          <option value="return_processed" {{ request('status') === 'return_processed' ? 'selected' : '' }}>Return Processed</option>
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary w-100"><i class="ri-filter-3-line me-1"></i> Filter Queue</button>
        <a href="{{ route('card-cash.processing.index') }}" class="btn btn-outline-secondary"><i class="ri-refresh-line"></i></a>
      </div>
    </form>
  </div>
</div>

<!-- Queue Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Action Items in Queue ({{ $leads->total() }})</h5>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Customer</th>
          <th>Card & Bank</th>
          <th>Type</th>
          <th>Requested Amount</th>
          <th>Current Status</th>
          <th>Next Action Required</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($leads as $lead)
          <tr>
            <td>
              <a href="{{ route('card-cash.processing.process', $lead->id) }}" class="fw-bold text-primary">
                {{ $lead->lead_number }}
              </a>
              <br><small class="text-muted">{{ $lead->lead_date ? $lead->lead_date->format('d M, h:i A') : '-' }}</small>
            </td>
            <td>
              <div class="d-flex flex-column">
                <span class="fw-bold text-heading">{{ optional($lead->customer)->customer_name ?? 'N/A' }}</span>
                <small class="text-muted font-monospace">{{ $lead->phone_number }}</small>
              </div>
            </td>
            <td>
              <span class="fw-medium">{{ $lead->card_name }}</span>
              <br><small class="text-muted">{{ $lead->csr_bank_name }}</small>
            </td>
            <td>
              @if ($lead->transaction_type === 'bill_payment')
                <span class="badge bg-label-primary"><i class="ri-bank-card-line me-1"></i> Bill Payment</span>
              @else
                <span class="badge bg-label-info"><i class="ri-swap-box-line me-1"></i> Swipe</span>
              @endif
            </td>
            <td>
              <span class="fw-bold fs-6">₹{{ number_format($lead->requested_amount, 2) }}</span>
            </td>
            <td>
              <span class="badge {{ $lead->status_badge }}">{{ $lead->status_label }}</span>
            </td>
            <td>
              @if (in_array($lead->status, ['new', 'processing', 'approved', 'payment_processing']))
                @if ($lead->transaction_type === 'bill_payment')
                  <span class="badge bg-label-warning"><i class="ri-arrow-right-line me-1"></i> Process Bill Payment</span>
                @else
                  <span class="badge bg-label-info"><i class="ri-arrow-right-line me-1"></i> Process Swipe</span>
                @endif
              @elseif (in_array($lead->status, ['payment_success', 'return_pending']))
                <span class="badge bg-label-success"><i class="ri-refund-2-line me-1"></i> Settle Customer Return</span>
              @elseif ($lead->status === 'return_processed')
                <span class="badge bg-label-primary"><i class="ri-checkbox-circle-line me-1"></i> Complete Lead</span>
              @else
                <span class="text-muted small">-</span>
              @endif
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.processing.process', $lead->id) }}" class="btn btn-sm btn-primary">
                <i class="ri-settings-4-line me-1"></i> Process
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center py-5 text-muted">
              <i class="ri-checkbox-circle-line text-success fs-1 mb-2 d-block"></i>
              Queue is clear! No active leads requiring processing at this time.
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
