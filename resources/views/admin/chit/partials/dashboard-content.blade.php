@php
  if (!function_exists('formatIndianCurrency')) {
      function formatIndianCurrency($amount) {
          $amount = (float) $amount;
          if ($amount >= 10000000) {
              return number_format($amount / 10000000, 2) . ' C';
          } elseif ($amount >= 100000) {
              return number_format($amount / 100000, 2) . ' L';
          } elseif ($amount >= 1000) {
              return number_format($amount / 1000, 2) . ' K';
          }
          return number_format($amount, 2);
      }
  }

  $periodCollections = $periodCollections ?? (($adminCollections ?? 0) + ($agentCollections ?? 0));
  $adminCollections = $adminCollections ?? 0;
  $agentCollections = $agentCollections ?? 0;
  $pendingInstallments = $pendingInstallments ?? 0;
  $overdueInstallments = $overdueInstallments ?? 0;
  $totalChitClients = $totalChitClients ?? 0;
  $activeChitClients = $activeChitClients ?? 0;
  $totalGroupsCount = $totalGroupsCount ?? (($activeGroupsCount ?? 0) + ($formingGroupsCount ?? 0));
  $periodLabel = $periodLabel ?? 'This Month';
  $periodKey = $periodKey ?? 'month';
  $trendYear = $trendYear ?? (int) date('Y');
@endphp

<style>
  .chit-dashboard-summary-row > [class*="col-"] {
    display: flex;
  }
  .chit-dashboard-summary-row .card {
    width: 100%;
  }
</style>

@if(!request()->routeIs('dashboard'))
{{-- Standalone chit page: same compact Period dropdown as loan dashboard --}}
<div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mb-4">
  <form method="GET" action="{{ route('chit.dashboard') }}" class="d-flex align-items-center flex-wrap gap-2" id="standaloneChitPeriodForm">
    <label for="standaloneChitPeriodFilter" class="form-label mb-0 text-nowrap small fw-medium">Period:</label>
    <select name="chit_period" id="standaloneChitPeriodFilter" class="form-select form-select-sm no-search dashboard-period-filter" style="min-width: 160px;" autocomplete="off">
      <option value="today" {{ $periodKey === 'today' ? 'selected' : '' }}>Today</option>
      <option value="month" {{ $periodKey === 'month' ? 'selected' : '' }}>This Month</option>
      <option value="year" {{ $periodKey === 'year' ? 'selected' : '' }}>This Year</option>
      <option value="custom" {{ $periodKey === 'custom' ? 'selected' : '' }}>Custom</option>
      <option value="all" {{ $periodKey === 'all' ? 'selected' : '' }}>All Time</option>
    </select>
    <div id="standaloneChitCustomDateRange" class="align-items-center gap-2 {{ $periodKey === 'custom' ? 'd-flex' : 'd-none' }}">
      <input type="date" name="chit_from" class="form-control form-control-sm" style="min-width: 145px;" value="{{ $periodFromInput ?? '' }}" {{ $periodKey === 'custom' ? '' : 'disabled' }}>
      <span class="text-muted small">to</span>
      <input type="date" name="chit_to" class="form-control form-control-sm" style="min-width: 145px;" value="{{ $periodToInput ?? '' }}" {{ $periodKey === 'custom' ? '' : 'disabled' }}>
      <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const select = document.getElementById('standaloneChitPeriodFilter');
  const customRange = document.getElementById('standaloneChitCustomDateRange');
  const form = document.getElementById('standaloneChitPeriodForm');
  if (!select || !form) return;

  function setCustomVisible(visible) {
    if (!customRange) return;
    customRange.classList.remove('d-flex', 'd-none');
    customRange.classList.add(visible ? 'd-flex' : 'd-none');
    customRange.style.display = visible ? 'flex' : 'none';
    customRange.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.disabled = !visible;
    });
  }

  setCustomVisible(select.value === 'custom');

  select.addEventListener('change', function () {
    const isCustom = this.value === 'custom';
    setCustomVisible(isCustom);
    if (!isCustom) {
      form.submit();
    }
  });

  if (customRange) {
    customRange.querySelectorAll('input[type="date"]').forEach(function (input) {
      input.addEventListener('change', function () {
        if (select.value !== 'custom') {
          select.value = 'custom';
          setCustomVisible(true);
        }
      });
    });
  }
});
</script>
@endif

