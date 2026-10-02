@extends('layouts/layoutMaster')

@section('title', 'Loans Report & Analytics')

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
        <h4 class="mb-1">Loans Report & Analytics</h4>
        <p class="text-muted mb-0">Comprehensive overview of loan portfolio and performance</p>
      </div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="row g-6 mb-6">
  <!-- Loans by Status -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Loans by Status</h5>
        <span class="text-muted small">Total {{ number_format($totalLoans ?? 0) }}</span>
      </div>
      <div class="card-body">
        <div id="loansByStatusChart"></div>
      </div>
    </div>
  </div>

  <!-- Loan Amount Distribution -->
  <div class="col-md-6 col-xl-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">Amount Distribution</h5>
      </div>
      <div class="card-body">
        <div id="amountDistributionChart"></div>
      </div>
    </div>
  </div>

  <!-- Loan Statistics -->
  <div class="col-md-12 col-xl-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">Loan Statistics</h5>
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
                <h6 class="mb-0">Closed Loans</h6>
                <small class="text-muted">Fully repaid</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($closedLoans) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex mb-6">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #fee3e3; color: #d93025;">
                <i class="ri ri-error-warning-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Overdue Loans</h6>
                <small class="text-muted">Payment overdue</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">{{ number_format($overdueLoans) }}</h6>
              </div>
            </div>
          </li>
          <li class="d-flex">
            <div class="avatar flex-shrink-0 me-4">
              <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: #e3f0ff; color: #185adb;">
                <i class="ri ri-calculator-line" style="font-size: 1.6rem;"></i>
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <h6 class="mb-0">Avg Loan Amount</h6>
                <small class="text-muted">Average disbursed</small>
              </div>
              <div class="user-progress">
                <h6 class="mb-0">₹{{ number_format($avgLoanAmount, 2) }}</h6>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Loan Disbursement Trend -->
<div class="row mb-6">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0">Loan Disbursement Trend (Last 12 Months)</h5>
      </div>
      <div class="card-body">
        <div id="loansPerMonthChart"></div>
      </div>
    </div>
  </div>
</div>

