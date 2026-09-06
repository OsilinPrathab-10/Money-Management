@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Expense Report'))

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
      <i class="ri-arrow-right-up-line text-danger me-2"></i>{{ $pageTitle ?? __('Expense Report') }}
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
          <div class="text-muted small mb-1">{{ __('Total Records') }}</div>
          <h5 class="mb-0 fw-bold">{{ $expenses->count() }}</h5>
        </div>
      </form>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Expense Number') }}</th>
              <th>{{ __('Date') }}</th>
              <th>{{ __('Category') }}</th>
              <th>{{ __('Account') }}</th>
              <th>{{ __('Reference') }}</th>
              <th>{{ __('Description') }}</th>
              <th class="text-end">{{ __('Amount') }}</th>
            </tr>
          </thead>
          <tbody>
            @php $totalAmount = 0; @endphp
            @forelse($expenses as $expense)
              @php $totalAmount += $expense->amount; @endphp
              <tr>
                <td><span class="fw-semibold">{{ $expense->expense_number }}</span></td>
                <td>{{ $expense->expense_date->format('d M, Y') }}</td>
                <td>
                  <span class="badge bg-label-danger">{{ $expense->category->name ?? __('N/A') }}</span>
                </td>
                <td>{{ $expense->bankAccount->bank_name ?? __('N/A') }}</td>
                <td><small class="text-muted">{{ $expense->reference_number ?? '-' }}</small></td>
                <td>{{ \Illuminate\Support\Str::limit($expense->description, 40) }}</td>
                <td class="text-end fw-bold text-danger">₹ {{ formatIndianCurrency($expense->amount) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No expense transactions found for the selected period.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
          @if($expenses->isNotEmpty())
            <tfoot class="table-light">
              <tr>
                <td colspan="6" class="text-end fw-bold">{{ __('Total') }}</td>
                <td class="text-end fw-bold text-danger" style="font-size: 1.1rem;">₹ {{ formatIndianCurrency($totalAmount) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
