@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('page-style')
@vite('resources/assets/vendor/scss/pages/app-logistics-dashboard.scss')
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/apex-charts/apexcharts.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-script')
@php
  $dashboardData = [
    'emiChart' => $emiChart,
    'loanPerformanceChart' => $loanPerformanceChart,
    'loanDistributionChart' => $loanDistributionChart,
    'currencySymbol' => '₹',
    'dateRange' => $dateRange ?? 'all',
    'dateFilterLabel' => $dateFilterLabel ?? 'All Time',
  ];

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
<script>
  window.dashboardData = @json($dashboardData);
</script>
@vite('resources/assets/custom-js/admin-dashboard.js')
@endsection

@section('content')
<style>
  .dashboard-summary-row > [class*="col-"] {
    display: flex;
  }

  .dashboard-summary-row .card {
    width: 100%;
  }
</style>
<!-- Header section with Date Range Filter & Refresh -->
<div class="row mb-4">
  <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
    <div>
      <h4 class="mb-0">Main Dashboard</h4>
      <p class="text-muted mb-0">Real-time business statistics overview <span class="badge bg-label-primary ms-2" id="activeFilterBadge">{{ $dateFilterLabel ?? 'All Time' }}</span></p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <!-- Date Filter Dropdown -->
      <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" id="dashboardDateFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="icon-base ri ri-calendar-event-line me-1_5"></i>
          <span id="selectedDateFilterLabel">{{ $dateFilterLabel ?? 'All Time' }}</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="dashboardDateFilterBtn" id="dashboardDateFilterMenu">
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'today' ? 'active' : '' }}" href="#" data-range="today"><i class="icon-base ri ri-calendar-check-line me-2"></i>Today</a></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'week' ? 'active' : '' }}" href="#" data-range="week"><i class="icon-base ri ri-calendar-2-line me-2"></i>This Week</a></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'month' ? 'active' : '' }}" href="#" data-range="month"><i class="icon-base ri ri-calendar-month-line me-2"></i>This Month</a></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == '3_months' ? 'active' : '' }}" href="#" data-range="3_months"><i class="icon-base ri ri-calendar-line me-2"></i>Last 3 Months</a></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'year' ? 'active' : '' }}" href="#" data-range="year"><i class="icon-base ri ri-calendar-view me-2"></i>This Year</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'custom' ? 'active' : '' }}" href="#" data-range="custom" id="customDateOption"><i class="icon-base ri ri-filter-3-line me-2"></i>Custom Range...</a></li>
          <li><a class="dropdown-item {{ ($dateRange ?? 'all') == 'all' ? 'active' : '' }}" href="#" data-range="all"><i class="icon-base ri ri-refresh-line me-2"></i>All Time</a></li>
        </ul>
      </div>

      <!-- Custom Date Inputs (hidden by default unless custom is selected) -->
      <div id="customDateContainer" class="d-none align-items-center gap-2">
        <input type="text" id="customStartDate" class="form-control form-control-sm flatpickr-date" style="max-width: 130px;" value="{{ request('start_date') }}" placeholder="Start Date">
        <span class="text-muted">to</span>
        <input type="text" id="customEndDate" class="form-control form-control-sm flatpickr-date" style="max-width: 130px;" value="{{ request('end_date') }}" placeholder="End Date">
        <button type="button" id="applyCustomDateBtn" class="btn btn-sm btn-primary">Apply</button>
      </div>

      <button type="button" class="btn btn-primary d-flex align-items-center" id="refreshStatsBtn" data-url="{{ route('dashboard.stats') }}">
        <i class="icon-base ri ri-refresh-line me-1"></i>
        <span class="d-none d-sm-inline">Refresh Data</span>
      </button>
    </div>
  </div>
</div>

