@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('General Ledger Report'))

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
      <i class="ri-file-paper-2-line text-warning me-2"></i>{{ $pageTitle ?? __('General Ledger Report') }}
    </h4>
    <a href="{{ route('account.reports.index') }}" class="btn btn-outline-secondary rounded-pill shadow-sm">
      <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Hub') }}
    </a>
  </div>

  <!-- Filters Card -->
  <div class="card shadow-sm border-0 rounded-4 mb-4">
    <div class="card-body">
      <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Filter Account') }}</label>
          <select name="account_id" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All Accounts') }}</option>
            @foreach($accountsList as $acc)
              <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>
                {{ $acc->account_name }} ({{ $acc->account_code }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Start Date') }}</label>
          <input type="date" name="start_date" class="form-control" value="{{ $startDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-3">
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('End Date') }}</label>
          <input type="date" name="end_date" class="form-control" value="{{ $endDate }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-3 text-md-end">
          <div class="text-muted small mb-1">{{ __('Active Accounts') }}</div>
          <h5 class="mb-0 fw-bold">{{ count($accountsData) }}</h5>
        </div>
      </form>
    </div>
  </div>

  <!-- Accounts Ledgers -->
  @forelse($accountsData as $data)
    <div class="card shadow-sm border-0 rounded-4 mb-4 overflow-hidden">
      <!-- Account Header Summary -->
      <div class="card-header bg-light border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center">
        <div>
          <h5 class="mb-0 fw-bold text-heading">{{ $data['account']->account_name }}</h5>
          <small class="text-muted">{{ __('Code') }}: {{ $data['account']->account_code }} | {{ $data['account']->accountType->name ?? '' }}</small>
        </div>
        <div class="text-end">
          <span class="text-muted small text-uppercase d-block">{{ __('Opening Balance') }}</span>
          <span class="fw-bold fs-5 {{ $data['opening_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
            ₹ {{ formatIndianCurrency(abs($data['opening_balance'])) }} {{ $data['opening_balance'] >= 0 ? 'Dr' : 'Cr' }}
          </span>
        </div>
      </div>

      <!-- Account Ledger Entries -->
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width: 12%;">{{ __('Date') }}</th>
                <th style="width: 15%;">{{ __('Reference') }}</th>
                <th>{{ __('Narration / Description') }}</th>
                <th class="text-end" style="width: 15%;">{{ __('Debit (Dr)') }}</th>
                <th class="text-end" style="width: 15%;">{{ __('Credit (Cr)') }}</th>
                <th class="text-end" style="width: 18%;">{{ __('Running Balance') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($data['items'] as $item)
                <tr>
                  <td>{{ $item['date'] ? $item['date']->format('d M, Y') : '-' }}</td>
                  <td><span class="fw-semibold text-heading">{{ $item['reference'] }}</span></td>
                  <td>{{ \Illuminate\Support\Str::limit($item['description'], 70) }}</td>
                  <td class="text-end text-success fw-medium">
                    {{ $item['debit'] > 0 ? '₹ ' . formatIndianCurrency($item['debit']) : '-' }}
                  </td>
                  <td class="text-end text-danger fw-medium">
                    {{ $item['credit'] > 0 ? '₹ ' . formatIndianCurrency($item['credit']) : '-' }}
                  </td>
                  <td class="text-end fw-bold {{ $item['running_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                    ₹ {{ formatIndianCurrency(abs($item['running_balance'])) }} {{ $item['running_balance'] >= 0 ? 'Dr' : 'Cr' }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center py-4 text-muted">
                    {{ __('No transactions recorded for this account in the selected period.') }}
                  </td>
                </tr>
              @endforelse
            </tbody>
            <tfoot class="table-light">
              <tr>
                <td colspan="3" class="text-end fw-bold">{{ __('Sub Total & Closing') }}</td>
                <td class="text-end text-success fw-bold">₹ {{ formatIndianCurrency($data['total_debit']) }}</td>
                <td class="text-end text-danger fw-bold">₹ {{ formatIndianCurrency($data['total_credit']) }}</td>
                <td class="text-end fw-bold fs-6 {{ $data['closing_balance'] >= 0 ? 'text-success' : 'text-danger' }}">
                  ₹ {{ formatIndianCurrency(abs($data['closing_balance'])) }} {{ $data['closing_balance'] >= 0 ? 'Dr' : 'Cr' }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  @empty
    <div class="card shadow-sm border-0 rounded-4 py-5 text-center text-muted">
      <div class="card-body">
        <i class="ri-information-line fs-1 mb-2"></i>
        <p class="mb-0">{{ __('No accounts with ledger entries or opening balances found.') }}</p>
      </div>
    </div>
  @endforelse
</div>
@endsection
