@extends('layouts/layoutMaster')

@section('title', 'Revenue Report')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h4 class="mb-1 text-primary"><i class="ri-money-rupee-circle-line me-2"></i>Revenue Report</h4>
        <p class="text-muted mb-0">Tab-wise breakdown of fees, charges, and profit across Loan, Chit, and Fixed Deposit portfolios.</p>
      </div>
    </div>
  </div>
</div>

{{-- Module Tabs --}}
<ul class="nav nav-pills mb-4 gap-2" role="tablist">
  <li class="nav-item">
    <a class="nav-link {{ $tab === 'loan' ? 'active' : '' }}" href="{{ route('reports-revenue', array_merge(request()->except('page'), ['tab' => 'loan'])) }}">
      <i class="ri-bank-line me-1"></i> Loan
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $tab === 'chit' ? 'active' : '' }}" href="{{ route('reports-revenue', array_merge(request()->except('page'), ['tab' => 'chit'])) }}">
      <i class="ri-group-line me-1"></i> Chit Fund
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $tab === 'fd' ? 'active' : '' }}" href="{{ route('reports-revenue', array_merge(request()->except('page'), ['tab' => 'fd'])) }}">
      <i class="ri-safe-2-line me-1"></i> Fixed Deposit
    </a>
  </li>
</ul>

{{-- KPI Cards --}}
@if($tab === 'loan')
  @include('admin.revenue.partials.loan-kpis')
@elseif($tab === 'chit')
  @include('admin.revenue.partials.chit-kpis')
@else
  @include('admin.revenue.partials.fd-kpis')
@endif

