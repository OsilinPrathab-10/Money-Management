@extends('layouts/layoutMaster')

@section('title', 'Applications Report & Analytics')

@section('vendor-style')
@vite(['resources/assets/vendor/libs/apex-charts/apex-charts.scss'])
@endsection

@section('vendor-script')
@vite(['resources/assets/vendor/libs/apex-charts/apexcharts.js'])
@endsection

@section('content')
<div class="row mb-6">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1">Applications Report & Analytics</h4>
        <p class="text-muted mb-0">Comprehensive overview of loan application statistics</p>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-6 mb-6">
  <!-- Applications by Status -->
  <div class="col-md-6 col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Applications by Status</h5>
      </div>
      <div class="card-body">
        <div id="applicationsByStatusChart"></div>
      </div>
    </div>
  </div>

  <!-- Application Statistics -->
  <div class="col-md-6 col-xl-6">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">Application Statistics</h5>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          <li class="d-flex mb-4">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #fff5d7; color: #d98500;">
                <i class="ri ri-time-line" style="font-size: 1.4rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Pending Applications</h6>
                <small class="text-muted">Awaiting review</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0 fw-semibold">{{ number_format($pendingApplications) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-4">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #e2f4ea; color: #1e8449;">
                <i class="ri ri-check-double-line" style="font-size: 1.4rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Approved & Disbursed</h6>
                <small class="text-muted">Successfully settled</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0 fw-semibold">{{ number_format($approvedApplications + $disbursedApplications) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-4">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #e3f0ff; color: #185adb;">
                <i class="ri ri-loader-line" style="font-size: 1.4rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">In Process</h6>
                <small class="text-muted">Under active processing</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0 fw-semibold">{{ number_format($processApplications) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-4">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #fee3e3; color: #d93025;">
                <i class="ri ri-close-line" style="font-size: 1.4rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Rejected Applications</h6>
                <small class="text-muted">Not approved</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0 fw-semibold">{{ number_format($rejectedApplications) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; background-color: #e3e7ff; color: #4b49ac;">
                <i class="ri ri-speed-up-line" style="font-size: 1.4rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Avg Processing Time</h6>
                <small class="text-muted">Days to disburse</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0 fw-semibold">{{ number_format($avgProcessingTime, 1) }} days</h6>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Application Trend -->
<div class="row mb-6">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0">Application Trend (Last 12 Months)</h5>
      </div>
      <div class="card-body">
        <div id="applicationsPerMonthChart"></div>
      </div>
    </div>
  </div>
</div>

