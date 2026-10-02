@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss'
])
@endsection

@section('page-style')
@vite('resources/assets/vendor/scss/pages/app-logistics-dashboard.scss')
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/apex-charts/apexcharts.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js'
])
@endsection

@section('page-script')
@php
  $dashboardData = [
    'emiChart' => $emiChart,
    'loanPerformanceChart' => $loanPerformanceChart,
    'loanDistributionChart' => $loanDistributionChart,
    'currencySymbol' => '₹',
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
@php
  $activeDashboardTab = $activeDashboardTab ?? 'loan';
@endphp
<style>
  .dashboard-summary-row > [class*="col-"] {
    display: flex;
  }

  .dashboard-summary-row .card {
    width: 100%;
  }
</style>
<!-- Header section with Refresh -->
<div class="row mb-4">
  <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h4 class="mb-0">Main Dashboard</h4>
      <p class="text-muted mb-0">
        Real-time business statistics overview
        <span class="badge bg-label-primary ms-1" id="dashboardPeriodBadge">
          @if($activeDashboardTab === 'chit')
            {{ $chitDashboardData['periodLabel'] ?? 'This Month' }}
          @elseif($activeDashboardTab === 'fd')
            {{ $fdPeriodLabel ?? 'This Month' }}
          @else
            {{ $periodLabel ?? 'This Month' }}
          @endif
        </span>
      </p>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
      {{-- Loan period filter: always submit tab=loan so period changes stay on this pane --}}
      <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 {{ $activeDashboardTab === 'loan' ? '' : 'd-none' }}" id="dashboardPeriodForm">
        <input type="hidden" name="tab" value="loan">
        @if(request('client_period'))
          <input type="hidden" name="client_period" value="{{ request('client_period') }}">
        @endif
        @if(request('chit_period'))
          <input type="hidden" name="chit_period" value="{{ request('chit_period') }}">
        @endif
        @if(request('chit_from'))
          <input type="hidden" name="chit_from" value="{{ request('chit_from') }}">
        @endif
        @if(request('chit_to'))
          <input type="hidden" name="chit_to" value="{{ request('chit_to') }}">
        @endif
        @if(request('fd_period'))
          <input type="hidden" name="fd_period" value="{{ request('fd_period') }}">
        @endif
        @if(request('fd_from'))
          <input type="hidden" name="fd_from" value="{{ request('fd_from') }}">
        @endif
        @if(request('fd_to'))
          <input type="hidden" name="fd_to" value="{{ request('fd_to') }}">
        @endif
        <label for="dashboardPeriodFilter" class="form-label mb-0 text-nowrap small fw-medium">Period:</label>
        <select name="period" id="dashboardPeriodFilter" class="form-select form-select-sm no-search dashboard-period-filter" style="min-width: 160px;" autocomplete="off">
          <option value="today" {{ ($periodKey ?? 'today') === 'today' ? 'selected' : '' }}>Today</option>
          <option value="month" {{ ($periodKey ?? 'today') === 'month' ? 'selected' : '' }}>This Month</option>
          <option value="year" {{ ($periodKey ?? '') === 'year' ? 'selected' : '' }}>This Year</option>
          <option value="custom" {{ ($periodKey ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
          <option value="all" {{ ($periodKey ?? '') === 'all' ? 'selected' : '' }}>All Time</option>
        </select>
        <div id="loanCustomDateRange" class="d-flex align-items-center gap-2 {{ ($periodKey ?? '') === 'custom' ? '' : 'd-none' }}">
          <input type="date" name="period_from" id="loanFromDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $periodFromInput ?? '' }}">
          <span class="text-muted small">to</span>
          <input type="date" name="period_to" id="loanToDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $periodToInput ?? '' }}">
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
      </form>

      {{-- Chit period filter --}}
      <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center flex-wrap gap-2 {{ $activeDashboardTab === 'chit' ? '' : 'd-none' }}" id="chitDashboardPeriodForm">
        <input type="hidden" name="tab" value="chit">
        @if(request('period'))
          <input type="hidden" name="period" value="{{ request('period') }}">
        @endif
        @if(request('period_from'))
          <input type="hidden" name="period_from" value="{{ request('period_from') }}">
        @endif
        @if(request('period_to'))
          <input type="hidden" name="period_to" value="{{ request('period_to') }}">
        @endif
        @if(request('client_period'))
          <input type="hidden" name="client_period" value="{{ request('client_period') }}">
        @endif
        @if(request('fd_period'))
          <input type="hidden" name="fd_period" value="{{ request('fd_period') }}">
        @endif
        @if(request('fd_from'))
          <input type="hidden" name="fd_from" value="{{ request('fd_from') }}">
        @endif
        @if(request('fd_to'))
          <input type="hidden" name="fd_to" value="{{ request('fd_to') }}">
        @endif
        <label for="chitDashboardPeriodFilter" class="form-label mb-0 text-nowrap small fw-medium">Period:</label>
        <select name="chit_period" id="chitDashboardPeriodFilter" class="form-select form-select-sm no-search dashboard-period-filter" style="min-width: 160px;" autocomplete="off">
          <option value="today" {{ ($chitDashboardData['periodKey'] ?? 'month') === 'today' ? 'selected' : '' }}>Today</option>
          <option value="month" {{ ($chitDashboardData['periodKey'] ?? 'month') === 'month' ? 'selected' : '' }}>This Month</option>
          <option value="year" {{ ($chitDashboardData['periodKey'] ?? '') === 'year' ? 'selected' : '' }}>This Year</option>
          <option value="custom" {{ ($chitDashboardData['periodKey'] ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
          <option value="all" {{ ($chitDashboardData['periodKey'] ?? '') === 'all' ? 'selected' : '' }}>All Time</option>
        </select>
        <div id="chitCustomDateRange" class="align-items-center gap-2 {{ ($chitDashboardData['periodKey'] ?? '') === 'custom' ? 'd-flex' : 'd-none' }}">
          <input type="date" name="chit_from" id="chitFromDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $chitDashboardData['periodFromInput'] ?? '' }}" {{ ($chitDashboardData['periodKey'] ?? '') === 'custom' ? '' : 'disabled' }}>
          <span class="text-muted small">to</span>
          <input type="date" name="chit_to" id="chitToDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $chitDashboardData['periodToInput'] ?? '' }}" {{ ($chitDashboardData['periodKey'] ?? '') === 'custom' ? '' : 'disabled' }}>
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
      </form>

      {{-- Fixed Deposit period filter --}}
      <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center flex-wrap gap-2 {{ $activeDashboardTab === 'fd' ? '' : 'd-none' }}" id="fdDashboardPeriodForm">
        <input type="hidden" name="tab" value="fd">
        @if(request('period'))
          <input type="hidden" name="period" value="{{ request('period') }}">
        @endif
        @if(request('period_from'))
          <input type="hidden" name="period_from" value="{{ request('period_from') }}">
        @endif
        @if(request('period_to'))
          <input type="hidden" name="period_to" value="{{ request('period_to') }}">
        @endif
        @if(request('chit_period'))
          <input type="hidden" name="chit_period" value="{{ request('chit_period') }}">
        @endif
        @if(request('chit_from'))
          <input type="hidden" name="chit_from" value="{{ request('chit_from') }}">
        @endif
        @if(request('chit_to'))
          <input type="hidden" name="chit_to" value="{{ request('chit_to') }}">
        @endif
        @if(request('client_period'))
          <input type="hidden" name="client_period" value="{{ request('client_period') }}">
        @endif
        <label for="fdDashboardPeriodFilter" class="form-label mb-0 text-nowrap small fw-medium">Period:</label>
        <select name="fd_period" id="fdDashboardPeriodFilter" class="form-select form-select-sm no-search dashboard-period-filter" style="min-width: 160px;" autocomplete="off">
          <option value="all" {{ ($fdPeriodKey ?? 'all') === 'all' ? 'selected' : '' }}>All Time</option>
          <option value="today" {{ ($fdPeriodKey ?? '') === 'today' ? 'selected' : '' }}>Today</option>
          <option value="month" {{ ($fdPeriodKey ?? '') === 'month' ? 'selected' : '' }}>This Month</option>
          <option value="year" {{ ($fdPeriodKey ?? '') === 'year' ? 'selected' : '' }}>This Year</option>
          <option value="custom" {{ ($fdPeriodKey ?? '') === 'custom' ? 'selected' : '' }}>Custom</option>
        </select>
        <div id="fdCustomDateRange" class="d-flex align-items-center gap-2 {{ ($fdPeriodKey ?? '') === 'custom' ? '' : 'd-none' }}">
          <input type="date" name="fd_from" id="fdFromDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $fdPeriodFromInput ?? '' }}">
          <span class="text-muted small">to</span>
          <input type="date" name="fd_to" id="fdToDate" class="form-control form-control-sm" style="min-width: 145px;"
                 value="{{ $fdPeriodToInput ?? '' }}">
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </div>
      </form>

      <button type="button" class="btn btn-primary d-flex align-items-center {{ $activeDashboardTab === 'loan' ? '' : 'd-none' }}" id="refreshStatsBtn" data-url="{{ route('dashboard.stats') }}">
        <i class="icon-base ri ri-refresh-line me-1"></i>
        <span class="d-none d-sm-inline">Refresh Data</span>
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const loanForm = document.getElementById('dashboardPeriodForm');
  const chitForm = document.getElementById('chitDashboardPeriodForm');
  const fdForm = document.getElementById('fdDashboardPeriodForm');
  const refreshBtn = document.getElementById('refreshStatsBtn');
  const periodBadge = document.getElementById('dashboardPeriodBadge');
  const chitPeriodSelect = document.getElementById('chitDashboardPeriodFilter');
  const chitCustomRange = document.getElementById('chitCustomDateRange');
  const loanPeriodSelect = document.getElementById('dashboardPeriodFilter');
  const loanCustomRange = document.getElementById('loanCustomDateRange');
  const fdPeriodSelect = document.getElementById('fdDashboardPeriodFilter');
  const fdCustomRange = document.getElementById('fdCustomDateRange');
  const loanPeriodLabel = @json($periodLabel ?? 'This Month');
  const chitPeriodLabel = @json($chitDashboardData['periodLabel'] ?? 'This Month');
  const fdPeriodLabel = @json($fdPeriodLabel ?? 'This Month');

  function periodLabelForTab(tab) {
    if (tab === 'chit') return chitPeriodLabel;
    if (tab === 'fd') return fdPeriodLabel;
    return loanPeriodLabel;
  }

  function toggleDashboardFilters(tab) {
    if (loanForm) loanForm.classList.toggle('d-none', tab !== 'loan');
    if (chitForm) chitForm.classList.toggle('d-none', tab !== 'chit');
    if (fdForm) fdForm.classList.toggle('d-none', tab !== 'fd');
    if (refreshBtn) refreshBtn.classList.toggle('d-none', tab !== 'loan');
    if (periodBadge) {
      periodBadge.textContent = periodLabelForTab(tab);
    }
  }

  document.querySelectorAll('[data-dashboard-tab]').forEach(function (btn) {
    btn.addEventListener('shown.bs.tab', function () {
      toggleDashboardFilters(btn.getAttribute('data-dashboard-tab'));
    });
  });

  function ensureNativeSelect(select) {
    if (!select) return;
    select.classList.add('no-search', 'dashboard-period-filter');
    if (window.jQuery) {
      const $el = window.jQuery(select);
      if ($el.data('select2')) {
        $el.select2('destroy');
      }
    }
  }

  function setCustomRangeVisible(customRange, visible) {
    if (!customRange) return;
    customRange.classList.remove('d-flex', 'd-none');
    customRange.classList.add(visible ? 'd-flex' : 'd-none');
    customRange.style.display = visible ? 'flex' : 'none';
    customRange.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.disabled = !visible;
    });
  }

  function bindPeriodSelect(select, customRange, form) {
    if (!select || !form) return;
    ensureNativeSelect(select);
    setCustomRangeVisible(customRange, select.value === 'custom');

    function onPeriodChange() {
      const isCustom = select.value === 'custom';
      setCustomRangeVisible(customRange, isCustom);
      if (!isCustom) {
        form.submit();
      }
    }

    select.addEventListener('change', onPeriodChange);
    if (window.jQuery) {
      window.jQuery(select).off('change.dashboardPeriod').on('change.dashboardPeriod', onPeriodChange);
    }

    if (customRange) {
      customRange.querySelectorAll('input[type="date"]').forEach(function (input) {
        input.addEventListener('change', function () {
          if (select.value !== 'custom') {
            select.value = 'custom';
            setCustomRangeVisible(customRange, true);
          }
        });
      });
    }
  }

  bindPeriodSelect(loanPeriodSelect, loanCustomRange, loanForm);
  bindPeriodSelect(chitPeriodSelect, chitCustomRange, chitForm);
  bindPeriodSelect(fdPeriodSelect, fdCustomRange, fdForm);

  // Select2 may init after this script; tear it down once more on window load.
  window.addEventListener('load', function () {
    ensureNativeSelect(loanPeriodSelect);
    ensureNativeSelect(chitPeriodSelect);
    ensureNativeSelect(fdPeriodSelect);
  });
});
</script>

<ul class="nav nav-pills mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button type="button"
      class="nav-link {{ $activeDashboardTab === 'loan' ? 'active' : '' }}"
      id="loan-dashboard-tab"
      data-bs-toggle="tab"
      data-bs-target="#loan-dashboard-pane"
      role="tab"
      aria-controls="loan-dashboard-pane"
      aria-selected="{{ $activeDashboardTab === 'loan' ? 'true' : 'false' }}"
      data-dashboard-tab="loan">
      <i class="ri-bank-line me-1"></i> Loan Dashboard
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button type="button"
      class="nav-link {{ $activeDashboardTab === 'chit' ? 'active' : '' }}"
      id="chit-dashboard-tab"
      data-bs-toggle="tab"
      data-bs-target="#chit-dashboard-pane"
      role="tab"
      aria-controls="chit-dashboard-pane"
      aria-selected="{{ $activeDashboardTab === 'chit' ? 'true' : 'false' }}"
      data-dashboard-tab="chit">
      <i class="ri-group-line me-1"></i> Chit Dashboard
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button type="button"
      class="nav-link {{ $activeDashboardTab === 'fd' ? 'active' : '' }}"
      id="fd-dashboard-tab"
      data-bs-toggle="tab"
      data-bs-target="#fd-dashboard-pane"
      role="tab"
      aria-controls="fd-dashboard-pane"
      aria-selected="{{ $activeDashboardTab === 'fd' ? 'true' : 'false' }}"
      data-dashboard-tab="fd">
      <i class="ri-safe-2-line me-1"></i> Fixed Deposit
    </button>
  </li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade {{ $activeDashboardTab === 'loan' ? 'show active' : '' }}" id="loan-dashboard-pane" role="tabpanel" aria-labelledby="loan-dashboard-tab" tabindex="0">

<!-- Statistics Cards -->
<div class="row g-6 mb-6 dashboard-summary-row">
  <!-- Total Collections (Overall / Admin / Agent) -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-hand-coin-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <a href="{{ route('agent-collections') }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View overall collections">
              <h5 class="mb-0 fw-bold" id="stat-totalCollections">₹ {{ formatIndianCurrency($totalCollections ?? (($adminCollections ?? 0) + ($agentCollections ?? 0))) }}</h5>
              <small class="text-muted">Overall</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('agent-collections', ['collector' => 'admin']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View admin collections">
              <h5 class="mb-0 fw-bold" id="stat-adminCollections">₹ {{ formatIndianCurrency($adminCollections ?? 0) }}</h5>
              <small class="text-muted">Admin</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('agent-collections', ['collector' => 'agent']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View agent collections">
              <h5 class="mb-0 fw-bold" id="stat-agentCollections">₹ {{ formatIndianCurrency($agentCollections ?? 0) }}</h5>
              <small class="text-muted">Agent</small>
            </a>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Collections</h6>
        <small class="text-muted">{{ $periodLabel ?? 'This Month' }}</small>
      </div>
    </div>
  </div>


   <!-- Pending & Overdue -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-danger h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-danger">
              <i class="icon-base ri ri-alert-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <a href="{{ route('emi-repayments', ['status' => 'pending']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View pending EMIs for selected period">
              <h4 class="mb-0 fw-bold" id="stat-pendingLoans">{{ $pendingLoans ?? 0 }}</h4>
              <small class="text-muted">Pending</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('emi-repayments', ['status' => 'overdue']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View overdue EMI repayments">
              <h4 class="mb-0 fw-bold" id="stat-overdueLoans">{{ $overdueLoans ?? 0 }}</h4>
              <small class="text-muted">Overdue</small>
            </a>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Pending · Overdue</h6>
        <small class="text-muted">{{ $periodLabel ?? 'This Month' }}</small>
      </div>
    </div>
  </div>



  <!-- Active, Closed & Total Loans -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-info">
              <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
            </span>
          </div>
          <div class="d-flex gap-2 align-items-baseline flex-wrap">
            <h4 class="mb-0 fw-bold" id="stat-activeLoans">{{ $activeLoans ?? 0 }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-secondary" id="stat-closedLoans">{{ $closedLoans ?? 0 }}</h4>
            <small class="text-muted">Closed</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted" id="stat-totalLoans">{{ $totalLoans ?? 0 }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Active · Closed · Total Loans</h6>
        <small class="text-muted">Sum of all loans (multi-loan clients included)</small>
        <a href="{{ route('loan-accounts') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Clients with Active Loans & System Clients -->
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
            <h4 class="mb-0 fw-bold" id="stat-loanActiveClients">{{ $loanActiveClientsCount ?? $activeClients ?? 0 }}</h4>
            <small class="text-muted">With Loan</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-primary" id="stat-activeClients">{{ $activeClients ?? 0 }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted" id="stat-totalClients">{{ $totalClients ?? 0 }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Clients with Active Loans</h6>
        <small class="text-muted">Distinct active clients having loans</small>
        <a href="{{ route('client-management') }}" class="stretched-link"></a>
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
        <small class="text-muted">{{ $periodLabel ?? 'This Month' }}</small>
        <a href="{{ route('reports-revenue') }}" class="stretched-link" title="View revenue report"></a>
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
        <small class="text-muted">{{ $periodLabel ?? 'This Month' }}</small>
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
        <small class="text-muted">{{ $periodLabel ?? 'This Month' }}</small>
        <a href="{{ route('loan-accounts') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Uncollected Interest Amount (Monthly Fixed EMI — current month) -->
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
        <h6 class="mb-0 h6 fw-normal">Uncollected Interest (Monthly EMI)</h6>
        <small class="text-muted">{{ now()->format('M Y') }} · Pending this month</small>
        <a href="{{ route('emi-repayments', [
              'status' => 'pending',
              'from_date' => now()->startOfMonth()->format('Y-m-d'),
              'to_date' => now()->endOfMonth()->format('Y-m-d'),
              'term_unit' => 'monthly',
              'loan_mode' => 'emi',
            ]) }}" class="stretched-link" title="View current month pending monthly EMI list"></a>
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

<!-- Agent Collection & Overdue Summary -->
<div class="row mb-6">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <h5 class="card-title mb-1">Agent Collection & Overdue Summary</h5>
          <p class="card-subtitle mb-0">Assigned customer status and collections for {{ now()->format('d M Y') }}</p>
        </div>
        <span class="badge bg-label-danger">
          {{ ($agentCollectionSummary ?? collect())->sum('overdue_customers') }} overdue customers
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Agent</th>
              <th class="text-center">Assigned</th>
              <th class="text-center">Overdue</th>
              <th class="text-center">Pending</th>
              <th class="text-center">Paid</th>
              <th class="text-center">Upcoming</th>
              <th class="text-end">Collected Today</th>
              <th class="text-end">Details</th>
            </tr>
          </thead>
          <tbody>
            @forelse (($agentCollectionSummary ?? collect()) as $agentSummary)
              <tr>
                <td>
                  <div class="fw-medium">{{ $agentSummary['name'] }}</div>
                  <small class="text-muted">{{ $agentSummary['code'] ?: 'No agent code' }}</small>
                </td>
                <td class="text-center">{{ $agentSummary['assigned_customers'] }}</td>
                <td class="text-center">
                  <span class="badge bg-label-danger">{{ $agentSummary['overdue_customers'] }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-label-warning">{{ $agentSummary['pending_customers'] }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-label-success">{{ $agentSummary['paid_customers'] }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-label-info">{{ $agentSummary['upcoming_customers'] }}</span>
                </td>
                <td class="text-end fw-medium">₹ {{ number_format($agentSummary['collected_today'], 2) }}</td>
                <td class="text-end">
                  <button type="button"
                    class="btn btn-sm btn-outline-primary js-agent-collection-details"
                    data-details-url="{{ $agentSummary['details_url'] }}"
                    data-agent-name="{{ $agentSummary['name'] }}">
                    View
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">No agents are available.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!--/ Agent Collection & Overdue Summary -->

<!-- EMI Collection Performance -->
<div class="row mb-0">
  <div class="col-12">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2 mb-1">EMI Collection Performance</h5>
          <p class="card-subtitle mb-0">{{ $periodLabel ?? 'This Month' }}</p>
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
</div>
<!--/ EMI Collection Performance -->
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
<div class="row">
  <div class="col-12 mt-5">
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
                <td colspan="6" class="text-center py-5">
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
  </div><!-- /#loan-dashboard-pane -->

  <div class="tab-pane fade {{ $activeDashboardTab === 'chit' ? 'show active' : '' }}" id="chit-dashboard-pane" role="tabpanel" aria-labelledby="chit-dashboard-tab" tabindex="0">
    @include('admin.chit.partials.dashboard-content', $chitDashboardData ?? [])
  </div>

  <div class="tab-pane fade {{ $activeDashboardTab === 'fd' ? 'show active' : '' }}" id="fd-dashboard-pane" role="tabpanel" aria-labelledby="fd-dashboard-tab" tabindex="0">
    @include('admin.fd.partials.dashboard-content')
  </div>
</div><!-- /.tab-content -->

<div class="modal fade" id="agentCollectionDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="agentCollectionDetailsTitle">Agent Collection Details</h5>
          <small class="text-muted" id="agentCollectionDetailsSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="agentCollectionDetailsLoading" class="text-center py-5">
          <span class="spinner-border text-primary" role="status"></span>
          <p class="text-muted mt-3 mb-0">Loading collection details...</p>
        </div>
        <div id="agentCollectionDetailsError" class="alert alert-danger d-none mb-0"></div>
        <div id="agentCollectionDetailsContent" class="d-none">
          <div class="row g-3 mb-5">
            <div class="col-md-4">
              <div class="border rounded p-3 h-100">
                <small class="text-muted">Agent</small>
                <h6 class="mb-0 mt-1" id="agentDetailName"></h6>
                <small class="text-muted" id="agentDetailContact"></small>
              </div>
            </div>
            <div class="col-md-4">
              <div class="border rounded p-3 h-100">
                <small class="text-muted">Report Date</small>
                <h6 class="mb-0 mt-1" id="agentDetailDate"></h6>
              </div>
            </div>
            <div class="col-md-4">
              <div class="border rounded p-3 h-100">
                <small class="text-muted">Collected Today</small>
                <h5 class="mb-0 mt-1 text-success" id="agentDetailTotal"></h5>
              </div>
            </div>
          </div>

          <h6 class="mb-3">Assigned Customer Status</h6>
          <div class="table-responsive border rounded mb-5">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th class="text-center">Overdue</th>
                  <th class="text-center">Pending</th>
                  <th class="text-center">Paid</th>
                  <th class="text-center">Upcoming</th>
                  <th class="text-end">Collected Today</th>
                </tr>
              </thead>
              <tbody id="agentClientStatusRows"></tbody>
            </table>
          </div>

          <h6 class="mb-3">Today's Client Collection Transactions</h6>
          <div class="table-responsive border rounded">
            <table class="table table-sm table-hover mb-0">
              <thead>
                <tr>
                  <th>Date & Time</th>
                  <th>Customer</th>
                  <th>Loan / EMI</th>
                  <th>Method</th>
                  <th>Status</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody id="agentCollectionRows"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const refreshBtn = document.getElementById('refreshStatsBtn');
    const tabButtons = document.querySelectorAll('[data-dashboard-tab]');

    tabButtons.forEach(function (btn) {
      btn.addEventListener('shown.bs.tab', function (event) {
        const tab = event.target.getAttribute('data-dashboard-tab') || 'loan';
        if (refreshBtn) {
          refreshBtn.classList.toggle('d-none', tab !== 'loan');
        }
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
      });
    });
  });
</script>
@endsection
