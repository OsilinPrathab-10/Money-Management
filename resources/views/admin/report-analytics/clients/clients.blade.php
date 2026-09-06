@extends('layouts/layoutMaster')

@section('title', 'Clients Report & Analytics')

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
        <h4 class="mb-1">Clients Report & Analytics</h4>
        <p class="text-muted mb-0">Comprehensive overview of client statistics and trends</p>
      </div>
    </div>
  </div>
</div>

<div id="reportsAnalyticsContent">
<!-- Charts Row -->
<div class="row g-6 mb-6">
  <!-- Clients by Status -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Clients by Status</h5>
      </div>
      <div class="card-body">
        <div id="clientsByStatusChart"></div>
      </div>
    </div>
  </div>

  <!-- Verification Status -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Verification Status</h5>
      </div>
      <div class="card-body">
        <div id="verificationStatusChart"></div>
      </div>
    </div>
  </div>

  <!-- Client Statistics -->
  <div class="col-md-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">Quick Statistics</h5>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          <li class="d-flex mb-6">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e3e7ff; color: #4b49ac;">
                <i class="ri ri-user-add-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Pending Clients</h6>
                <small class="text-muted">Awaiting approval</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($pendingClients) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-6">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fee3e3; color: #d93025;">
                <i class="ri ri-user-unfollow-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Inactive Clients</h6>
                <small class="text-muted">Not currently active</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($inactiveClients) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e2f4ea; color: #1e8449;">
                <i class="ri ri-user-follow-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Active Clients</h6>
                <small class="text-muted">Currently active</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($activeClients) }}</h6>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Latest Clients Table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0">Latest Clients</h5>
            <small class="text-muted">Filter and export recent client registrations</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              @php
                $exportQuery = http_build_query([
                    'status' => $filterStatus,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'sort' => $sortOption,
                ]);
              @endphp
              <li><a class="dropdown-item" href="{{ route('reports-clients-export', array_merge(['format' => 'csv'], request()->only(['status','from_date','to_date','sort','location_id']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-clients-export', array_merge(['format' => 'excel'], request()->only(['status','from_date','to_date','sort','location_id']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-clients-export', array_merge(['format' => 'pdf'], request()->only(['status','from_date','to_date','sort','location_id']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" class="row g-3 align-items-end mb-4" id="clientsFilterForm">
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
          <div class="col-md-3 text-capitalize">
            <label class="form-label">Area (Location)</label>
            <select name="location_id" class="form-select" data-auto-submit="true">
              <option value="">All Areas</option>
              @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
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
          <div class="col-md-3">
            <label class="form-label">Sort By</label>
            <select name="sort" class="form-select" data-auto-submit="true">
              <option value="newest" {{ $sortOption === 'newest' ? 'selected' : '' }}>Newest First</option>
              <option value="oldest" {{ $sortOption === 'oldest' ? 'selected' : '' }}>Oldest First</option>
              <option value="status_asc" {{ $sortOption === 'status_asc' ? 'selected' : '' }}>Status A-Z</option>
              <option value="status_desc" {{ $sortOption === 'status_desc' ? 'selected' : '' }}>Status Z-A</option>
            </select>
          </div>
          <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('reports-clients') }}" class="btn btn-outline-secondary" id="resetFiltersBtn"><i class="ri-refresh-line me-1"></i>Reset</a>
          </div>
        </form>

        <div id="latestClientsContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">S.No</th>
                  <th scope="col">Client Name</th>
                  <th scope="col">Phone</th>
                  <th scope="col">Status</th>
                  <th scope="col">Registered On</th>
                </tr>
              </thead>
              <tbody>
                @forelse($latestClients as $index => $client)
                  @php
                    $statusColors = [
                      'active' => 'bg-label-success',
                      'inactive' => 'bg-label-secondary',
                      'pending' => 'bg-label-warning',
                      'unverified' => 'bg-label-warning',
                      'verified' => 'bg-label-info',
                      'blacklist' => 'bg-label-danger',
                    ];
                    $statusLabel = ucfirst(str_replace('_', ' ', $client->status));
                    $statusColor = $statusColors[$client->status] ?? 'bg-label-primary';
                  @endphp
                  <tr>
                    <td>{{ $latestClients->firstItem() + $index }}</td>
                    <td>
                      <div class="fw-semibold">{{ $client->user?->name ?? ($client->client_name ?? 'N/A') }}</div>
                    </td>
                    <td>{{ $client->client_phone ?? 'N/A' }}</td>
                    <td>
                      <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $statusLabel }}</span>
                    </td>
                    <td>{{ $client->created_at->format('d-m-Y') }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-5">
                      <i class="ri-user-search-line ri-24px d-block mb-2"></i>
                      No clients found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            {{ $latestClients->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  // Refactor chart rendering into a reusable function
  function initCharts() {
    const clientsByStatusData = @json($clientsByStatus);
    const verifiedClients = {{ $verifiedClients ?? 0 }};
    const unverifiedClients = {{ $unverifiedClients ?? 0 }};
    
    console.log('Clients by Status:', clientsByStatusData);

    // Clients by Status Chart
    const clientsByStatusOptions = {
      series: clientsByStatusData.length > 0 ? clientsByStatusData.map(item => parseInt(item.count)) : [1],
      labels: clientsByStatusData.length > 0 ? clientsByStatusData.map(item => item.label ?? item.status.charAt(0).toUpperCase() + item.status.slice(1)) : ['No Data'],
      chart: {
        type: 'donut',
        height: 300
      },
      colors: clientsByStatusData.length > 0 ? ['#5f61e6', '#00a896', '#ff9f43', '#ea5455', '#00cfe8'] : ['#e0e0e0'],
      legend: {
        position: 'bottom'
      },
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: true,
              value: {
                fontSize: '1.5rem',
                fontWeight: 600
              },
              total: {
                show: true,
                label: 'Total',
                fontSize: '1rem'
              }
            }
          }
        }
      },
      noData: {
        text: 'No data available',
        align: 'center',
        verticalAlign: 'middle'
      }
    };

    // Verification Status Chart
    const verificationStatusOptions = {
      series: [verifiedClients, unverifiedClients],
      labels: ['Verified', 'Unverified'],
      chart: {
        type: 'donut',
        height: 300
      },
      colors: ['#00a896', '#ff9f43'],
      legend: {
        position: 'bottom'
      },
      plotOptions: {
        pie: {
          donut: {
            size: '70%',
            labels: {
              show: true,
              value: {
                fontSize: '1.5rem',
                fontWeight: 600
              },
              total: {
                show: true,
                label: 'Total',
                fontSize: '1rem'
              }
            }
          }
        }
      }
    };

    const statusChartEl = document.querySelector("#clientsByStatusChart");
    const verifyChartEl = document.querySelector("#verificationStatusChart");
    
    if (statusChartEl) new ApexCharts(statusChartEl, clientsByStatusOptions).render();
    if (verifyChartEl) new ApexCharts(verifyChartEl, verificationStatusOptions).render();
  }

  // Render charts on initial load
  document.addEventListener('DOMContentLoaded', function() {
    initCharts();

    const filterForm = document.getElementById('clientsFilterForm');
    const reportsAnalyticsContent = document.getElementById('reportsAnalyticsContent');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');

    if (filterForm && reportsAnalyticsContent) {
      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = isLoading => {
        reportsAnalyticsContent.classList.toggle('opacity-50', isLoading);
        reportsAnalyticsContent.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateReportData = url => {
        toggleLoadingState(true);
        fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html'
          }
        })
          .then(response => {
            if (!response.ok) throw new Error('Failed to fetch report data');
            return response.text();
          })
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContent = doc.getElementById('reportsAnalyticsContent');
            if (newContent) {
              reportsAnalyticsContent.innerHTML = newContent.innerHTML;
              window.history.replaceState({}, '', url);
              
              // Re-extract data from script tag if necessary, but here we can just re-init charts
              // since the JSON data is in the script tag of the new HTML.
              // Wait, the script tag itself might need execution.
              // Actually, a better way is to extract the script content and eval it or just re-run initCharts.
              // But initCharts relies on server-side rendered data.
              // We need to extract the new data from the new HTML.
              
              const newScript = doc.querySelector('script:contains("const clientsByStatusData")');
              // This is complicated. Let's just do a full page reload if AJAX is too complex for charts.
              // OR, we can just make it a regular form submission by removing the AJAX logic.
              
              window.location.href = url; // Simpler and robust for Reports module.
            }
          })
          .catch(error => {
            console.error('Report filters error:', error);
            window.location.href = url; // Fallback to full reload
          })
          .finally(() => toggleLoadingState(false));
      };

      const submitFilters = customUrl => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateReportData(url);
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 250);
      };

      autoSubmitFields.forEach(field => {
        field.addEventListener('change', debouncedSubmit);
      });

      filterForm.addEventListener('submit', event => {
        event.preventDefault();
        submitFilters();
      });

      if (resetFiltersBtn) {
        resetFiltersBtn.addEventListener('click', event => {
          event.preventDefault();
          filterForm.reset();
          submitFilters(baseUrl);
        });
      }

      // Handle pagination via AJAX
      reportsAnalyticsContent.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            updateReportData(url);
          }
        }
      });
    }
  });
</script>
@endsection
