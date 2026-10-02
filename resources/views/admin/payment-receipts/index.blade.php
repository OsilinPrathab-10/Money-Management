@extends('layouts/layoutMaster')

@section('title', 'Payment Receipts')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/payment-receipts.js'])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
  }
  .datatables-receipts {
    width: 100% !important;
    margin: 0 !important;
  }
  .receipt-stat-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0,0,0,0.06);
  }
  .receipt-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
  }
  .receipt-code {
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.85rem;
    font-weight: 700;
    color: #0f172a;
  }
  .time-badge {
    background-color: #f1f5f9;
    color: #334155;
    border: 1px solid #e2e8f0;
    font-family: 'SFMono-Regular', Menlo, Monaco, Consolas, monospace;
    font-size: 0.72rem;
    padding: 2px 7px;
    border-radius: 4px;
    letter-spacing: 0.2px;
  }
  .receipt-pill {
    padding: 0.32rem 0.65rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 6px;
  }
</style>
@endsection

@section('content')
@php
  $filterQuery = request()->except(['page', 'module', 'type']);
@endphp
<div class="row mb-4">
  <div class="col-12">
    <h4 class="mb-1 text-primary"><i class="ri-receipt-line me-2"></i>Payment Receipts</h4>
    <p class="text-muted mb-0">Print collection receipts for Loan EMI, Chit installments, and Fixed Deposit payments.</p>
  </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" role="tablist">
  <li class="nav-item">
    <a class="nav-link {{ $module === 'all' ? 'active' : '' }}" href="{{ route('all-payment-receipts', $filterQuery) }}">
      <i class="ri-list-check-2 me-1"></i> All
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'loan' ? 'active' : '' }}" href="{{ route('loan-payment-receipts', $filterQuery) }}">
      <i class="ri-bank-line me-1"></i> Loan
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'chit' ? 'active' : '' }}" href="{{ route('chit-payment-receipts', $filterQuery) }}">
      <i class="ri-group-line me-1"></i> Chit
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $module === 'fd' ? 'active' : '' }}" href="{{ route('fd-payment-receipts', $filterQuery) }}">
      <i class="ri-safe-2-line me-1"></i> Fixed Deposit
    </a>
  </li>
</ul>

<div class="row g-6 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="ri-file-list-3-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['total_receipts']) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Total Receipts</p>
        <p class="mb-0 small text-muted">{{ $module === 'all' ? 'Loan, Chit and FD receipts' : ('All-time ' . $module . ' receipts') }}</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-success">
              <i class="ri-money-rupee-circle-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['total_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Total Collected</p>
        <p class="mb-0 small text-muted">Lifetime collection amount</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-info">
              <i class="ri-calendar-event-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['month_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Monthly Collection</p>
        <p class="mb-0 small text-muted">Current month performance</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-warning">
              <i class="ri-time-line ri-24px"></i>
            </span>
          </div>
          <h4 class="ms-1 mb-0">₹{{ number_format($stats['today_collected'], 2) }}</h4>
        </div>
        <p class="mb-1 fw-medium text-heading">Today's Collection</p>
        <p class="mb-0 small text-muted">Daily collection summary</p>
      </div>
    </div>
  </div>
</div>

@php
  $selectedType = request('type', $module === 'all' ? '' : $module);
