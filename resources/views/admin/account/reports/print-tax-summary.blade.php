@extends('layouts/layoutMaster')

@section('title', __('Tax Summary Report'))

@section('page-style')
<style>
  .report-header-card {
    background: linear-gradient(145deg, #064e3b, #022c22);
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
    background: rgba(var(--bs-success-rgb), 0.05);
    color: var(--bs-heading-color);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 1rem;
    border-bottom: 2px solid rgba(var(--bs-success-rgb), 0.2);
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
    background-color: rgba(var(--bs-success-rgb), 0.02);
  }
  .tax-card {
    border-radius: 1rem;
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    height: 100%;
    overflow: hidden;
  }
  .tax-card-header {
    background: rgba(var(--bs-success-rgb), 0.08);
    padding: 1.25rem;
    border-bottom: 1px solid var(--bs-border-color);
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--bs-heading-color);
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }
  .tax-card-footer {
    background: rgba(var(--bs-success-rgb), 0.04);
    padding: 1.25rem;
    border-top: 1px solid var(--bs-border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 700;
  }
  .net-liability-card {
    background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.1), rgba(var(--bs-primary-rgb), 0.02));
    border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
    border-radius: 1rem;
    padding: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .net-liability-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--bs-primary);
    margin: 0;
  }
  .net-liability-amount {
    font-size: 2rem;
    font-weight: 800;
    color: var(--bs-primary);
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
          <div class="bg-success rounded p-2 text-white">
            <i class="ri-percent-line fs-4"></i>
          </div>
          <h4 class="mb-0 text-white fw-bold">{{ __('Tax Summary Report') }}</h4>
        </div>
        <p class="text-white-50 mb-0 ms-1">{{ __('Overview of collected vs paid taxes for the specified period.') }}</p>
      </div>
      <div class="d-flex flex-column align-items-md-end gap-3">
        <form method="GET" action="{{ route('account.reports.tax-summary.print') }}" class="m-0">
          <div class="report-date-badge d-inline-flex align-items-center gap-2 p-1 pe-3">
            <i class="ri-calendar-event-line ms-2"></i>
            <span class="small">{{ __('Period') }}:</span>
            <input type="date" name="from_date" class="form-control form-control-sm bg-transparent text-white border-0 shadow-none p-0 m-0" style="color-scheme: dark; width: auto; font-weight: 600; cursor: pointer; outline: none;" value="{{ $filters['from_date'] ?? '' }}" onchange="this.form.submit()">
            <span class="small text-white-50 mx-1">{{ __('to') }}</span>
            <input type="date" name="to_date" class="form-control form-control-sm bg-transparent text-white border-0 shadow-none p-0 m-0" style="color-scheme: dark; width: auto; font-weight: 600; cursor: pointer; outline: none;" value="{{ $filters['to_date'] ?? '' }}" onchange="this.form.submit()">
          </div>
        </form>
        <div class="d-flex align-items-center gap-2">
          @include('admin.account.reports._export-toolbar', [
            'exportRoute' => 'account.reports.tax-summary.export',
            'query' => ['from_date' => $filters['from_date'] ?? '', 'to_date' => $filters['to_date'] ?? ''],
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
    @php
      $collectedItems = $data['tax_collected']['items'] ?? collect();
      $paidItems = $data['tax_paid']['items'] ?? collect();
    @endphp

    <div class="row g-4 mb-4">
      <!-- Tax Collected Column -->
      <div class="col-lg-6">
        <div class="tax-card d-flex flex-column">
          <div class="tax-card-header">
            <div class="avatar avatar-sm bg-success rounded-circle d-flex align-items-center justify-content-center text-white">
              <i class="ri-arrow-down-line"></i>
            </div>
            {{ __('Tax Collected (Sales)') }}
          </div>
          
          <div class="card-body p-0 flex-grow-1">
            <div class="table-responsive">
              <table class="table premium-table mb-0 w-100">
                <thead>
                  <tr>
                    <th class="ps-4">{{ __('Tax Type') }}</th>
                    <th class="text-end pe-4">{{ __('Amount') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($collectedItems as $row)
                    <tr>
                      <td class="ps-4 fw-medium text-heading">{{ is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—') }}</td>
                      <td class="text-end pe-4 text-muted">₹{{ number_format((float) (is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0)), 2) }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="2" class="text-center p-4">
                        <div class="text-muted">
                          <i class="ri-file-list-3-line fs-3 mb-2 d-block text-opacity-50"></i>
                          <small>{{ __('No tax collected in this period.') }}</small>
                        </div>
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="tax-card-footer">
            <span class="text-muted text-uppercase small">{{ __('Total Collected') }}</span>
            <span class="text-success fs-5">₹{{ number_format((float) ($data['tax_collected']['total'] ?? 0), 2) }}</span>
          </div>
        </div>
      </div>

      <!-- Tax Paid Column -->
      <div class="col-lg-6">
        <div class="tax-card d-flex flex-column">
          <div class="tax-card-header" style="background: rgba(var(--bs-warning-rgb), 0.08);">
            <div class="avatar avatar-sm bg-warning rounded-circle d-flex align-items-center justify-content-center text-white">
              <i class="ri-arrow-up-line"></i>
            </div>
            {{ __('Tax Paid (Purchases)') }}
          </div>
          
          <div class="card-body p-0 flex-grow-1">
            <div class="table-responsive">
              <table class="table premium-table mb-0 w-100">
                <thead style="background: rgba(var(--bs-warning-rgb), 0.05);">
                  <tr>
                    <th class="ps-4" style="border-bottom: 2px solid rgba(var(--bs-warning-rgb), 0.2);">{{ __('Tax Type') }}</th>
                    <th class="text-end pe-4" style="border-bottom: 2px solid rgba(var(--bs-warning-rgb), 0.2);">{{ __('Amount') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse ($paidItems as $row)
                    <tr>
                      <td class="ps-4 fw-medium text-heading">{{ is_array($row) ? ($row['tax_name'] ?? '—') : ($row->tax_name ?? '—') }}</td>
                      <td class="text-end pe-4 text-muted">₹{{ number_format((float) (is_array($row) ? ($row['amount'] ?? 0) : ($row->amount ?? 0)), 2) }}</td>
                    </tr>
                  @empty
                    <tr>
                      <td colspan="2" class="text-center p-4">
                        <div class="text-muted">
                          <i class="ri-file-list-3-line fs-3 mb-2 d-block text-opacity-50"></i>
                          <small>{{ __('No tax paid in this period.') }}</small>
                        </div>
                      </td>
                    </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="tax-card-footer" style="background: rgba(var(--bs-warning-rgb), 0.04);">
            <span class="text-muted text-uppercase small">{{ __('Total Paid') }}</span>
            <span class="text-warning fs-5">₹{{ number_format((float) ($data['tax_paid']['total'] ?? 0), 2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Net Liability Summary -->
    <div class="net-liability-card shadow-sm mt-2">
      <div class="d-flex align-items-center gap-3">
        <div class="avatar avatar-md bg-primary rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm">
          <i class="ri-scales-3-line fs-4"></i>
        </div>
        <div>
          <p class="text-muted small text-uppercase fw-bold mb-0">{{ __('Calculated Result') }}</p>
          <h4 class="net-liability-title">{{ __('Net Tax Liability') }}</h4>
        </div>
      </div>
      <div class="text-end">
        <div class="net-liability-amount">₹{{ number_format((float) ($data['net_tax_liability'] ?? 0), 2) }}</div>
        @if(($data['net_tax_liability'] ?? 0) > 0)
          <small class="text-danger fw-medium"><i class="ri-arrow-right-up-line"></i> {{ __('Amount Payable') }}</small>
        @elseif(($data['net_tax_liability'] ?? 0) < 0)
          <small class="text-success fw-medium"><i class="ri-arrow-right-down-line"></i> {{ __('Amount Refundable') }}</small>
        @else
          <small class="text-muted fw-medium">{{ __('Balanced') }}</small>
        @endif
      </div>
    </div>
  @endif
</div>
@endsection
