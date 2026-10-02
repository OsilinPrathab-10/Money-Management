@extends('layouts/layoutMaster')

@section('title', 'Card to Cash Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/apex-charts/apexcharts.js'
])
@endsection

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-6 gap-3">
  <div>
    <h4 class="mb-1 fw-bold">Card to Cash Dashboard</h4>
    <p class="text-muted mb-0">Overview of credit card leads, bill payments, swipe transactions, settlements, and wallet balances.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('card-cash.leads.create') }}" class="btn btn-primary">
      <i class="ri-add-line me-1"></i> Add Lead
    </a>
    <a href="{{ route('card-cash.processing.index') }}" class="btn btn-label-warning">
      <i class="ri-time-line me-1"></i> Processing Queue ({{ $processingCount }})
    </a>
    <a href="{{ route('card-cash.reports.index') }}" class="btn btn-label-primary">
      <i class="ri-file-chart-line me-1"></i> Reports
    </a>
  </div>
</div>

@if (session('success'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<!-- Row 1: KPI Stats Cards -->
<div class="row g-6 mb-6">
  <!-- Total Leads -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <h4 class="mb-0 fw-bold">{{ number_format($totalLeads) }}</h4>
            <small class="text-muted">Total</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-success">+{{ number_format($todayLeads) }}</h5>
            <small class="text-muted">Today</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">All Card to Cash Leads</h6>
        <small class="text-muted">Total customer requests created</small>
      </div>
    </div>
  </div>

  <!-- In Processing -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-loader-4-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold text-warning">{{ number_format($processingCount) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Active Processing Queue</h6>
        <small class="text-muted">Leads undergoing payment / swipe</small>
      </div>
    </div>
  </div>

  <!-- Pending Returns / Settlements -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-info">
              <i class="icon-base ri ri-arrow-left-right-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <h4 class="mb-0 fw-bold">{{ number_format($returnPendingCount) }}</h4>
            <small class="text-muted">Pending</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-info">₹{{ number_format($returnPendingAmount, 0) }}</h5>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Returns Pending Settlement</h6>
        <small class="text-muted">Payments completed, return pending</small>
      </div>
    </div>
  </div>

  <!-- Total Available Wallet Balance -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-wallet-3-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold text-success">₹{{ number_format($totalWalletBalance, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Total Available Wallet Balance</h6>
        <small class="text-muted">Across {{ $wallets->count() }} active credit wallets</small>
      </div>
    </div>
  </div>
</div>

<!-- Row 2: Volume & Settlement Stats -->
<div class="row g-6 mb-6">
  <!-- Bill Payments Volume -->
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-label-primary rounded-pill">Bill Payment</span>
          <span class="text-muted">{{ number_format($billPaymentCount) }} txns</span>
        </div>
        <h4 class="mb-1 fw-bold">₹{{ number_format($billPaymentVolume, 2) }}</h4>
        <small class="text-muted">Total Bill Payment Disbursed</small>
      </div>
    </div>
  </div>

  <!-- Swipe Volume -->
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-label-info rounded-pill">Swipe</span>
          <span class="text-muted">{{ number_format($swipeCount) }} txns</span>
        </div>
        <h4 class="mb-1 fw-bold">₹{{ number_format($swipeVolume, 2) }}</h4>
        <small class="text-muted">Total Card Swipe Volume</small>
      </div>
    </div>
  </div>

  <!-- Total Returned to Customer -->
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-label-success rounded-pill">Settled Returns</span>
          <span class="text-muted">{{ number_format($completedCount) }} completed</span>
        </div>
        <h4 class="mb-1 fw-bold text-success">₹{{ number_format($totalReturnsAmount, 2) }}</h4>
        <small class="text-muted">Returned to Customers</small>
      </div>
    </div>
  </div>

  <!-- Charges / Profit Earned -->
  <div class="col-md-3 col-sm-6">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
          <span class="badge bg-label-warning rounded-pill">Net Charges</span>
          <span class="text-muted">Revenue</span>
        </div>
        <h4 class="mb-1 fw-bold text-warning">₹{{ number_format($totalChargesEarned, 2) }}</h4>
        <small class="text-muted">Total Service Charges Retained</small>
      </div>
    </div>
  </div>
</div>

<!-- Row 3: Charts -->
<div class="row g-6 mb-6">
  <!-- Leads Daily Trend Chart -->
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="card-title mb-0">Lead Creation Trend (Last 14 Days)</h5>
          <small class="text-muted">Daily incoming lead volume</small>
        </div>
        <a href="{{ route('card-cash.leads.index') }}" class="btn btn-sm btn-outline-primary">View All Leads</a>
      </div>
      <div class="card-body">
        <div id="leadsTrendChart" style="min-height: 290px;"></div>
      </div>
    </div>
  </div>

  <!-- Status Breakdown & Wallets -->
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">Active Wallet Balances</h5>
        <small class="text-muted">Real-time withdrawal wallet balances</small>
      </div>
      <div class="card-body">
        <div class="d-flex flex-column gap-4">
          @forelse ($wallets as $w)
            <div class="d-flex justify-content-between align-items-center p-3 rounded-2 bg-light">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar avatar-sm">
                  <span class="avatar-initial rounded bg-label-primary">
                    <i class="ri-wallet-line"></i>
                  </span>
                </div>
                <div>
                  <h6 class="mb-0 fw-bold">{{ $w->wallet_name }}</h6>
                  <small class="text-muted">{{ strtoupper($w->wallet_code) }}</small>
                </div>
              </div>
              <div class="text-end">
                <h6 class="mb-0 fw-bold text-success">₹{{ number_format($w->current_balance, 2) }}</h6>
                <a href="{{ route('card-cash.wallets.show', $w->id) }}" class="btn btn-xs btn-link p-0 text-muted">Ledger &rarr;</a>
              </div>
            </div>
          @empty
            <p class="text-muted mb-0">No active wallets configured.</p>
          @endforelse
        </div>
        <div class="mt-4 pt-2 border-top text-center">
          <a href="{{ route('card-cash.wallets.index') }}" class="btn btn-sm btn-label-primary w-100">
            <i class="ri-settings-3-line me-1"></i> Manage All Wallets
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Row 4: Recent Leads Table -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h5 class="card-title mb-0">Recent Card to Cash Leads</h5>
      <small class="text-muted">Latest customer submissions</small>
    </div>
    <a href="{{ route('card-cash.leads.index') }}" class="btn btn-sm btn-primary">View All</a>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Lead #</th>
          <th>Customer</th>
          <th>Card & Bank</th>
          <th>Type</th>
          <th>Amount</th>
          <th>Status</th>
          <th>Assigned Staff</th>
          <th>Date</th>
          <th class="text-end">Action</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse ($recentLeads as $lead)
          <tr>
            <td>
              <a href="{{ route('card-cash.leads.show', $lead->id) }}" class="fw-bold text-primary">
                {{ $lead->lead_number }}
              </a>
            </td>
            <td>
              <div class="d-flex flex-column">
                <span class="fw-medium text-heading">{{ optional($lead->customer)->customer_name ?? 'N/A' }}</span>
                <small class="text-muted">{{ $lead->phone_number }}</small>
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
              <span class="fw-bold">₹{{ number_format($lead->requested_amount, 2) }}</span>
            </td>
            <td>
              <span class="badge {{ $lead->status_badge }}">{{ $lead->status_label }}</span>
            </td>
            <td>
              <small class="text-muted">{{ optional($lead->assignedStaff)->name ?? 'Unassigned' }}</small>
            </td>
            <td>
              <small class="text-muted">{{ $lead->lead_date ? $lead->lead_date->format('d M, h:i A') : '-' }}</small>
            </td>
            <td class="text-end">
              <a href="{{ route('card-cash.processing.process', $lead->id) }}" class="btn btn-sm btn-primary">
                Process
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="text-center py-4 text-muted">
              No Card to Cash leads created yet. <a href="{{ route('card-cash.leads.create') }}">Create your first lead</a>.
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const chartEl = document.querySelector('#leadsTrendChart');
  if (chartEl) {
    const dates = @json($dates);
    const counts = @json($leadCounts);

    const options = {
      chart: {
        height: 290,
        type: 'area',
        toolbar: { show: false },
        parentHeightOffset: 0
      },
      dataLabels: { enabled: false },
      stroke: {
        curve: 'smooth',
        width: 3
      },
      colors: ['#666cff'],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 1,
          opacityFrom: 0.5,
          opacityTo: 0.05,
          stops: [0, 95, 100]
        }
      },
      series: [{
        name: 'New Leads',
        data: counts
      }],
      xaxis: {
        categories: dates,
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: {
          style: { colors: '#a1acb8', fontSize: '13px' }
        }
      },
      yaxis: {
        labels: {
          style: { colors: '#a1acb8', fontSize: '13px' }
        },
        min: 0,
        forceNiceScale: true
      },
      grid: {
        borderColor: '#e7e7e7',
        strokeDashArray: 5
      }
    };

    const chart = new ApexCharts(chartEl, options);
    chart.render();
  }
});
</script>
@endsection