<!-- Latest Applications Table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0">Latest Applications</h5>
            <small class="text-muted">Filter and export application records</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="{{ route('reports-applications-export', array_merge(['format' => 'csv'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-applications-export', array_merge(['format' => 'excel'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-applications-export', array_merge(['format' => 'pdf'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="{{ route('reports-applications') }}" class="row g-3 align-items-end mb-4" id="applicationsFilterForm">
          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" data-auto-submit="true">
              <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
              @foreach($availableStatuses as $statusOption)
                <option value="{{ $statusOption['value'] }}" {{ $filterStatus === $statusOption['value'] ? 'selected' : '' }}>
                  {{ $statusOption['label'] }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 text-capitalize">
            <label class="form-label">Area (Location)</label>
            <select name="location_id" class="form-select" data-auto-submit="true">
              <option value="">All Areas</option>
              @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" data-auto-submit="true">
              <option value="">All Products</option>
              @foreach($products as $prod)
                <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>{{ $prod->loan_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-2">
            <label class="form-label">Sort By</label>
            <select name="sort" class="form-select" data-auto-submit="true">
              <option value="newest" {{ $sortOption === 'newest' ? 'selected' : '' }}>Newest First</option>
              <option value="oldest" {{ $sortOption === 'oldest' ? 'selected' : '' }}>Oldest First</option>
              <option value="name_asc" {{ $sortOption === 'name_asc' ? 'selected' : '' }}>A–Z (Client Name)</option>
              <option value="name_desc" {{ $sortOption === 'name_desc' ? 'selected' : '' }}>Z–A (Client Name)</option>
              <option value="amount_high" {{ $sortOption === 'amount_high' ? 'selected' : '' }}>Amount High-Low</option>
              <option value="amount_low" {{ $sortOption === 'amount_low' ? 'selected' : '' }}>Amount Low-High</option>
              <option value="status_asc" {{ $sortOption === 'status_asc' ? 'selected' : '' }}>Status A-Z</option>
              <option value="status_desc" {{ $sortOption === 'status_desc' ? 'selected' : '' }}>Status Z-A</option>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('reports-applications') }}" class="btn btn-outline-secondary" id="resetApplicationsFilters"><i class="ri-refresh-line me-1"></i>Reset</a>
          </div>
        </form>

        <div id="latestApplicationsContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">S.No</th>
                  <th scope="col">Application Number</th>
                  <th scope="col">Client Name</th>
                  <th scope="col">Loan Code</th>
                  <th scope="col">Loan Amount</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($latestApplications as $index => $application)
                  @php
                    $statusColors = [
                      'pending' => 'bg-label-warning',
                      'process' => 'bg-label-primary',
                      'approved' => 'bg-label-success',
                      'disbursed' => 'bg-label-success',
                      'rejected' => 'bg-label-danger',
                    ];
                    $statusLabel = match ($application->status) {
                      'process' => 'In Progress',
                      'pending' => 'Pending Approval',
                      default => ucfirst(str_replace('_', ' ', $application->status)),
                    };
                    $statusColor = $statusColors[$application->status] ?? 'bg-label-secondary';
                  @endphp
                  <tr>
                    <td>{{ $latestApplications->firstItem() + $index }}</td>
                    <td>
                      <div class="fw-semibold">{{ $application->application_number }}</div>
                    </td>
                    <td>{{ $application->client?->user?->name ?? 'N/A' }}</td>
                    <td>{{ $application->loan_code }}</td>
                    <td>₹{{ number_format($application->loan_amount, 0) }}</td>
                    <td>
                      <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $statusLabel }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                      <i class="ri-file-search-line ri-24px d-block mb-2"></i>
                      No applications found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            {{ $latestApplications->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  const applicationsByStatusData = @json($applicationsByStatus);
  const applicationsPerMonthData = @json($applicationsPerMonth);
  
  console.log('Applications by Status:', applicationsByStatusData);
  console.log('Applications per Month:', applicationsPerMonthData);

  // Applications by Status Chart
  const applicationsByStatusChart = {
    series: applicationsByStatusData.length > 0 ? applicationsByStatusData.map(item => parseInt(item.count)) : [1],
    labels: applicationsByStatusData.length > 0 ? applicationsByStatusData.map(item => item.label ?? item.status.charAt(0).toUpperCase() + item.status.slice(1)) : ['No Data'],
    chart: {
      type: 'pie',
      height: 350
    },
    colors: applicationsByStatusData.length > 0 ? ['#ff9f43', '#00a896', '#5f61e6', '#00cfe8', '#ea5455', '#5f58ad'] : ['#e0e0e0'],
    legend: {
      position: 'bottom'
    },
    responsive: [{
      breakpoint: 480,
      options: {
        chart: {
          width: 300
        },
        legend: {
          position: 'bottom'
        }
      }
    }]
  };

  // Applications Per Month Chart
  const applicationsPerMonthChart = {
    series: [{
      name: 'Applications',
      data: applicationsPerMonthData.length > 0 ? applicationsPerMonthData.map(item => parseInt(item.count)) : []
    }],
    chart: {
      type: 'bar',
      height: 350,
      toolbar: {
        show: false
      }
    },
    plotOptions: {
      bar: {
        borderRadius: 8,
        dataLabels: {
          position: 'top'
        }
      }
    },
    dataLabels: {
      enabled: true,
      offsetY: -20,
      style: {
        fontSize: '12px',
        colors: ["#304758"]
      }
    },
    colors: ['#5f61e6'],
    xaxis: {
      categories: applicationsPerMonthData.length > 0 ? applicationsPerMonthData.map(item => item.month) : [],
      labels: {
        style: {
          fontSize: '13px'
        }
      }
    },
    yaxis: {
      labels: {
        style: {
          fontSize: '13px'
        }
      }
    },
    grid: {
      borderColor: '#e7e7e7',
      strokeDashArray: 5
    },
    noData: {
      text: 'No data available',
      align: 'center',
      verticalAlign: 'middle'
    }
  };

  // Render charts
  document.addEventListener('DOMContentLoaded', function() {
    new ApexCharts(document.querySelector("#applicationsByStatusChart"), applicationsByStatusChart).render();
    new ApexCharts(document.querySelector("#applicationsPerMonthChart"), applicationsPerMonthChart).render();

    const filterForm = document.getElementById('applicationsFilterForm');
    const latestApplicationsContainer = document.getElementById('latestApplicationsContainer');
    const resetBtn = document.getElementById('resetApplicationsFilters');

    if (filterForm && latestApplicationsContainer) {
      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = isLoading => {
        latestApplicationsContainer.classList.toggle('opacity-50', isLoading);
        latestApplicationsContainer.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateLatestApplications = url => {
        window.location.href = url;
      };

      const submitFilters = customUrl => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateLatestApplications(url);
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 250);
      };

      autoSubmitFields.forEach(field => field.addEventListener('change', debouncedSubmit));

      filterForm.addEventListener('submit', event => {
        event.preventDefault();
        submitFilters();
      });

      if (resetBtn) {
        resetBtn.addEventListener('click', event => {
          event.preventDefault();
          filterForm.reset();
          submitFilters(baseUrl);
        });
      }

      latestApplicationsContainer.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            updateLatestApplications(url);
          }
        }
      });
    }
  });
</script>
@endsection
