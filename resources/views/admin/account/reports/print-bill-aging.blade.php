@extends('layouts/layoutMaster')

@section('title', __('Bill Aging Report'))

@section('page-style')
<style>
  .report-header-card {
    background: linear-gradient(145deg, #2a1111, #1e0909);
    color: white;
    border: none;
    border-radius: 1rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  }
  .report-date-badge {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-weight: 500;
  }
  .premium-table {
    border-collapse: separate;
    border-spacing: 0;
  }
  .premium-table thead th {
    background: rgba(var(--bs-danger-rgb), 0.05);
    color: var(--bs-heading-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid rgba(var(--bs-danger-rgb), 0.2);
  }
  .premium-table tbody td {
    padding: 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--bs-border-color);
  }
  .premium-table tbody tr:last-child td {
    border-bottom: none;
  }
  .premium-table tbody tr {
    transition: background-color 0.2s ease;
  }
  .premium-table tbody tr:hover {
    background-color: rgba(var(--bs-danger-rgb), 0.02);
  }
  .summary-card {
    border-radius: 1rem;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease;
  }
  .summary-card:hover {
    transform: translateY(-2px);
  }
  .summary-amount {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--bs-heading-color);
  }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  
  <!-- Header Section -->
  <div class="card report-header-card mb-4">
    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
      <div>
        <div class="d-flex align-items-center gap-3 mb-2">
          <div class="bg-danger rounded p-2 text-white">
            <i class="ri-bill-line fs-4"></i>
          </div>
          <h4 class="mb-0 text-white fw-bold">{{ __('Bill Aging Report') }}</h4>
        </div>
        <p class="text-white-50 mb-0 ms-1">{{ __('Comprehensive breakdown of outstanding purchase bills by aging buckets.') }}</p>
      </div>
      <div class="d-flex flex-column align-items-md-end gap-3">
        <form method="GET" action="{{ route('account.reports.bill-aging.print') }}" class="m-0">
          <div class="report-date-badge d-inline-flex align-items-center gap-2 p-1 pe-3">
            <i class="ri-calendar-check-line ms-2"></i>
            <span class="small">{{ __('As of') }}:</span>
            <input type="date" name="as_of_date" class="form-control form-control-sm bg-transparent text-white border-0 shadow-none p-0 m-0" style="color-scheme: dark; width: auto; font-weight: 600; cursor: pointer; outline: none;" value="{{ $filters['as_of_date'] ?? date('Y-m-d') }}" onchange="this.form.submit()">
          </div>
        </form>
        <div class="d-flex align-items-center gap-2">
          @include('admin.account.reports._export-toolbar', [
            'exportRoute' => 'account.reports.bill-aging.export',
            'query' => ['as_of_date' => $filters['as_of_date'] ?? date('Y-m-d')],
          ])
          <a href="{{ route('account.reports.index') }}" class="btn btn-outline-light rounded-pill px-3">
            <i class="ri-arrow-left-line me-1"></i> {{ __('Back to Hub') }}
          </a>
        </div>
      </div>
    </div>
  </div>

  @if (!empty($data['error']))
    <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
      <i class="ri-error-warning-fill fs-4 me-2"></i>
      <div>{{ $data['error'] }}</div>
    </div>
  @else
    @php $a = $data['aging_summary'] ?? []; @endphp

    <!-- Summary Overview -->
    <h5 class="fw-bold mb-3"><i class="ri-pie-chart-2-line text-danger me-2"></i>{{ __('Aging Summary') }}</h5>
    <div class="row g-3 mb-5">
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100">
          <div class="card-body p-3 text-center">
            <div class="text-muted small mb-1">{{ __('Current') }}</div>
            <div class="summary-amount text-success">₹{{ number_format((float) ($a['current'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100">
          <div class="card-body p-3 text-center">
            <div class="text-muted small mb-1">1–30 {{ __('Days') }}</div>
            <div class="summary-amount text-info">₹{{ number_format((float) ($a['1_30_days'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100">
          <div class="card-body p-3 text-center">
            <div class="text-muted small mb-1">31–60 {{ __('Days') }}</div>
            <div class="summary-amount text-warning">₹{{ number_format((float) ($a['31_60_days'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100">
          <div class="card-body p-3 text-center">
            <div class="text-muted small mb-1">61–90 {{ __('Days') }}</div>
            <div class="summary-amount text-warning text-opacity-75">₹{{ number_format((float) ($a['61_90_days'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100">
          <div class="card-body p-3 text-center">
            <div class="text-muted small mb-1">90+ {{ __('Days') }}</div>
            <div class="summary-amount text-danger">₹{{ number_format((float) ($a['over_90_days'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-4 col-xl-2">
        <div class="card summary-card h-100 bg-danger-subtle border-danger">
          <div class="card-body p-3 text-center">
            <div class="text-danger fw-bold small mb-1">{{ __('Total') }}</div>
            <div class="summary-amount text-danger fw-bolder">₹{{ number_format((float) ($a['total'] ?? 0), 2) }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Detailed Vendor Breakdown -->
    <div class="d-flex align-items-center mb-3">
      <h5 class="mb-0 fw-bold"><i class="ri-store-2-line text-danger me-2"></i>{{ __('Outstanding by Vendor') }}</h5>
    </div>
    
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="table-responsive">
        <table class="table premium-table mb-0 w-100">
          <thead>
            <tr>
              <th class="ps-4">{{ __('Vendor Name') }}</th>
              <th class="text-end">{{ __('Current') }}</th>
              <th class="text-end">1–30 {{ __('Days') }}</th>
              <th class="text-end">31–60 {{ __('Days') }}</th>
              <th class="text-end">61–90 {{ __('Days') }}</th>
              <th class="text-end">90+ {{ __('Days') }}</th>
              <th class="text-end pe-4 text-danger">{{ __('Total') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($data['vendors'] ?? [] as $row)
              <tr>
                <td class="ps-4 fw-medium text-heading">
                  <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar-sm bg-label-danger rounded-circle d-flex align-items-center justify-content-center fw-bold">
                      {{ strtoupper(substr($row['vendor_name'] ?? 'V', 0, 1)) }}
                    </div>
                    <span>{{ $row['vendor_name'] ?? '—' }}</span>
                  </div>
                </td>
                <td class="text-end text-muted">₹{{ number_format((float) ($row['current'] ?? 0), 2) }}</td>
                <td class="text-end text-muted">₹{{ number_format((float) ($row['1_30_days'] ?? 0), 2) }}</td>
                <td class="text-end text-muted">₹{{ number_format((float) ($row['31_60_days'] ?? 0), 2) }}</td>
                <td class="text-end text-muted">₹{{ number_format((float) ($row['61_90_days'] ?? 0), 2) }}</td>
                <td class="text-end text-danger">₹{{ number_format((float) ($row['over_90_days'] ?? 0), 2) }}</td>
                <td class="text-end pe-4 fw-bold text-heading">₹{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center p-5">
                  <div class="d-flex flex-column align-items-center text-muted">
                    <i class="ri-shield-check-line fs-1 text-success mb-2"></i>
                    <p class="mb-0 fs-5">{{ __('All Bills Paid!') }}</p>
                    <small>{{ __('There are no open bills in this scope.') }}</small>
                  </div>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>
@endsection
