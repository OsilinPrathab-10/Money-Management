@extends('layouts/layoutMaster')

@section('title', 'API Usage')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h4 class="mb-1">API Usage</h4>
    <p class="text-muted mb-0">Verification API hits and SMS OTP logs (login, reset MPIN, password)</p>
  </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" role="tablist">
  <li class="nav-item">
    <a class="nav-link {{ $tab === 'sms' ? 'active' : '' }}" href="{{ route('api-wallet.index', ['tab' => 'sms']) }}">
      <i class="ri-message-3-line me-1"></i> SMS OTP Logs
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ $tab === 'verification' ? 'active' : '' }}" href="{{ route('api-wallet.index', ['tab' => 'verification']) }}">
      <i class="ri-shield-check-line me-1"></i> Verification Hits
    </a>
  </li>
</ul>

@if($tab === 'sms')
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Total OTP Count</p>
          <h3 class="mb-0">{{ $otpCounts['total'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Login OTP</p>
          <h3 class="mb-0 text-primary">{{ $otpCounts['first_login'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Reset MPIN OTP</p>
          <h3 class="mb-0 text-info">{{ $otpCounts['forgot_mpin'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Password OTP</p>
          <h3 class="mb-0 text-success">{{ $otpCounts['forgot_password'] }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header border-bottom">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h5 class="mb-0">SMS OTP Logs</h5>
          <small class="text-muted">Login, reset MPIN and forgot password OTP SMS</small>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="badge bg-label-success">Sent: {{ $otpCounts['sent'] }}</span>
          <span class="badge bg-label-danger">Failed: {{ $otpCounts['failed'] }}</span>
          <span class="badge bg-label-secondary">Test: {{ $otpCounts['test'] }}</span>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'sms', 'format' => 'csv'])) }}">
                  <i class="ri-file-text-line me-2"></i>CSV
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'sms', 'format' => 'excel'])) }}">
                  <i class="ri-file-excel-2-line me-2"></i>Excel
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'sms', 'format' => 'pdf'])) }}">
                  <i class="ri-file-pdf-line me-2"></i>PDF
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle">
      <form method="GET" action="{{ route('api-wallet.index') }}" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="sms">

        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-shield-keyhole-line me-1 text-primary"></i>OTP Type
          </label>
          <select name="purpose" class="form-select">
            <option value="all" @selected($purpose === 'all')>All OTP Types</option>
            <option value="first_login" @selected(in_array($purpose, ['first_login', 'login']))>Login OTP</option>
            <option value="forgot_mpin" @selected(in_array($purpose, ['forgot_mpin', 'reset_mpin', 'mpin']))>Reset MPIN OTP</option>
            <option value="forgot_password" @selected(in_array($purpose, ['forgot_password', 'reset_password', 'password']))>Password OTP</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-checkbox-circle-line me-1 text-success"></i>Status
          </label>
          <select name="otp_status" class="form-select">
            <option value="all" @selected($otpStatus === 'all')>All Statuses</option>
            <option value="sent" @selected($otpStatus === 'sent')>Sent</option>
            <option value="failed" @selected($otpStatus === 'failed')>Failed</option>
            <option value="test" @selected($otpStatus === 'test')>Test</option>
          </select>
        </div>

        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-phone-line me-1 text-info"></i>Mobile Number
          </label>
          <input type="text" name="mobile" value="{{ $mobile }}" class="form-control" placeholder="10-digit mobile" maxlength="15">
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-calendar-line me-1 text-warning"></i>Date Preset
          </label>
          <select name="date_preset" class="form-select date-preset-toggle" data-container="#smsCustomDateContainer">
            <option value="all" @selected(!request('date_preset') || request('date_preset') === 'all')>All Time</option>
            <option value="today" @selected(request('date_preset') === 'today')>Today</option>
            <option value="this_week" @selected(request('date_preset') === 'this_week' || request('date_preset') === 'week')>This Week</option>
            <option value="this_month" @selected(request('date_preset') === 'this_month' || request('date_preset') === 'month')>This Month</option>
            <option value="this_year" @selected(request('date_preset') === 'this_year' || request('date_preset') === 'year')>This Year</option>
            <option value="custom" @selected(request('date_preset') === 'custom' || (!request('date_preset') && ($from || $to)))>Custom Range</option>
          </select>
        </div>

        <div class="col-xl-2 col-md-4 col-sm-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">
            <i class="ri-filter-3-line me-1"></i>Filter
          </button>
          <a href="{{ route('api-wallet.index', ['tab' => 'sms']) }}" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="ri-refresh-line"></i>
          </a>
        </div>

        <!-- Custom Date Range Sub-Row -->
        <div id="smsCustomDateContainer" class="col-12 mt-2 {{ (request('date_preset') === 'custom' || (!request('date_preset') && ($from || $to))) ? '' : 'd-none' }}">
          <div class="p-3 bg-white border rounded-2 shadow-xs">
            <div class="row g-3 align-items-center">
              <div class="col-auto">
                <span class="fw-semibold text-dark small"><i class="ri-calendar-event-line me-1 text-primary"></i>Custom Date Range:</span>
              </div>
              <div class="col-md-3 col-sm-5">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
              </div>
              <div class="col-md-3 col-sm-5">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Type</th>
            <th>Mobile</th>
            <th>Status</th>
            <th>Client</th>
            <th>Message</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($logs as $i => $log)
            <tr>
              <td>{{ $logs->firstItem() + $i }}</td>
              <td>
                @php
                  $pKey = strtolower((string) $log->purpose);
                  $badge = match(true) {
                    in_array($pKey, ['first_login', 'login']) => 'primary',
                    in_array($pKey, ['forgot_mpin', 'reset_mpin', 'mpin']) => 'info',
                    in_array($pKey, ['forgot_password', 'reset_password', 'password']) => 'success',
                    default => 'secondary',
                  };
                @endphp
                <span class="badge bg-label-{{ $badge }}">{{ $log->purposeLabel() }}</span>
              </td>
              <td><span class="fw-medium">{{ $log->mobile }}</span></td>
              <td>
                @if($log->status === 'sent')
                  <span class="badge bg-label-success">Sent</span>
                @elseif($log->status === 'failed')
                  <span class="badge bg-label-danger">Failed</span>
                @else
                  <span class="badge bg-label-secondary">Test</span>
                @endif
              </td>
              <td>{{ $log->client?->client_name ?? $log->user?->name ?? '—' }}</td>
              <td class="text-truncate" style="max-width: 260px;" title="{{ $log->provider_message }}">{{ $log->provider_message ?? '—' }}</td>
              <td>{{ $log->created_at?->format('d-m-Y h:i A') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-5">No SMS OTP logs for the selected filters.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($logs instanceof \Illuminate\Pagination\AbstractPaginator && $logs->hasPages())
      <div class="card-footer">
        {{ $logs->links() }}
      </div>
    @endif
  </div>
@else
  <div class="row g-6 mb-6">
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Total API Hits</p>
          <h3 class="mb-0">{{ $counts['total'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Aadhaar Hits</p>
          <h3 class="mb-0 text-primary">{{ $counts['aadhaar'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">PAN Hits</p>
          <h3 class="mb-0 text-info">{{ $counts['pan'] }}</h3>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">Bank Hits</p>
          <h3 class="mb-0 text-success">{{ $counts['bank'] }}</h3>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header border-bottom">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <h5 class="mb-0">Verification Hits</h5>
          <small class="text-muted">Aadhaar, PAN and Bank API calls</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <span class="badge bg-label-success">Success: {{ $counts['success'] }}</span>
          <span class="badge bg-label-danger">Failed: {{ $counts['failed'] }}</span>
          <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
              <i class="ri-download-line me-1"></i> Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'verification', 'format' => 'csv'])) }}">
                  <i class="ri-file-text-line me-2"></i>CSV
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'verification', 'format' => 'excel'])) }}">
                  <i class="ri-file-excel-2-line me-2"></i>Excel
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{{ route('api-wallet.export', array_merge(request()->query(), ['tab' => 'verification', 'format' => 'pdf'])) }}">
                  <i class="ri-file-pdf-line me-2"></i>PDF
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="card-body border-bottom bg-light-subtle">
      <form method="GET" action="{{ route('api-wallet.index') }}" class="row g-3 align-items-end">
        <input type="hidden" name="tab" value="verification">

        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-shield-check-line me-1 text-primary"></i>Service
          </label>
          <select name="service" class="form-select">
            <option value="all" @selected($service === 'all')>All Services</option>
            <option value="aadhaar" @selected($service === 'aadhaar')>Aadhaar</option>
            <option value="pan" @selected($service === 'pan')>PAN</option>
            <option value="bank" @selected($service === 'bank')>Bank</option>
          </select>
        </div>

        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-checkbox-circle-line me-1 text-success"></i>Status
          </label>
          <select name="status" class="form-select">
            <option value="all" @selected($status === 'all')>All Statuses</option>
            <option value="success" @selected($status === 'success')>Success</option>
            <option value="failed" @selected($status === 'failed')>Failed</option>
          </select>
        </div>

        <div class="col-xl-3 col-md-4 col-sm-6">
          <label class="form-label fw-semibold text-dark mb-1">
            <i class="ri-calendar-line me-1 text-warning"></i>Date Preset
          </label>
          <select name="date_preset" class="form-select date-preset-toggle" data-container="#verifCustomDateContainer">
            <option value="all" @selected(!request('date_preset') || request('date_preset') === 'all')>All Time</option>
            <option value="today" @selected(request('date_preset') === 'today')>Today</option>
            <option value="this_week" @selected(request('date_preset') === 'this_week' || request('date_preset') === 'week')>This Week</option>
            <option value="this_month" @selected(request('date_preset') === 'this_month' || request('date_preset') === 'month')>This Month</option>
            <option value="this_year" @selected(request('date_preset') === 'this_year' || request('date_preset') === 'year')>This Year</option>
            <option value="custom" @selected(request('date_preset') === 'custom' || (!request('date_preset') && ($from || $to)))>Custom Range</option>
          </select>
        </div>

        <div class="col-xl-3 col-md-4 col-sm-6 d-flex gap-2">
          <button type="submit" class="btn btn-primary flex-fill">
            <i class="ri-filter-3-line me-1"></i>Filter
          </button>
          <a href="{{ route('api-wallet.index', ['tab' => 'verification']) }}" class="btn btn-outline-secondary" title="Reset Filters">
            <i class="ri-refresh-line"></i>
          </a>
        </div>

        <!-- Custom Date Range Sub-Row -->
        <div id="verifCustomDateContainer" class="col-12 mt-2 {{ (request('date_preset') === 'custom' || (!request('date_preset') && ($from || $to))) ? '' : 'd-none' }}">
          <div class="p-3 bg-white border rounded-2 shadow-xs">
            <div class="row g-3 align-items-center">
              <div class="col-auto">
                <span class="fw-semibold text-dark small"><i class="ri-calendar-event-line me-1 text-primary"></i>Custom Date Range:</span>
              </div>
              <div class="col-md-3 col-sm-5">
                <label class="form-label small text-muted mb-1">From Date</label>
                <input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm">
              </div>
              <div class="col-md-3 col-sm-5">
                <label class="form-label small text-muted mb-1">To Date</label>
                <input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm">
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Service</th>
            <th>Endpoint</th>
            <th>Status</th>
            <th>HTTP</th>
            <th>Message</th>
            <th>User</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @forelse($hits as $i => $hit)
            <tr>
              <td>{{ $hits->firstItem() + $i }}</td>
              <td>
                @php
                  $badge = match($hit->service) {
                    'aadhaar' => 'primary',
                    'pan' => 'info',
                    'bank' => 'success',
                    default => 'secondary',
                  };
                @endphp
                <span class="badge bg-label-{{ $badge }}">{{ strtoupper($hit->service) }}</span>
              </td>
              <td><code class="small">{{ $hit->endpoint }}</code></td>
              <td>
                @if($hit->success)
                  <span class="badge bg-label-success">Success</span>
                @else
                  <span class="badge bg-label-danger">Failed</span>
                @endif
              </td>
              <td>{{ $hit->http_status ?? '—' }}</td>
              <td class="text-truncate" style="max-width: 260px;">{{ $hit->response_message ?? '—' }}</td>
              <td>{{ $hit->user?->name ?? 'System' }}</td>
              <td>{{ $hit->created_at?->format('d-m-Y h:i A') }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center text-muted py-5">No API hits recorded yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($hits instanceof \Illuminate\Pagination\AbstractPaginator && $hits->hasPages())
      <div class="card-footer">
        {{ $hits->links() }}
      </div>
    @endif
  </div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  function formatDate(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function syncPresetDates(selectEl) {
    const targetId = selectEl.getAttribute('data-container');
    const container = document.querySelector(targetId);
    const form = selectEl.closest('form');
    if (!form) return;

    const fromInput = form.querySelector('input[name="from"]');
    const toInput = form.querySelector('input[name="to"]');
    const val = selectEl.value;

    if (container) {
      if (val === 'custom') {
        container.classList.remove('d-none');
      } else {
        container.classList.add('d-none');
      }
    }

    if (!fromInput || !toInput) return;
    const today = new Date();

    if (val === 'today') {
      const formatted = formatDate(today);
      fromInput.value = formatted;
      toInput.value = formatted;
    } else if (val === 'this_week' || val === 'week') {
      const first = new Date(today);
      const day = today.getDay();
      const diff = today.getDate() - day + (day === 0 ? -6 : 1);
      first.setDate(diff);
      const last = new Date(first);
      last.setDate(first.getDate() + 6);
      fromInput.value = formatDate(first);
      toInput.value = formatDate(last);
    } else if (val === 'this_month' || val === 'month') {
      const first = new Date(today.getFullYear(), today.getMonth(), 1);
      const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      fromInput.value = formatDate(first);
      toInput.value = formatDate(last);
    } else if (val === 'this_year' || val === 'year') {
      const first = new Date(today.getFullYear(), 0, 1);
      const last = new Date(today.getFullYear(), 11, 31);
      fromInput.value = formatDate(first);
      toInput.value = formatDate(last);
    } else if (val === 'all' || val === 'all_time') {
      fromInput.value = '';
      toInput.value = '';
    }
  }

  document.querySelectorAll('.date-preset-toggle').forEach(function(select) {
    select.addEventListener('change', function() {
      syncPresetDates(this);
      this.closest('form').submit();
    });
  });

  document.querySelectorAll('select[name="purpose"], select[name="otp_status"], select[name="service"], select[name="status"]').forEach(function(select) {
    select.addEventListener('change', function() {
      this.closest('form').submit();
    });
  });

  document.querySelectorAll('input[name="from"], input[name="to"]').forEach(function(input) {
    input.addEventListener('change', function() {
      const form = this.closest('form');
      if (!form) return;
      const presetSelect = form.querySelector('.date-preset-toggle');
      if (presetSelect && (this.value || form.querySelector('input[name="from"]').value || form.querySelector('input[name="to"]').value)) {
        presetSelect.value = 'custom';
        const container = document.querySelector(presetSelect.getAttribute('data-container'));
        if (container) {
          container.classList.remove('d-none');
        }
      }
    });
  });
});
</script>
@endsection
