@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Profit & Loss Statement'))

@php
  if (!function_exists('formatIndianCurrency')) {
      function formatIndianCurrency($amount) {
          $amount = (float)$amount;
          if ($amount >= 10000000) { // Crore
              return number_format($amount / 10000000, 2) . ' C';
          } elseif ($amount >= 100000) { // Lakh
              return number_format($amount / 100000, 2) . ' L';
          } elseif ($amount >= 1000) { // Thousand
              return number_format($amount / 1000, 2) . ' K';
          }
          return number_format($amount, 2);
      }
  }
@endphp

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">
      <i class="ri-line-chart-line text-primary me-2"></i>{{ $pageTitle ?? __('Profit & Loss Statement') }}
    </h4>
    <a href="{{ route('account.reports.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm">
      <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Hub') }}
    </a>
  </div>

  <!-- Filters Card -->
  <div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body">
      <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Start Date') }}</label>
          <input type="date" name="start_date" class="form-control" value="{{ $startDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('End Date') }}</label>
          <input type="date" name="end_date" class="form-control" value="{{ $endDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-4 text-md-end">
          <span class="text-muted small d-block mb-1">{{ __('Net Profit') }}</span>
          <h4 class="mb-0 fw-bold @if($netProfit >= 0) text-success @else text-danger @endif">
            ₹ {{ formatIndianCurrency($netProfit) }}
          </h4>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-4">
    <!-- Revenues Section -->
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-4 h-100">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center rounded-top-4 py-3">
          <h5 class="mb-0 fw-bold text-success"><i class="ri-arrow-right-down-line me-1"></i>{{ __('Revenues') }}</h5>
          <span class="fw-bold text-success">₹ {{ formatIndianCurrency($totalRevenue) }}</span>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            @forelse($revenueCategories as $category)
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <span>{{ $category['name'] }}</span>
                <span class="fw-semibold">₹ {{ formatIndianCurrency($category['total']) }}</span>
              </li>
            @empty
              <li class="list-group-item text-center py-5 text-muted">
                {{ __('No revenue recorded for this period.') }}
              </li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>

    <!-- Expenses Section -->
    <div class="col-md-6">
      <div class="card shadow-sm border-0 rounded-4 h-100">
        <div class="card-header bg-light border-bottom d-flex justify-content-between align-items-center rounded-top-4 py-3">
          <h5 class="mb-0 fw-bold text-danger"><i class="ri-arrow-right-up-line me-1"></i>{{ __('Expenses') }}</h5>
          <span class="fw-bold text-danger">₹ {{ formatIndianCurrency($totalExpense) }}</span>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            @forelse($expenseCategories as $category)
              <li class="list-group-item d-flex justify-content-between align-items-center py-3">
                <span>{{ $category['name'] }}</span>
                <span class="fw-semibold">₹ {{ formatIndianCurrency($category['total']) }}</span>
              </li>
            @empty
              <li class="list-group-item text-center py-5 text-muted">
                {{ __('No expenses recorded for this period.') }}
              </li>
            @endforelse
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
