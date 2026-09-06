@extends('layouts/layoutMaster')

@section('title', 'Revenue Report')

@section('content')
<div class="row mb-6">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-1 text-primary"><i class="ri-money-rupee-circle-line me-2"></i>Revenue Report</h4>
        <p class="text-muted mb-0">Detailed breakdown of fees, charges, and interest collected across all loan portfolios.</p>
      </div>
    </div>
  </div>
</div>

<!-- KPI Cards Row -->
<div class="row g-6 mb-6">
  <!-- Processing Fees Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-primary p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-settings-4-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Processing Fee</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalProcessingFees, 2) }}</h4>
        <small class="text-muted">Total applied fees</small>
      </div>
    </div>
  </div>

  <!-- Document Charges Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-info p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-file-shield-2-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Doc Charges</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalDocumentCharges, 2) }}</h4>
        <small class="text-muted">Agreement & documentation</small>
      </div>
    </div>
  </div>

  <!-- Other Charges Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-warning p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-add-box-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Other Charges</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalOtherCharges, 2) }}</h4>
        <small class="text-muted">Miscellaneous charges</small>
      </div>
    </div>
  </div>

  <!-- Interest Collected Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-success p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-percent-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Interest Collected</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalInterestCollected, 2) }}</h4>
        <small class="text-muted">EMI/Cycle interest collected</small>
      </div>
    </div>
  </div>

  <!-- Foreclose Revenue Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-warning p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-lock-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Foreclose Revenue</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalForeclosureRevenue, 2) }}</h4>
        <small class="text-muted">Foreclosure charges collected</small>
      </div>
    </div>
  </div>

  <!-- Penalty Amount Card -->
  <div class="col-md-6 col-xl-2-5 col-xxl-2">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-label-danger p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
              <i class="ri-error-warning-line ri-24px"></i>
            </span>
          </div>
          <span class="text-muted small fw-semibold text-uppercase">Penalty Amount</span>
        </div>
        <h4 class="mb-0 fw-bold">₹{{ number_format($totalPenaltyAmount, 2) }}</h4>
        <small class="text-muted">Overdue penalty collected</small>
      </div>
    </div>
  </div>

  <!-- Total Revenue Card (Premium Gradient Theme) -->
  <div class="col-md-12 col-xl-4 col-xxl-4">
    <div class="card shadow-sm border-0 h-100 bg-gradient-primary text-white" style="background: linear-gradient(135deg, #696cff 0%, #3f3dbe 100%);">
      <div class="card-body d-flex flex-column justify-content-between">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="avatar flex-shrink-0">
            <span class="avatar-initial rounded bg-white text-primary p-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; opacity: 0.95;">
              <i class="ri-wallet-3-line ri-24px"></i>
            </span>
          </div>
          <span class="text-white-50 small fw-bold text-uppercase">Overall Revenue</span>
        </div>
        <div>
          <h3 class="mb-0 fw-bold text-white">₹{{ number_format($overallTotalRevenue, 2) }}</h3>
          <small class="text-white-50">Fees, charges, interest, foreclose &amp; penalty</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Revenue Detailed Table and Filters -->
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0 fw-semibold text-dark">Revenue Statement Accounts</h5>
            <small class="text-muted">Generate, filter, and export customized revenue details</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ri-download-line me-1"></i> Export Data
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item py-2" id="exportCsv" href="#">
                <i class="ri-file-text-line me-2 text-secondary"></i>CSV Format
              </a></li>
              <li><a class="dropdown-item py-2" id="exportExcel" href="#">
                <i class="ri-file-excel-2-line me-2 text-success"></i>Excel Format
              </a></li>
              <li><a class="dropdown-item py-2" id="exportPdf" href="#">
                <i class="ri-file-pdf-line me-2 text-danger"></i>PDF Document
              </a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body pt-4">
        <!-- Interactive Filter Panel -->
        <form method="GET" action="{{ route('reports-revenue') }}" class="row g-3 align-items-end mb-4" id="revenueFilterForm">
          <div class="col-md-3">
            <label class="form-label fw-medium text-secondary">Search Accounts</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="ri-search-line"></i></span>
              <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Name, code, or account no..." data-auto-submit="true">
            </div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-medium text-secondary">Loan Type</label>
            <select name="loan_mode" class="form-select" data-auto-submit="true">
              <option value="all" {{ $loanMode === 'all' ? 'selected' : '' }}>All Types</option>
              <option value="emi" {{ $loanMode === 'emi' ? 'selected' : '' }}>Standard EMI</option>
              <option value="interest_only" {{ $loanMode === 'interest_only' ? 'selected' : '' }}>Open Loan (Kandhuvatti)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-medium text-secondary">From Disbursed Date</label>
            <input type="date" name="from_date" value="{{ $fromDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-medium text-secondary">To Disbursed Date</label>
            <input type="date" name="to_date" value="{{ $toDate }}" class="form-control" data-auto-submit="true">
          </div>
          <div class="col-md-3 d-flex gap-2 justify-content-end">
            <button type="submit" class="btn btn-outline-primary d-none"><i class="ri-search-line me-1"></i>Search</button>
            <a href="{{ route('reports-revenue') }}" class="btn btn-outline-secondary w-100" id="resetRevenueFilters">
              <i class="ri-refresh-line me-1"></i>Reset Filters
            </a>
          </div>
        </form>

        <!-- Ajax Loading Spinner -->
        <div id="revenueTableContainer" class="position-relative">
          @include('admin.revenue.table')
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('revenueFilterForm');
    const tableContainer = document.getElementById('revenueTableContainer');
    const resetBtn = document.getElementById('resetRevenueFilters');
    
    // Exports
    const exportCsvBtn = document.getElementById('exportCsv');
    const exportExcelBtn = document.getElementById('exportExcel');
    const exportPdfBtn = document.getElementById('exportPdf');

    const getExportUrl = (format) => {
      const formData = new FormData(filterForm);
      const params = new URLSearchParams(formData);
      params.set('format', format);
      return `{{ route('reports-revenue-export') }}?${params.toString()}`;
    };

    exportCsvBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.location.href = getExportUrl('csv');
    });

    exportExcelBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.location.href = getExportUrl('excel');
    });

    exportPdfBtn.addEventListener('click', function(e) {
      e.preventDefault();
      window.location.href = getExportUrl('pdf');
    });

    if (filterForm && tableContainer) {
      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = isLoading => {
        tableContainer.classList.toggle('opacity-50', isLoading);
        tableContainer.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateRevenueData = url => {
        toggleLoadingState(true);
        fetch(url, {
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html'
          }
        })
          .then(response => {
            if (!response.ok) throw new Error('Failed to fetch revenue data');
            return response.text();
          })
          .then(html => {
            tableContainer.innerHTML = html;
            window.history.replaceState({}, '', url);
          })
          .catch(error => console.error('Revenue filter error:', error))
          .finally(() => toggleLoadingState(false));
      };

      const submitFilters = customUrl => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams(formData);
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateRevenueData(url);
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 250);
      };

      autoSubmitFields.forEach(field => {
        if (field.tagName === 'INPUT' && field.type === 'text') {
          field.addEventListener('input', debouncedSubmit);
        } else {
          field.addEventListener('change', debouncedSubmit);
        }
      });

      filterForm.addEventListener('submit', event => {
        event.preventDefault();
        submitFilters();
      });

      if (resetBtn) {
        resetBtn.addEventListener('click', event => {
          event.preventDefault();
          filterForm.reset();
          // Reset text inputs & selects manually to make sure
          filterForm.querySelectorAll('input[type="text"], input[type="date"]').forEach(input => input.value = '');
          filterForm.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
          submitFilters(baseUrl);
        });
      }

      // Handle Pagination click delegation
      tableContainer.addEventListener('click', event => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) {
            updateRevenueData(url);
          }
        }
      });
    }
  });
</script>
@endsection
