  @extends('layouts/layoutMaster')
@section('title',
  isset($mode) && $mode === 'client' ? 'Client Installments — ' . ($client->client_name ?? '') :
  (isset($mode) && $mode === 'show' ? 'Group Installments — Month ' . $month : 'Chit Installments')
)

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-style')
<style>
  .card-datatable.table-responsive {
    overflow-x: auto !important;
  }
  .datatables-chit-installments {
    width: 100% !important;
    margin: 0 !important;
  }
  .client-installment-details {
    background-color: #f8f9fa;
    padding: 1rem 1.25rem;
  }
  .client-installment-details .inst-section-title {
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.5rem;
  }
  .client-installment-details table {
    margin-bottom: 1rem;
    background: #fff;
  }
  td.details-control {
    cursor: pointer;
    text-align: center;
    vertical-align: middle;
  }
  td.details-control i {
    font-size: 1.25rem;
    color: #7367f0;
    transition: transform 0.2s ease;
  }
  tr.shown td.details-control i {
    transform: rotate(90deg);
  }

  #installmentsTabs {
    border-bottom: none;
  }
  #installmentsTabs .nav-item {
    margin-bottom: -1px;
  }
  #installmentsTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    padding: 1.25rem 1rem;
    font-weight: 500;
    color: #5d596c;
    transition: all 0.3s ease;
    border-radius: 0;
  }
  #installmentsTabs .nav-link:hover {
    color: #7367f0;
    background-color: rgba(115, 103, 240, 0.04);
  }
  #installmentsTabs .nav-link.active {
    color: #7367f0;
    border-bottom-color: #7367f0;
    background-color: transparent;
    font-weight: 600;
  }
  #installmentsTabs .nav-link.active .badge {
    transform: scale(1.05);
  }
  #installmentsTabs .badge {
    transition: all 0.3s ease;
    font-weight: 600;
    padding: 0.25em 0.6em;
  }
  #installmentsTabs .nav-link i {
    font-size: 1.2rem;
    vertical-align: middle;
    transition: transform 0.3s ease;
  }
  #installmentsTabs .nav-link:hover i {
    transform: translateY(-2px);
  }
</style>
@endsection

@section('page-script')
<script>
  window.chitPartialPaymentConfig = @json($partialPaymentConfig ?? ['is_active' => false]);
  window.chitInstallmentsDataUrl = @json(route('chit.installments.data'));
  window.clientInstallmentsBaseUrl = @json(url('/admin/chit/installments/client'));
  window.chitBulkCollectUrl = @json(route('chit.installments.bulk-collect'));

  // Instant EMI count + enable Pay Selected (same as group view).
  (function () {
    function money(v) {
      return '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function syncSummaryForTable(table) {
      if (!table || !table.id) return;
      let count = 0;
      let sum = 0;
      table.querySelectorAll('.chit-bulk-cb[data-is-freq="1"]:checked').forEach(function (cb) {
        var row = cb.closest('tr');
        if (row && row.style.display === 'none') return;
        count += 1;
        sum += parseFloat(cb.getAttribute('data-period-amount') || cb.getAttribute('data-suggested') || '0') || 0;
      });
      var moneyText = money(sum);
      document.querySelectorAll('.chit-inst-bulk-summary[data-table-id="' + table.id + '"]').forEach(function (summary) {
        var c = summary.querySelector('.chit-inst-bulk-count');
        var s = summary.querySelector('.chit-inst-bulk-sum');
        if (c) c.textContent = String(count);
        if (s) s.textContent = moneyText;
        summary.querySelectorAll('.chit-inst-bulk-pay-btn, .chit-inst-bulk-clear-btn').forEach(function (btn) {
          btn.disabled = count === 0;
        });
      });
      document.querySelectorAll('.chit-inst-bulk-pay-btn[data-table-id="' + table.id + '"]').forEach(function (btn) {
        btn.disabled = count === 0;
      });
      // Footer count/sum next to the table (View Installments)
      var card = table.closest('.card');
      if (card) {
        card.querySelectorAll('.chit-inst-bulk-count').forEach(function (el) { el.textContent = String(count); });
        card.querySelectorAll('.chit-inst-bulk-sum').forEach(function (el) { el.textContent = moneyText; });
      }
    }
    document.addEventListener('change', function (e) {
      var t = e.target;
      if (!t || !t.classList) return;
      if (t.classList.contains('chit-bulk-cb') || t.classList.contains('chit-bulk-select-all')) {
        var table = t.closest('table');
        if (!table && t.classList.contains('chit-bulk-select-all')) {
          var tid = t.getAttribute('data-table-id');
          table = tid ? document.getElementById(tid) : null;
        }
        if (table) {
          setTimeout(function () { syncSummaryForTable(table); }, 0);
        }
      }
    });
  })();
</script>
@vite(['resources/assets/custom-js/chit-installments.js'])
@if(session('success') || session('error'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(session('success'))
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'success', title: 'Success', text: @json(session('success')) });
    }
    @endif
    @if(session('error'))
    if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Error', text: @json(session('error')) });
    }
    @endif
});
</script>
@endif
<script>
function applyGroupMonthTableFilters() {
    const table = document.getElementById('chitInstallmentsTable');
    if (!table) return;
    const statusSel = document.querySelector('.group-month-status-filter');
    const periodSel = document.querySelector('.group-month-period-filter');
    const statusVal = statusSel ? statusSel.value : 'all';
    const periodVal = periodSel ? periodSel.value : 'all';

    const parents = table.querySelectorAll('tbody tr.inst-parent-row');
    parents.forEach(function (parent) {
        const st = parent.getAttribute('data-status');
        let statusOk = true;
        if (statusVal === 'unpaid') {
            statusOk = st === 'pending' || st === 'overdue' || st === 'partial';
        } else if (statusVal === 'paid') {
            statusOk = st === 'paid';
        } else if (statusVal === 'partial') {
            statusOk = st === 'partial';
        }

        const instId = parent.getAttribute('data-inst-id');
        const freqRows = instId
            ? Array.from(table.querySelectorAll('tbody tr.inst-freq-row[data-parent-inst="' + instId + '"]'))
            : [];

        if (!statusOk) {
            parent.style.display = 'none';
            freqRows.forEach(function (r) { r.style.display = 'none'; });
            return;
        }

        parent.style.display = '';

        if (periodVal === 'all' || periodVal.indexOf('period-') !== 0) {
            // Month-wise: show every day/week part under this parent so paid updates are visible
            freqRows.forEach(function (r) { r.style.display = ''; });
            return;
        }

        const wantIdx = periodVal.replace('period-', '');
        let anyPartVisible = false;
        freqRows.forEach(function (r) {
            const match = String(r.getAttribute('data-period-index') || '') === wantIdx;
            r.style.display = match ? '' : 'none';
            if (match) anyPartVisible = true;
        });

        if (parent.getAttribute('data-is-freq') === '1') {
            parent.style.display = (anyPartVisible || freqRows.length === 0) ? '' : 'none';
        }
    });
}
window.applyGroupMonthTableFilters = applyGroupMonthTableFilters;

