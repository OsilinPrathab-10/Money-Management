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
              <li><a class="dropdown-item" href="{{ route('reports-loans-export', array_merge(['format' => 'csv'], request()->only(['status','location_id','loan_type_id','product_id','loan_mode','from_date','to_date','sort']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-loans-export', array_merge(['format' => 'excel'], request()->only(['status','location_id','loan_type_id','product_id','loan_mode','from_date','to_date','sort']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item" href="{{ route('reports-loans-export', array_merge(['format' => 'pdf'], request()->only(['status','location_id','loan_type_id','product_id','loan_mode','from_date','to_date','sort']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="{{ route('reports-loans') }}" class="row g-3 align-items-end mb-4" id="loansFilterForm">
          <div class="col-md-3 col-lg-2">
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
          <div class="col-md-3 col-lg-2 text-capitalize">
            <label class="form-label">Area (Location)</label>
            <select name="location_id" class="form-select" data-auto-submit="true">
              <option value="">All Areas</option>
              @foreach($locations as $loc)
                <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label">Loan Type</label>
            <select name="loan_type_id" id="reportLoanTypeFilter" class="form-select" data-auto-submit="true">
              <option value="">All Loan Types</option>
              @foreach($loanTypes as $lt)
                <option value="{{ $lt->id }}" {{ request('loan_type_id') == $lt->id ? 'selected' : '' }}>{{ $lt->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label">Product</label>
            <select name="product_id" id="reportProductFilter" class="form-select" data-auto-submit="true">
              <option value="">All Products</option>
              @foreach($products as $prod)
                <option value="{{ $prod->id }}" data-loan-type-id="{{ $prod->loan_type_id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>
                  {{ $prod->loan_name }}{{ $prod->loanType ? ' ('.$prod->loanType->name.')' : '' }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label">Mode</label>
            <select name="loan_mode" class="form-select" data-auto-submit="true">
              <option value="all" {{ request('loan_mode', 'all') === 'all' ? 'selected' : '' }}>All Modes</option>
              <option value="emi" {{ request('loan_mode') === 'emi' ? 'selected' : '' }}>Standard EMI</option>
              <option value="interest_only" {{ request('loan_mode') === 'interest_only' ? 'selected' : '' }}>Open Loan (Interest-Only)</option>
            </select>
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label">From Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-3 col-lg-2">
            <label class="form-label">To Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-3 col-lg-2">
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
            <a href="{{ route('reports-loans') }}" class="btn btn-outline-secondary" id="resetLoansFilters"><i class="ri-refresh-line me-1"></i>Reset</a>
          </div>
        </form>

        <div id="latestLoansContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th scope="col">S.No</th>
                  <th scope="col">Account Number</th>
                  <th scope="col">Loan Amount</th>
                  <th scope="col">Outstanding</th>
                  <th scope="col">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($latestLoans as $index => $loan)
                  @php
                    $statusColors = [
                      'active' => 'bg-label-success',
                      'closed' => 'bg-label-secondary',
                      'overdue' => 'bg-label-danger',
                      'pending' => 'bg-label-warning',
                    ];
                    $statusLabel = ucfirst(str_replace('_', ' ', $loan->status));
                    $statusColor = $statusColors[$loan->status] ?? 'bg-label-primary';
                  @endphp
                  <tr>
                    <td>{{ $latestLoans->firstItem() + $index }}</td>
                    <td>
                      <div class="fw-semibold">{{ $loan->account_number ?? $loan->id }}</div>
                    </td>
                    <td>₹{{ number_format($loan->loan_amount, 0) }}</td>
                    <td>₹{{ number_format($loan->outstanding_amount, 2) }}</td>
                    <td>
                      <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $statusLabel }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-5">
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
      const typeSelect = document.getElementById('reportLoanTypeFilter');
      const prodSelect = document.getElementById('reportProductFilter');
      if (typeSelect && prodSelect) {
        const filterProducts = () => {
          const selectedTypeId = typeSelect.value;
          Array.from(prodSelect.options).forEach(opt => {
            const typeId = opt.getAttribute('data-loan-type-id');
            if (!selectedTypeId || !typeId || typeId === selectedTypeId) {
              opt.hidden = false;
              opt.style.display = '';
            } else {
              opt.hidden = true;
              opt.style.display = 'none';
            }
          });
          const selectedOpt = prodSelect.options[prodSelect.selectedIndex];
          if (selectedOpt && selectedOpt.style.display === 'none') {
            prodSelect.value = '';
          }
        };
        typeSelect.addEventListener('change', filterProducts);
        filterProducts();
      }

      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = isLoading => {
        latestLoansContainer.classList.toggle('opacity-50', isLoading);
        latestLoansContainer.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateLatestLoans = url => {
        window.location.href = url;
      };

      const submitFilters = customUrl => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateLatestLoans(url);
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

      latestLoansContainer.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            updateLatestLoans(url);
          }
        }
      });
    }
  });
</script>
@endsection