<!-- Loan-style summary cards -->
<div class="row g-6 mb-6 chit-dashboard-summary-row">
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
            <a href="{{ route('agent-collections', ['type' => 'chit']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View overall chit collections">
              <h5 class="mb-0 fw-bold">₹ {{ formatIndianCurrency($periodCollections) }}</h5>
              <small class="text-muted">Overall</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('agent-collections', ['type' => 'chit', 'collector' => 'admin']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View admin chit collections">
              <h5 class="mb-0 fw-bold">₹ {{ formatIndianCurrency($adminCollections) }}</h5>
              <small class="text-muted">Admin</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('agent-collections', ['type' => 'chit', 'collector' => 'agent']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View agent chit collections">
              <h5 class="mb-0 fw-bold">₹ {{ formatIndianCurrency($agentCollections) }}</h5>
              <small class="text-muted">Agent</small>
            </a>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Collections</h6>
        <small class="text-muted">{{ $periodLabel }}</small>
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
            <a href="{{ route('chit.installments.index', ['status' => 'pending']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View pending chit installments">
              <h4 class="mb-0 fw-bold">{{ $pendingInstallments }}</h4>
              <small class="text-muted">Pending</small>
            </a>
            <span class="text-muted align-self-center">|</span>
            <a href="{{ route('chit.installments.index', ['status' => 'overdue']) }}"
              class="text-decoration-none text-body d-flex flex-column"
              title="View overdue chit installments">
              <h4 class="mb-0 fw-bold">{{ $overdueInstallments }}</h4>
              <small class="text-muted">Overdue</small>
            </a>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Pending · Overdue</h6>
        <small class="text-muted">{{ $periodLabel }}</small>
      </div>
    </div>
  </div>

  <!-- Active, Closed & Total Groups -->
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
            <h4 class="mb-0 fw-bold">{{ $activeGroupsCount ?? 0 }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-secondary">{{ $completedGroupsCount ?? 0 }}</h4>
            <small class="text-muted">Closed</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted">{{ $totalGroupsCount ?? 0 }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Active · Closed · Total Groups</h6>
        <small class="text-muted">{{ $totalActiveChitSeats ?? 0 }} active member seats total</small>
        <a href="{{ route('chit.groups.index') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Clients with Active Chits & System Clients -->
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
            <h4 class="mb-0 fw-bold">{{ $activeChitClients ?? 0 }}</h4>
            <small class="text-muted">With Chit</small>
            <span class="text-muted">|</span>
            <h4 class="mb-0 fw-bold text-primary">{{ $activeSystemClients ?? $activeChitClients ?? 0 }}</h4>
            <small class="text-muted">Active</small>
            <span class="text-muted">|</span>
            <h5 class="mb-0 fw-bold text-muted">{{ $totalChitClients ?? 0 }}</h5>
            <small class="text-muted">Total</small>
          </div>
        </div>
        <h6 class="mb-0 h6 fw-normal">Clients with Active Chits</h6>
        <small class="text-muted">Distinct active clients in chit groups</small>
        <a href="{{ route('chit.accounts.index') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>
</div>

<!-- Statistics Cards -->
<div class="row g-6 mb-6">
  <!-- Group Balance -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-scales-3-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($groupBalance, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Group Balance</h6>
        <small class="text-muted">Collected − Settled − Foreman Commission</small>
      </div>
    </div>
  </div>

  <!-- Available Group Funds -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-wallet-3-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($availableGroupFunds ?? $groupBalance, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Available Group Funds</h6>
        <small class="text-muted">Balance + Dividend Pool (₹{{ number_format($dividendPoolTotal ?? 0, 2) }})</small>
      </div>
    </div>
  </div>

  <!-- Total Collections -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-arrow-down-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($totalCollections, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Total Collection Amount</h6>
        <small class="text-muted">Total installment collections received</small>
      </div>
    </div>
  </div>

  <!-- Total Settlements -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-danger h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-danger">
              <i class="icon-base ri ri-arrow-up-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹{{ number_format($totalSettlements, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Total Settlement Amount</h6>
        <small class="text-muted">Disbursed winning bids</small>
      </div>
    </div>
  </div>

  <!-- Overall Group Dividend -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-gift-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold text-warning">₹{{ number_format($dividendPoolTotal ?? 0, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Overall Group Dividend</h6>
        <small class="text-muted">Pool Balance (Distributed: ₹{{ number_format($totalDistributedDividends ?? 0, 2) }})</small>
        <a href="{{ route('chit.dividends.index') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Foreman Commission -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-secondary h-100 position-relative">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-secondary">
              <i class="icon-base ri ri-user-star-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold text-secondary">₹{{ number_format($totalForemanCommission ?? 0, 2) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Foreman Commission</h6>
        <small class="text-muted">From paid payouts  </small>
        <a href="{{ route('chit.settlements.history') }}" class="stretched-link"></a>
      </div>
    </div>
  </div>

  <!-- Active Groups -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-info">
              <i class="icon-base ri ri-group-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $activeGroupsCount }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal text-muted">Active Chit Groups</h6>
        <small class="text-muted">Out of {{ $activeGroupsCount + $formingGroupsCount }} active/forming ({{ $deletedGroupsCount ?? 0 }} in Recycle Bin)</small>
      </div>
    </div>
  </div>
</div>

<div class="row g-6 mb-6">
  <!-- Analytics Chart: Collections vs Settlements -->
  <div class="col-lg-8 col-12">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <h5 class="card-title mb-1">Collections vs Settlements Trend</h5>
          <p class="card-subtitle mb-0">Yearly breakdown for {{ $trendYear }} · {{ $periodLabel }}</p>
        </div>
      </div>
      <div class="card-body">
        @if($totalCollections == 0 && $totalSettlements == 0)
          <div class="text-center py-5">
            <div class="mb-3">
              <i class="icon-base ri ri-bar-chart-line text-muted" style="font-size: 48px;"></i>
            </div>
            <h6 class="text-muted">No transaction data available yet</h6>
            <p class="text-muted small">Analytics will appear here once collections and settlements are processed</p>
          </div>
        @else
          <div id="collectionsSettlementsChart"></div>
        @endif
      </div>
    </div>
  </div>

  <!-- Summary Statistics List -->
  <div class="col-lg-4 col-12">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Chit Overview Statistics</h5>
      </div>
      <div class="card-body pb-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-success p-2 rounded"><i class="ri-checkbox-circle-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Active Schemes</p>
              <h6 class="mb-0">{{ $totalSchemesCount }}</h6>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-warning p-2 rounded"><i class="ri-user-add-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Enrolled Members</p>
              <h6 class="mb-0">{{ $totalMembersCount }}</h6>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-info p-2 rounded"><i class="ri-time-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Forming Groups</p>
              <h6 class="mb-0">{{ $formingGroupsCount }}</h6>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-danger p-2 rounded"><i class="ri-delete-bin-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Recycle Bin (Deleted Groups)</p>
              <h6 class="mb-0 text-danger">{{ $deletedGroupsCount ?? 0 }}</h6>
            </div>
          </div>
          <a href="{{ route('chit.groups.index', ['trash' => 'true']) }}" class="btn btn-xs btn-outline-danger">View</a>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-primary p-2 rounded"><i class="ri-money-rupee-circle-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Available Group Funds</p>
              <h6 class="mb-0 text-primary">₹{{ number_format($availableGroupFunds ?? $groupBalance, 2) }}</h6>
            </div>
          </div>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-warning p-2 rounded"><i class="ri-gift-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Overall Group Dividend</p>
              <h6 class="mb-0 text-warning">₹{{ number_format($dividendPoolTotal ?? 0, 2) }}</h6>
            </div>
          </div>
          <a href="{{ route('chit.dividends.index') }}" class="btn btn-xs btn-outline-warning">View</a>
        </div>
        <hr class="my-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-label-secondary p-2 rounded"><i class="ri-user-star-line ri-20px"></i></span>
            <div>
              <p class="mb-0 text-muted small">Foreman Commission</p>
              <h6 class="mb-0 text-secondary">₹{{ number_format($totalForemanCommission ?? 0, 2) }}</h6>
            </div>
          </div>
          <a href="{{ route('chit.settlements.history') }}" class="btn btn-xs btn-outline-secondary">History</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Group-wise Financial Summary Table -->
<div class="card mb-6">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h5 class="mb-0"><i class="ri-pages-line me-2 text-primary"></i>Group-wise Financial Summary</h5>
      <small class="text-muted">Active and forming chit groups</small>
    </div>
    <a href="{{ route('chit.groups.index') }}" class="btn btn-sm btn-label-primary">View All Groups</a>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>Group Code</th>
          <th>Scheme Name</th>
          <th>Type</th>
          <th>Members slots</th>
          <th class="text-end">Collections</th>
          <th class="text-end">Settlements</th>
          <th>Settlement to whom</th>
          <th class="text-end">Group Balance</th>
          <th class="text-end">Dividend Pool</th>
          <th class="text-end">Available Funds</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse(($groupWiseSummary ?? collect()) as $g)
          <tr>
            <td><strong>{{ $g['group_code'] }}</strong></td>
            <td>{{ $g['scheme_name'] }}</td>
            <td><span class="badge bg-label-secondary small">{{ $g['scheme_type'] }}</span></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress w-100" style="height: 6px;">
                  <div class="progress-bar bg-primary" style="width: {{ $g['total_members'] > 0 ? min(100, ($g['members_count'] / $g['total_members'] * 100)) : 0 }}%"></div>
                </div>
                <small class="text-muted">
                  {{ is_numeric($g['members_count']) && abs($g['members_count'] - round($g['members_count'])) < 0.001 ? (int) round($g['members_count']) : number_format($g['members_count'], 1) }}/{{ $g['total_members'] }} seats
                  @if(!empty($g['members_enrolled']) && (int) $g['members_enrolled'] !== (int) round($g['members_count']))
                    · {{ $g['members_enrolled'] }} {{ \Illuminate\Support\Str::plural('member', $g['members_enrolled']) }}
                  @endif
                </small>
              </div>
            </td>
            <td class="text-end text-success fw-medium">₹{{ number_format($g['collections'], 2) }}</td>
            <td class="text-end text-danger fw-medium">₹{{ number_format($g['settlements'], 2) }}</td>
            <td>
              @if(!empty($g['settlement_to']))
                <div class="fw-semibold">{{ $g['settlement_to'] }}</div>
                <small class="text-muted">
                  @if(!empty($g['settlement_to_member']))#{{ $g['settlement_to_member'] }}@endif
                  @if(!empty($g['settlement_month'])) · Month {{ $g['settlement_month'] }}@endif
                </small>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>
            <td class="text-end fw-bold {{ ($g['balance'] ?? 0) < 0 ? 'text-danger' : '' }}">₹{{ number_format($g['balance'], 2) }}</td>
            <td class="text-end text-success">₹{{ number_format($g['dividend_pool'] ?? 0, 2) }}</td>
            <td class="text-end fw-bold text-primary">₹{{ number_format($g['available_funds'] ?? $g['balance'], 2) }}</td>
            <td class="text-center">
              <span class="badge bg-{{ $g['status_badge'] }}">{{ ucfirst($g['status']) }}</span>
            </td>
            <td class="text-center">
              <a href="{{ route('chit.groups.show', $g['id']) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill border-0">
                <i class="ri-eye-line"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="12" class="text-center py-5">
              <div class="mb-3">
                <i class="icon-base ri ri-pages-line text-muted" style="font-size: 48px;"></i>
              </div>
              <h6 class="text-muted">No active or forming chit groups found</h6>
              <p class="text-muted small">Groups will appear here once created and kept out of Recycle Bin</p>
              <a href="{{ route('chit.groups.index') }}" class="btn btn-sm btn-primary mt-2">Go to Chit Groups</a>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<!-- Recent Settlements — Settlement to whom -->
<div class="card mb-6">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h5 class="mb-0"><i class="ri-user-received-2-line me-2 text-danger"></i>Recent Settlements — Settlement to whom</h5>
    <a href="{{ route('chit.settlements.history') }}" class="btn btn-sm btn-label-primary">
      View All History
    </a>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Code</th>
          <th>Group</th>
          <th>Month</th>
          <th>Settlement to whom</th>
          <th class="text-end">Amount</th>
          <th>Paid Date</th>
          <th>Mode</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse(($recentSettlements ?? collect()) as $settlement)
          <tr>
            <td><code>{{ $settlement['payout_code'] }}</code></td>
            <td>
              <div class="fw-semibold">{{ $settlement['group_code'] }}</div>
              <small class="text-muted">{{ $settlement['scheme_name'] }}</small>
            </td>
            <td><span class="badge bg-label-primary">Month {{ $settlement['month_number'] }}</span></td>
            <td>
              <div class="fw-semibold">{{ $settlement['settlement_to'] }}</div>
              @if(!empty($settlement['member_number']))
                <small class="text-muted">Member #{{ $settlement['member_number'] }}</small>
              @endif
            </td>
            <td class="text-end text-danger fw-bold">₹{{ number_format($settlement['amount'], 2) }}</td>
            <td>{{ $settlement['paid_date'] }}</td>
            <td>{{ $settlement['payment_mode'] ?: '—' }}</td>
            <td class="text-center">
              <a href="{{ route('chit.groups.show', $settlement['group_id']) }}" class="btn btn-sm btn-icon btn-text-secondary rounded-pill border-0" title="View group">
                <i class="ri-eye-line"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center py-5">
              <div class="mb-3">
                <i class="icon-base ri ri-user-received-2-line text-muted" style="font-size: 48px;"></i>
              </div>
              <h6 class="text-muted">No settlements paid yet</h6>
              <p class="text-muted small">Paid settlement recipients will appear here</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@if($totalCollections > 0 || $totalSettlements > 0)
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const headingColor = '#5d596c';
    const labelColor = '#a1b0cb';
    const borderColor = '#dbdade';
    let chitTrendChart = null;

    const chartOptions = {
      series: [
        {
          name: 'Collections',
          data: @json($collectionsTrend)
        },
        {
          name: 'Settlements',
          data: @json($settlementsTrend)
        }
      ],
      chart: {
        height: 320,
        type: 'area',
        parentHeightOffset: 0,
        toolbar: {
          show: false
        }
      },
      dataLabels: {
        enabled: false
      },
      stroke: {
        show: true,
        curve: 'smooth',
        width: 2,
        lineCap: 'round'
      },
      legend: {
        show: true,
        position: 'top',
        horizontalAlign: 'start',
        labels: {
          colors: headingColor
        }
      },
      colors: ['#28c76f', '#ea5455'],
      fill: {
        type: 'gradient',
        gradient: {
          shade: 'dark',
          shadeIntensity: 0.6,
          opacityFrom: 0.5,
          opacityTo: 0.15,
          stops: [0, 90, 100]
        }
      },
      xaxis: {
        categories: @json($months),
        axisBorder: {
          show: false
        },
        axisTicks: {
          show: false
        },
        labels: {
          style: {
            colors: labelColor,
            fontSize: '13px'
          }
        }
      },
      yaxis: {
        labels: {
          formatter: function (value) {
            return '₹' + value.toLocaleString();
          },
          style: {
            colors: labelColor,
            fontSize: '13px'
          }
        }
      },
      grid: {
        borderColor: borderColor,
        strokeDashArray: 5,
        padding: {
          top: -20,
          bottom: -10,
          left: 0,
          right: 0
        }
      }
    };

    function renderChitTrendChart() {
      const chartElement = document.querySelector('#collectionsSettlementsChart');
      if (!chartElement || typeof ApexCharts === 'undefined') {
        return;
      }

      if (chitTrendChart) {
        chitTrendChart.windowResizeHandler();
        return;
      }

      chitTrendChart = new ApexCharts(chartElement, chartOptions);
      chitTrendChart.render();
    }

    const chitPane = document.getElementById('chit-dashboard-pane');
    const chitTab = document.getElementById('chit-dashboard-tab');

    if (chitPane && chitPane.classList.contains('active')) {
      renderChitTrendChart();
    } else if (!chitPane) {
      // Standalone chit dashboard page
      renderChitTrendChart();
    }

    if (chitTab) {
      chitTab.addEventListener('shown.bs.tab', function () {
        renderChitTrendChart();
      });
    }
  });
</script>
@endif
