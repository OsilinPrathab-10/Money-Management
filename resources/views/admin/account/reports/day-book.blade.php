@extends('layouts/layoutMaster')

@section('title', $pageTitle ?? __('Day Book Report'))

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
      <i class="ri-book-open-line text-warning me-2"></i>{{ $pageTitle ?? __('Day Book Report') }}
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
          <label class="form-label fw-bold small text-muted text-uppercase">{{ __('Select Date') }}</label>
          <input type="date" name="date" class="form-control" value="{{ $date }}" onchange="this.form.submit()">
        </div>
        <div class="col-md-8 text-md-end">
          <div class="d-flex justify-content-md-end gap-4">
            <div>
              <div class="text-muted small mb-1">{{ __('Total Inflow') }}</div>
              <h5 class="mb-0 fw-bold text-success">₹ {{ formatIndianCurrency($revenues->sum('amount')) }}</h5>
            </div>
            <div class="border-start ps-4">
              <div class="text-muted small mb-1">{{ __('Total Outflow') }}</div>
              <h5 class="mb-0 fw-bold text-danger">₹ {{ formatIndianCurrency($expenses->sum('amount')) }}</h5>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Table Card -->
  <div class="card shadow-sm border-0 rounded-4">
    <div class="card-header bg-light border-bottom rounded-top-4 py-3">
      <h5 class="mb-0 fw-bold">{{ __('Daily Transactions') }}</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Time/ID') }}</th>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Particulars') }}</th>
              <th>{{ __('Account') }}</th>
              <th class="text-end">{{ __('Inflow (Receipt)') }}</th>
              <th class="text-end">{{ __('Outflow (Payment)') }}</th>
            </tr>
          </thead>
          <tbody>
            @php 
              $allTxns = collect();
              foreach($revenues as $rev) {
                $allTxns->push([
                  'id' => $rev->revenue_number,
                  'type' => 'Revenue',
                  'time' => $rev->created_at,
                  'desc' => $rev->description ?? $rev->category->name ?? __('Revenue'),
                  'account' => $rev->bankAccount->bank_name ?? '-',
                  'inflow' => $rev->amount,
                  'outflow' => 0
                ]);
              }
              foreach($expenses as $exp) {
                $allTxns->push([
                  'id' => $exp->expense_number,
                  'type' => 'Expense',
                  'time' => $exp->created_at,
                  'desc' => $exp->description ?? $exp->category->name ?? __('Expense'),
                  'account' => $exp->bankAccount->bank_name ?? '-',
                  'inflow' => 0,
                  'outflow' => $exp->amount
                ]);
              }
              $allTxns = $allTxns->sortBy('time');
            @endphp

            @forelse($allTxns as $txn)
              <tr>
                <td>
                  <span class="fw-semibold d-block">{{ $txn['id'] }}</span>
                  <small class="text-muted">{{ $txn['time']->format('h:i A') }}</small>
                </td>
                <td>
                  <span class="badge @if($txn['type'] == 'Revenue') bg-label-success @else bg-label-danger @endif">
                    {{ $txn['type'] }}
                  </span>
                </td>
                <td>{{ \Illuminate\Support\Str::limit($txn['desc'], 50) }}</td>
                <td>{{ $txn['account'] }}</td>
                <td class="text-end fw-bold text-success">
                  @if($txn['inflow'] > 0)
                    ₹ {{ formatIndianCurrency($txn['inflow']) }}
                  @else
                    -
                  @endif
                </td>
                <td class="text-end fw-bold text-danger">
                  @if($txn['outflow'] > 0)
                    ₹ {{ formatIndianCurrency($txn['outflow']) }}
                  @else
                    -
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-5 text-muted">
                  <i class="ri-information-line fs-1 mb-2"></i>
                  <p class="mb-0">{{ __('No transactions recorded for this day.') }}</p>
                </td>
              </tr>
            @endforelse
          </tbody>
          @if($allTxns->isNotEmpty())
            <tfoot class="table-light">
              <tr>
                <td colspan="4" class="text-end fw-bold">{{ __('Total') }}</td>
                <td class="text-end fw-bold text-success">₹ {{ formatIndianCurrency($revenues->sum('amount')) }}</td>
                <td class="text-end fw-bold text-danger">₹ {{ formatIndianCurrency($expenses->sum('amount')) }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
