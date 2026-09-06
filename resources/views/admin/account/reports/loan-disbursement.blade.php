@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Loan Disbursement Report'))

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
      <i class="ri-exchange-dollar-line text-success me-2"></i>{{ $pageTitle ?? __('Loan Disbursement Report') }}
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
          <div class="text-muted small mb-1">{{ __('Total Disbursements') }}</div>
          <h5 class="mb-0 fw-bold">{{ $loans->count() }}</h5>
        </div>
      </form>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-4 mb-4">
    @php
      $sumLoanAmount = $loans->sum('loan_amount');
      $sumDisbursedAmount = $loans->sum('disbursed_amount');
    @endphp
    <div class="col-md-6 col-lg-6">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(99, 102, 241, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Sanctioned Amount') }}</div>
          <h4 class="mb-0 fw-bold text-primary">₹ {{ formatIndianCurrency($sumLoanAmount) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-lg-6">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(16, 185, 129, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Disbursed Amount') }}</div>
          <h4 class="mb-0 fw-bold text-success">₹ {{ formatIndianCurrency($sumDisbursedAmount) }}</h4>
        </div>
      </div>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Account / Loan Code') }}</th>
              <th>{{ __('Client / Borrower') }}</th>
              <th>{{ __('Payment Mode') }}</th>
              <th class="text-end">{{ __('Loan Amount') }}</th>
              <th class="text-end">{{ __('Disbursed Amount') }}</th>
              <th class="text-center">{{ __('UTR Number') }}</th>
              <th class="text-center">{{ __('Disbursed Date') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($loans as $loan)
              <tr>
                <td>
                  <span class="fw-semibold text-heading d-block">{{ $loan->account_number }}</span>
                  <small class="text-muted">{{ $loan->loan_code }}</small>
                </td>
                <td>
                  <span class="fw-semibold d-block text-heading">{{ $loan->client->client_name ?? __('N/A') }}</span>
                  <small class="text-muted">{{ $loan->client->client_phone ?? '-' }}</small>
                </td>
                <td>
                  <span class="badge bg-label-primary">{{ strtoupper($loan->payment_method ?? 'N/A') }}</span>
                </td>
                <td class="text-end fw-semibold">₹ {{ formatIndianCurrency($loan->loan_amount) }}</td>
                <td class="text-end fw-bold text-success">₹ {{ formatIndianCurrency($loan->disbursed_amount) }}</td>
                <td class="text-center">
                  <small class="text-heading fw-medium">{{ $loan->utr_number ?: '-' }}</small>
                </td>
                <td class="text-center text-muted">
                  {{ $loan->disbursed_at ? $loan->disbursed_at->format('d M, Y') : '-' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No loan disbursements found for the selected period.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
          @if($loans->isNotEmpty())
            <tfoot class="table-light">
              <tr>
                <td colspan="3" class="text-end fw-bold">{{ __('Total') }}</td>
                <td class="text-end fw-bold text-primary">₹ {{ formatIndianCurrency($sumLoanAmount) }}</td>
                <td class="text-end fw-bold text-success" style="font-size: 1.1rem;">₹ {{ formatIndianCurrency($sumDisbursedAmount) }}</td>
                <td colspan="2"></td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
