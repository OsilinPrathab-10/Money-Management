@extends('layouts/layoutMaster')

@section('title', 'FD Reports')

@section('content')
<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-6">
  <div>
    <h4 class="mb-1">Fixed Deposit Reports</h4>
    <p class="text-muted mb-0">Operational and financial reports for the FD portfolio.</p>
  </div>
  <a href="{{ route('fd.dashboard') }}" class="btn btn-outline-secondary">
    <i class="ri-arrow-left-line me-1"></i> FD Dashboard
  </a>
</div>

@php
  $icons = [
    'register' => 'ri-book-2-line',
    'active' => 'ri-checkbox-circle-line',
    'matured' => 'ri-calendar-check-line',
    'closed' => 'ri-lock-line',
    'premature' => 'ri-alarm-warning-line',
    'renewals' => 'ri-refresh-line',
    'interest_earned' => 'ri-funds-line',
    'interest_payable' => 'ri-percent-line',
    'interest_summary' => 'ri-bar-chart-grouped-line',
    'maturity_today' => 'ri-calendar-todo-line',
    'maturity_upcoming' => 'ri-calendar-event-line',
    'maturity_overdue' => 'ri-time-line',
    'transactions' => 'ri-exchange-line',
    'wallet_credits' => 'ri-wallet-3-line',
    'chit_adjustments' => 'ri-pages-line',
    'financial_summary' => 'ri-pie-chart-line',
  ];
@endphp

<div class="row g-6">
  @foreach ($reports as $key => $title)
    <div class="col-md-6 col-xl-4">
      <div class="card h-100 card-border-shadow-primary">
        <div class="card-body d-flex flex-column">
          <div class="avatar mb-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="ri {{ $icons[$key] ?? 'ri-file-chart-line' }} ri-24px"></i>
            </span>
          </div>
          <h5 class="mb-2">{{ $title }}</h5>
          <p class="text-muted flex-grow-1">Open the {{ strtolower($title) }} report with filters and export options.</p>
          <a href="{{ route('fd.reports.show', $key) }}" class="btn btn-primary">
            Open Report <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>
    </div>
  @endforeach
</div>
@endsection
