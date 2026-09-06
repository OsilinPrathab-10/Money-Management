@extends('layouts/layoutMaster')

@php
  if (!function_exists('formatIndianCurrency')) {
      function formatIndianCurrency($amount) {
          $amount = (float)$amount;
          if ($amount >= 10000000) { // Crore
              return number_format($amount / 10000000, 2) . ' C';
          } elseif ($amount >= 100000) { // Lakh
              return number_format($amount / 100000, 2) . ' L';
          } elseif ($amount >= 1000) { // Thousand
              return number_format($amount / 1000, 2) . ' K';
          }
          return number_format($amount, 2);
      }
  }
@endphp

@section('title', __('Accounting reports'))

@section('page-style')
<style>
  /* Premium UI Custom Styles for Accounting Reports */
  .premium-report-card {
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    border: none;
    border-radius: 1rem;
    background: #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    overflow: hidden;
    position: relative;
    z-index: 1;
  }

  [data-bs-theme="dark"] .premium-report-card {
    background: #2b2c40;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
  }

  .premium-report-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--bs-primary), #a855f7);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s ease;
    z-index: 2;
  }

  .premium-report-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.08);
  }

  [data-bs-theme="dark"] .premium-report-card:hover {
    box-shadow: 0 12px 24px rgba(0,0,0,0.3);
  }

  .premium-report-card:hover::before {
    transform: scaleX(1);
  }

  .premium-icon-wrapper {
    width: 3.5rem;
    height: 3.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.15) 0%, rgba(var(--bs-primary-rgb), 0.05) 100%);
    color: var(--bs-primary);
    transition: all 0.3s ease;
  }

  .premium-report-card:hover .premium-icon-wrapper {
    background: linear-gradient(135deg, var(--bs-primary) 0%, #a855f7 100%);
    color: #ffffff;
    transform: scale(1.1) rotate(5deg);
  }

  .premium-title {
    font-weight: 700;
    font-size: 1.15rem;
    color: var(--bs-heading-color);
    margin-bottom: 0.5rem;
    letter-spacing: -0.01em;
  }

  .premium-subtitle {
    color: var(--bs-secondary-color);
    font-size: 0.875rem;
    line-height: 1.5;
  }

  .premium-value {
    font-size: 1.5rem;
    font-weight: 800;
    background: linear-gradient(90deg, var(--bs-primary), #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    display: inline-block;
  }

  .premium-btn-group .btn {
    border-radius: 0.5rem;
    font-weight: 500;
    transition: all 0.2s ease;
  }

  .premium-btn-group .btn-primary {
    background: linear-gradient(135deg, var(--bs-primary), #a855f7);
    border: none;
    box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.3);
  }

  .premium-btn-group .btn-primary:hover {
    box-shadow: 0 6px 15px rgba(var(--bs-primary-rgb), 0.4);
    transform: translateY(-2px);
  }

  .premium-api-card {
    background: linear-gradient(145deg, #1e293b, #0f172a);
    border: none;
    border-radius: 1rem;
    color: #f8fafc;
    position: relative;
    overflow: hidden;
  }

  .premium-api-card::after {
    content: '\eb3d';
    font-family: 'remixicon';
    position: absolute;
    right: -20px;
    bottom: -30px;
    font-size: 10rem;
    color: rgba(255, 255, 255, 0.03);
    z-index: 0;
    pointer-events: none;
  }

  .premium-api-card .card-body {
    position: relative;
    z-index: 1;
  }

  .api-endpoint {
    background: rgba(255, 255, 255, 0.1);
    border-left: 4px solid var(--bs-primary);
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    margin-bottom: 0.75rem;
    font-family: 'JetBrains Mono', monospace, Consolas;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #cbd5e1;
    transition: background 0.2s ease;
  }

  .api-endpoint:hover {
    background: rgba(255, 255, 255, 0.15);
  }

  .api-method {
    background: var(--bs-primary);
    color: white;
    padding: 0.15rem 0.5rem;
    border-radius: 0.25rem;
    font-weight: 700;
    font-size: 0.75rem;
  }

  /* Date inputs custom styling */
  .report-date-filter {
    background-color: rgba(var(--bs-secondary-rgb), 0.05);
    border: 1px solid rgba(var(--bs-border-color-rgb), 0.1);
    border-radius: 0.5rem;
    padding: 0.75rem;
    margin-bottom: 1rem;
  }

  .report-date-filter .form-label {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--bs-secondary-color);
  }

  .report-date-filter .form-control {
    border: 1px solid rgba(var(--bs-border-color-rgb), 0.3);
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
  }

  .report-date-filter .form-control:focus {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--bs-primary-rgb), 0.15);
  }