{{-- Table & Filters --}}
<div class="row">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header border-bottom py-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
          <div>
            <h5 class="card-title m-0 fw-semibold text-dark">
              @if($tab === 'chit')
                Chit Group Revenue Statement
              @elseif($tab === 'fd')
                Fixed Deposit Revenue Statement
              @else
                Loan Revenue Statement
              @endif
            </h5>
            <small class="text-muted">Generate, filter, and export customized revenue details</small>
          </div>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="ri-download-line me-1"></i> Export Data
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item py-2" id="exportCsv" href="#"><i class="ri-file-text-line me-2 text-secondary"></i>CSV Format</a></li>
              <li><a class="dropdown-item py-2" id="exportExcel" href="#"><i class="ri-file-excel-2-line me-2 text-success"></i>Excel Format</a></li>
              <li><a class="dropdown-item py-2" id="exportPdf" href="#"><i class="ri-file-pdf-line me-2 text-danger"></i>PDF Document</a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="card-body pt-4">
        <form method="GET" action="{{ route('reports-revenue') }}" class="row g-3 mb-4" id="revenueFilterForm">
          <input type="hidden" name="tab" value="{{ $tab }}">
          
          <div class="col-12 col-lg-3 col-md-6">
            <label class="form-label fw-medium text-secondary">Search</label>
            <div class="input-group input-group-merge">
              <span class="input-group-text"><i class="ri-search-line"></i></span>
              <input type="text" name="search" value="{{ $search }}" class="form-control"
                placeholder="@if($tab === 'chit')Group or scheme...@elseif($tab === 'fd')Name or FD no...@else Name, code, account...@endif"
                data-auto-submit="true">
            </div>
          </div>
          
          @if($tab === 'loan')
            <div class="col-12 col-lg-2 col-md-6">
              <label class="form-label fw-medium text-secondary">Loan Type</label>
              <select name="loan_mode" class="form-select" data-auto-submit="true">
                <option value="all" {{ $loanMode === 'all' ? 'selected' : '' }}>All Types</option>
                <option value="emi" {{ $loanMode === 'emi' ? 'selected' : '' }}>Standard EMI</option>
                <option value="interest_only" {{ $loanMode === 'interest_only' ? 'selected' : '' }}>Open Loan</option>
              </select>
            </div>
          @endif
          
          <div class="col-12 col-lg-auto d-flex align-items-end">
            @include('partials.date-range-filter', [
              'fromId' => 'revenueReportDateFrom',
              'toId' => 'revenueReportDateTo',
              'presetId' => 'revenueReportDatePreset',
              'fromName' => 'from_date',
              'toName' => 'to_date',
              'fromValue' => $fromDate,
              'toValue' => $toDate,
              'presetValue' => request('date_preset', 'all'),
              'dataAutoSubmit' => true,
              'size' => 'sm',
            ])
          </div>
          <div class="col-12 col-lg-2 col-md-3 d-flex flex-column justify-content-end ms-lg-auto">
            <label class="form-label fw-medium text-secondary d-none d-lg-block">&nbsp;</label>
            <a href="{{ route('reports-revenue', ['tab' => $tab]) }}" class="btn btn-outline-secondary w-100" id="resetRevenueFilters">
              <i class="ri-refresh-line me-1"></i>Reset
            </a>
          </div>
        </form>

        <div id="revenueTableContainer" class="position-relative">
          @if($tab === 'chit')
            @include('admin.revenue.chit-table')
          @elseif($tab === 'fd')
            @include('admin.revenue.fd-table')
          @else
            @include('admin.revenue.table')
          @endif
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
    const exportCsvBtn = document.getElementById('exportCsv');
    const exportExcelBtn = document.getElementById('exportExcel');
    const exportPdfBtn = document.getElementById('exportPdf');

    const getExportUrl = (format) => {
      const formData = new FormData(filterForm);
      const params = new URLSearchParams();
      for (const [key, value] of formData.entries()) {
        if (key === 'date_preset' || (value !== null && value.toString().trim() !== '')) {
          params.append(key, value.toString().trim());
        }
      }
      params.set('format', format);
      return `{{ route('reports-revenue-export') }}?${params.toString()}`;
    };

    exportCsvBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = getExportUrl('csv'); });
    exportExcelBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = getExportUrl('excel'); });
    exportPdfBtn.addEventListener('click', (e) => { e.preventDefault(); window.location.href = getExportUrl('pdf'); });

    if (filterForm && tableContainer) {
      const autoSubmitFields = filterForm.querySelectorAll('[data-auto-submit="true"]');
      const baseUrl = filterForm.getAttribute('action') || window.location.pathname;
      let submitTimer = null;

      const toggleLoadingState = (isLoading) => {
        tableContainer.classList.toggle('opacity-50', isLoading);
        tableContainer.style.pointerEvents = isLoading ? 'none' : '';
      };

      const updateRevenueData = (url) => {
        toggleLoadingState(true);
        fetch(url, {
          headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }
        })
          .then((response) => {
            if (!response.ok) throw new Error('Failed to fetch revenue data');
            return response.text();
          })
          .then((html) => {
            tableContainer.innerHTML = html;
            window.history.replaceState({}, '', url);
          })
          .catch((error) => console.error('Revenue filter error:', error))
          .finally(() => toggleLoadingState(false));
      };

      const submitFilters = (customUrl) => {
        const formData = new FormData(filterForm);
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
          if (key === 'date_preset' || (value !== null && String(value).trim() !== '')) {
            params.append(key, String(value).trim());
          }
        }
        const url = customUrl || `${baseUrl}?${params.toString()}`;
        updateRevenueData(url);
      };

      const debouncedSubmit = () => {
        if (submitTimer) clearTimeout(submitTimer);
        submitTimer = setTimeout(() => submitFilters(), 250);
      };

      autoSubmitFields.forEach((field) => {
        if (field.tagName === 'INPUT' && field.type === 'text') {
          field.addEventListener('input', debouncedSubmit);
        } else {
          field.addEventListener('change', debouncedSubmit);
        }
      });

      filterForm.addEventListener('date-range:change', debouncedSubmit);

      filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitFilters();
      });

      if (resetBtn) {
        resetBtn.addEventListener('click', (event) => {
          event.preventDefault();
          const tab = filterForm.querySelector('[name="tab"]')?.value || 'loan';
          submitFilters(`${baseUrl}?tab=${tab}`);
        });
      }

      tableContainer.addEventListener('click', (event) => {
        const paginationLink = event.target.closest('.pagination a');
        if (paginationLink) {
          event.preventDefault();
          const url = paginationLink.getAttribute('href');
          if (url) updateRevenueData(url);
        }
      });
    }
  });
</script>
@endsection