@endphp
<div class="card">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-4">{{ $moduleTitle }}</h5>
    <form method="GET" action="{{ url()->current() }}" class="row g-3 align-items-end">
      <div class="col-12 col-sm-6 col-xl-2">
        <label for="receiptType" class="form-label mb-1">Type</label>
        <select id="receiptType"
                name="type"
                class="form-select form-select-sm"
                data-all-url="{{ route('all-payment-receipts') }}"
                data-loan-url="{{ route('loan-payment-receipts') }}"
                data-chit-url="{{ route('chit-payment-receipts') }}"
                data-fd-url="{{ route('fd-payment-receipts') }}"
                onchange="var key=this.value||'all'; this.form.action=this.dataset[key+'Url']||this.form.action; this.form.requestSubmit();">
          <option value="" @selected($selectedType === '' || $selectedType === 'all')>All Types</option>
          <option value="loan" @selected($selectedType === 'loan')>Loan</option>
          <option value="chit" @selected($selectedType === 'chit')>Chit</option>
          <option value="fd" @selected($selectedType === 'fd')>Fixed Deposit</option>
        </select>
      </div>
      <div class="col-12 col-sm-6 col-xl-3">
        <label for="clientName" class="form-label mb-1">Client Name</label>
        <input type="text"
               id="clientName"
               name="client_name"
               class="form-control form-control-sm"
               value="{{ request('client_name') }}"
               placeholder="Search client name">
      </div>
      <div class="col-12 col-sm-6 col-xl-2">
        <label for="receiptZone" class="form-label mb-1">Zone</label>
        <select id="receiptZone" name="location_id" class="form-select form-select-sm" onchange="this.form.requestSubmit()">
          <option value="">All Zones</option>
          @foreach(($locations ?? []) as $location)
            <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-xl-3">
        @include('partials.date-range-filter', [
          'fromId' => 'fromDate',
          'toId' => 'toDate',
          'presetId' => 'datePreset',
          'fromName' => 'from_date',
          'toName' => 'to_date',
          'presetName' => 'date_preset',
          'fromValue' => request('from_date'),
          'toValue' => request('to_date'),
          'presetValue' => request('date_preset', 'all'),
          'autoSubmit' => true,
        ])
      </div>
      <div class="col-12 col-sm-6 col-xl-2 d-flex gap-2">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="ri-search-line me-1"></i> Filter
        </button>
        <a href="{{ url()->current() }}" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-receipts table table-hover text-nowrap"
           id="receiptsTable"
           data-module="{{ $module }}"
           data-ajax-url="{{ route('payment-receipts.data', $module) }}"
           data-print-base="{{ url('payment-receipts') }}">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Type</th>
          <th>Receipt No.</th>
          <th>Client Name</th>
          <th>Zone</th>
          <th>{{ $accountHeading }}</th>
          <th>Amount</th>
          <th>Payment Method</th>
          <th>Payment Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse(($rows ?? []) as $row)
          @php
            $typeKey = strtolower((string) ($row['module'] ?? 'loan'));
            $typeClass = $typeKey === 'chit' ? 'info' : ($typeKey === 'fd' ? 'warning' : 'primary');
            $printModule = strtolower((string) ($row['module'] ?? $module));
            $printUrl = $row['receipt_print_url'] ?? null;
            if (!$printUrl && !empty($row['id']) && in_array($printModule, ['loan', 'chit', 'fd'], true)) {
                $printParams = ['module' => $printModule, 'id' => $row['id']];
                if (!empty($row['bulk_key'])) {
                    $printParams['bulk'] = $row['bulk_key'];
                } elseif (!empty($row['is_bulk'])) {
                    $printParams['bulk'] = 1;
                }
                $printUrl = route('payment-receipts.print', $printParams, false);
            }
            $bulkParam = !empty($row['bulk_key']) ? $row['bulk_key'] : (!empty($row['is_bulk']) ? '1' : '');
          @endphp
          <tr>
            <td><span class="text-muted fw-medium">{{ $row['sno'] }}</span></td>
            <td><span class="badge bg-label-{{ $typeClass }} receipt-pill">{{ $row['module_label'] ?? ucfirst($typeKey) }}</span></td>
            <td>
              <span class="receipt-code">{{ $row['receipt_number'] }}</span>
              @if(!empty($row['is_bulk']))
                @php
                  $splitLabel = ($row['module'] ?? '') === 'chit' ? 'Inst' : 'EMI';
                @endphp
                <button type="button"
                        class="btn btn-icon btn-text-info btn-sm rounded-pill view-bulk-emis ms-1"
                        data-splits="{{ json_encode($row['emi_splits'] ?? []) }}"
                        data-label="{{ $splitLabel }}"
                        data-title="{{ $row['emi_split'] ?? ($splitLabel . ' details') }}"
                        title="View {{ $splitLabel }}s">
                  <i class="icon-base ri ri-eye-line icon-20px"></i>
                </button>
              @elseif(!empty($row['emi_split']))
                <span class="badge bg-label-secondary ms-1">{{ $row['emi_split'] }}</span>
              @endif
            </td>
            <td><span class="fw-medium text-heading">{{ $row['client_name'] ?? 'N/A' }}</span></td>
            <td><span class="badge bg-label-secondary"><i class="ri-map-pin-line me-1" style="font-size: 11px;"></i>{{ $row['zone'] ?? 'N/A' }}</span></td>
            <td><span class="fw-medium font-monospace text-muted">{{ $row['application_number'] ?? 'N/A' }}</span></td>
            <td>
              <span class="fw-bold text-success" style="font-size: 0.95rem;">{{ $row['paid_amount_formatted'] ?? ('₹' . number_format((float) ($row['paid_amount'] ?? 0), 2)) }}</span>
            </td>
            <td>
              <span class="badge bg-label-secondary"><i class="ri-wallet-3-line me-1" style="font-size: 11px;"></i>{{ $row['payment_method'] ?? 'N/A' }}</span>
            </td>
            <td>
              <div>
                <div class="fw-semibold text-heading d-flex align-items-center gap-1">
                  <i class="ri-calendar-line text-muted" style="font-size: 14px;"></i>
                  <span>{{ $row['paid_date'] ?? 'N/A' }}</span>
                </div>
                @if(!empty($row['paid_time']) && $row['paid_time'] !== '12:00 AM')
                  <div class="small d-flex align-items-center gap-1 mt-1">
                    <i class="ri-time-line text-primary" style="font-size: 13px;"></i>
                    <span class="time-badge">{{ $row['paid_time'] }} IST</span>
                  </div>
                @elseif(!empty($row['paid_time']))
                  <div class="small d-flex align-items-center gap-1 mt-1">
                    <i class="ri-time-line text-muted" style="font-size: 13px;"></i>
                    <span class="time-badge">{{ $row['paid_time'] }} IST</span>
                  </div>
                @endif
              </div>
            </td>
            <td>
              <div class="d-flex align-items-center gap-1">
                @if($printUrl)
                  <button type="button"
                          class="btn btn-icon btn-text-primary btn-sm rounded-pill view-receipt-preview"
                          data-url="{{ $printUrl }}"
                          data-title="{{ $row['receipt_number'] }} - {{ $row['client_name'] ?? 'Receipt' }}"
                          title="Quick Preview Receipt">
                    <i class="icon-base ri ri-eye-line icon-20px"></i>
                  </button>
                  <a href="{{ $printUrl }}"
                     target="_blank"
                     rel="noopener"
                     class="btn btn-icon btn-text-secondary btn-sm rounded-pill print-receipt"
                     data-id="{{ $row['id'] }}"
                     data-module="{{ $printModule }}"
                     data-bulk="{{ $bulkParam }}"
                     title="Print / Open in New Tab">
                    <i class="icon-base ri ri-printer-line icon-20px"></i>
                  </a>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr class="receipts-empty-row">
            <td colspan="10" class="text-center py-5 text-muted">No payment receipts found for this period.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if(method_exists($rows, 'hasPages'))
    <div class="card-footer d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-2">
        <span class="text-muted small">
          Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} receipts
        </span>
        <form method="GET" action="{{ url()->current() }}" class="d-flex align-items-center gap-2">
          @foreach(request()->except(['page', 'per_page']) as $name => $value)
            @if(is_array($value))
              @continue
            @endif
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
          @endforeach
          <label class="small text-muted mb-0" for="perPage">Per page</label>
          <select id="perPage" name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.requestSubmit()">
            @foreach([10, 25, 50, 100] as $size)
              <option value="{{ $size }}" @selected((int) request('per_page', 25) === $size)>{{ $size }}</option>
            @endforeach
          </select>
        </form>
      </div>
      <div>
        {{ $rows->onEachSide(1)->links() }}
      </div>
    </div>
  @endif
