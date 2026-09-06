@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Revenue Report'))

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
      <i class="ri-arrow-right-down-line text-success me-2"></i>{{ $pageTitle ?? __('Revenue Report') }}
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
          <h5 class="mb-0 fw-bold">{{ $revenues->count() }}</h5>
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
              <th>{{ __('Revenue Number') }}</th>
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
            @forelse($revenues as $revenue)
              @php $totalAmount += $revenue->amount; @endphp
              <tr>
                <td><span class="fw-semibold">{{ $revenue->revenue_number }}</span></td>
                <td>{{ $revenue->revenue_date->format('d M, Y') }}</td>
                <td>
                  <span class="badge bg-label-success">{{ $revenue->category->name ?? __('N/A') }}</span>
                </td>
                <td>{{ $revenue->bankAccount->bank_name ?? __('N/A') }}</td>
                <td><small class="text-muted">{{ $revenue->reference_number ?? '-' }}</small></td>
                <td>{{ \Illuminate\Support\Str::limit($revenue->description, 40) }}</td>
                <td class="text-end fw-bold">₹ {{ formatIndianCurrency($revenue->amount) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No revenue transactions found for the selected period.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
          @if($revenues->isNotEmpty())
            <tfoot class="table-light">
              <tr>
                <td colspan="6" class="text-end fw-bold">{{ __('Total') }}</td>
                <td class="text-end fw-bold text-success" style="font-size: 1.1rem;">₹ {{ formatIndianCurrency($totalAmount) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