<!-- Statistics Cards -->
<div class="row g-6 mb-6 dashboard-summary-row">
  <!-- Total Agents & Staff -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-user-star-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-3 align-items-baseline">
            <h4 class="mb-0 fw-bold" id="stat-totalAgents">{{ $totalAgents ?? 0 }}</h4>
            <small class="text-muted">Agents</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold" id="stat-totalStaff">{{ $totalStaff ?? 0 }}</h4>
            <small class="text-muted">Staff</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Agents & Staff</h6>
        <a href="{{ route('agent-management.index') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>


   <!-- Pending & Overdue -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-danger h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-danger">
              <i class="icon-base ri ri-alert-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-3 align-items-baseline">
            <h4 class="mb-0 fw-bold" id="stat-pendingApplications">{{ $pendingApplications ?? 0 }}</h4>
            <small class="text-muted">Pending</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold" id="stat-overdueLoans">{{ $overdueLoans ?? 0 }}</h4>
            <small class="text-muted">Overdue</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Pending & Overdue</h6>
        <a href="{{ route('loan-applications') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>



  <!-- Total Clients -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-group-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-totalClients">{{ $totalClients ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Clients</h6>
        <a href="{{ route('client-management') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Total & Active Loans -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-info">
              <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-3 align-items-baseline">
            <h4 class="mb-0 fw-bold" id="stat-totalLoans">{{ $totalLoans ?? 0 }}</h4>
            <small class="text-muted">Total</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold" id="stat-activeLoans">{{ $activeLoans ?? 0 }}</h4>
            <small class="text-muted">Active</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total & Active Loans</h6>
        <a href="{{ route('loan-accounts') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

    
  <!-- Total Revenue -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-wallet-3-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-totalRevenue">₹ {{ formatIndianCurrency($totalRevenue ?? 0) }}</h4>  
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Revenue</h6>
        <a href="{{ route('emi-repayments') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Total Disbursed Amount -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-money-rupee-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-totalDisbursedAmount">₹ {{ formatIndianCurrency($totalDisbursedAmount ?? 0) }}</h4>  
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Disbursed Amount</h6>
        <a href="{{ route('loan-accounts') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Total Outstanding Amount -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-bank-card-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-totalOutstandingAmount">₹ {{ formatIndianCurrency($totalOutstandingAmount ?? 0) }}</h4>  
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Outstanding Amount</h6>
        <a href="{{ route('loan-accounts') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Uncollected Interest Amount -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-percent-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-uncollectedInterest">₹ {{ formatIndianCurrency($uncollectedInterest ?? 0) }}</h4>  
        </div>
        <h6 class="mb-0 h6 fw-normal">Uncollected Interest Amount</h6>
        <a href="{{ route('emi-repayments') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

 
  <!-- Revenue This Month -->
  <!-- <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-line-chart-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold" id="stat-revenueThisMonth">₹ {{ number_format($revenueThisMonth ?? 0, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Revenue This Month</h6>
        <a href="{{ route('emi-repayments') }}" class="stretched-link"></a>
      </div>
    </div>
  </div> -->

