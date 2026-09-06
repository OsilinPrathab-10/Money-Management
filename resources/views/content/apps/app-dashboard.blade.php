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
@vite('resources/assets/js/app-logistics-dashboard.js')
@endsection

@section('content')
<!-- Statistics Cards -->
<div class="row g-6 mb-6">
  <!-- Total Agents -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-user-star-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $totalAgents ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Agents</h6>
      </div>
    </div>
  </div>

  <!-- Total Clients -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-group-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $totalClients ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Clients</h6>
      </div>
    </div>
  </div>

  <!-- Total Loans -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-info">
              <i class="icon-base ri ri-file-list-3-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $totalLoans ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Loans</h6>
      </div>
    </div>
  </div>

  <!-- Active Loans -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-warning">
              <i class="icon-base ri ri-checkbox-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $activeLoans ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Active Loans</h6>
      </div>
    </div>
  </div>

  <!-- Pending Applications -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-secondary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-secondary">
              <i class="icon-base ri ri-time-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $pendingApplications ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Pending Applications</h6>
      </div>
    </div>
  </div>

  <!-- Total Disbursed Amount -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-primary">
              <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹ {{ number_format($totalDisbursedAmount ?? 0) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Total Disbursed Amount</h6>
      </div>
    </div>
  </div>

  <!-- Overdue Loans -->
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-danger h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-danger">
              <i class="icon-base ri ri-alert-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">{{ $overdueLoans ?? 0 }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Overdue Loans</h6>
      </div>
    </div>
  </div>

  <!-- Revenue This Month -->
  <!-- <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2">
          <div class="avatar me-4">
            <span class="avatar-initial rounded-3 bg-label-success">
              <i class="icon-base ri ri-line-chart-line icon-24px"></i>
            </span>
          </div>
          <h4 class="mb-0 fw-bold">₹ {{ number_format($revenueThisMonth ?? 0) }}</h4>
        </div>
        <h6 class="mb-0 h6 fw-normal">Revenue This Month</h6>
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
        <div class="btn-group">
          <button type="button" class="btn btn-outline-primary">January</button>
          <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown" aria-expanded="false">
            <span class="visually-hidden">Toggle Dropdown</span>
          </button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="javascript:void(0);">January</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">February</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">March</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">April</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">May</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">June</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">July</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">August</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">September</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">October</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">November</a></li>
            <li><a class="dropdown-item" href="javascript:void(0);">December</a></li>
          </ul>
        </div>
      </div>
      <div class="card-body">
        <div class="text-center py-5">
          <div class="mb-3">
            <i class="icon-base ri ri-line-chart-line text-muted" style="font-size: 48px;"></i>
          </div>
          <h6 class="text-muted">No EMI collection data available</h6>
          <p class="text-muted small">EMI collection performance will appear here once loans are disbursed</p>
        </div>
      </div>
    </div>
  </div>
  <!--/ Shipment statistics -->
  <!-- Loan Performance Overview -->
  <div class="row">
   <div class="col-lg-6 col-md-6 col-xxl-4 order-2 order-xxl-2 mt-5">
    <div class="card h-100"> 
      <div class="card-header d-flex justify-content-between">
        <div>
          <h5 class="card-title mb-1">Loan Performance Overview</h5>
          <p class="card-subtitle mb-0 mt-1">Approved loans by category</p>
        </div>
        <div class="dropdown">
          <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-1" type="button"
            id="loanPerformance" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="icon-base ri ri-more-2-line"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="loanPerformance">
            <a class="dropdown-item" href="javascript:void(0);">Select All</a>
            <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
            <a class="dropdown-item" href="javascript:void(0);">Share</a>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="text-center py-5">
          <div class="mb-3">
            <i class="icon-base ri ri-bar-chart-line text-muted" style="font-size: 48px;"></i>
          </div>
          <h6 class="text-muted">No loan data available</h6>
          <p class="text-muted small">Loan performance data will appear here once loans are created</p>
        </div>
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
        <div class="dropdown">
          <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-1" type="button"
            id="loanDistributionReasons" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="icon-base ri ri-more-2-line"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="loanDistributionReasons">
            <a class="dropdown-item" href="javascript:void(0);">Select All</a>
            <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
            <a class="dropdown-item" href="javascript:void(0);">Share</a>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="text-center py-5">
          <div class="mb-3">
            <i class="icon-base ri ri-pie-chart-line text-muted" style="font-size: 48px;"></i>
          </div>
          <h6 class="text-muted">No loan distribution data available</h6>
          <p class="text-muted small">Loan distribution by type will appear here once loans are created</p>
        </div>
      </div>
    </div>
  </div>
  <!--/ Loan Distribution by Type -->
  
  <!-- Client Summary -->
  <div class="col-md-6 col-lg-4 col-xxl-4 mt-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Client Summary</h5>
        <div class="dropdown">
          <button class="btn text-body-secondary p-0" type="button" id="clientSummary" data-bs-toggle="dropdown"
            aria-haspopup="true" aria-expanded="false">
            <i class="icon-base ri ri-more-2-line"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="clientSummary">
            <a class="dropdown-item" href="javascript:void(0);">Last 28 Days</a>
            <a class="dropdown-item" href="javascript:void(0);">Last Month</a>
            <a class="dropdown-item" href="javascript:void(0);">Last Year</a>
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
            @php
              $activityPercentage = $totalClients > 0 ? ($activeClients / $totalClients) * 100 : 0;
            @endphp
            <div class="progress-bar bg-primary" style="width: {{ $activityPercentage }}%" role="progressbar" aria-valuenow="{{ $activityPercentage }}"
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
                    <span class="text-heading fw-medium me-2">{{ $activeClients ?? 0 }}</span>
                  </div>
                </td>
              </tr>
              <tr>
                <td class="ps-0 py-4">
                  <i class="icon-base ri ri-circle-fill icon-14px text-warning me-3"></i>
                  <span class="text-heading align-middle">Inactive Clients</span>
                </td>
                <td class="text-end pe-0 py-4">
                  <div class="d-flex align-items-center justify-content-end">
                    <span class="text-heading fw-medium me-2">{{ $inactiveClients ?? 0 }}</span>
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
                    <span class="text-heading fw-medium me-2">{{ $blacklistedClients ?? 0 }}</span>
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
        <div class="dropdown">
          <button class="btn btn-text-secondary rounded-pill text-body-secondary border-0 p-1" type="button"
            id="recentApplications" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="icon-base ri ri-more-2-line"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="recentApplications">
            <a class="dropdown-item" href="javascript:void(0);">Select All</a>
            <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
            <a class="dropdown-item" href="javascript:void(0);">Share</a>
          </div>
        </div>
      </div>
      <div class="card-datatable table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Application ID</th>
              <th>Client Name</th>
              <th>Loan Type</th>
              <th>Loan Amount</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="5" class="text-center py-5">
                <div class="mb-3">
                  <i class="icon-base ri ri-file-list-3-line text-muted" style="font-size: 48px;"></i>
                </div>
                <h6 class="text-muted">No loan applications yet</h6>
                <p class="text-muted small">Recent applications will appear here once created</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<!--/ Recent Applications Table -->
@endsection