document.addEventListener('change', function (e) {
    if (e.target.classList.contains('group-month-status-filter')
        || e.target.classList.contains('group-month-period-filter')) {
        applyGroupMonthTableFilters();
    }
});

document.addEventListener('DOMContentLoaded', function () {
    try {
        const raw = sessionStorage.getItem('chitInstFilterState');
        if (raw) {
            const filters = JSON.parse(raw);
            const periodSel = document.querySelector('.group-month-period-filter');
            const statusSel = document.querySelector('.group-month-status-filter');
            if (periodSel && filters.__period) {
                periodSel.value = filters.__period;
            } else if (periodSel && periodSel.dataset.defaultPeriod) {
                periodSel.value = periodSel.dataset.defaultPeriod;
            }
            if (statusSel && filters.__status) {
                statusSel.value = filters.__status;
            }
        } else {
            const periodSel = document.querySelector('.group-month-period-filter');
            if (periodSel && periodSel.dataset.defaultPeriod) {
                periodSel.value = periodSel.dataset.defaultPeriod;
            }
        }
    } catch (e) {
        const periodSel = document.querySelector('.group-month-period-filter');
        if (periodSel && periodSel.dataset.defaultPeriod) {
            periodSel.value = periodSel.dataset.defaultPeriod;
        }
    }
    applyGroupMonthTableFilters();
});
</script>
@endsection

