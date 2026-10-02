@extends('layouts/layoutMaster')

@section('title', 'Chit Settlement Applications')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
<script>
  window.chitSettlementMembers = @json($groupMembers->values());
</script>
@vite(['resources/assets/custom-js/chit-settlement-applications.js'])
@endsection

@section('content')
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer stat-card" id="cardApplied" data-status="pending">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="text-heading mb-1">Applied</p>
            <h4 class="mb-0" id="countPending">{{ number_format($counts['pending'] ?? 0) }}</h4>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-warning"><i class="ri-time-line ri-24px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer stat-card" id="cardApproved" data-status="approved">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="text-heading mb-1">Approved</p>
            <h4 class="mb-0" id="countApproved">{{ number_format($counts['approved'] ?? (($counts['paid'] ?? 0) + ($counts['processing'] ?? 0))) }}</h4>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-success"><i class="ri-checkbox-circle-line ri-24px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card cursor-pointer stat-card" id="cardRejected" data-status="rejected">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <p class="text-heading mb-1">Rejected</p>
            <h4 class="mb-0" id="countRejected">{{ number_format($counts['rejected'] ?? 0) }}</h4>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-danger"><i class="ri-close-circle-line ri-24px"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card">
      <div class="card-body d-flex align-items-center justify-content-center">
        <button class="btn btn-primary d-flex align-items-center gap-2" id="btnApplySettlement">
          <i class="ri-hand-coin-line ri-20px"></i> Apply for Settlement
        </button>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom pb-0">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
      <h5 class="mb-0"><i class="ri-file-list-3-line me-1 text-primary"></i>Chit Settlement Applications</h5>
      <div class="d-flex gap-2 flex-wrap align-items-center">
        <div class="d-flex align-items-center gap-1">
          <label class="form-label mb-0 small text-nowrap">Source:</label>
          <select id="sourceFilter" class="form-select form-select-sm" style="min-width: 140px;">
            <option value="all">All Sources</option>
            <option value="customer">Customer App</option>
            <option value="admin">Admin Portal</option>
            <option value="agent">Agent App</option>
          </select>
        </div>
        <div class="d-flex align-items-center gap-1">
          <label class="form-label mb-0 small text-nowrap">Group:</label>
          <select id="groupFilter" class="form-select form-select-sm" style="min-width: 180px; max-width: 280px;">
            <option value="">All Groups</option>
            @foreach($activeGroups as $g)
              <option value="{{ $g->id }}" @selected((string) request('group_id') === (string) $g->id)>{{ $g->group_code }} — {{ $g->scheme->name ?? '—' }}</option>
            @endforeach
          </select>
        </div>
        @include('partials.date-range-filter', [
          'fromId' => 'fromDateFilter',
          'toId' => 'toDateFilter',
          'presetId' => 'settlementAppDatePreset',
        ])
        <button type="button" id="btnResetFilters" class="btn btn-sm btn-label-secondary ms-1">
          <i class="ri-refresh-line me-1"></i>Reset
        </button>
      </div>
    </div>

    <input type="hidden" id="statusFilter" value="pending" />
    <ul class="nav nav-tabs nav-fill" role="tablist" id="settlementApplicationTabs">
      <li class="nav-item" role="presentation">
        <button type="button" class="nav-link active" role="tab" data-status="pending" data-bs-toggle="tab">
          <i class="ri-time-line me-1"></i>Applied
          <span class="badge rounded-pill bg-warning ms-1 text-dark" id="tab-count-pending">{{ number_format($counts['pending'] ?? 0) }}</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" role="tab" data-status="approved" data-bs-toggle="tab">
          <i class="ri-checkbox-circle-line me-1"></i>Approved
          <span class="badge rounded-pill bg-success ms-1" id="tab-count-approved">{{ number_format($counts['approved'] ?? 0) }}</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" role="tab" data-status="rejected" data-bs-toggle="tab">
          <i class="ri-close-circle-line me-1"></i>Rejected
          <span class="badge rounded-pill bg-danger ms-1" id="tab-count-rejected">{{ number_format($counts['rejected'] ?? 0) }}</span>
        </button>
      </li>
    </ul>
  </div>

  <div class="card-body border-bottom py-3">
    <h5 class="mb-0 fw-semibold text-primary" id="settlementTableTitle">
      <i class="icon-base ri ri-file-list-3-line me-2"></i>Applied Applications
    </h5>
  </div>

  <div class="card-datatable table-responsive">
    <table class="datatables-chit-settlements table border-top">
      <thead>
        <tr>
          <th>#</th>
          <th>Payout Code</th>
          <th>Source</th>
          <th>Client</th>
          <th>Phone</th>
          <th>Group</th>
          <th>Scheme</th>
          <th>Chit Value</th>
          <th>Settlement Amount</th>
          <th>Settlement Month</th>
          <th>Status</th>
          <th>Applied On</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