</div>
<!--/ Statistics Cards -->
<!-- Shipment statistics-->
  <div class="col-lg-12 col-xxl-12 order-3 order-xxl-1">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2 mb-1">EMI Collection Performance</h5>
          <p class="card-subtitle mb-0">Performance of {{ date('Y') }}</p>
        </div>
      </div>
      <div class="card-body">
        @if ($emiChart['hasData'])
          <div id="emiCollectionChart" style="min-height: 320px;"></div>
        @else
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="icon-base ri ri-line-chart-line text-muted" style="font-size: 48px;"></i>
            </div>
            <h6 class="text-muted">No EMI collection data available</h6>
            <p class="text-muted small">EMI collection performance will appear here once loans are disbursed</p>
          </div>
        @endif
      </div>
    </div>
  </div>
  <!--/ Shipment statistics -->
  <!-- Loan Performance Overview -->
  <div class="row">
  <div class="col-lg-6 col-md-6 col-xxl-4 order-2 order-xxl-2 mt-5">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="card-title mb-1">Loan Performance Overview</h5>
          <p class="card-subtitle mb-0 mt-1">Approved loans by category</p>
        </div>
      </div>
      <div class="card-body">
        @if (($loanPerformanceList ?? collect())->isNotEmpty())
          <div class="loan-performance-list d-flex flex-column gap-4">
            @foreach ($loanPerformanceList as $performance)
              <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                  <div class="avatar avatar-sm">
                    <div class="avatar-initial rounded-circle bg-label-{{ $performance['color'] }}">
                      <i class="icon-base ri {{ $performance['icon'] }}"></i>
                    </div>
                  </div>
                  <div>
                    <h6 class="mb-0">{{ $performance['name'] }}</h6>
                    <small class="text-success">{{ $performance['loan_count'] }} loans &bull; {{ $performance['share_percent'] }}%</small>
                  </div>
                </div>
                <div class="text-end">
                  <h6 class="mb-0 text-heading">₹ {{ number_format($performance['total_disbursed'], 0) }}</h6>
                  <small class="text-muted">share of total</small>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="icon-base ri ri-bar-chart-line text-muted" style="font-size: 48px;"></i>
            </div>
            <h6 class="text-muted">No loan performance data</h6>
            <p class="text-muted small">Data will appear once loans are disbursed.</p>
          </div>
        @endif
      </div>
    </div>
  </div>
  <!--/ Loan Performance Overview -->
  <!-- Loan Distribution by Type -->
  <div class="col-md-6 col-lg-6 col-xxl-4 order-1 order-xxl-3 mt-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2">Loan Distribution by Type</h5>
          <p class="card-subtitle mb-0 mt-1">Total active loans breakdown</p>
        </div>
      </div>
      <div class="card-body">
        @if ($loanDistributionChart['hasData'])
          <div id="loanDistributionChart" style="min-height: 340px;"></div>
        @else
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="icon-base ri ri-pie-chart-line text-muted" style="font-size: 48px;"></i>
            </div>
            <h6 class="text-muted">No loan distribution data available</h6>
            <p class="text-muted small">Loan distribution by type will appear here once loans are created</p>
          </div>
        @endif
      </div>
    </div>
  </div>
  <!--/ Loan Distribution by Type -->
  
  <!-- Client Summary -->
  <div class="col-md-6 col-lg-4 col-xxl-4 mt-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Client Summary ({{ $clientPeriodLabel }})</h5>
        <div class="dropdown">
          <button class="btn text-body-secondary p-0" type="button" id="clientSummary" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <i class="icon-base ri ri-more-2-line"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="clientSummary">
            <a class="dropdown-item {{ request('client_period') == '28_days' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['client_period' => '28_days']) }}">Last 28 Days</a>
            <a class="dropdown-item {{ request('client_period') == 'month' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['client_period' => 'month']) }}">This Month</a>
            <a class="dropdown-item {{ request('client_period') == 'year' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['client_period' => 'year']) }}">This Year</a>
            <a class="dropdown-item border-top {{ request('client_period', 'all') == 'all' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['client_period' => 'all']) }}">All Time</a>
          </div>
        </div>
      </div>
      <div class="card-body pb-4">
        <div class="mb-9">
          <div class="d-flex align-items-center">
            <div class="avatar avatar-md">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-group-line icon-24px"></i>
              </div>
            </div>
            <div class="ms-4">
              <h3 class="mb-0">{{ $totalClients ?? 0 }}</h3>
              <p class="mb-0">Total Registered Clients</p>
            </div>
          </div>
        </div>
        <div class="mb-5">
          <h6 class="mb-2">Current Activity</h6>
          <div class="progress w-100 rounded bg-label-primary" style="height: 6px;">
            <div class="progress-bar bg-primary" style="width: {{ $clientActivityPercentage }}%" role="progressbar" aria-valuenow="{{ $clientActivityPercentage }}"
              aria-valuemin="0" aria-valuemax="100"></div>
          </div>
        </div>
        <div class="table-responsive text-nowrap">
          <table class="table">
            <tbody class="table-border-bottom-0">
              <tr>
                <td class="ps-0 pb-4">
                  <i class="icon-base ri ri-circle-fill icon-14px text-primary me-3"></i>
                  <span class="text-heading align-middle">Active Clients</span>
                </td>
                <td class="text-end pe-0 pb-4">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-heading fw-medium me-2" id="stat-summary-activeClients">{{ $activeClients ?? 0 }}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="ps-0 py-4">
                  <i class="icon-base ri ri-circle-fill icon-14px text-warning me-3"></i>
                  <span class="text-heading align-middle">Pending Clients</span>
                </td>
                <td class="text-end pe-0 py-4">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-heading fw-medium me-2" id="stat-summary-pendingClients">{{ $pendingClients ?? 0 }}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="ps-0 py-4">
                  <i class="icon-base ri ri-circle-fill icon-14px text-secondary me-3"></i>
                  <span class="text-heading align-middle">Inactive Clients</span>
                </td>
                <td class="text-end pe-0 py-4">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-heading fw-medium me-2" id="stat-summary-inactiveClients">{{ $inactiveClients ?? 0 }}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="ps-0 pt-4">
                  <i class="icon-base ri ri-circle-fill icon-14px text-danger me-3"></i>
                  <span class="text-heading align-middle">Blacklisted Clients</span>
                </td>
                <td class="text-end pe-0 pt-4">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-heading fw-medium me-2" id="stat-summary-blacklistedClients">{{ $blacklistedClients ?? 0 }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!--/ Client Summary -->
</div>  

  <!-- Recent Applications Table -->
  <div class="col-12 order-5 mt-5">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2">Recent Applications</h5>
        </div>
      </div>
      <div class="card-datatable table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Application ID</th>
              <th>Client Name</th>
              <th>Zone</th>
              <th>Loan Type</th>
              <th>Loan Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @php
              $statusBadgeColors = [
                'pending' => 'bg-label-warning text-warning',
                'approved' => 'bg-label-success text-success',
                'disbursed' => 'bg-label-info text-info',
                'rejected' => 'bg-label-danger text-danger'
              ];
            @endphp
            @forelse ($recentApplications as $application)
              <tr>
                <td>{{ $application->application_number }}</td>
                <td>{{ $application->client->user->name ?? $application->client->client_name ?? 'N/A' }}</td>
                <td><span class="badge bg-label-secondary">{{ $application->client->location->name ?? 'N/A' }}</span></td>
                <td>{{ $application->product->loan_name ?? $application->loan_code ?? 'N/A' }}</td>
                <td>₹ {{ number_format($application->display_amount ?? 0, 0) }}</td>
                <td>
                  @php
                    $badgeClass = $statusBadgeColors[$application->status] ?? 'bg-label-secondary text-body';
                  @endphp
                  <span class="badge rounded-pill {{ $badgeClass }}">
                    {{ ucfirst($application->status) }}
                  </span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-5">
                  <div class="mb-3">
                    <i class="icon-base ri ri-file-list-3-line text-muted" style="font-size: 48px;"></i>
                  </div>
                  <h6 class="text-muted">No loan applications yet</h6>
                  <p class="text-muted small">Recent applications will appear here once created</p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!--/ Recent Applications Table -->
@endsection
