@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Outstanding Loans Report'))

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
      <i class="ri-safe-2-line text-primary me-2"></i>{{ $pageTitle ?? __('Outstanding Loans Report') }}
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
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Start Date (Disbursed)') }}</label>
          <input type="date" name="start_date" class="form-control" value="{{ $startDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('End Date (Disbursed)') }}</label>
          <input type="date" name="end_date" class="form-control" value="{{ $endDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-4 text-md-end">
          <div class="text-muted small mb-1">{{ __('Total Active Loans') }}</div>
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
      $sumPaidAmount = $loans->sum('paid_amount');
      $sumOutstandingAmount = $loans->sum('outstanding_amount');
    @endphp
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(99, 102, 241, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Loan Amount') }}</div>
          <h4 class="mb-0 fw-bold text-primary">₹ {{ formatIndianCurrency($sumLoanAmount) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(6, 182, 212, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Disbursed') }}</div>
          <h4 class="mb-0 fw-bold text-info">₹ {{ formatIndianCurrency($sumDisbursedAmount) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(16, 185, 129, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Collected') }}</div>
          <h4 class="mb-0 fw-bold text-success">₹ {{ formatIndianCurrency($sumPaidAmount) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <div class="card shadow-sm border-0 rounded-4" style="background: rgba(239, 68, 68, 0.05);">
        <div class="card-body">
          <div class="text-muted small mb-1 text-uppercase">{{ __('Total Outstanding') }}</div>
          <h4 class="mb-0 fw-bold text-danger">₹ {{ formatIndianCurrency($sumOutstandingAmount) }}</h4>
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
              <th class="text-end">{{ __('Loan Amount') }}</th>
              <th class="text-end">{{ __('Disbursed Amount') }}</th>
              <th class="text-end">{{ __('Paid Amount') }}</th>
              <th class="text-end">{{ __('Outstanding') }}</th>
              <th class="text-center">{{ __('EMI Info') }}</th>
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
                <td class="text-end fw-semibold">₹ {{ formatIndianCurrency($loan->loan_amount) }}</td>
                <td class="text-end fw-semibold text-info">₹ {{ formatIndianCurrency($loan->disbursed_amount) }}</td>
                <td class="text-end fw-semibold text-success">₹ {{ formatIndianCurrency($loan->paid_amount) }}</td>
                <td class="text-end fw-bold text-danger">₹ {{ formatIndianCurrency($loan->outstanding_amount) }}</td>
                <td class="text-center">
                  <span class="badge bg-label-secondary">₹ {{ number_format($loan->emi_amount, 2) }}</span>
                  <small class="d-block text-muted" style="font-size: 0.75rem;">Day: {{ $loan->emi_day }}</small>
                </td>
                <td class="text-center text-muted">
                  {{ $loan->disbursed_at ? $loan->disbursed_at->format('d M, Y') : '-' }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No active outstanding loans found.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
          @if($loans->isNotEmpty())
            <tfoot class="table-light">
              <tr>
                <td colspan="2" class="text-end fw-bold">{{ __('Total') }}</td>
                <td class="text-end fw-bold text-primary">₹ {{ formatIndianCurrency($sumLoanAmount) }}</td>
                <td class="text-end fw-bold text-info">₹ {{ formatIndianCurrency($sumDisbursedAmount) }}</td>
                <td class="text-end fw-bold text-success">₹ {{ formatIndianCurrency($sumPaidAmount) }}</td>
                <td class="text-end fw-bold text-danger" style="font-size: 1.1rem;">₹ {{ formatIndianCurrency($sumOutstandingAmount) }}</td>
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
