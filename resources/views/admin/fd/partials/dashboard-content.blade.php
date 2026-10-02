{{-- FD Dashboard Content Partial --}}
@if(!request()->routeIs('dashboard'))
{{-- Standalone FD page: same compact Period dropdown as main dashboard --}}
<div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mb-4">
  <form method="GET" action="{{ route('fd.dashboard') }}" class="d-flex align-items-center flex-wrap gap-2" id="standaloneFdPeriodForm">
    <label for="standaloneFdPeriodFilter" class="form-label mb-0 text-nowrap small fw-medium">Period:</label>
    <select name="fd_period" id="standaloneFdPeriodFilter" class="form-select form-select-sm no-search dashboard-period-filter" style="min-width: 160px;" autocomplete="off">
      <option value="all" {{ ($fdPeriodKey ?? 'all') === 'all' ? 'selected' : '' }}>All Time</option>
      <option value="today" {{ ($fdPeriodKey ?? '') === 'today' ? 'selected' : '' }}>Today</option>
      <option value="month" {{ ($fdPeriodKey ?? '') === 'month' ? 'selected' : '' }}>This Month</option>
      <option value="year" {{ ($fdPeriodKey ?? '') === 'year' ? 'selected' : '' }}>This Year</option>
      <option value="custom" {{ ($fdPeriodKey ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
    </select>
    <div id="standaloneFdCustomDateRange" class="align-items-center gap-2 {{ ($fdPeriodKey ?? '') === 'custom' ? 'd-flex' : 'd-none' }}">
      <input type="date" name="fd_from" class="form-control form-control-sm" style="min-width: 145px;" value="{{ $fdPeriodFromInput ?? '' }}" {{ ($fdPeriodKey ?? '') === 'custom' ? '' : 'disabled' }}>
      <span class="text-muted small">to</span>
      <input type="date" name="fd_to" class="form-control form-control-sm" style="min-width: 145px;" value="{{ $fdPeriodToInput ?? '' }}" {{ ($fdPeriodKey ?? '') === 'custom' ? '' : 'disabled' }}>
      <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const select = document.getElementById('standaloneFdPeriodFilter');
  const customBox = document.getElementById('standaloneFdCustomDateRange');
  if (!select || !customBox) return;

  select.addEventListener('change', function () {
    if (this.value === 'custom') {
      customBox.classList.remove('d-none');
      customBox.classList.add('d-flex');
      customBox.querySelectorAll('input').forEach(i => i.removeAttribute('disabled'));
    } else {
      customBox.classList.remove('d-flex');
      customBox.classList.add('d-none');
      customBox.querySelectorAll('input').forEach(i => i.setAttribute('disabled', 'disabled'));
      document.getElementById('standaloneFdPeriodForm').submit();
    }
  });
});
</script>
@endif

<div class="row g-6 mb-6">
  <!-- Active, Closed & Total FDs -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-safe-2-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <h4 class="mb-0 fw-bold">{{ number_format($fdTotalActiveDeposits ?? 0) }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-secondary">{{ number_format($fdClosedDeposits ?? 0) }}</h4>
            <small class="text-muted">Closed</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted">{{ number_format($fdTotalDeposits ?? 0) }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Active · Closed · Total FDs</h6>
        <small class="text-muted">Sum of all FDs (multi-FD clients included)</small>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-money-rupee-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($fdTotalDepositAmount, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Total Deposit Amount</h6>
        <small class="text-muted">Active principal outstanding</small>
        @if(($fdPeriodKey ?? 'all') !== 'all')
          <div class="small text-muted mt-1">New in period: ₹{{ number_format($fdPeriodNewDepositsAmount ?? 0, 2) }} ({{ $fdPeriodNewDepositsCount ?? 0 }})</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-percent-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($fdTotalInterestLiability, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Interest Liability</h6>
        <small class="text-muted">On active deposits</small>
      </div>
    </div>
  </div>
  <!-- Clients with Active FDs & System Clients -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-group-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <h4 class="mb-0 fw-bold">{{ number_format($fdActiveClients ?? 0) }}</h4>
            <small class="text-muted">With FD</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-primary">{{ number_format($fdActiveSystemClients ?? $fdActiveClients ?? 0) }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted">{{ number_format($fdTotalClients ?? 0) }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Clients with Active FDs</h6>
        <small class="text-muted">Distinct active clients having FDs</small>
        <a href="{{ route('fd.deposits.index') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>
</div>

<div class="row g-6 mb-6">
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Today's Maturity</small>
        <h4 class="mb-0 mt-1">{{ number_format($fdTodaysMaturity) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Upcoming (30 days)</small>
        <h4 class="mb-0 mt-1">{{ number_format($fdUpcomingMaturity) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Overdue Maturity</small>
        <h4 class="mb-0 mt-1 text-danger">{{ number_format($fdOverdueMaturity) }}</h4>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card h-100">
      <div class="card-body">
        <small class="text-muted">Wallet Credits / Renewals</small>
        <h4 class="mb-0 mt-1">₹{{ number_format($fdTotalWalletCredits, 2) }} / {{ number_format($fdTotalRenewals) }}</h4>
      </div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-6">
  <a href="{{ route('fd.deposits.create') }}" class="btn btn-primary">
    <i class="ri-add-line me-1"></i>Create FD
  </a>
  <a href="{{ route('fd.deposits.index') }}" class="btn btn-outline-secondary">
    <i class="ri-list-check me-1"></i>All Deposits
  </a>
  <a href="{{ route('fd.reports.index') }}" class="btn btn-outline-primary">
    <i class="ri-file-chart-line me-1"></i>Reports
  </a>
  <a href="{{ route('fd.schemes.index') }}" class="btn btn-outline-secondary">
    <i class="ri-settings-3-line me-1"></i>Schemes
  </a>
  <a href="{{ route('fd.wallets.index') }}" class="btn btn-outline-secondary">
    <i class="ri-wallet-3-line me-1"></i>Wallets
  </a>
</div>

<div class="row g-6 mb-6">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-1">Deposits vs Maturity</h5>
        <p class="card-subtitle mb-0">Yearly breakdown for {{ date('Y') }}</p>
      </div>
      <div class="card-body">
        <div id="fdDepositsMaturityChart"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-1">Interest Paid vs Liability</h5>
        <p class="card-subtitle mb-0">Monthly trend</p>
      </div>
      <div class="card-body">
        <div id="fdInterestChart"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-6 mb-6">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">Deposit Growth</h5>
      </div>
      <div class="card-body">
        <div id="fdDepositGrowthChart"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title mb-0">Renewal Trends</h5>
      </div>
      <div class="card-body">
        <div id="fdRenewalTrendsChart"></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-6 mb-6">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ri-history-line me-2 text-primary"></i>Recent Deposits</h5>
        <a href="{{ route('fd.deposits.index') }}" class="btn btn-sm btn-label-primary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>FD Number</th>
              <th>Customer</th>
              <th class="text-end">Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fdRecentDeposits as $fd)
              <tr>
                <td><a href="{{ route('fd.deposits.show', $fd) }}" class="fw-semibold">{{ $fd->fd_number }}</a></td>
                <td>{{ $fd->client->client_name ?? '—' }}</td>
                <td class="text-end">₹{{ number_format((float) $fd->deposit_amount, 2) }}</td>
                <td><span class="badge bg-{{ $fd->status_badge }}">{{ $fd->status_label }}</span></td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No recent deposits</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="ri-calendar-event-line me-2 text-warning"></i>Upcoming Maturities</h5>
        <a href="{{ route('fd.reports.show', 'maturity_upcoming') }}" class="btn btn-sm btn-label-warning">Report</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>FD Number</th>
              <th>Customer</th>
              <th>Maturity</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            @forelse($fdUpcomingList as $fd)
              <tr>
                <td><a href="{{ route('fd.deposits.show', $fd) }}" class="fw-semibold">{{ $fd->fd_number }}</a></td>
                <td>{{ $fd->client->client_name ?? '—' }}</td>
                <td>{{ optional($fd->maturity_date)->format('d M Y') }}</td>
                <td class="text-end">₹{{ number_format((float) $fd->maturity_amount, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-4 text-muted">No upcoming maturities</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof ApexCharts === 'undefined') return;

  const months = @json($fdMonths ?? []);
  const labelColor = '#a1b0cb';
  const borderColor = '#dbdade';
  const headingColor = '#5d596c';

  const commonAxis = {
    xaxis: {
      categories: months,
      labels: { style: { colors: labelColor, fontSize: '12px' } },
      axisBorder: { show: false },
      axisTicks: { show: false }
    },
    yaxis: {
      labels: {
        formatter: v => '₹' + Number(v).toLocaleString('en-IN'),
        style: { colors: labelColor, fontSize: '12px' }
      }
    },
    grid: { borderColor: borderColor, strokeDashArray: 5 },
    legend: { labels: { colors: headingColor } },
    dataLabels: { enabled: false },
    chart: { toolbar: { show: false }, parentHeightOffset: 0 }
  };

  const el1 = document.querySelector('#fdDepositsMaturityChart');
  if (el1) {
    new ApexCharts(el1, {
      ...commonAxis,
      chart: { ...commonAxis.chart, type: 'area', height: 320 },
      series: [
        { name: 'Deposits', data: @json($fdMonthlyDeposits ?? []) },
        { name: 'Maturity Payouts', data: @json($fdMonthlyMaturity ?? []) }
      ],
      colors: ['#666cff', '#28c76f'],
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { opacityFrom: 0.45, opacityTo: 0.1 } }
    }).render();
  }

  const el2 = document.querySelector('#fdInterestChart');
  if (el2) {
    new ApexCharts(el2, {
      ...commonAxis,
      chart: { ...commonAxis.chart, type: 'bar', height: 320 },
      series: [
        { name: 'Interest Paid', data: @json($fdInterestPaid ?? []) },
        { name: 'Liability', data: @json($fdInterestLiabilityChart ?? []) }
      ],
      colors: ['#28c76f', '#ff9f43'],
      plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } }
    }).render();
  }

  const el3 = document.querySelector('#fdDepositGrowthChart');
  if (el3) {
    new ApexCharts(el3, {
      ...commonAxis,
      chart: { ...commonAxis.chart, type: 'line', height: 280 },
      series: [{ name: 'Deposit Growth', data: @json($fdDepositGrowth ?? []) }],
      colors: ['#00cfe8'],
      stroke: { curve: 'smooth', width: 3 }
    }).render();
  }

  const el4 = document.querySelector('#fdRenewalTrendsChart');
  if (el4) {
    new ApexCharts(el4, {
      chart: { type: 'bar', height: 280, toolbar: { show: false }, parentHeightOffset: 0 },
      series: [{ name: 'Renewals', data: @json($fdRenewalTrends ?? []) }],
      colors: ['#7367f0'],
      xaxis: {
        categories: months,
        labels: { style: { colors: labelColor, fontSize: '12px' } }
      },
      yaxis: {
        labels: {
          formatter: v => Math.round(v),
          style: { colors: labelColor, fontSize: '12px' }
        }
      },
      dataLabels: { enabled: false },
      grid: { borderColor: borderColor, strokeDashArray: 5 },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } }
    }).render();
  }
});
</script>
