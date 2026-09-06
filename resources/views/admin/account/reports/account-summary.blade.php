@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Account Summary Report'))

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
      <i class="ri-folder-open-line text-primary me-2"></i>{{ $pageTitle ?? __('Account Summary Report') }}
    </h4>
    <a href="{{ route('account.reports.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm">
      <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Hub') }}
    </a>
  </div>

  <!-- Filters Card -->
  <div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body">
      <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Account Type') }}</label>
          <select name="account_type_id" class="form-select select2" onchange="this.form.submit()">
            <option value="">{{ __('All Types') }}</option>
            @foreach($accountTypes as $type)
              <option value="{{ $type->id }}" @if($accountTypeId == $type->id) selected @endif>
                {{ $type->name }} ({{ $type->code }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-6 col-lg-8 text-md-end">
          <div class="text-muted small mb-1">{{ __('Total Accounts Listed') }}</div>
          <h5 class="mb-0 fw-bold">{{ $accounts->count() }}</h5>
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
              <th>{{ __('Code') }}</th>
              <th>{{ __('Account Name') }}</th>
              <th>{{ __('Account Type') }}</th>
              <th>{{ __('Normal Balance') }}</th>
              <th class="text-end">{{ __('Opening Balance') }}</th>
              <th class="text-end">{{ __('Current Balance') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse($accounts as $account)
              <tr>
                <td><span class="badge bg-label-primary fw-semibold">{{ $account->account_code }}</span></td>
                <td><span class="fw-semibold">{{ $account->account_name }}</span></td>
                <td>{{ $account->accountType->name ?? __('N/A') }}</td>
                <td>
                  <span class="badge bg-label-secondary text-uppercase">{{ $account->normal_balance }}</span>
                </td>
                <td class="text-end">₹ {{ formatIndianCurrency($account->opening_balance) }}</td>
                <td class="text-end fw-bold @if($account->current_balance >= 0) text-success @else text-danger @endif">
                  ₹ {{ formatIndianCurrency($account->current_balance) }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No accounts found for the selected type.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