</style>
@endsection

@section('content')
@php
  $today = date('Y-m-d');
  $fyStart = $financialYear['year_start_date'] ?? date('Y') . '-01-01';
  $fyEnd = $financialYear['year_end_date'] ?? date('Y') . '-12-31';
@endphp

<div class="container-xxl flex-grow-1 container-p-y">
  @include('admin.account.shared.page-header', [
    'title' => __('Accounting Reports'),
    'subtitle' => __('Comprehensive financial analytics, aging reports, tax summaries, and balance statements.'),
    'icon' => 'ri-file-chart-line',
    'toolbar' => '<a href="' . e(route('account.index')) . '" class="btn btn-primary shadow-sm rounded-pill px-4"><i class="icon-base ri ri-dashboard-3-line me-2"></i>' . e(__('Account Dashboard')) . '</a>',
  ])
<!-- 
  @if (empty($erpSalesInvoicesAvailable))
    <div class="alert alert-warning d-flex gap-3 align-items-center mb-5 rounded-4 shadow-sm border-0 bg-warning-subtle text-warning-emphasis" role="alert">
      <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
        <i class="icon-base ri ri-error-warning-fill fs-5"></i>
      </div>
      <div>
        <h6 class="alert-heading mb-1 fw-bold">{{ __('ERP tables not detected') }}</h6>
        <p class="mb-0 small">{{ __('Invoice and bill aging reports use optional tables such as :table. You will see empty data until those modules are migrated.', ['table' => 'sales_invoices']) }}</p>
      </div>
    </div>
  @endif -->

  @php
    $previewTotal = $invoicePreview['aging_summary']['total'] ?? 0;
  @endphp

  <!-- Primary Reports -->
  <!-- <div class="d-flex align-items-center mb-4 mt-2">
    <h5 class="mb-0 fw-bold"><i class="ri-bar-chart-box-line text-primary me-2"></i>{{ __('Core Financials') }}</h5>
    <div class="flex-grow-1 ms-3 border-top"></div>
  </div> -->

  <!-- <div class="row g-4 mb-5">
    {{-- Invoice aging --}}
    <div class="col-md-6 col-xl-4">
      <div class="card premium-report-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="premium-icon-wrapper">
              <i class="icon-base ri ri-time-line fs-3"></i>
            </div>
            <div class="text-end">
                @if (!empty($invoicePreview['erp_tables_missing'] ?? false))
                  <div class="text-muted small"><i class="ri-database-2-line"></i> {{ __('No Data') }}</div>
                @else
                  <div class="premium-value" style="font-size: 1.2rem;">₹{{ number_format((float) $previewTotal, 2) }}</div>
                  <div class="text-muted" style="font-size: 0.7rem;">{{ __('Total Outstanding') }}</div>
                @endif
            </div>
          </div>
          <div class="mb-3 flex-grow-1">
            <h5 class="premium-title">{{ __('Invoice Aging') }}</h5>
            <p class="premium-subtitle">{{ __('Track open sales invoices by aging bucket to monitor outstanding receivables.') }}</p>
          </div>
          
          <div class="report-date-filter">
            <label class="form-label">{{ __('As of Date') }}</label>
            <input type="date" class="form-control report-filter-input" data-target="invoice-aging" value="{{ $today }}">
          </div>

          <div class="d-flex gap-2 premium-btn-group mt-auto">
            <a href="{{ route('account.reports.invoice-aging.print', ['as_of_date' => $today]) }}" id="btn-view-invoice-aging" class="btn btn-primary flex-grow-1" data-base-url="{{ route('account.reports.invoice-aging.print') }}">
              <i class="ri-eye-line me-1"></i> {{ __('View') }}
            </a>
            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-icon dropdown-toggle hide-arrow rounded-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-download-2-line"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.invoice-aging.export', ['format' => 'pdf']) }}" href="{{ route('account.reports.invoice-aging.export', ['format' => 'pdf', 'as_of_date' => $today]) }}"><i class="ri-file-pdf-line text-danger me-2"></i>{{ __('Export as PDF') }}</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.invoice-aging.export', ['format' => 'xlsx']) }}" href="{{ route('account.reports.invoice-aging.export', ['format' => 'xlsx', 'as_of_date' => $today]) }}"><i class="ri-file-excel-2-line text-success me-2"></i>{{ __('Export as Excel') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.invoice-aging.export', ['format' => 'csv']) }}" href="{{ route('account.reports.invoice-aging.export', ['format' => 'csv', 'as_of_date' => $today]) }}"><i class="ri-file-text-line text-secondary me-2"></i>{{ __('Export as CSV') }}</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Bill aging --}}
    <div class="col-md-6 col-xl-4">
      <div class="card premium-report-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="premium-icon-wrapper" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); color: #ef4444;">
              <i class="icon-base ri ri-bill-line fs-3"></i>
            </div>
          </div>
          <div class="mb-3 flex-grow-1">
            <h5 class="premium-title">{{ __('Bill Aging') }}</h5>
            <p class="premium-subtitle">{{ __('Review open purchase bills by aging bucket to manage upcoming liabilities.') }}</p>
          </div>

          <div class="report-date-filter">
            <label class="form-label">{{ __('As of Date') }}</label>
            <input type="date" class="form-control report-filter-input" data-target="bill-aging" value="{{ $today }}">
          </div>

          <div class="d-flex gap-2 premium-btn-group mt-auto">
            <a href="{{ route('account.reports.bill-aging.print', ['as_of_date' => $today]) }}" id="btn-view-bill-aging" class="btn btn-primary flex-grow-1" style="background: linear-gradient(135deg, #ef4444, #f97316); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);" data-base-url="{{ route('account.reports.bill-aging.print') }}">
              <i class="ri-eye-line me-1"></i> {{ __('View') }}
            </a>
            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-icon dropdown-toggle hide-arrow rounded-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-download-2-line"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.bill-aging.export', ['format' => 'pdf']) }}" href="{{ route('account.reports.bill-aging.export', ['format' => 'pdf', 'as_of_date' => $today]) }}"><i class="ri-file-pdf-line text-danger me-2"></i>PDF</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.bill-aging.export', ['format' => 'xlsx']) }}" href="{{ route('account.reports.bill-aging.export', ['format' => 'xlsx', 'as_of_date' => $today]) }}"><i class="ri-file-excel-2-line text-success me-2"></i>Excel</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.bill-aging.export', ['format' => 'csv']) }}" href="{{ route('account.reports.bill-aging.export', ['format' => 'csv', 'as_of_date' => $today]) }}"><i class="ri-file-text-line text-secondary me-2"></i>CSV</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Tax summary --}}
    <div class="col-md-6 col-xl-4">
      <div class="card premium-report-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div class="premium-icon-wrapper" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); color: #10b981;">
              <i class="icon-base ri ri-percent-line fs-3"></i>
            </div>
          </div>
          <div class="mb-3 flex-grow-1">
            <h5 class="premium-title">{{ __('Tax Summary') }}</h5>
            <p class="premium-subtitle">{{ __('Analyze collected vs paid taxes across the selected financial period.') }}</p>
          </div>

          <div class="report-date-filter">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">{{ __('From') }}</label>
                <input type="date" class="form-control report-filter-input-from" data-target="tax-summary" value="{{ $fyStart }}">
              </div>
              <div class="col-6">
                <label class="form-label">{{ __('To') }}</label>
                <input type="date" class="form-control report-filter-input-to" data-target="tax-summary" value="{{ $fyEnd }}">
              </div>
            </div>
          </div>

          <div class="d-flex gap-2 premium-btn-group mt-auto">
            <a href="{{ route('account.reports.tax-summary.print', ['from_date' => $fyStart, 'to_date' => $fyEnd]) }}" id="btn-view-tax-summary" class="btn btn-primary flex-grow-1" style="background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);" data-base-url="{{ route('account.reports.tax-summary.print') }}">
              <i class="ri-eye-line me-1"></i> {{ __('View') }}
            </a>
            <div class="dropdown">
              <button class="btn btn-outline-secondary btn-icon dropdown-toggle hide-arrow rounded-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-download-2-line"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.tax-summary.export', ['format' => 'pdf']) }}" href="{{ route('account.reports.tax-summary.export', ['format' => 'pdf', 'from_date' => $fyStart, 'to_date' => $fyEnd]) }}"><i class="ri-file-pdf-line text-danger me-2"></i>PDF</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.tax-summary.export', ['format' => 'xlsx']) }}" href="{{ route('account.reports.tax-summary.export', ['format' => 'xlsx', 'from_date' => $fyStart, 'to_date' => $fyEnd]) }}"><i class="ri-file-excel-2-line text-success me-2"></i>Excel</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.tax-summary.export', ['format' => 'csv']) }}" href="{{ route('account.reports.tax-summary.export', ['format' => 'csv', 'from_date' => $fyStart, 'to_date' => $fyEnd]) }}"><i class="ri-file-text-line text-secondary me-2"></i>CSV</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div> -->

  <!-- Balances Section -->
  <!-- <div class="d-flex align-items-center mb-4 mt-2">
    <h5 class="mb-0 fw-bold"><i class="ri-scales-3-line text-primary me-2"></i>{{ __('Ledger Balances') }}</h5>
    <div class="flex-grow-1 ms-3 border-top"></div>
  </div>

  <div class="row g-4 mb-5">
    {{-- Customer balance --}}
    <div class="col-md-6">
      <div class="card premium-report-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-4 mb-3">
            <div class="premium-icon-wrapper flex-shrink-0" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(59, 130, 246, 0.05) 100%); color: #3b82f6;">
              <i class="icon-base ri ri-user-3-line fs-3"></i>
            </div>
            <div>
              <h5 class="premium-title mb-1">{{ __('Customer Balance') }}</h5>
              <p class="premium-subtitle mb-0">{{ __('Detailed overview of outstanding accounts receivable broken down by customer.') }}</p>
            </div>
          </div>

          <div class="report-date-filter mt-2 mb-4 d-flex align-items-end gap-3">
            <div class="flex-grow-1">
              <label class="form-label">{{ __('As of Date') }}</label>
              <input type="date" class="form-control report-filter-input" data-target="customer-balance" value="{{ $today }}">
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input report-filter-checkbox" type="checkbox" role="switch" data-target="customer-balance" id="showZeroCustomer" value="true">
              <label class="form-check-label text-secondary small fw-medium" for="showZeroCustomer">{{ __('Show Zero Balances') }}</label>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 premium-btn-group mt-auto">
            <a href="{{ route('account.reports.customer-balance.print', ['as_of_date' => $today]) }}" id="btn-view-customer-balance" class="btn btn-outline-primary px-4" data-base-url="{{ route('account.reports.customer-balance.print') }}">
              <i class="ri-eye-line me-1"></i> {{ __('View Report') }}
            </a>
            <div class="dropdown">
              <button class="btn btn-primary btn-icon dropdown-toggle hide-arrow rounded-3 px-3" style="background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-download-cloud-2-line"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.customer-balance.export', ['format' => 'pdf']) }}" href="{{ route('account.reports.customer-balance.export', ['format' => 'pdf', 'as_of_date' => $today]) }}"><i class="ri-file-pdf-line text-danger me-2"></i>PDF</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.customer-balance.export', ['format' => 'xlsx']) }}" href="{{ route('account.reports.customer-balance.export', ['format' => 'xlsx', 'as_of_date' => $today]) }}"><i class="ri-file-excel-2-line text-success me-2"></i>Excel</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.customer-balance.export', ['format' => 'csv']) }}" href="{{ route('account.reports.customer-balance.export', ['format' => 'csv', 'as_of_date' => $today]) }}"><i class="ri-file-text-line text-secondary me-2"></i>CSV</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Vendor balance --}}
    <div class="col-md-6">
      <div class="card premium-report-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-4 mb-3">
            <div class="premium-icon-wrapper flex-shrink-0" style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0.05) 100%); color: #f59e0b;">
              <i class="icon-base ri ri-store-2-line fs-3"></i>
            </div>
            <div>
              <h5 class="premium-title mb-1">{{ __('Vendor Balance') }}</h5>
              <p class="premium-subtitle mb-0">{{ __('Comprehensive summary of outstanding accounts payable organized by vendor.') }}</p>
            </div>
          </div>

          <div class="report-date-filter mt-2 mb-4 d-flex align-items-end gap-3">
            <div class="flex-grow-1">
              <label class="form-label">{{ __('As of Date') }}</label>
              <input type="date" class="form-control report-filter-input" data-target="vendor-balance" value="{{ $today }}">
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input report-filter-checkbox" type="checkbox" role="switch" data-target="vendor-balance" id="showZeroVendor" value="true">
              <label class="form-check-label text-secondary small fw-medium" for="showZeroVendor">{{ __('Show Zero Balances') }}</label>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 premium-btn-group mt-auto">
            <a href="{{ route('account.reports.vendor-balance.print', ['as_of_date' => $today]) }}" id="btn-view-vendor-balance" class="btn btn-outline-warning px-4 text-warning border-warning" data-base-url="{{ route('account.reports.vendor-balance.print') }}">
              <i class="ri-eye-line me-1"></i> {{ __('View Report') }}
            </a>
            <div class="dropdown">
              <button class="btn btn-warning btn-icon dropdown-toggle hide-arrow text-white rounded-3 px-3" style="background: linear-gradient(135deg, #f59e0b, #d97706); border: none; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ri-download-cloud-2-line"></i>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.vendor-balance.export', ['format' => 'pdf']) }}" href="{{ route('account.reports.vendor-balance.export', ['format' => 'pdf', 'as_of_date' => $today]) }}"><i class="ri-file-pdf-line text-danger me-2"></i>PDF</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.vendor-balance.export', ['format' => 'xlsx']) }}" href="{{ route('account.reports.vendor-balance.export', ['format' => 'xlsx', 'as_of_date' => $today]) }}"><i class="ri-file-excel-2-line text-success me-2"></i>Excel</a></li>
                <li><a class="dropdown-item d-flex align-items-center export-link" data-base-url="{{ route('account.reports.vendor-balance.export', ['format' => 'csv']) }}" href="{{ route('account.reports.vendor-balance.export', ['format' => 'csv', 'as_of_date' => $today]) }}"><i class="ri-file-text-line text-secondary me-2"></i>CSV</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div> -->

  <!-- Navigation Tabs -->
  <ul class="nav nav-tabs mb-5 border-bottom" role="tablist" style="display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; width: 100% !important; gap: 0.5rem;">
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-financial" aria-controls="navs-financial" aria-selected="true">
        <i class="ri-book-open-line me-1"></i> {{ __('Financial Statements') }}
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-ledgers" aria-controls="navs-ledgers" aria-selected="false">
        <i class="ri-book-2-line me-1"></i> {{ __('General Ledger & Books') }}
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-income-expense" aria-controls="navs-income-expense" aria-selected="false">
        <i class="ri-funds-line me-1"></i> {{ __('Income & Expense') }}
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-loans" aria-controls="navs-loans" aria-selected="false">
        <i class="ri-hand-coin-line me-1"></i> {{ __('Loans & Advances') }}
      </button>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content bg-transparent p-0 shadow-none border-0">
    <!-- Tab 1: Financial Statements -->
    <div class="tab-pane fade show active" id="navs-financial" role="tabpanel">
      <div class="row g-4">
        @php
          $financialStatements = [
        ['title' => 'Account Summary Report', 'icon' => 'ri-file-list-3-line', 'color' => '#6366f1', 'desc' => 'Detailed summary of all active accounts.', 'route' => route('account.reports.account-summary')]
          ];
        @endphp
        @foreach($financialStatements as $report)
        <div class="col-md-6 col-xl-4">
          <div class="card premium-report-card h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="premium-icon-wrapper flex-shrink-0" style="background: rgba(0,0,0,0.03); color: {{ $report['color'] }};">
                  <i class="icon-base {{ $report['icon'] }} fs-3"></i>
                </div>
                @if(isset($report['value']))
                <div class="text-end">
                   <div class="fw-bold text-heading" style="font-size: 1.1rem;">{{ $report['value'] }}</div>
                   <div class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ __($report['valueLabel']) }}</div>
                </div>
                @endif
              </div>
              <div class="mb-4 flex-grow-1">
                 <h5 class="premium-title mb-1">{{ __($report['title']) }}</h5>
                 <p class="premium-subtitle mb-0" style="font-size: 0.85rem;">{{ __($report['desc']) }}</p>
              </div>
              <a href="{{ $report['route'] }}" class="btn btn-outline-primary w-100">{{ __('View Report') }}</a>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>

    <!-- Tab 2: General Ledger & Books -->
    <div class="tab-pane fade" id="navs-ledgers" role="tabpanel">
      <div class="row g-4">
        @php
          $ledgerBooks = [
        ['title' => 'General Ledger Report', 'icon' => 'ri-file-paper-2-line', 'color' => '#eab308', 'desc' => 'Master record of all your financial transactions.', 'route' => route('account.reports.general-ledger')],
            ['title' => 'Day Book Report', 'icon' => 'ri-calendar-todo-line', 'color' => '#f97316', 'desc' => 'Daily chronological record of transactions.', 'route' => route('account.reports.day-book')],
          ];
        @endphp
        @foreach($ledgerBooks as $report)
        <div class="col-md-6 col-xl-3">
          <div class="card premium-report-card h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="premium-icon-wrapper flex-shrink-0" style="background: rgba(0,0,0,0.03); color: {{ $report['color'] }};">
                  <i class="icon-base {{ $report['icon'] }} fs-3"></i>
                </div>
                @if(isset($report['value']))
                <div class="text-end">
                   <div class="fw-bold text-heading" style="font-size: 1.1rem;">{{ $report['value'] }}</div>
                   <div class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ __($report['valueLabel']) }}</div>
                </div>
                @endif
              </div>
              <div class="mb-4 flex-grow-1">
                 <h5 class="premium-title mb-1">{{ __($report['title']) }}</h5>
                 <p class="premium-subtitle mb-0" style="font-size: 0.85rem;">{{ __($report['desc']) }}</p>
              </div>
              <a href="{{ $report['route'] }}" class="btn btn-outline-secondary w-100">{{ __('View Report') }}</a>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>

    <!-- Tab 3: Income & Expense -->
    <div class="tab-pane fade" id="navs-income-expense" role="tabpanel">
      <div class="row g-4">
        @php
          $incomeExpense = [
            ['title' => 'Revenue Report', 'icon' => 'ri-arrow-right-down-line', 'color' => '#10b981', 'desc' => 'Detailed list of all revenue transactions.', 'route' => route('account.reports.revenue-report'), 'value' => formatIndianCurrency($reportStats['totalRevenue'] ?? 0), 'valueLabel' => 'Total Revenue'],
            ['title' => 'Expense Report', 'icon' => 'ri-arrow-right-up-line', 'color' => '#ef4444', 'desc' => 'Detailed list of all expense transactions.', 'route' => route('account.reports.expense-report'), 'value' => formatIndianCurrency($reportStats['totalExpense'] ?? 0), 'valueLabel' => 'Total Expense'],
             ];
        @endphp
        @foreach($incomeExpense as $report)
        <div class="col-md-6 col-xl-3">
          <div class="card premium-report-card h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="premium-icon-wrapper flex-shrink-0" style="background: rgba(0,0,0,0.03); color: {{ $report['color'] }};">
                  <i class="icon-base {{ $report['icon'] }} fs-3"></i>
                </div>
                @if(isset($report['value']))
                <div class="text-end">
                   <div class="fw-bold text-heading" style="font-size: 1.1rem;">{{ $report['value'] }}</div>
                   <div class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ __($report['valueLabel']) }}</div>
                </div>
                @endif
              </div>
              <div class="mb-4 flex-grow-1">
                 <h5 class="premium-title mb-1">{{ __($report['title']) }}</h5>
                 <p class="premium-subtitle mb-0" style="font-size: 0.85rem;">{{ __($report['desc']) }}</p>
              </div>
              <a href="{{ $report['route'] }}" class="btn btn-outline-secondary w-100">{{ __('View Report') }}</a>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>

    <!-- Tab 4: Loans & Advances -->
    <div class="tab-pane fade" id="navs-loans" role="tabpanel">
      <div class="row g-4">
        @php
          $loanReports = [
            ['title' => 'Outstanding Loans Report', 'icon' => 'ri-safe-2-line', 'color' => '#8b5cf6', 'desc' => 'Overview of all active loans and outstanding balances.', 'route' => route('account.reports.outstanding-loans'), 'value' => formatIndianCurrency($reportStats['totalOutstandingLoans'] ?? 0), 'valueLabel' => 'Outstanding'],
            ['title' => 'Loan Disbursement Report', 'icon' => 'ri-exchange-dollar-line', 'color' => '#ec4899', 'desc' => 'Detailed list of loan disbursements and collections.', 'route' => route('account.reports.loan-disbursement'), 'value' => formatIndianCurrency($reportStats['totalLoanDisbursed'] ?? 0), 'valueLabel' => 'Disbursed']
          ];
        @endphp
        @foreach($loanReports as $report)
        <div class="col-md-6 col-xl-4">
          <div class="card premium-report-card h-100">
            <div class="card-body d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <div class="premium-icon-wrapper flex-shrink-0" style="background: rgba(0,0,0,0.03); color: {{ $report['color'] }};">
                  <i class="icon-base {{ $report['icon'] }} fs-3"></i>
                </div>
                @if(isset($report['value']))
                <div class="text-end">
                   <div class="fw-bold text-heading" style="font-size: 1.1rem;">{{ $report['value'] }}</div>
                   <div class="text-muted" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">{{ __($report['valueLabel']) }}</div>
                </div>
                @endif
              </div>
              <div class="mb-4 flex-grow-1">
                 <h5 class="premium-title mb-1">{{ __($report['title']) }}</h5>
                 <p class="premium-subtitle mb-0" style="font-size: 0.85rem;">{{ __($report['desc']) }}</p>
              </div>
              <a href="{{ $report['route'] }}" class="btn btn-outline-secondary w-100">{{ __('View Report') }}</a>
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>
 