{{-- Apply for Settlement Modal --}}
<div class="modal fade" id="modalApplySettlement" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content overflow-hidden" style="max-height: 90vh;">
      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1056; position: absolute; right: 1rem; top: 1rem;"></button>
      <div class="modal-body p-0 overflow-auto" style="max-height: 90vh;">
        <div class="p-5 text-center bg-primary position-relative overflow-hidden" style="border-radius: 0.5rem 0.5rem 0 0;">
          <div class="position-absolute w-100 h-100 top-0 start-0" style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%); z-index: 1;"></div>
          <div class="position-relative" style="z-index: 2;">
            <h3 class="text-white mb-1">Apply for Chit Settlement</h3>
            <p class="text-white opacity-75 mb-0">Select a member to view enrolled chit groups, available settlement months, and submit settlement application</p>
          </div>
        </div>
        <div class="p-5">
          <form id="formApplySettlement" class="row g-4" action="{{ route('chit.settlements.apply') }}" method="POST">
            @csrf

            <input type="hidden" id="settle_group_id_hidden" name="group_id">
            <input type="hidden" id="settle_member_id_hidden" name="member_id">
            <input type="hidden" id="settle_payout_kind_hidden" name="payout_kind" value="original">
            <input type="hidden" id="settle_month_number_hidden" name="month_number">

            <div class="col-12">
              <label class="form-label fw-semibold" for="settle_member_id">Select Member / Client <span class="text-danger">*</span></label>
              @php
                $seatCountByClientGroup = collect($groupMembers)->countBy(function ($row) {
                  return ((int) ($row['client_id'] ?? 0)) . ':' . ((int) ($row['group_id'] ?? 0));
                });

                $uniqueClients = collect($groupMembers)->reduce(function ($carry, $row) use ($seatCountByClientGroup) {
                  $clientId = (int) ($row['client_id'] ?? 0);
                  $groupId = (int) ($row['group_id'] ?? 0);
                  $isShared = !empty($row['is_shared']);
                  $isContribution = !empty($row['uses_contribution_settlement'])
                    || !empty($row['is_outgoing_transferred'])
                    || !empty($row['is_cancelled_withdrawn']);
                  $seatsInGroup = (int) $seatCountByClientGroup->get($clientId . ':' . $groupId, 1);
                  // Contribution seats (outgoing transfer / cancelled) always get their own option
                  // so they are not hidden behind the client's active destination seat.
                  $multiSeatSameGroup = ! $isShared && $clientId > 0 && $seatsInGroup > 1;
                  $key = ($multiSeatSameGroup || $isShared || $isContribution || $clientId <= 0)
                    ? ('m:' . $row['id'])
                    : ('c:' . $clientId);

                  if (!isset($carry[$key])) {
                    $carry[$key] = [
                      'id' => $row['id'],
                      'client_id' => $row['client_id'],
                      'client_name' => $row['client_name'],
                      'group_code' => $row['group_code'] ?? '',
                      'is_multi_seat' => $multiSeatSameGroup,
                      'is_contribution' => $isContribution,
                      'is_outgoing' => !empty($row['is_outgoing_transferred']),
                      'group_ids' => [],
                    ];
                  }
                  if ($groupId > 0 && !in_array($groupId, $carry[$key]['group_ids'], true)) {
                    $carry[$key]['group_ids'][] = $groupId;
                  }
                  $carry[$key]['group_count'] = count($carry[$key]['group_ids']);
                  return $carry;
                }, []);
              @endphp
              <select id="settle_member_id" class="form-select" required data-placeholder="Select member or client">
                <option></option>
                @foreach($uniqueClients as $clientOption)
                  <option value="{{ $clientOption['id'] }}"
                    data-client-id="{{ $clientOption['client_id'] }}"
                    data-client-name="{{ $clientOption['client_name'] }}">
                    {{ $clientOption['client_name'] }}
                    @if(!empty($clientOption['is_outgoing']))
                      — Outgoing transfer ({{ $clientOption['group_code'] }})
                    @elseif(!empty($clientOption['is_contribution']))
                      — Cancelled ({{ $clientOption['group_code'] }})
                    @elseif(!empty($clientOption['is_multi_seat']) && !empty($clientOption['group_code']))
                      — {{ $clientOption['group_code'] }}
                    @elseif($clientOption['group_count'] > 1)
                      ({{ $clientOption['group_count'] }} groups)
                    @endif
                  </option>
                @endforeach
              </select>
              <small class="text-muted">Outgoing transferred / cancelled clients appear separately so they can apply for paid months − foreman commission.</small>
            </div>

            <div class="col-12" id="memberGroupsContainer" style="display:none;">
              <div class="border rounded p-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="mb-0 text-primary">
                    <i class="ri-git-branch-line me-1"></i>Enrolled Chit Groups & Available Settlements
                  </h6>
                  <span class="badge bg-label-primary fs-6" id="selectedMemberNameBadge">Client: —</span>
                </div>
                <div id="memberGroupsList" class="d-flex flex-column gap-3">
                  <!-- Dynamically rendered via JS -->
                </div>
              </div>
            </div>

            <div class="col-12 d-none" id="settleRemarksContainer">
              <label class="form-label fw-semibold" for="settle_remarks">Remarks / Notes</label>
              <input type="text" id="settle_remarks" name="remarks" class="form-control" placeholder="Optional notes for this settlement application">
            </div>

            <div class="col-12 text-end">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