</div>

<div class="modal fade" id="bulkEmiModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkEmiModalTitle">EMI details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th id="bulkEmiModalLabel">EMI</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody id="bulkEmiModalBody"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Receipt Quick Preview Modal -->
<div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" style="max-width: 860px;">
    <div class="modal-content shadow-lg border-0 overflow-hidden">
      <div class="modal-header border-bottom py-3 bg-light">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar avatar-sm bg-label-primary rounded p-1">
            <i class="ri-receipt-line ri-20px"></i>
          </div>
          <div>
            <h5 class="modal-title mb-0" id="receiptPreviewModalTitle">Receipt Preview</h5>
            <small class="text-muted">Digital Payment Receipt</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 position-relative" style="min-height: 520px; background: #f8fafc;">
        <div id="receiptPreviewSpinner" class="d-flex flex-column align-items-center justify-content-center py-5" style="min-height: 500px;">
          <div class="spinner-border text-primary mb-2" role="status"></div>
          <span class="text-muted small">Loading payment receipt...</span>
        </div>
        <iframe id="receiptPreviewFrame"
                style="width: 100%; height: 560px; border: none; display: none;"
                onload="if(this.src && this.src !== 'about:blank'){ document.getElementById('receiptPreviewSpinner').style.display='none'; this.style.display='block'; }">
        </iframe>
      </div>
      <div class="modal-footer border-top py-2 d-flex justify-content-between">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
          Close
        </button>
        <div class="d-flex gap-2">
          <a href="#" target="_blank" rel="noopener" id="modalOpenReceiptBtn" class="btn btn-sm btn-outline-primary">
            <i class="ri-external-link-line me-1"></i> Open New Tab
          </a>
          <button type="button" class="btn btn-sm btn-primary" id="modalPrintReceiptBtn">
            <i class="ri-printer-line me-1"></i> Print Receipt
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