@section('content')
@if(isset($mode) && $mode === 'client')
{{-- CLIENT FULL INSTALLMENT SCHEDULE --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h4 class="mb-1">{{ $client->client_name }}</h4>
    <div class="text-muted small">
      @if($client->client_phone)
        <a href="tel:{{ preg_replace('/\s+/', '', $client->client_phone) }}">{{ $client->client_phone }}</a>
      @endif
      @if(optional($client->location)->name)
        <span class="mx-1">•</span>{{ $client->location->name }}
      @endif
      <span class="mx-1">•</span>
      @if($summary['groups'] <= 1)
        <span class="badge bg-label-primary">Same group</span>
        @if(!empty($summary['has_multiple_seats']))
          <span class="badge bg-label-warning">Multiple seats (A, B…)</span>
        @endif
      @else
        <span class="badge bg-label-info">More groups ({{ $summary['groups'] }})</span>
      @endif
    </div>
  </div>
  @php
      $chitPublicToken = \App\Support\HashId::encode($client->id);
      $chitPublicLink = route('public.view-chit-schedule', $chitPublicToken);
  @endphp
  <div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-sm btn-outline-success btn-copy-public-link" data-link="{{ $chitPublicLink }}" title="Copy Public Chit Schedule Link">
      <i class="icon-base ri ri-link me-1"></i>Copy Public Link
    </button>
    <a href="{{ $chitPublicLink }}" target="_blank" class="btn btn-sm btn-outline-info" title="View Public Chit Schedule">
      <i class="icon-base ri ri-external-link-line me-1"></i>Public View
    </a>
    <a href="{{ route('client-view-chits', $client->getRouteKey()) }}" class="btn btn-sm btn-outline-primary">
      <i class="ri-group-2-line me-1"></i>Client Chits
    </a>
    <a href="{{ route('chit.installments.index') }}" class="btn btn-sm btn-label-secondary">
      <i class="ri-arrow-left-line me-1"></i>Back to Installments
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Total</p>
      <h3 class="mb-0">{{ $summary['total'] }}</h3>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Paid</p>
      <h3 class="text-success mb-0">{{ $summary['paid'] }}</h3>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Partial</p>
      <h3 class="text-info mb-0">{{ $summary['partial'] }}</h3>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Pending</p>
      <h3 class="text-warning mb-0">{{ $summary['pending'] }}</h3>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Overdue</p>
      <h3 class="text-danger mb-0">{{ $summary['overdue'] }}</h3>
    </div>
  </div>
  <div class="col-md-2 col-6">
    <div class="card p-3">
      <p class="text-muted mb-1" style="font-size:.8rem;">Balance Due</p>
      <h3 class="mb-0 text-danger">₹{{ number_format($summary['balance'], 2) }}</h3>
    </div>
  </div>
</div>

@forelse($byGroup as $memberId => $groupInstallments)
  @php
    $group = $groupInstallments->first()->group;
    $seatLetter = $groupInstallments->first()->seat_letter ?? null;
    $displayName = $groupInstallments->first()->client_display_name ?? $client->client_name;
  @endphp
  <div class="card mb-4">
    @php
      $clientCardMember = $groupInstallments->first()->member;
      $clientCardFreq = $clientCardMember->collection_frequency ?? 'monthly';
      $clientCardIsFreq = in_array($clientCardFreq, ['daily', 'weekly'], true)
          && (($group->installment_frequency ?? 'monthly') === 'monthly' || ($group->installment_frequency ?? '') === '');
      $clientCardTableId = 'client-inst-table-' . ($group->id ?? '0') . '-' . ($groupInstallments->first()->member_id ?? '0') . '-' . ($groupInstallments->first()->viewing_client_id ?? ($client->id ?? '0'));
      $clientDefaultMonth = 'all';
      if ($clientCardIsFreq) {
          $firstUnpaid = $groupInstallments->first(function ($fi) {
              $bal = (float) ($fi->client_share_balance ?? $fi->balance);
              return $fi->isCollectible() && $bal > 0.009;
          });
          $clientDefaultMonth = $firstUnpaid
              ? 'month-' . $firstUnpaid->month_number
              : 'month-' . ($groupInstallments->first()->month_number ?? 1);
      }
    @endphp
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge bg-label-{{ $summary['groups'] <= 1 ? 'primary' : 'info' }}">
          {{ $summary['groups'] <= 1 ? 'Same group' : 'Group' }}
        </span>
        <h5 class="mb-0">{{ $group->group_code ?? 'Group' }}</h5>
        @if(!empty($groupInstallments->first()->member->is_shared))
          <span class="badge bg-info text-white"><i class="ri-user-shared-line me-1"></i>Shared Seat ({{ $groupInstallments->first()->ownership_percentage }}% share)</span>
        @elseif($seatLetter)
          <span class="badge bg-warning text-dark">{{ $displayName }}</span>
        @else
          <span class="fw-semibold">{{ $displayName }}</span>
        @endif
        @if($group)
          <span class="badge bg-label-secondary">Member #{{ $groupInstallments->first()->member->display_member_number ?? '—' }}</span>
          @if(!empty($group->scheme_type_label))
            <span class="badge bg-{{ $group->scheme_type_badge_color ?? 'secondary' }}">{{ $group->scheme_type_label }}</span>
          @endif
        @endif
        @if($clientCardIsFreq)
          <span class="badge bg-label-{{ $clientCardFreq === 'daily' ? 'warning' : 'info' }}" style="font-size:.65rem;">
            {{ $clientCardFreq === 'daily' ? 'Daily' : 'Weekly' }} parts under each month
          </span>
        @endif
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($clientCardIsFreq)
          <label class="form-label mb-0 text-nowrap small fw-semibold text-muted">
            <i class="ri-filter-3-line me-1"></i>{{ $clientCardFreq === 'daily' ? 'Daily' : 'Weekly' }} by Month:
          </label>
          <select class="form-select form-select-sm member-inst-filter no-search py-1 px-2"
                  style="min-width: 200px;"
                  data-table-id="{{ $clientCardTableId }}"
                  data-default-month="{{ $clientDefaultMonth }}">
            <option value="all" @selected($clientDefaultMonth === 'all')>All Months ({{ $groupInstallments->count() }})</option>
            <option value="unpaid">Pending &amp; Overdue</option>
            <option value="paid">Paid</option>
            @foreach($groupInstallments as $filterInst)
              @php
                $filterDays = $filterInst->due_date ? (int) $filterInst->due_date->daysInMonth : 30;
                $filterLabel = 'Month ' . $filterInst->month_number;
                if ($filterInst->due_date) {
                    $filterLabel .= ' — ' . $filterInst->due_date->format('M Y');
                }
                if ($clientCardFreq === 'daily') {
                    $filterLabel .= ' (' . $filterDays . ' days)';
                } else {
                    $filterLabel .= ' (4 weeks)';
                }
                $filterBal = (float) ($filterInst->client_share_balance ?? $filterInst->balance);
                $filterPaidAmt = (float) ($filterInst->client_share_paid ?? $filterInst->paid_amount);
                if ($filterBal <= 0.009 && $filterPaidAmt > 0.009) {
                    $filterLabel .= ' ✓ Paid';
                }
              @endphp
              <option value="month-{{ $filterInst->month_number }}"
                      @selected($clientDefaultMonth === 'month-'.$filterInst->month_number)>
                {{ $filterLabel }}
              </option>
            @endforeach
          </select>
        @endif
        <small class="text-muted">{{ $groupInstallments->count() }} installment{{ $groupInstallments->count() === 1 ? '' : 's' }}</small>
      </div>
    </div>
    <div class="card-body p-0">
      @if(!empty($clientCardIsFreq))
      <div class="chit-inst-bulk-summary border-top bg-label-primary py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
           data-table-id="{{ $clientCardTableId }}">
        <div class="small mb-0">
          <i class="ri-checkbox-multiple-line me-1"></i>
          Selected day/week parts:
          <strong class="chit-inst-bulk-count">0</strong> ·
          EMI total:
          <strong class="chit-inst-bulk-sum text-primary">₹0.00</strong>
          <span class="text-muted ms-1">(in order)</span>
        </div>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-outline-secondary chit-inst-bulk-clear-btn" disabled>Clear</button>
          <button type="button" class="btn btn-sm btn-primary chit-inst-bulk-pay-btn" data-table-id="{{ $clientCardTableId }}" disabled>
            <i class="ri-money-rupee-circle-line me-1"></i>Pay Selected
          </button>
        </div>
      </div>
      @endif
      <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0" id="{{ $clientCardTableId }}">
          <thead class="table-light">
            <tr>
              @if($clientCardIsFreq)
              <th class="text-center" style="width:36px;">
                <input type="checkbox" class="form-check-input chit-bulk-select-all" data-table-id="{{ $clientCardTableId }}" title="Select days/weeks in order">
              </th>
              @endif
              <th>Period</th>
              <th>Client Seat</th>
              <th>Due Date</th>
              <th class="text-end">Amount</th>
              <th class="text-end">Penalty</th>
              <th class="text-end">Paid</th>
              <th class="text-end">Balance</th>
              <th class="text-center">Status</th>
              <th class="text-center" style="min-width:180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($groupInstallments as $inst)
              @php
                $freq = $group->installment_frequency ?? $inst->group->installment_frequency ?? 'monthly';
                $displayStatus = $inst->display_status ?? $inst->status;
                $bVal = (float) ($inst->client_share_balance ?? $inst->balance);
                $paidVal = (float) ($inst->client_share_paid ?? $inst->paid_amount);
                if ($bVal <= 0.009 && $paidVal > 0.009) {
                    $displayStatus = 'paid';
                } elseif ($paidVal > 0.009 && $bVal > 0.009) {
                    $displayStatus = 'partial';
                }
                $statusBadge = match($displayStatus) {
                  'paid' => 'success',
                  'partial' => 'info',
                  'overdue' => 'danger',
                  'pending' => 'warning',
                  'waived' => 'secondary',
                  default => 'secondary',
                };
                $periodLabel = match($freq) {
                  'daily' => 'Day ' . $inst->month_number,
                  'weekly' => 'Week ' . $inst->month_number,
                  default => 'Month ' . $inst->month_number,
                };
                $memberFreq = $inst->member->collection_frequency ?? 'monthly';
                $freqPeriodsCount = 0;
                $clientNextSuggested = $bVal;
                if ($clientCardIsFreq && $inst->member) {
                    $subAmt = (float) ($inst->client_share_amount ?? $inst->amount);
                    $clientSched = $inst->member->collectionPeriodSchedule($inst->due_date, $subAmt, $paidVal);
                    $freqPeriodsCount = count($clientSched);
                    $clientNext = collect($clientSched)->firstWhere('is_next', true);
                    $clientNextSuggested = $clientNext
                        ? (float) $clientNext['balance']
                        : $inst->member->suggestedCollectionAmount($subAmt, $bVal, $inst->due_date);
                }
                $instRowHidden = $clientCardIsFreq
                    && $clientDefaultMonth !== 'all'
                    && $clientDefaultMonth !== ('month-' . $inst->month_number);
                $clientCanBulk = $clientCardIsFreq && $inst->isCollectible() && $bVal > 0.009;
              @endphp
              <tr class="inst-row group-month-inst-row"
                  data-month="month-{{ $inst->month_number }}"
                  data-status="{{ $displayStatus }}"
                  @if($instRowHidden) style="display:none" @endif>
                @if($clientCardIsFreq)
                <td class="text-center"></td>
                @endif
                <td class="fw-semibold">
                  {{ $periodLabel }}
                  @if($freqPeriodsCount > 0)
                    <div class="text-muted small fw-normal" style="font-size:.65rem;">
                      {{ $freqPeriodsCount }} {{ $memberFreq === 'daily' ? 'days' : 'weeks' }}
                      @if($inst->due_date)
                        ({{ $inst->due_date->format('M Y') }})
                      @endif
                    </div>
                  @endif
                </td>
                <td>
                  @if(!empty($inst->seat_letter))
                    <span class="badge bg-label-warning">{{ $inst->client_display_name }}</span>
                  @else
                    {{ $inst->client_display_name ?? $client->client_name }}
                  @endif
                </td>
                <td>{{ $inst->due_date ? $inst->due_date->format('d M Y') : '—' }}</td>
                <td class="text-end">₹{{ number_format((float) ($inst->client_share_amount ?? $inst->amount), 2) }}</td>
                <td class="text-end {{ $inst->penalty_amount > 0 ? 'text-danger' : 'text-muted' }}">
                  {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                </td>
                <td class="text-end {{ $paidVal > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                  {{ $paidVal > 0 ? '₹'.number_format($paidVal, 2) : '—' }}
                </td>
                <td class="text-end fw-semibold {{ $bVal > 0.009 ? 'text-danger' : 'text-muted' }}">
                  {{ $bVal > 0.009 ? '₹' . number_format($bVal, 2) : '—' }}
                </td>
                <td class="text-center">
                  <span class="badge bg-label-{{ $statusBadge }}">{{ ucfirst($displayStatus) }}</span>
                  @php
                    $latestColl = $inst->collections ? $inst->collections->sortByDesc('id')->first() : null;
                    $pDt = $latestColl?->collected_at 
                        ?? $latestColl?->created_at 
                        ?? ($inst->paid_date ? \Carbon\Carbon::parse($inst->paid_date) : null)
                        ?? (in_array($displayStatus, ['paid', 'partial'], true) ? $inst->updated_at : null);
                    $pDtFormatted = $pDt ? $pDt->format('d-m-Y h:i A') : null;
                  @endphp
                  @if(in_array($displayStatus, ['paid', 'partial'], true) && $pDtFormatted)
                    <div class="mt-1 small text-muted" style="font-size: 0.72rem;" title="Paid Date & Time">
                      <i class="ri-time-line me-1"></i>{{ $pDtFormatted }}
                    </div>
                  @endif
                </td>
                <td class="text-center">
                  @include('admin.chit.installments.partials.installment-actions', ['inst' => $inst, 'group' => $group])
                </td>
              </tr>
              @if($clientCardIsFreq)
                @include('admin.chit.installments.partials.frequency-period-rows', [
                  'inst' => $inst,
                  'group' => $group,
                  'rowHidden' => $instRowHidden,
                  'viewingClientId' => $inst->viewing_client_id ?? $client->id,
                  'showBulkCheckbox' => true,
                ])
              @endif
            @endforeach
          </tbody>
        </table>
      </div>
      @if(!empty($clientCardIsFreq))
      <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center">
        <button type="button"
                class="btn btn-sm btn-primary chit-inst-bulk-pay-btn"
                data-table-id="{{ $clientCardTableId }}"
                disabled>
          <i class="ri-money-rupee-circle-line me-1"></i>Pay Selected Day/Week Parts
        </button>
      </div>
      @endif
    </div>
  </div>
@empty
  <div class="card">
    <div class="card-body text-center text-muted py-5">No installments found for this client.</div>
  </div>
@endforelse

@elseif(isset($mode) && $mode === 'show')
{{-- SHOW MONTH INSTALLMENTS VIEW --}}
<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Total Members</p>
            <h3 class="mb-0">{{ $summary['total'] }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Paid</p>
            <h3 class="text-success mb-0">{{ $summary['paid'] }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Partial</p>
            <h3 class="text-warning mb-0">{{ $summary['partial'] }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Pending</p>
            <h3 class="text-secondary mb-0">{{ $summary['pending'] }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Overdue</p>
            <h3 class="text-danger mb-0">{{ $summary['overdue'] }}</h3>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card p-3">
            <p class="text-muted mb-1" style="font-size:.8rem;">Collected</p>
            <h3 class="mb-0">₹{{ number_format($summary['collected'], 2) }}</h3>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        @php
            $freqLabel = match($group->installment_frequency ?? 'monthly') {
                'daily'  => 'Day',
                'weekly' => 'Week',
                default  => 'Month',
            };
            $showHasFreqMembers = $installments->contains(function ($inst) use ($group) {
                $cf = $inst->member->collection_frequency ?? 'monthly';
                return in_array($cf, ['daily', 'weekly'], true)
                    && (($group->installment_frequency ?? 'monthly') === 'monthly' || ($group->installment_frequency ?? '') === '');
            });
            $showPeriodMax = 0;
            $showPeriodUnit = 'Day';
            if ($showHasFreqMembers) {
                foreach ($installments as $tmpInst) {
                    $cf = $tmpInst->member->collection_frequency ?? 'monthly';
                    if (! in_array($cf, ['daily', 'weekly'], true)) {
                        continue;
                    }
                    $cnt = $tmpInst->member
                        ? $tmpInst->member->collectionSplitCount($tmpInst->due_date)
                        : 0;
                    if ($cnt > $showPeriodMax) {
                        $showPeriodMax = $cnt;
                        $showPeriodUnit = $cf === 'weekly' ? 'Week' : 'Day';
                    }
                }
            }
            $showDefaultPeriod = 'all';
            // Keep "All days/weeks" so the full month stays visible and paid parts update in place.
            // Users can still pick a specific Day/Week from the dropdown.
        @endphp
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h5 class="mb-0">{{ $freqLabel }} {{ $month }} Installments</h5>
            <span class="badge bg-{{ $group->scheme_type_badge_color }}">{{ $group->scheme_type_label }}</span>
            @if($group->is_private)
                <span class="badge bg-dark"><i class="ri-lock-line" style="font-size:.75rem;"></i> Private</span>
            @endif

            {{-- Month Selector Dropdown --}}
            <div class="d-flex align-items-center gap-1 ms-lg-2">
                <label class="form-label mb-0 text-nowrap small fw-semibold text-muted"><i class="ri-calendar-event-line me-1"></i>Select {{ $freqLabel }}:</label>
                <select class="form-select form-select-sm fw-semibold border-primary text-primary no-search" style="min-width: 170px;" onchange="location = this.value;">
                    @for($m = 1; $m <= ($group->total_months ?? 1); $m++)
                        @php
                            $mStartDate = $group->start_date
                                ? \Carbon\Carbon::parse($group->start_date)
                                : ($group->created_at ? \Carbon\Carbon::parse($group->created_at) : now());
                            $mDateText = $mStartDate->copy()->addMonths($m - 1)->format('M Y');
                            $isForeman = $group->isForemanCommissionMonth($m);
                            $isClientWiseFc = $group->usesClientWiseForemanCommission()
                                && $m === $group->scheme?->clientWiseForemanCollectionMonth();
                        @endphp
                        <option value="{{ route('chit.installments.show', [$group->id, $m]) }}" {{ (int)$month === $m ? 'selected' : '' }}>
                            {{ $freqLabel }} {{ $m }} — {{ $mDateText }}{{ $isForeman ? ' (Foreman)' : ($isClientWiseFc ? ' (Client-wise FC)' : '') }}
                        </option>
                    @endfor
                </select>
            </div>

            @if($showHasFreqMembers && $showPeriodMax > 0)
            <div class="d-flex align-items-center gap-1">
                <label class="form-label mb-0 text-nowrap small fw-semibold text-muted">
                    <i class="ri-filter-3-line me-1"></i>{{ $showPeriodUnit === 'Week' ? 'Weekly' : 'Daily' }} part:
                </label>
                <select class="form-select form-select-sm group-month-period-filter no-search"
                        style="min-width: 140px;"
                        data-table-id="chitInstallmentsTable"
                        data-default-period="{{ $showDefaultPeriod }}">
                    <option value="all" @selected($showDefaultPeriod === 'all')>All {{ strtolower($showPeriodUnit) }}s</option>
                    @for($pi = 1; $pi <= $showPeriodMax; $pi++)
                        <option value="period-{{ $pi }}" @selected($showDefaultPeriod === 'period-'.$pi)>
                            {{ $showPeriodUnit }} {{ $pi }}
                        </option>
                    @endfor
                </select>
            </div>
            @endif

            {{-- Status Filter Dropdown --}}
            <div class="d-flex align-items-center gap-1">
                <label class="form-label mb-0 text-nowrap small fw-semibold text-muted"><i class="ri-filter-3-line me-1"></i>Status:</label>
                <select class="form-select form-select-sm group-month-status-filter no-search" style="min-width: 130px;" data-table-id="chitInstallmentsTable">
                    <option value="all">All Statuses</option>
                    <option value="unpaid">Pending & Overdue</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                </select>
            </div>
        </div>
        <a href="{{ route('chit.groups.show', $group) }}" class="btn btn-sm btn-label-secondary">
            <i class="ri-arrow-left-line me-1"></i>Back to Group
        </a>
    </div>
    <div class="card-body p-0">
        @if(!empty($showHasFreqMembers))
        <div class="chit-inst-bulk-summary border-top bg-label-primary py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2"
             data-table-id="chitInstallmentsTable">
            <div class="small mb-0">
                <i class="ri-checkbox-multiple-line me-1"></i>
                Selected day/week parts:
                <strong class="chit-inst-bulk-count">0</strong> ·
                EMI total:
                <strong class="chit-inst-bulk-sum text-primary">₹0.00</strong>
                <span class="text-muted ms-1">(select Day 1 → Day 2 in order)</span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary chit-inst-bulk-clear-btn" disabled>Clear</button>
                <button type="button" class="btn btn-sm btn-primary chit-inst-bulk-pay-btn" data-table-id="chitInstallmentsTable" disabled>
                    <i class="ri-money-rupee-circle-line me-1"></i>Pay Selected
                </button>
            </div>
        </div>
        @endif
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="chitInstallmentsTable">
                <thead class="table-light">
                    <tr>
                        @if($showHasFreqMembers)
                        <th class="text-center" style="width:40px;">
                            <input type="checkbox" class="form-check-input chit-bulk-select-all" data-table-id="chitInstallmentsTable" title="Select days/weeks in order">
                        </th>
                        @endif
                        <th class="text-center">Member #</th>
                        <th>Client</th>
                        <th class="text-end">Installment</th>
                        <th class="text-end">Penalty</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th>Due Date</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="min-width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($installments as $inst)
                    @php
                        $rowBal = (float) $inst->balance;
                        $rowPaid = (float) $inst->paid_amount;
                        $rowStatus = $inst->status;
                        if ($rowBal <= 0.009 && $rowPaid > 0.009) {
                            $rowStatus = 'paid';
                        } elseif ($rowPaid > 0.009 && $rowBal > 0.009) {
                            $rowStatus = 'partial';
                        }
                        $memberCollFreq = $inst->member->collection_frequency ?? 'monthly';
                        $showIsFreq = in_array($memberCollFreq, ['daily', 'weekly'], true)
                            && (($group->installment_frequency ?? 'monthly') === 'monthly' || ($group->installment_frequency ?? '') === '');
                        $showFreqCount = 0;
                        $nextSuggested = $rowBal;
                        $showFreqSched = [];
                        if ($showIsFreq && $inst->member) {
                            $showFreqSched = $inst->member->collectionPeriodSchedule(
                                $inst->due_date,
                                (float) ($inst->client_share_amount ?? $inst->amount),
                                (float) ($inst->client_share_paid ?? $rowPaid)
                            );
                            $showFreqCount = count($showFreqSched);
                            $nextPart = collect($showFreqSched)->firstWhere('is_next', true);
                            $nextSuggested = $nextPart
                                ? (float) $nextPart['balance']
                                : $inst->member->suggestedCollectionAmount(
                                    (float) ($inst->client_share_amount ?? $inst->amount),
                                    $rowBal,
                                    $inst->due_date
                                );
                        }
                        $bulkClientId = $inst->viewing_client_id ?? ($inst->member->client_id ?? '');
                        // Bulk pay is only for daily/weekly collection members.
                        $canBulk = $showIsFreq && $inst->isCollectible() && $rowBal > 0.009;
                    @endphp
                    <tr class="group-month-inst-row inst-row inst-parent-row"
                        data-status="{{ $rowStatus }}"
                        data-month="month-{{ $inst->month_number }}"
                        data-inst-id="{{ $inst->id }}"
                        data-is-freq="{{ $showIsFreq ? '1' : '0' }}">
                        @if($showHasFreqMembers)
                        <td class="text-center"></td>
                        @endif
                        <td class="text-center"><strong>{{ $inst->display_member_number ?? $inst->member->display_member_number }}</strong></td>
                        <td>
                            @php
                                $rowClient = $inst->member->client ?? null;
                                $rowClientId = $inst->viewing_client_id ?? ($rowClient ? $rowClient->getRouteKey() : null);
                                $isShared = !empty($inst->member->is_shared);
                                $isShareRow = !empty($inst->is_share_row);
                                $rowDisplayName = $inst->client_display_name
                                    ?? ($isShared
                                        ? ($inst->member->owners_display ?? '—')
                                        : ($inst->member?->displayClientName() ?? ($rowClient->client_name ?? '—')));
                                $seatLetter = $inst->seat_letter ?? null;
                                $isConsolidated = !empty($inst->is_consolidated);
                            @endphp
                            @if($rowClientId)
                                <a href="{{ route('chit.installments.client', $rowClientId) }}" class="fw-semibold text-primary" style="font-size:.875rem;" title="View all installments">
                                    {{ $rowDisplayName }}
                                </a>
                                @if($isShareRow)
                                    <span class="badge bg-label-info ms-1" style="font-size:.65rem;"><i class="ri-user-shared-line me-1"></i>Shared</span>
                                @endif
                            @else
                                <div class="fw-semibold" style="font-size:.875rem;">
                                    {{ $rowDisplayName }}
                                    @if($isShared || $isShareRow)
                                        <span class="badge bg-label-info ms-1" style="font-size:.65rem;"><i class="ri-user-shared-line me-1"></i>Shared</span>
                                    @endif
                                </div>
                            @endif
                            <small class="text-muted d-block">
                                @if($isShareRow)
                                    {{ $rowClient->client_phone ?? '' }}
                                @elseif($isShared)
                                    {{ $inst->member->owners_display }}
                                @elseif($isConsolidated && $seatLetter)
                                    <span class="badge bg-label-warning" style="font-size:.65rem;">Seats {{ $seatLetter }}</span>
                                    · Cumulative
                                @elseif($seatLetter)
                                    <span class="badge bg-label-warning" style="font-size:.65rem;">Seat {{ $seatLetter }}</span>
                                    · {{ $rowClient->client_phone ?? '' }}
                                @else
                                    {{ $rowClient->client_phone ?? '' }}
                                @endif
                            </small>
                            @if($showIsFreq)
                                <span class="badge bg-label-{{ $memberCollFreq === 'daily' ? 'warning' : 'info' }} mt-1" style="font-size:.65rem;">
                                    {{ $memberCollFreq === 'daily' ? 'Daily' : 'Weekly' }}
                                    @if($showFreqCount > 0)
                                        · {{ $showFreqCount }} {{ $memberCollFreq === 'daily' ? 'days' : 'weeks' }}
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="text-end">{{ $inst->amount_display }}</td>
                        <td class="text-end {{ $inst->penalty_amount > 0 ? 'text-danger' : 'text-muted' }}">
                            {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                        </td>
                        <td class="text-end {{ $rowPaid > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                            {{ $rowPaid > 0 ? '₹'.number_format($rowPaid, 2) : '—' }}
                        </td>
                        <td class="text-end fw-semibold {{ $rowBal > 0.009 ? 'text-danger' : 'text-muted' }}">
                            {{ $rowBal > 0.009 ? '₹' . number_format($rowBal, 2) : '—' }}
                        </td>
                        <td>{{ $inst->due_date ? Carbon\Carbon::parse($inst->due_date)->format('d M Y') : '—' }}</td>
                        <td class="text-center">
                            <span class="badge bg-label-{{ match($rowStatus) { 'paid' => 'success', 'partial' => 'info', 'overdue' => 'danger', 'waived' => 'secondary', default => 'warning' } }}">{{ ucfirst($rowStatus) }}</span>
                            @php
                              $latestColl = $inst->collections ? $inst->collections->sortByDesc('id')->first() : null;
                              $pDt = $latestColl?->collected_at
                                  ?? $latestColl?->created_at
                                  ?? ($inst->paid_date ? \Carbon\Carbon::parse($inst->paid_date) : null)
                                  ?? (in_array($rowStatus, ['paid', 'partial'], true) ? $inst->updated_at : null);
                              $pDtFormatted = $pDt ? $pDt->format('d-m-Y h:i A') : null;
                            @endphp
                            @if(in_array($rowStatus, ['paid', 'partial'], true) && $pDtFormatted)
                              <div class="mt-1 small text-muted" style="font-size: 0.72rem;" title="Paid Date & Time">
                                <i class="ri-time-line me-1"></i>{{ $pDtFormatted }}
                              </div>
                            @endif
                        </td>
                        <td class="text-center">
                            @include('admin.chit.installments.partials.installment-actions', ['inst' => $inst, 'group' => $group])
                        </td>
                    </tr>
                    @if($showIsFreq)
                        @php
                            $viewFreqPeriods = $showFreqSched;
                            $viewClientId = $inst->viewing_client_id
                                ?? ($inst->member->client_id ?? null);
                            $viewClientName = $inst->client_display_name
                                ?? $inst->member?->displayClientName()
                                ?? ($inst->member?->client?->client_name ?? '—');
                            $viewPartialRulesUrl = route('chit.installments.partial-rules', $inst)
                                . ($viewClientId ? ('?client_id=' . $viewClientId) : '');
                            $viewPeriodLabel = 'Month ' . $inst->month_number;
                            $viewRowAmount = (float) ($inst->client_share_amount ?? $inst->amount);
                            $viewRowPaid = (float) ($inst->client_share_paid ?? $rowPaid);
                            $viewRowBalance = (float) ($inst->client_share_balance ?? $rowBal);
                        @endphp
                        @foreach($viewFreqPeriods as $period)
                            @php
                                $pStatusColor = match ($period['status']) {
                                    'paid' => 'success',
                                    'partial' => 'info',
                                    'overdue' => 'danger',
                                    default => 'warning',
                                };
                                $canPayPeriod = $inst->isCollectible()
                                    && $viewRowBalance > 0.009
                                    && $period['balance'] > 0.009;
                            @endphp
                            <tr class="inst-row inst-freq-row group-month-inst-row bg-light"
                                data-month="month-{{ $inst->month_number }}"
                                data-status="{{ $period['status'] }}"
                                data-period-index="{{ $period['index'] }}"
                                data-parent-inst="{{ $inst->id }}">
                                @if($showHasFreqMembers)
                                <td class="text-center">
                                    @if($canPayPeriod)
                                        <input type="checkbox"
                                               class="form-check-input chit-bulk-cb"
                                               value="{{ $inst->id }}"
                                               data-client-id="{{ $viewClientId }}"
                                               data-balance="{{ $viewRowBalance }}"
                                               data-suggested="{{ $period['balance'] }}"
                                               data-period-amount="{{ $period['balance'] }}"
                                               data-period-index="{{ $period['index'] }}"
                                               data-period-label="{{ $period['label'] }}"
                                               data-month-number="{{ $inst->month_number }}"
                                               data-is-next="{{ !empty($period['is_next']) ? '1' : '0' }}"
                                               data-is-freq="1">
                                    @endif
                                </td>
                                @endif
                                <td class="text-center text-muted small">—</td>
                                <td class="text-muted small ps-3">
                                    <i class="ri-corner-down-right-line me-1"></i>{{ $period['label'] }}
                                </td>
                                <td class="text-end small">₹{{ number_format($period['amount'], 2) }}</td>
                                <td class="text-end small text-muted">—</td>
                                <td class="text-end text-success small">₹{{ number_format($period['paid'], 2) }}</td>
                                <td class="text-end fw-semibold small {{ $period['balance'] > 0.009 ? 'text-danger' : 'text-muted' }}">
                                    {{ $period['balance'] > 0.009 ? '₹' . number_format($period['balance'], 2) : '—' }}
                                </td>
                                <td class="small">{{ $period['due_date'] ? $period['due_date']->format('d M Y') : '—' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-label-{{ $pStatusColor }} text-capitalize" style="font-size:.65rem;">{{ $period['status'] }}</span>
                                </td>
                                <td class="text-center text-nowrap">
                                    @if($canPayPeriod)
                                        <a href="javascript:void(0);"
                                           class="btn btn-xs btn-primary chit-partial-btn"
                                           data-installment-id="{{ $inst->id }}"
                                           data-collect-url="{{ route('chit.installments.collect', $inst) }}"
                                           data-partial-rules-url="{{ $viewPartialRulesUrl }}"
                                           data-client="{{ $viewClientName }}"
                                           data-client-id="{{ $viewClientId }}"
                                           data-single-seat-only="{{ !empty($inst->is_share_row) ? 1 : 0 }}"
                                           data-period="{{ $viewPeriodLabel }} — {{ $period['label'] }}"
                                           data-amount="{{ $viewRowAmount }}"
                                           data-penalty="0"
                                           data-paid="{{ $viewRowPaid }}"
                                           data-balance="{{ $viewRowBalance }}"
                                           data-is-consolidated="0"
                                           data-member-numbers="{{ $inst->display_member_number ?? $inst->member->display_member_number }}"
                                           data-single-amount="{{ $viewRowAmount }}"
                                           data-cumulative-amount="{{ $viewRowAmount }}"
                                           data-collection-frequency="{{ $memberCollFreq }}"
                                           data-suggested-amount="{{ $period['balance'] }}"
                                           data-split-amount="{{ $period['amount'] }}"
                                           data-split-count="{{ count($viewFreqPeriods) }}"
                                           data-force-frequency-partial="1"
                                           data-min-percentage="1"
                                           data-penalty-method="{{ $partialPaymentConfig['penalty_calculation_method'] ?? 'emi_amount' }}">
                                            <i class="ri-money-dollar-circle-line me-1"></i>Pay {{ $period['label'] }}
                                        </a>
                                    @elseif($period['status'] === 'paid')
                                        <span class="badge bg-label-success" style="font-size:.65rem;">Paid</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(!empty($showHasFreqMembers))
        <div class="p-3 border-top d-flex flex-wrap gap-2 align-items-center bg-label-primary bg-opacity-10">
            <button type="button"
                    class="btn btn-sm btn-primary chit-inst-bulk-pay-btn"
                    data-table-id="chitInstallmentsTable"
                    disabled>
                <i class="ri-money-rupee-circle-line me-1"></i>Pay Selected Day/Week Parts
            </button>
            <span class="small mb-0">
                Selected: <strong class="chit-inst-bulk-count" id="chitShowBulkCountFooter">0</strong>
                · EMI total: <strong class="chit-inst-bulk-sum text-primary" id="chitShowBulkSumFooter">₹0.00</strong>
            </span>
            <small class="text-muted">Tick Day 1 → Day 2 in order, then pay.</small>
        </div>
        @endif
    </div>
</div>

@else
{{-- INDEX: EMI-style client view with status tabs --}}
<div class="row g-4 mb-4">
  <div class="col-xl-3 col-sm-6">
    <div class="card cursor-pointer stat-card" id="card-total-installments" data-status="all">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Total Installments</p>
            <h4 class="mb-0 text-primary" id="stat-total-installments">{{ number_format($stats['total_installments']) }}</h4>
            <small class="text-muted">All Clients</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-primary">
              <i class="icon-base ri ri-file-list-3-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card cursor-pointer stat-card" id="card-paid-installments" data-status="paid">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Paid</p>
            <h4 class="mb-0 text-success" id="stat-paid-installments">{{ number_format($stats['paid_installments']) }}</h4>
            <small class="text-muted" id="stat-total-collected">₹{{ number_format($stats['total_collected'], 2) }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success">
              <i class="icon-base ri ri-checkbox-circle-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card cursor-pointer stat-card" id="card-pending-installments" data-status="pending">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Pending (This Month)</p>
            <h4 class="mb-0 text-warning" id="stat-pending-installments">{{ number_format($stats['pending_installments']) }}</h4>
            <small class="text-muted" id="stat-total-pending">₹{{ number_format($stats['total_pending'], 2) }}</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-warning">
              <i class="icon-base ri ri-time-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card cursor-pointer stat-card" id="card-overdue-installments" data-status="overdue">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between">
          <div class="content-left">
            <p class="mb-1">Overdue</p>
            <h4 class="mb-0 text-danger" id="stat-overdue-installments">{{ number_format($stats['overdue_installments']) }}</h4>
            <small class="text-muted">Needs attention</small>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger">
              <i class="icon-base ri ri-alarm-warning-line"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header p-0 border-bottom">
    <div class="nav-align-top">
      <ul class="nav nav-tabs nav-fill" role="tablist" id="installmentsTabs">
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="all" data-bs-toggle="tab">
            <i class="icon-base ri ri-file-list-3-line me-1_5 text-primary"></i>
            All
            <span class="badge rounded-pill bg-primary ms-1" id="tab-count-all">{{ number_format($stats['total_installments']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link active" role="tab" data-status="overdue" data-bs-toggle="tab">
            <i class="icon-base ri ri-alarm-warning-line me-1_5 text-danger"></i>
            Overdue
            <span class="badge rounded-pill bg-danger ms-1" id="tab-count-overdue">{{ number_format($stats['overdue_installments']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="pending" data-bs-toggle="tab">
            <i class="icon-base ri ri-time-line me-1_5 text-warning"></i>
            Pending
            <span class="badge rounded-pill bg-warning ms-1 text-dark" id="tab-count-pending">{{ number_format($stats['pending_installments']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="upcoming" data-bs-toggle="tab">
            <i class="icon-base ri ri-calendar-schedule-line me-1_5 text-secondary"></i>
            Upcoming
            <span class="badge rounded-pill bg-secondary ms-1" id="tab-count-upcoming">{{ number_format($stats['upcoming_installments'] ?? 0) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="partial" data-bs-toggle="tab">
            <i class="icon-base ri ri-pie-chart-line me-1_5 text-info"></i>
            Partial Paid
            <span class="badge rounded-pill bg-info ms-1" id="tab-count-partial">{{ number_format($stats['partial_installments']) }}</span>
          </button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link" role="tab" data-status="paid" data-bs-toggle="tab">
            <i class="icon-base ri ri-checkbox-circle-line me-1_5 text-success"></i>
            Paid
            <span class="badge rounded-pill bg-success ms-1" id="tab-count-paid">{{ number_format($stats['paid_installments']) }}</span>
          </button>
        </li>
      </ul>
    </div>
  </div>

  <div class="card-body border-bottom py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
      <h5 class="mb-0 fw-semibold text-primary text-nowrap d-flex align-items-center" id="tableTitle"><i class="icon-base ri ri-file-list-3-line me-2 text-primary"></i>Overdue Clients</h5>
      {{-- No overflow clipping here: these selects become Select2 widgets whose
           dropdown panel is rendered inside their own wrapper. --}}
      <div class="d-flex flex-wrap align-items-center gap-2 w-100 justify-content-md-end">
        <input type="hidden" id="statusFilter" value="overdue" />

        <div class="d-flex align-items-center gap-1">
          <label for="groupFilter" class="form-label mb-0 text-nowrap small fw-medium">Group:</label>
          <div class="position-relative" style="width: 210px;">
            <select id="groupFilter" class="form-select form-select-sm">
              <option value="">All Groups</option>
              @foreach($groups as $g)
                <option value="{{ $g->id }}" title="{{ $g->group_code }} — {{ $g->scheme->name ?? '' }}">{{ $g->group_code }} — {{ $g->scheme->name ?? '' }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="d-flex align-items-center gap-1">
          <label for="familyFilter" class="form-label mb-0 text-nowrap small fw-medium">Family:</label>
          <div class="position-relative" style="width: 160px;">
            <select id="familyFilter" class="form-select form-select-sm">
              <option value="">All Families</option>
              @foreach($families as $fam)
                <option value="{{ $fam->id }}">{{ $fam->name }}</option>
              @endforeach
            </select>
          </div>
        </div>

        @include('partials.date-range-filter', [
          'fromId' => 'fromDateFilter',
          'toId' => 'toDateFilter',
          'presetId' => 'chitInstallmentsDatePreset',
        ])

        <button type="button" id="resetFilters" class="btn btn-sm btn-outline-secondary text-nowrap">
          <i class="icon-base ri ri-refresh-line me-1"></i>Reset
        </button>
      </div>
    </div>
  </div>

  <div class="card-datatable table-responsive">
    <div class="px-4 pt-3 pb-1 text-muted small">
      <span>One row per client. Click the client name to open <strong>all installments</strong> across every chit group. Expand a row for a quick status view.</span>
    </div>
    <table class="datatables-chit-installments table table-hover" id="chitInstallmentsDataTable">
      <thead>
        <tr>
          <th style="width: 30px;"></th>
          <th>S.No</th>
          <th>Client</th>
          <th>Phone</th>
          <th>Chit Groups</th>
          <th>Zone</th>
          <th>Installment Amount</th>
          <th>Total Balance</th>
          <th>Summary</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>
@endif

@include('admin.chit.installments.partials.collect-modals')
@endsection
