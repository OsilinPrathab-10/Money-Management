@extends('layouts/layoutMaster')

@section('title', 'EMI Report & Analytics')

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
        <h4 class="mb-1">EMI Report & Analytics</h4>
        <p class="text-muted mb-0">Comprehensive overview of EMI collection and performance</p>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-6 mb-6">
  <!-- EMIs by Status -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">EMIs by Status</h5>
      </div>
      <div class="card-body">
        <div id="emisByStatusChart"></div>
      </div>
    </div>
  </div>

  <!-- Upcoming EMIs -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">Upcoming EMIs (30 Days)</h5>
      </div>
      <div class="card-body d-flex flex-column justify-content-center align-items-center text-center py-5">
        <span class="badge rounded-pill text-uppercase fw-semibold mb-3" style="background-color: rgba(16, 156, 241, 0.12); color: #109cf1;">
          Next 30 Days
        </span>

        <div class="mb-4 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; border-radius: 24px;
              background: radial-gradient(circle at 30% 30%, rgba(255, 214, 126, 0.65), rgba(255, 181, 60, 0.45));
              box-shadow: 0 12px 25px rgba(255, 183, 66, 0.25);">
          <i class="ri ri-calendar-event-line" style="font-size: 2.2rem; color: #d98500;"></i>
        </div>

        <h1 class="fw-bold text-dark mb-1" style="font-size: 2.5rem;">{{ number_format($upcomingEmis) }}</h1>
        <p class="text-muted mb-4">EMIs due within the next 30 days</p>

        <div class="d-flex flex-wrap justify-content-center gap-3 w-100">
          <div class="border rounded-4 px-4 py-3 text-start" style="min-width: 160px; background-color: rgba(226, 244, 234, 0.6);">
            <small class="text-uppercase text-muted fw-semibold">Total Amount</small>
            <h5 class="mb-0 text-dark">₹{{ number_format($upcomingEmiAmount, 2) }}</h5>
          </div>
          <div class="border rounded-4 px-4 py-3 text-start" style="min-width: 160px; background-color: rgba(240, 244, 255, 0.7);">
            <small class="text-uppercase text-muted fw-semibold">Average EMI</small>
            <h5 class="mb-0 text-dark">
              ₹{{ $upcomingEmis > 0 ? number_format($upcomingEmiAmount / $upcomingEmis, 2) : number_format(0, 2) }}
            </h5>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- EMI Statistics -->
  <div class="col-md-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">EMI Statistics</h5>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          <li class="d-flex mb-6">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e2f4ea; color: #1e8449;">
                <i class="ri ri-check-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Paid EMIs</h6>
                <small class="text-muted">Successfully collected</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($paidEmis) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-6">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fff5d7; color: #d98500;">
                <i class="ri ri-time-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Pending EMIs</h6>
                <small class="text-muted">Yet to collect</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($pendingEmis) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fee3e3; color: #d93025;">
                <i class="ri ri-error-warning-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Overdue EMIs</h6>
                <small class="text-muted">Payment overdue</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($overdueEmis) }}</h6>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- EMI Collection Trend -->
<div class="row mb-6">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0">EMI Collection Trend (Last 12 Months)</h5>
      </div>
      <div class="card-body">
        <div id="emiCollectionChart"></div>
      </div>
    </div>
  </div>
</div>

