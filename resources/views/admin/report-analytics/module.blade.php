@extends('layouts/layoutMaster')

@section('title', $pageTitle)

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
        <h4 class="mb-1">{{ $pageTitle }}</h4>
        <p class="text-muted mb-0">{{ $pageSubtitle }}</p>
      </div>
    </div>
  </div>
</div>

@php
  $showAmountChart = $showAmountChart ?? false;
  $statusColClass = $showAmountChart ? 'col-md-6 col-xl-4' : 'col-md-6 col-xl-6';
  $statsColClass = $showAmountChart ? 'col-md-12 col-xl-4' : 'col-md-6 col-xl-6';
  $clientColumns = ['Client Name', 'Client', 'Customer Name'];
  $badgeColumns = ['Scheme', 'Group', 'Type', 'Payment Mode', 'Loan Product'];
@endphp

<!-- Charts Row -->
<div class="row g-6 mb-6">
  <div class="{{ $statusColClass }}">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0 me-2">{{ $pieTitle }}</h5>
      </div>
      <div class="card-body">
        <div id="{{ $chartId }}StatusChart"></div>
      </div>
    </div>
  </div>

  @if($showAmountChart)
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <h5 class="card-title m-0 me-2">Amount Distribution</h5>
        </div>
        <div class="card-body">
          <div id="{{ $chartId }}AmountChart"></div>
        </div>
      </div>
    </div>
  @endif

  <div class="{{ $statsColClass }}">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="card-title m-0">{{ $statsTitle ?? 'Statistics' }}</h5>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          @foreach($stats as $stat)
            <li class="d-flex {{ !$loop->last ? 'mb-6' : '' }}">
              <div class="avatar flex-shrink-0 me-4">
                <span class="avatar-initial rounded d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: {{ $stat['bg'] }}; color: {{ $stat['color'] }};">
                  <i class="ri {{ $stat['icon'] }}" style="font-size: 1.6rem;"></i>
                </span>
              </div>
              <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                <div class="me-2">
                  <h6 class="mb-0">{{ $stat['title'] }}</h6>
                  <small class="text-muted">{{ $stat['subtitle'] }}</small>
                </div>
                <div class="user-progress">
                  <h6 class="mb-0">{{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}</h6>
                </div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>
</div>

<!-- Trend -->
<div class="row mb-6">
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title m-0">{{ $trendTitle }}</h5>
      </div>
      <div class="card-body">
        <div id="{{ $chartId }}TrendChart"></div>
      </div>
    </div>
  </div>
</div>

<!-- Latest records table -->
<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0">{{ $tableTitle }}</h5>
            <small class="text-muted">Filter and export records</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item export-link" data-format="csv" href="{{ route($exportRoute, array_merge(['format' => 'csv'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','scheme_id']))) }}">
                <i class="ri-file-text-line me-2"></i>CSV
              </a></li>
              <li><a class="dropdown-item export-link" data-format="excel" href="{{ route($exportRoute, array_merge(['format' => 'excel'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','scheme_id']))) }}">
                <i class="ri-file-excel-2-line me-2"></i>Excel
              </a></li>
              <li><a class="dropdown-item export-link" data-format="pdf" href="{{ route($exportRoute, array_merge(['format' => 'pdf'], request()->only(['status','from_date','to_date','date_preset','sort','location_id','scheme_id']))) }}">
                <i class="ri-file-pdf-line me-2"></i>PDF
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="GET" action="{{ route($filterRoute) }}" class="mb-4" id="{{ $chartId }}FilterForm">
          <div class="bg-label-secondary p-4 rounded-3 border">
            <div class="row g-3 align-items-end">
              <div class="col-md-6 col-lg-3">
                <label class="form-label fw-medium text-dark"><i class="ri-shopping-bag-3-line me-1"></i>{{ $schemeLabel }}</label>
                <select name="scheme_id" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="">All {{ $schemeLabel }}s</option>
                  @foreach($schemes as $scheme)
                    <option value="{{ $scheme->id }}" {{ request('scheme_id') == $scheme->id ? 'selected' : '' }}>{{ $scheme->name }}</option>
                  @endforeach
                </select>
              </div>

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

              <div class="col-md-6 col-lg-2">
                <label class="form-label fw-medium text-dark"><i class="ri-map-pin-line me-1"></i>Area / Location</label>
                <select name="location_id" class="form-select border-0 shadow-sm" data-auto-submit="true">
                  <option value="">All Areas</option>
                  @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ request('location_id') == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>
                  @endforeach
                </select>
              </div>

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

              <div class="col-md-6 col-lg-2 d-flex align-items-end justify-content-lg-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1 shadow-sm">
                  <i class="ri-filter-3-line me-1"></i>Filter
                </button>
                <a href="{{ route($filterRoute) }}" class="btn btn-outline-danger shadow-sm" id="{{ $chartId }}ResetFilters" title="Reset Filters">
                  <i class="ri-refresh-line"></i>
                </a>
              </div>

              <div class="col-12 pt-3 border-top">
                @include('partials.date-range-filter', [
                  'fromId' => $chartId . 'DateFrom',
                  'toId' => $chartId . 'DateTo',
                  'presetId' => $chartId . 'DatePreset',
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

        <div id="{{ $chartId }}TableContainer">
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  @foreach($columns as $column)
                    <th scope="col">{{ $column }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @forelse($tableRows as $row)
                  @php
                    $statusColors = [
                      'pending' => 'bg-label-warning',
                      'applied' => 'bg-label-warning',
                      'process' => 'bg-label-primary',
                      'approved' => 'bg-label-success',
                      'active' => 'bg-label-success',
                      'paid' => 'bg-label-success',
                      'verified' => 'bg-label-success',
                      'booked' => 'bg-label-success',
                      'completed' => 'bg-label-info',
                      'matured' => 'bg-label-info',
                      'partial' => 'bg-label-info',
                      'in_progress' => 'bg-label-primary',
                      'overdue' => 'bg-label-danger',
                      'rejected' => 'bg-label-danger',
                      'defaulted' => 'bg-label-danger',
                      'closed' => 'bg-label-secondary',
                      'premature_closed' => 'bg-label-secondary',
                      'frozen' => 'bg-label-warning',
                      'renewed' => 'bg-label-primary',
                      'transferred' => 'bg-label-info',
                    ];
                    $statusKey = strtolower((string) ($row['status'] ?? ''));
                    $statusColor = $statusColors[$statusKey] ?? 'bg-label-secondary';
                    $statusLabel = $row['status_label'] ?? ucfirst(str_replace('_', ' ', $statusKey));
                  @endphp
                  <tr>
                    <td>{{ $row['sno'] }}</td>
                    @foreach($row['cells'] as $i => $cell)
                      @php
                        $colName = $columns[$i + 1] ?? '';
                        $isClient = in_array($colName, $clientColumns, true);
                        $isBadge = in_array($colName, $badgeColumns, true);
                        $isStatus = strcasecmp($colName, 'Status') === 0;
                        $isTypeAsStatus = strcasecmp($colName, 'Type') === 0 && ! in_array('Status', $columns, true);
                        $clientParts = $isClient ? explode('||', (string) $cell, 2) : [];
                      @endphp
                      <td>
                        @if($isStatus || $isTypeAsStatus)
                          <span class="badge {{ $statusColor }} rounded-pill text-uppercase">{{ $isStatus ? $statusLabel : $cell }}</span>
                        @elseif($isClient)
                          <div class="fw-medium">{{ $clientParts[0] ?? $cell }}</div>
                          @if(!empty($clientParts[1]))
                            <small class="text-muted">{{ $clientParts[1] }}</small>
                          @endif
                        @elseif($isBadge)
                          <span class="badge bg-label-info">{{ $cell }}</span>
                        @else
                          {{ $cell }}
                        @endif
                      </td>
                    @endforeach
                  </tr>
                @empty
                  <tr>
                    <td colspan="{{ count($columns) }}" class="text-center text-muted py-5">
                      <i class="ri-file-search-line ri-24px d-block mb-2"></i>
                      No records found for the selected filters.
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          <div class="mt-3">
            {{ $records->links('pagination::bootstrap-5') }}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  const statusData = @json($byStatus);
  const trendData = @json($perMonth);
  const collectedAmount = {{ (float) ($collectedAmount ?? 0) }};
  const outstandingAmount = {{ (float) ($outstandingAmount ?? 0) }};
  const collectedLabel = @json($collectedLabel ?? 'Paid');
  const outstandingLabel = @json($outstandingLabel ?? 'Outstanding');
  const showAmountChart = @json($showAmountChart ?? false);
  const countSeriesName = @json($countSeriesName ?? 'Count');

  const statusColorsMap = {
    active: '#4c40d0',
    approved: '#4c40d0',
    booked: '#4c40d0',
    verified: '#4c40d0',
    paid: '#4c40d0',
    disbursed: '#ff7f11',
    pending: '#ff7f11',
    applied: '#ff7f11',
    in_progress: '#ff7f11',
    partial: '#ff7f11',
    closed: '#2f8f5b',
    completed: '#2f8f5b',
    matured: '#2f8f5b',
    renewed: '#2f8f5b',
    overdue: '#d22630',
    rejected: '#d22630',
    defaulted: '#d22630',
    frozen: '#d22630',
    transferred: '#3f3cc8',
    default: '#3f3cc8'
  };

  const statusChartColors = statusData.length > 0
    ? statusData.map(item => statusColorsMap[item.status] || statusColorsMap.default)
    : ['#ff7f11'];

  const statusChart = {
    series: statusData.length > 0 ? statusData.map(item => parseInt(item.count)) : [1],
    labels: statusData.length > 0 ? statusData.map(item => item.label) : ['No Data'],
    chart: {
      type: 'donut',
      height: 300
    },
    colors: statusChartColors,
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

  const amountChart = {
    series: [collectedAmount, outstandingAmount],
    labels: [collectedLabel, outstandingLabel],
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
              formatter: function() {
                return '₹' + (collectedAmount + outstandingAmount).toFixed(2);
              }
            }
          }
        }
      }
    }
  };

  const trendChart = {
    series: [
      {
        name: countSeriesName,
        type: 'column',
        data: trendData.length > 0 ? trendData.map(item => parseInt(item.count)) : []
      },
      {
        name: 'Total Amount (₹)',
        type: 'line',
        data: trendData.length > 0 ? trendData.map(item => parseFloat(item.total_amount || 0)) : []
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
      categories: trendData.length > 0 ? trendData.map(item => item.month) : [],
      labels: {
        style: {
          fontSize: '13px'
        }
      }
    },
    yaxis: [
      {
        title: {
          text: countSeriesName
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

  document.addEventListener('DOMContentLoaded', function() {
    new ApexCharts(document.querySelector('#{{ $chartId }}StatusChart'), statusChart).render();
    if (showAmountChart) {
      new ApexCharts(document.querySelector('#{{ $chartId }}AmountChart'), amountChart).render();
    }
    new ApexCharts(document.querySelector('#{{ $chartId }}TrendChart'), trendChart).render();

    const filterForm = document.getElementById('{{ $chartId }}FilterForm');
    const tableContainer = document.getElementById('{{ $chartId }}TableContainer');
    const resetBtn = document.getElementById('{{ $chartId }}ResetFilters');

    if (filterForm && tableContainer) {
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
        window.location.href = queryStr ? `${baseUrl}?${queryStr}` : baseUrl;
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 300);
      };

      filterForm.querySelectorAll('select, input').forEach(field => {
        field.addEventListener('change', debouncedSubmit);
      });

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

      document.querySelectorAll('.export-link').forEach(link => {
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

          window.location.href = `{{ route($exportRoute) }}?${params.toString()}`;
        });
      });

      tableContainer.addEventListener('click', event => {
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