</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Logic to dynamically update View and Export Links based on date selection
  const updateLinks = (targetClass, paramString) => {
    const viewBtn = document.getElementById(`btn-view-${targetClass}`);
    if (viewBtn) {
      const baseUrl = viewBtn.getAttribute('data-base-url');
      viewBtn.setAttribute('href', `${baseUrl}?${paramString}`);
    }

    const exportLinks = document.querySelectorAll(`[data-target="${targetClass}"]`).length 
      ? document.querySelectorAll(`a.export-link[href*="${targetClass}"]`) 
      : document.querySelectorAll(`a.export-link[data-base-url*="${targetClass}"]`);
      
    exportLinks.forEach(link => {
      const baseUrl = link.getAttribute('data-base-url');
      if (baseUrl.includes(targetClass)) {
        link.setAttribute('href', `${baseUrl}&${paramString}`);
      }
    });
  };

  // For single date inputs (aging & balances)
  document.querySelectorAll('.report-filter-input').forEach(input => {
    input.addEventListener('change', function() {
      const target = this.getAttribute('data-target');
      const dateVal = this.value;
      
      let params = new URLSearchParams();
      params.append('as_of_date', dateVal);

      // check if there's a checkbox for zero balances
      const checkbox = document.querySelector(`.report-filter-checkbox[data-target="${target}"]`);
      if (checkbox && checkbox.checked) {
        params.append('show_zero_balances', 'true');
      }

      updateLinks(target, params.toString());
    });
  });

  // For checkboxes (show zero balances)
  document.querySelectorAll('.report-filter-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      const target = this.getAttribute('data-target');
      const input = document.querySelector(`.report-filter-input[data-target="${target}"]`);
      const dateVal = input ? input.value : '';

      let params = new URLSearchParams();
      if (dateVal) params.append('as_of_date', dateVal);
      if (this.checked) params.append('show_zero_balances', 'true');

      updateLinks(target, params.toString());
    });
  });

  // For date ranges (tax summary)
  const taxFrom = document.querySelector('.report-filter-input-from[data-target="tax-summary"]');
  const taxTo = document.querySelector('.report-filter-input-to[data-target="tax-summary"]');
  
  const updateTaxLinks = () => {
    if(!taxFrom || !taxTo) return;
    let params = new URLSearchParams();
    params.append('from_date', taxFrom.value);
    params.append('to_date', taxTo.value);
    updateLinks('tax-summary', params.toString());
  };

  if(taxFrom) taxFrom.addEventListener('change', updateTaxLinks);
  if(taxTo) taxTo.addEventListener('change', updateTaxLinks);
});
</script>
@endsection