<!-- Latest EMIs Table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0">Latest EMIs</h5>
            <small class="text-muted">Filter and export EMI records</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="{{ route('reports-emi-export', array_merge(['format' => 'csv'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-emi-export', array_merge(['format' => 'excel'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-emi-export', array_merge(['format' => 'pdf'], request()->only(['status','from_date','to_date','sort','location_id','product_id']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="{{ route('reports-emi') }}" class="row g-3 align-items-end mb-4" id="emiFilterForm">
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
              <option value="amount_high" {{ $sortOption === 'amount_high' ? 'selected' : '' }}>Amount High-Low</option>
              <option value="amount_low" {{ $sortOption === 'amount_low' ? 'selected' : '' }}>Amount Low-High</option>
              <option value="status_asc" {{ $sortOption === 'status_asc' ? 'selected' : '' }}>Status A-Z</option>
              <option value="status_desc" {{ $sortOption === 'status_desc' ? 'selected' : '' }}>Status Z-A</option>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end gap-2">
            <a href="{{ route('reports-emi') }}" class="btn btn-outline-secondary" id="resetEmiFilters"><i class="ri-refresh-line me-1"></i>Reset</a>
          </div>
        </form>

        <div id="latestEmisContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">S.No</th>
                  <th scope="col">Customer Name</th>
                  <th scope="col">Contact</th>
                  <th scope="col">Principal Amount</th>
                  <th scope="col">Interest Amount</th>
                  <th scope="col">Total Amount</th>
                  <th scope="col">Due Date</th>
                  <th scope="col">Status</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($latestEmis as $index => $emi)
                  @php
                    $statusColors = [
                      'paid' => 'bg-label-success',
                      'pending' => 'bg-label-warning',
                      'overdue' => 'bg-label-danger',
                      'partial' => 'bg-label-info',
                    ];
                    $statusLabel = ucfirst(str_replace('_', ' ', $emi->status));
                    $statusColor = $statusColors[$emi->status] ?? 'bg-label-secondary';
                    $client = $emi->loanAccount->loanApplication->client ?? null;
                  @endphp
                  <tr>
                    <td>{{ $latestEmis->firstItem() + $index }}</td>
                    <td>
                      <span class="fw-medium text-heading">{{ $client?->client_name ?? 'N/A' }}</span>
                    </td>
                    <td>{{ $client?->client_phone ?? 'N/A' }}</td>
                    <td>₹{{ number_format($emi->principal_amount, 2) }}</td>
                    <td>₹{{ number_format($emi->interest_amount, 2) }}</td>
                    <td>₹{{ number_format($emi->total_amount, 2) }}</td>
                    <td>{{ $emi->due_date->format('d-m-Y') }}</td>
                    <td>
                      <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $statusLabel }}</span>
                    </td>
                    <td>
                      @if($client && $client->client_phone)
                        <a href="tel:{{ $client->client_phone }}" class="btn btn-sm btn-icon btn-label-primary waves-effect" title="Call Customer">
                          <i class="ri-phone-line"></i>
                        </a>
                      @else
                        <button class="btn btn-sm btn-icon btn-label-secondary" disabled title="No phone number">
                          <i class="ri-phone-line"></i>
                        </button>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                      <i class="ri-file-search-line ri-24px d-block mb-2"></i>
                      No EMIs found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            {{ $latestEmis->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  const emisByStatusData = @json($emisByStatus);
  const emiCollectionPerMonthData = @json($emiCollectionPerMonth);
  
  console.log('EMIs by Status:', emisByStatusData);
  console.log('EMI Collection per Month:', emiCollectionPerMonthData);

  const emiStatusColorsMap = {
    paid: '#4c40d0',
    pending: '#ff7f11',
    overdue: '#d22630',
    partial: '#03c3ec'
  };

  const emiStatusColors = emisByStatusData.length > 0
    ? emisByStatusData.map(item => emiStatusColorsMap[item.status] || '#3f3cc8')
    : ['#6c757d'];

  // EMIs by Status Chart
  const emisByStatusChart = {
    series: emisByStatusData.length > 0 ? emisByStatusData.map(item => parseInt(item.count)) : [1],
    labels: emisByStatusData.length > 0 ? emisByStatusData.map(item => item.label ?? item.status.charAt(0).toUpperCase() + item.status.slice(1)) : ['No Data'],
    chart: {
      type: 'donut',
      height: 300
    },
    colors: emiStatusColors,
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

  // EMI Collection Chart
  const emiCollectionChart = {
    series: [
      {
        name: 'Expected Amount',
        type: 'column',
        data: emiCollectionPerMonthData.length > 0 ? emiCollectionPerMonthData.map(item => parseFloat(item.total_amount)) : []
      },
      {
        name: 'Collected Amount',
        type: 'line',
        data: emiCollectionPerMonthData.length > 0 ? emiCollectionPerMonthData.map(item => parseFloat(item.collected_amount)) : []
      }
    ],
    chart: {
      height: 350,
      type: 'line',
      toolbar: {
        show: false
      }
    },
    stroke: {
      width: [0, 4],
      curve: 'smooth'
    },
    plotOptions: {
      bar: {
        columnWidth: '55%',
        borderRadius: 8
      }
    },
    dataLabels: {
      enabled: true,
      enabledOnSeries: [1],
      formatter: function (val) {
        return '₹' + parseFloat(val).toFixed(2);
      }
    },
    colors: ['#5f61e6', '#00a896'],
    xaxis: {
      categories: emiCollectionPerMonthData.length > 0 ? emiCollectionPerMonthData.map(item => item.month) : [],
      labels: {
        style: {
          fontSize: '13px'
        }
      }
    },
    yaxis: [
      {
        title: {
          text: 'Expected Amount (₹)'
        },
        labels: {
          style: {
            fontSize: '13px'
          },
          formatter: function(val) {
            return '₹' + parseFloat(val).toFixed(2);
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Collected Amount (₹)'
        },
        labels: {
          style: {
            fontSize: '13px'
          },
          formatter: function(val) {
            return '₹' + parseFloat(val).toFixed(2);
          }
        }
      }
    ],
    tooltip: {
      y: {
        formatter: function(val) {
          return '₹' + parseFloat(val).toFixed(2);
        }
      }
    },
    grid: {
      borderColor: '#e7e7e7',
      strokeDashArray: 5
    },
    legend: {
      position: 'top'
    },
    noData: {
      text: 'No data available',
      align: 'center',
      verticalAlign: 'middle'
    }
  };

  // Render charts
  document.addEventListener('DOMContentLoaded', function() {
    new ApexCharts(document.querySelector("#emisByStatusChart"), emisByStatusChart).render();
    new ApexCharts(document.querySelector("#emiCollectionChart"), emiCollectionChart).render();

    const filterForm = document.getElementById('emiFilterForm');
    const latestEmisContainer = document.getElementById('latestEmisContainer');
    const resetBtn = document.getElementById('resetEmiFilters');

    if (filterForm && latestEmisContainer) {
      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = isLoading => {
        latestEmisContainer.classList.toggle('opacity-50', isLoading);
        latestEmisContainer.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateLatestEmis = url => {
        toggleLoadingState(true);
        fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html'
          }
        })
          .then(response => {
            if (!response.ok) throw new Error('Failed to fetch EMIs');
            return response.text();
          })
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContainer = doc.getElementById('latestEmisContainer');
            if (newContainer) {
              latestEmisContainer.innerHTML = newContainer.innerHTML;
              window.history.replaceState({}, '', url);
            }
          })
          .catch(error => console.error('EMI filter error:', error))
          .finally(() => toggleLoadingState(false));
      };

      const submitFilters = customUrl => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateLatestEmis(url);
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

      latestEmisContainer.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            updateLatestEmis(url);
          }
        }
      });
    }
  });
</script>
@endsection