<!-- Latest Loans Table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0">Latest Loans</h5>
            <small class="text-muted">Filter and export loan records</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item export-link" data-format="csv" href="{{ route('reports-loans-export', array_merge(['format' => 'csv'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','loan_product_id']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item export-link" data-format="excel" href="{{ route('reports-loans-export', array_merge(['format' => 'excel'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','loan_product_id']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item export-link" data-format="pdf" href="{{ route('reports-loans-export', array_merge(['format' => 'pdf'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','loan_product_id']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="{{ route('reports-loans') }}" class="mb-4" id="loansFilterForm">
          <div class="bg-label-secondary p-4 rounded-3 border">
            <div class="row g-3 align-items-end">
              <!-- Loan Product Filter -->
              <div class="col-md-6 col-lg-3">
                <label class="form-label fw-medium text-dark"><i class="ri-shopping-bag-3-line me-1"></i>Loan Product</label>
                <select name="loan_product_id" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="">All Products</option>
                  @foreach($loanProducts as $product)
                    <option value="{{ $product->id }}" {{ ((string)request('loan_product_id') === (string)$product->id || (string)request('loan_product_id') === (string)$product->loan_code) ? 'selected' : '' }}>
                      {{ $product->loan_name }} ({{ $product->loan_code }})
                    </option>
                  @endforeach
                </select>
              </div>

              <!-- Status Filter -->
              <div class="col-md-6 col-lg-2">
                <label class="form-label fw-medium text-dark"><i class="ri-checkbox-circle-line me-1"></i>Status</label>
                <select name="status" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="all" {{ $filterStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                  @foreach($availableStatuses as $statusOption)
                    <option value="{{ $statusOption['value'] }}" {{ $filterStatus === $statusOption['value'] ? 'selected' : '' }}>
                      {{ $statusOption['label'] }}
                    </option>
                  @endforeach
                </select>
              </div>

              <!-- Area / Location Filter -->
              <div class="col-md-6 col-lg-2">
                <label class="form-label fw-medium text-dark"><i class="ri-map-pin-line me-1"></i>Area / Location</label>
                <select name="location_id" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="">All Areas</option>
                  @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                  @endforeach
                </select>
              </div>

              <!-- Sort By Filter -->
              <div class="col-md-6 col-lg-3">
                <label class="form-label fw-medium text-dark"><i class="ri-sort-desc me-1"></i>Sort By</label>
                <select name="sort" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="newest" {{ $sortOption === 'newest' ? 'selected' : '' }}>Newest First</option>
                  <option value="oldest" {{ $sortOption === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                  <option value="amount_high" {{ $sortOption === 'amount_high' ? 'selected' : '' }}>Amount High-Low</option>
                  <option value="amount_low" {{ $sortOption === 'amount_low' ? 'selected' : '' }}>Amount Low-High</option>
                  <option value="status_asc" {{ $sortOption === 'status_asc' ? 'selected' : '' }}>Status A-Z</option>
                  <option value="status_desc" {{ $sortOption === 'status_desc' ? 'selected' : '' }}>Status Z-A</option>
                </select>
              </div>

              <!-- Action Buttons (Filter & Reset) -->
              <div class="col-md-6 col-lg-2 d-flex align-items-end justify-content-lg-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1 shadow-sm">
                  <i class="ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route('reports-loans') }}" class="btn btn-outline-danger shadow-sm" id="resetLoansFilters" title="Reset Filters">
                  <i class="ri-refresh-line"></i>
                </a>
              </div>

              <!-- Date Range Filter Row -->
              <div class="col-12 pt-3 border-top">
                @include('partials.date-range-filter', [
                  'fromId' => 'loansReportDateFrom',
                  'toId' => 'loansReportDateTo',
                  'presetId' => 'loansReportDatePreset',
                  'fromName' => 'from_date',
                  'toName' => 'to_date',
                  'fromValue' => $fromDate,
                  'toValue' => $toDate,
                  'presetValue' => request('date_preset', 'all'),
                  'dataAutoSubmit' => true,
                  'size' => 'sm',
                ])
              </div>
            </div>
          </div>
        </form>

        <div id="latestLoansContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">S.No</th>
                  <th scope="col">Account Number</th>
                  <th scope="col">Client</th>
                  <th scope="col">Loan Product</th>
                  <th scope="col">Loan Amount</th>
                  <th scope="col">Total Paid</th>
                  <th scope="col">Outstanding</th>
                  <th scope="col">Overdue</th>
                  <th scope="col">Loan End Date</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($latestLoans as $index => $loan)
                  @php
                    $effectiveStatus = strtolower($loan->status ?? 'active');
                    $today = \Carbon\Carbon::today();
                    $isClosed = $loan->closed_at !== null
                        || $loan->is_foreclosed
                        || (float) $loan->outstanding_amount <= 0.05
                        || in_array($effectiveStatus, ['closed', 'completed', 'foreclosed'], true);
                    $isOverdue = ! $isClosed && $loan->emis->contains(function ($emi) use ($today) {
                        if (! in_array(strtolower((string) $emi->status), ['pending', 'overdue', 'partial'], true)) {
                            return false;
                        }
                        if ((float) ($emi->pending_amount ?? 0) <= 0) {
                            return false;
                        }
                        $due = $emi->due_date ? \Carbon\Carbon::parse($emi->due_date)->startOfDay() : null;
                        return $due && $due->lt($today);
                    });
                    if ($isClosed) {
                        $effectiveStatus = 'closed';
                    } elseif ($isOverdue) {
                        $effectiveStatus = 'overdue';
                    }
                    $statusColors = [
                      'active' => 'bg-label-success',
                      'closed' => 'bg-label-secondary',
                      'overdue' => 'bg-label-danger',
                      'pending' => 'bg-label-warning',
                      'disbursed' => 'bg-label-info',
                      'approved' => 'bg-label-primary',
                    ];
                    $statusLabel = ucfirst(str_replace('_', ' ', $effectiveStatus));
                    $statusColor = $statusColors[$effectiveStatus] ?? 'bg-label-primary';
                  @endphp
                  <tr>
                    <td>{{ $latestLoans->firstItem() + $index }}</td>
                    <td>
                      <div class="fw-semibold">{{ $loan->account_number ?? $loan->id }}</div>
                    </td>
                    <td>
                      <div class="fw-medium">{{ $loan->client->user->name ?? $loan->client->client_name ?? 'N/A' }}</div>
                      <small class="text-muted">{{ $loan->client->client_phone ?? '' }}</small>
                    </td>
                    <td>
                      <span class="badge bg-label-info">{{ $loan->loanProduct->loan_name ?? $loan->loan_code ?? 'N/A' }}</span>
                    </td>
                    <td>₹{{ number_format($loan->loan_amount, 0) }}</td>
                    <td>₹{{ number_format((float) $loan->total_paid, 2) }}</td>
                    <td>₹{{ number_format($loan->outstanding_amount, 2) }}</td>
                    <td>
                      @php
                        $overdueAmount = $loan->emis->where('due_date', '<', \Carbon\Carbon::today())
                                                    ->whereIn('status', ['pending', 'overdue', 'partial'])
                                                    ->sum('pending_amount');
                      @endphp
                      @if($overdueAmount > 0)
                        <span class="text-danger fw-semibold">₹{{ number_format($overdueAmount, 2) }}</span>
                      @else
                        <span class="text-muted">₹0.00</span>
                      @endif
                    </td>
                    <td>
                      @php
                        $loanEndDate = $loan->emis->max('due_date');
                      @endphp
                      {{ $loanEndDate ? \Carbon\Carbon::parse($loanEndDate)->format('d M Y') : 'N/A' }}
                    </td>
                    <td>
                      <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $statusLabel }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                      <i class="ri-file-search-line ri-24px d-block mb-2"></i>
                      No loans found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="mt-3">
            {{ $latestLoans->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  const loansByStatusData = @json($loansByStatus);
  const loansPerMonthData = @json($loansPerMonth);
  const totalLoans = {{ (int) ($totalLoans ?? 0) }};
  const totalDisbursed = {{ $totalDisbursed ?? 0 }};
  const totalPaid = {{ $totalPaid ?? 0 }};
  const totalOutstanding = {{ $totalOutstanding ?? 0 }};
  
  console.log('Loans by Status:', loansByStatusData);
  console.log('Loans per Month:', loansPerMonthData);

  const loanStatusColorsMap = {
    active: '#4c40d0',
    approved: '#4c40d0',
    disbursed: '#ff7f11',
    pending: '#ff7f11',
    closed: '#2f8f5b',
    overdue: '#d22630',
    default: '#3f3cc8'
  };

  const loansByStatusColors = loansByStatusData.length > 0
    ? loansByStatusData.map(item => loanStatusColorsMap[item.status] || loanStatusColorsMap.default)
    : ['#ff7f11'];

  // Loans by Status Chart
  const loansByStatusChart = {
    series: loansByStatusData.length > 0 ? loansByStatusData.map(item => parseInt(item.count)) : [1],
    labels: loansByStatusData.length > 0 ? loansByStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)) : ['No Data'],
    chart: {
      type: 'donut',
      height: 300
    },
    colors: loansByStatusColors,
    legend: {
      position: 'bottom',
      formatter: function (seriesName, opts) {
        return seriesName + ' (' + opts.w.globals.series[opts.seriesIndex] + ')';
      }
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
          labels: {
            show: true,
            value: {
              fontSize: '1.5rem',
              fontWeight: 600,
              formatter: function (val) {
                return val;
              }
            },
            total: {
              show: true,
              showAlways: true,
              label: 'Total',
              fontSize: '1rem',
              formatter: function () {
                // Unique loan count — overdue is a slice of open loans, not extra loans.
                return String(totalLoans);
              }
            }
          }
        }
      }
    }
  };

  // Amount Distribution Chart
  const amountDistributionChart = {
    series: [totalPaid, totalOutstanding],
    labels: ['Paid', 'Outstanding'],
    chart: {
      type: 'donut',
      height: 300
    },
    colors: ['#4c40d0', '#ff7f11'],
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
              fontWeight: 600,
              formatter: function(val) {
                return '₹' + parseFloat(val).toFixed(2);
              }
            },
            total: {
              show: true,
              label: 'Total',
              fontSize: '1rem',
              formatter: function(w) {
                return '₹' + (totalPaid + totalOutstanding).toFixed(2);
              }
            }
          }
        }
      }
    }
  };

  // Loans Per Month Chart
  const loansPerMonthChart = {
    series: [
      {
        name: 'Loan Count',
        type: 'column',
        data: loansPerMonthData.length > 0 ? loansPerMonthData.map(item => parseInt(item.count)) : []
      },
      {
        name: 'Total Amount (₹)',
        type: 'line',
        data: loansPerMonthData.length > 0 ? loansPerMonthData.map(item => parseFloat(item.total_amount)) : []
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
    dataLabels: {
      enabled: true,
      enabledOnSeries: [1]
    },
    colors: ['#5f61e6', '#00a896'],
    xaxis: {
      categories: loansPerMonthData.length > 0 ? loansPerMonthData.map(item => item.month) : [],
      labels: {
        style: {
          fontSize: '13px'
        }
      }
    },
    yaxis: [
      {
        title: {
          text: 'Loan Count'
        },
        labels: {
          style: {
            fontSize: '13px'
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Amount (₹)'
        },
        labels: {
          style: {
            fontSize: '13px'
          }
        }
      }
    ],
    grid: {
      borderColor: '#e7e7e7',
      strokeDashArray: 5
    },
    legend: {
      position: 'top'
    }
  };

  // Render charts
  document.addEventListener('DOMContentLoaded', function() {
    new ApexCharts(document.querySelector("#loansByStatusChart"), loansByStatusChart).render();
    new ApexCharts(document.querySelector("#amountDistributionChart"), amountDistributionChart).render();
    new ApexCharts(document.querySelector("#loansPerMonthChart"), loansPerMonthChart).render();

    const filterForm = document.getElementById('loansFilterForm');
    const latestLoansContainer = document.getElementById('latestLoansContainer');
    const resetBtn = document.getElementById('resetLoansFilters');

    if (filterForm && latestLoansContainer) {
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const submitFilters = customUrl => {
        if (customUrl) {
          window.location.href = customUrl;
          return;
        }

        const formData = new FormData(filterForm);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
          if (value !== null && value !== undefined && value.toString().trim() !== '') {
            params.append(key, value.toString().trim());
          }
        }

        const queryStr = params.toString();
        const url = queryStr ? `${baseUrl}?${queryStr}` : baseUrl;
        window.location.href = url;
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 300);
      };

      // Listen on all inputs & selects in filter form
      filterForm.querySelectorAll('select, input').forEach(field => {
        field.addEventListener('change', debouncedSubmit);
      });

      // Delegated listener for date presets & dynamic pickers
      filterForm.addEventListener('change', debouncedSubmit);

      filterForm.addEventListener('submit', event => {
        event.preventDefault();
        submitFilters();
      });

      if (resetBtn) {
        resetBtn.addEventListener('click', event => {
          event.preventDefault();
          window.location.href = baseUrl;
        });
      }

      // Dynamic export click handler
      const exportLinks = document.querySelectorAll('.export-link');
      exportLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const format = this.getAttribute('data-format') || 'csv';
          const formData = new FormData(filterForm);
          const params = new URLSearchParams();
          params.append('format', format);

          for (const [key, value] of formData.entries()) {
            if (value !== null && value !== undefined && value.toString().trim() !== '') {
              params.append(key, value.toString().trim());
            }
          }

          const exportBaseUrl = '{{ route('reports-loans-export') }}';
          window.location.href = `${exportBaseUrl}?${params.toString()}`;
        });
      });

      latestLoansContainer.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            window.location.href = url;
          }
        }
      });
    }
  });
</script>
@endsection
