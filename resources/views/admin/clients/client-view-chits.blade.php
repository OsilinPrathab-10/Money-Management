@extends('layouts/layoutMaster')

@section('title', 'Client View - Chits')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('content')

<div class="row">
  <div class="col-12">
    <!-- User Tabs -->
    <div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
      <div class="nav-align-top w-100 w-md-auto">
        <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/account/'.$client->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
          @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/kyc/'.$client->id) }}"><i class="icon-base ri ri-shield-check-line me-1_5"></i>KYC</a></li>
          @endif
          <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/loans/'.$client->id) }}"><i class="icon-base ri ri-file-list-3-line me-1_5"></i>Loans</a></li>
          <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-group-2-line me-1_5"></i>Chits</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/ledger/'.$client->id) }}"><i class="icon-base ri ri-wallet-3-line me-1_5"></i>Ledger</a></li>
          <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/notifications/'.$client->id) }}"><i class="icon-base ri ri-notification-3-line me-1_5"></i>Notifications</a></li>
        </ul>
      </div>
      <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
        @php
            $chitPublicToken = \App\Support\HashId::encode($client->id);
            $chitPublicLink = route('public.view-chit-schedule', $chitPublicToken);
        @endphp
        <button type="button" class="btn btn-sm btn-outline-success d-inline-flex align-items-center justify-content-center" onclick="copyChitPublicLink()" title="Copy public chit schedule link">
          <i class="icon-base ri ri-share-line me-1"></i>
          <span>Share Link</span>
        </button>
        <a href="{{ $chitPublicLink }}" target="_blank" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center justify-content-center" title="View public chit schedule">
          <i class="icon-base ri ri-external-link-line me-1"></i>
          <span>Public View</span>
        </a>
        <a href="{{ route('chit.settlement-applications.index', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-info d-inline-flex align-items-center justify-content-center" title="View settlement applications for this client">
          <i class="icon-base ri ri-money-rupee-circle-line me-1"></i>
          <span>Settlement Page</span>
        </a>
        <a href="{{ route('client-management') }}" class="btn btn-sm btn-outline-secondary w-sm-auto d-inline-flex align-items-center justify-content-center">
          <i class="icon-base ri ri-arrow-left-line me-1"></i>
          <span>Back to Clients</span>
        </a>
      </div>
    </div>

    {{-- Chit Subscriptions Tab Navigation (For Single & Multiple Chits) --}}
    @if($enrollments->count() > 0)
    <div class="card mb-4 shadow-xs">
      <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
          <div class="d-flex align-items-center gap-2">
            <i class="icon-base ri ri-group-2-line text-primary fs-4"></i>
            <div>
              <h6 class="mb-0 fw-bold text-dark">Chit Subscriptions</h6>
              <small class="text-muted">{{ $client->client_name }} is enrolled in {{ $enrollments->count() }} chit {{ $enrollments->count() > 1 ? 'groups' : 'group' }}</small>
            </div>
          </div>
          <ul class="nav nav-pills gap-2" id="chitSubscriptionsTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active btn btn-sm px-3 py-1_5" id="tab-all-chits-btn" data-bs-toggle="pill" data-bs-target="#tab-all-chits" type="button" role="tab" aria-controls="tab-all-chits" aria-selected="true">
                <i class="icon-base ri ri-apps-2-line me-1"></i>All Chits ({{ $enrollments->count() }})
              </button>
            </li>
            @foreach($enrollments as $index => $sub)
            <li class="nav-item" role="presentation">
              <button class="nav-link btn btn-sm px-3 py-1_5" id="tab-chit-{{ $sub->id }}-btn" data-bs-toggle="pill" data-bs-target="#tab-chit-{{ $sub->id }}" type="button" role="tab" aria-controls="tab-chit-{{ $sub->id }}" aria-selected="false">
                <i class="icon-base ri ri-community-line me-1"></i>Chit #{{ $index + 1 }} ({{ $sub->group?->group_code ?? 'Group' }})
              </button>
            </li>
            @endforeach
          </ul>
        </div>
      </div>
    </div>
    @endif

    <div class="tab-content p-0 bg-transparent shadow-none border-0" id="chitSubscriptionsTabContent">
      {{-- ── TAB 1: ALL CHITS / CONSOLIDATED VIEW ── --}}
      <div class="tab-pane fade show active" id="tab-all-chits" role="tabpanel" aria-labelledby="tab-all-chits-btn">
        <!-- Group Enrollments Card -->
        <div class="card mb-6">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="icon-base ri ri-group-line me-2 text-primary"></i>Chit Group Enrollments</h5>
            <span class="badge bg-label-primary rounded-pill">{{ $enrollments->count() }} Total</span>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>S.No</th>
                    <th>Group Code</th>
                    <th>Scheme Name</th>
                    <th>Chit Value</th>
                    <th>Your Share</th>
                    <th>Member No.</th>
                    <th>Settlement Amount</th>
                    <th>Group Status</th>
                    <th>Settlement Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($enrollments as $index => $sub)
                  @php
                    $statusBadgeMap = [
                      'applied' => 'warning',
                      'approved' => 'info',
                      'active' => 'success',
                      'defaulted' => 'danger',
                      'completed' => 'primary',
                      'withdrawn' => 'secondary',
                    ];
                    $badge = $statusBadgeMap[$sub->status] ?? 'secondary';
                    $ownPct = (float) (
                        $sub->is_shared
                            ? ($sub->ownership_percentage ?? 100)
                            : ($sub->effective_share_percentage ?? $sub->share_percentage ?? 100)
                    );
                    $settleInfo = $sub->settlement_status_info;
                  @endphp
                  <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                      @if($sub->group)
                        <a href="{{ route('chit.groups.show', $sub->group->id) }}" class="fw-semibold">
                          {{ $sub->group->group_code }}
                        </a>
                      @else
                        <span class="text-muted">Group removed</span>
                      @endif
                      @if($sub->is_shared)
                        <div><span class="badge bg-label-primary" style="font-size:.65rem;">Shared</span></div>
                      @endif
                    </td>
                    <td>{{ $sub->group?->scheme?->name ?? '—' }}</td>
                    <td>₹{{ number_format($sub->group?->chit_value ?? 0, 2) }}</td>
                    <td>
                      <div class="fw-semibold">{{ rtrim(rtrim(number_format($ownPct, 2), '0'), '.') }}%</div>
                      <small class="text-muted">₹{{ number_format($sub->share_of_chit ?? (($sub->group?->chit_value ?? 0) * $ownPct / 100), 2) }}</small>
                    </td>
                    <td><span class="badge bg-label-dark">#{{ $sub->member_number }}</span></td>
                    <td class="fw-semibold text-success">₹{{ number_format($settleInfo['amount'] ?? 0, 2) }}</td>
                    <td>
                      <span class="badge bg-label-{{ $badge }}">
                        {{ ucfirst($sub->status) }}
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-{{ $settleInfo['badge'] ?? 'secondary' }}">
                        {{ $settleInfo['label'] ?? '—' }}
                      </span>
                    </td>
                    <td>
                      <div class="d-flex align-items-center gap-1 flex-wrap">
                        @if(($settleInfo['status'] ?? '') === 'eligible' && $sub->group)
                          <button type="button" class="btn btn-xs btn-primary btn-request-settlement"
                                  data-group-id="{{ $sub->group_id }}"
                                  data-member-id="{{ $sub->id }}"
                                  data-group-code="{{ $sub->group?->group_code ?? '—' }}"
                                  data-amount="₹{{ number_format($settleInfo['amount'] ?? 0, 2) }}">
                            <i class="ri-money-rupee-circle-line me-1"></i>Apply Settlement
                          </button>
                        @elseif(($settleInfo['status'] ?? '') === 'pending' && $sub->group)
                          <a href="{{ route('chit.settlements.confirm', [$sub->group_id, $sub->id]) }}" class="btn btn-xs btn-warning text-dark">
                            <i class="ri-check-line me-1"></i>Confirm Settlement
                          </a>
                        @elseif(($settleInfo['status'] ?? '') === 'done')
                          <span class="badge bg-label-success"><i class="ri-checkbox-circle-line me-1"></i>Completed</span>
                        @endif
                        <button type="button" class="btn btn-xs btn-outline-info ms-1" onclick="document.getElementById('tab-chit-{{ $sub->id }}-btn').click()" title="View Chit Details Tab">
                          <i class="ri-calendar-check-line me-1"></i>Chit Tab
                        </button>
                      </div>
                    </td>
                  </tr>
                  @empty
                  <tr>
                    <td colspan="10" class="text-center text-muted py-6">
                      <i class="icon-base ri ri-group-line" style="font-size: 2rem;"></i>
                      <p class="mb-0 mt-2">No chit group enrollments found</p>
                    </td>
                  </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Group Installments Card (Consolidated All Chits) -->
        <div id="chitGroupInstallmentsCard" class="card mb-6 shadow-sm">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <h5 class="mb-0 fw-bold">
                <i class="icon-base ri ri-calendar-check-line me-2 text-success"></i>Consolidated Installments Schedule
              </h5>
              <span class="badge bg-label-success rounded-pill px-3">{{ $installments->count() }} Total</span>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <!-- View Switcher Tabs -->
              <ul class="nav nav-pills nav-sm bg-light p-1 rounded border mb-0" id="scheduleViewPills" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link btn-xs py-1 px-3 active fw-medium" id="pills-groupwise-tab" data-bs-toggle="pill" data-bs-target="#pills-groupwise" type="button" role="tab" aria-controls="pills-groupwise" aria-selected="true">
                    <i class="ri-node-tree me-1"></i>Group-Wise View
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link btn-xs py-1 px-3 fw-medium" id="pills-timeline-tab" data-bs-toggle="pill" data-bs-target="#pills-timeline" type="button" role="tab" aria-controls="pills-timeline" aria-selected="false">
                    <i class="ri-list-check me-1"></i>Combined Timeline
                  </button>
                </li>
              </ul>
              
              <a href="{{ route('chit.installments.client', $client->getRouteKey()) }}" class="btn btn-sm btn-outline-primary ms-1">
                <i class="ri-eye-line me-1"></i>Full Schedule
              </a>
            </div>
          </div>
          
          <div class="card-body p-4">
            @php
              $installmentsByGroup = $installments->groupBy(function($inst) {
                return $inst->member_id ? ('member_' . $inst->member_id) : ('group_' . $inst->group_id);
              });
            @endphp

            <div class="tab-content p-0 bg-transparent border-0" id="scheduleViewTabContent">
              
              {{-- ── GROUP-WISE VIEW (DISPLAY ALL GROUPS AT A TIME) ── --}}
              <div class="tab-pane fade show active" id="pills-groupwise" role="tabpanel" aria-labelledby="pills-groupwise-tab">
                @forelse($installmentsByGroup as $groupKey => $groupInsts)
                  @php
                    $firstInst = $groupInsts->first();
                    $mGroup = $firstInst->group;
                    $mMember = $firstInst->member;
                    
                    $mFreq = $mGroup->installment_frequency ?? 'monthly';
                    $mCollFreq = $mMember->collection_frequency ?? 'monthly';
                    $mIsFreq = in_array($mCollFreq, ['daily', 'weekly'], true)
                        && ($mFreq === 'monthly' || $mFreq === '');
                    $mTableId = 'client-chits-inst-' . ($mGroup->id ?? 0) . '-' . ($mMember->id ?? 0);
                    $mDefaultMonth = 'all';
                    if ($mIsFreq) {
                        $firstUnpaid = $groupInsts->first(function ($fi) {
                            $bal = (float) ($fi->client_share_balance ?? $fi->balance);
                            return $fi->isCollectible() && $bal > 0.009;
                        });
                        $mDefaultMonth = $firstUnpaid
                            ? 'month-' . $firstUnpaid->month_number
                            : 'month-' . ($groupInsts->first()->month_number ?? 1);
                    }
                    $mTotalInsts = $groupInsts->count();
                    $mPaidInsts = $groupInsts->filter(fn($i) => in_array($i->display_status ?? $i->status, ['paid', 'waived'], true) || ($i->client_share_balance ?? $i->balance) <= 0.009)->count();
                    $mProgress = $mTotalInsts > 0 ? round(($mPaidInsts / $mTotalInsts) * 100) : 0;
                    
                    $mTotalShareAmount = $groupInsts->sum(fn($i) => (float) ($i->client_share_amount ?? $i->amount));
                    $mTotalPaid = $groupInsts->sum(fn($i) => (float) ($i->client_share_paid ?? $i->paid_amount));
                    $mTotalPenalty = $groupInsts->sum(fn($i) => (float) $i->penalty_amount);
                    $mTotalBalance = $groupInsts->sum(fn($i) => (float) ($i->client_share_balance ?? $i->balance));
                  @endphp

                  <div class="border rounded mb-4 overflow-hidden shadow-xs bg-white">
                    {{-- Group Header Banner --}}
                    <div class="bg-light px-4 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                      <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-primary fs-6 px-3 py-1.5">
                          <i class="ri-group-line me-1"></i>Group: {{ $mGroup?->group_code ?? 'N/A' }}
                        </span>
                        <span class="fw-bold text-dark fs-6">{{ $mGroup?->scheme?->name ?? 'Chit Scheme' }}</span>
                        
                        @if($mMember)
                          <span class="badge bg-label-dark">Member #{{ $mMember->display_member_number ?? '—' }}</span>
                          @if(isset($mMember->ownership_percentage) && $mMember->ownership_percentage < 100)
                            <span class="badge bg-label-info"><i class="ri-user-shared-line me-1"></i>Shared ({{ rtrim(rtrim(number_format($mMember->ownership_percentage, 2), '0'), '.') }}%)</span>
                          @endif
                        @endif
                        @if($mIsFreq)
                          <span class="badge bg-label-{{ $mCollFreq === 'daily' ? 'warning' : 'info' }}" style="font-size:.65rem;">
                            {{ $mCollFreq === 'daily' ? 'Daily' : 'Weekly' }} parts under each month
                          </span>
                        @endif
                      </div>

                      {{-- Group Summary Pills --}}
                      <div class="d-flex align-items-center gap-3 flex-wrap text-sm">
                        @if($mIsFreq)
                          <div class="d-flex align-items-center gap-1">
                            <label class="form-label mb-0 text-nowrap small fw-semibold text-muted">
                              <i class="ri-filter-3-line me-1"></i>{{ $mCollFreq === 'daily' ? 'Daily' : 'Weekly' }} by Month:
                            </label>
                            <select class="form-select form-select-sm member-inst-filter no-search py-1 px-2"
                                    style="min-width:200px;"
                                    data-table-id="{{ $mTableId }}"
                                    data-default-month="{{ $mDefaultMonth }}">
                              <option value="all" @selected($mDefaultMonth === 'all')>All Months ({{ $mTotalInsts }})</option>
                              <option value="unpaid">Pending &amp; Overdue</option>
                              <option value="paid">Paid</option>
                              @foreach($groupInsts as $filterInst)
                                @php
                                  $fDays = $filterInst->due_date ? (int) $filterInst->due_date->daysInMonth : 30;
                                  $fLabel = 'Month ' . $filterInst->month_number;
                                  if ($filterInst->due_date) {
                                      $fLabel .= ' — ' . $filterInst->due_date->format('M Y');
                                  }
                                  $fLabel .= $mCollFreq === 'daily' ? (' (' . $fDays . ' days)') : ' (4 weeks)';
                                  $fBal = (float) ($filterInst->client_share_balance ?? $filterInst->balance);
                                  $fPaid = (float) ($filterInst->client_share_paid ?? $filterInst->paid_amount);
                                  if ($fBal <= 0.009 && $fPaid > 0.009) {
                                      $fLabel .= ' ✓ Paid';
                                  }
                                @endphp
                                <option value="month-{{ $filterInst->month_number }}"
                                        @selected($mDefaultMonth === 'month-'.$filterInst->month_number)>
                                  {{ $fLabel }}
                                </option>
                              @endforeach
                            </select>
                          </div>
                          <div class="vr mx-1"></div>
                        @endif
                        <div>
                          <small class="text-muted d-block" style="font-size:0.75rem;">Progress</small>
                          <span class="fw-semibold text-dark">{{ $mPaidInsts }} / {{ $mTotalInsts }} Paid ({{ $mProgress }}%)</span>
                        </div>
                        <div class="vr mx-1"></div>
                        <div>
                          <small class="text-muted d-block" style="font-size:0.75rem;">Paid</small>
                          <span class="fw-bold text-success">₹{{ number_format($mTotalPaid, 2) }}</span>
                        </div>
                        <div class="vr mx-1"></div>
                        <div>
                          <small class="text-muted d-block" style="font-size:0.75rem;">Balance Due</small>
                          <span class="fw-bold {{ $mTotalBalance > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($mTotalBalance, 2) }}</span>
                        </div>
                        @if($mGroup)
                          <a href="{{ route('chit.groups.show', $mGroup->id) }}" class="btn btn-xs btn-outline-secondary ms-2" title="View Group Details">
                            <i class="ri-external-link-line me-1"></i>View Group
                          </a>
                        @endif
                      </div>
                    </div>

                    {{-- Group Installments Table --}}
                    <div class="table-responsive">
                      <table class="table table-hover table-bordered mb-0 align-middle" id="{{ $mTableId }}">
                        <thead class="table-light">
                          <tr>
                            @if($mIsFreq)
                            <th class="text-center" style="width:40px;">
                              <input type="checkbox" class="form-check-input chit-bulk-select-all" data-table-id="{{ $mTableId }}" title="Select all daily/weekly">
                            </th>
                            @endif
                            <th class="text-center" style="width:60px;">#</th>
                            <th>Period</th>
                            <th>Member #</th>
                            <th>Due Date</th>
                            <th class="text-end">Share Amount</th>
                            <th class="text-end">Penalty</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="min-width:180px;">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($groupInsts as $gIdx => $inst)
                            @php
                              $freq = $inst->group->installment_frequency ?? 'monthly';
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
                              $freqCount = 0;
                              $nextSuggested = $bVal;
                              if ($mIsFreq && $inst->member) {
                                  $sched = $inst->member->collectionPeriodSchedule(
                                      $inst->due_date,
                                      (float) ($inst->client_share_amount ?? $inst->amount),
                                      $paidVal
                                  );
                                  $freqCount = count($sched);
                                  $nextPart = collect($sched)->firstWhere('is_next', true);
                                  $nextSuggested = $nextPart
                                      ? (float) $nextPart['balance']
                                      : $inst->member->suggestedCollectionAmount(
                                          (float) ($inst->client_share_amount ?? $inst->amount),
                                          $bVal,
                                          $inst->due_date
                                      );
                              }
                              $rowHidden = $mIsFreq && $mDefaultMonth !== 'all' && $mDefaultMonth !== ('month-' . $inst->month_number);
                              $canBulk = $mIsFreq && $inst->isCollectible() && $bVal > 0.009;
                            @endphp
                            <tr class="inst-row"
                                data-month="month-{{ $inst->month_number }}"
                                data-status="{{ $displayStatus }}"
                                @if($rowHidden) style="display:none" @endif>
                              @if($mIsFreq)
                              <td class="text-center"></td>
                              @endif
                              <td class="text-center fw-semibold text-muted">{{ $gIdx + 1 }}</td>
                              <td class="fw-semibold text-primary">
                                {{ $periodLabel }}
                                @if($freqCount > 0)
                                  <div class="text-muted small fw-normal" style="font-size:.65rem;">
                                    {{ $freqCount }} {{ $mCollFreq === 'daily' ? 'days' : 'weeks' }}
                                  </div>
                                @endif
                              </td>
                              <td><span class="badge bg-label-dark">#{{ $inst->member->display_member_number ?? '—' }}</span></td>
                              <td>{{ $inst->due_date ? $inst->due_date->format('d-m-Y') : '—' }}</td>
                              <td class="text-end">₹{{ number_format((float) ($inst->client_share_amount ?? $inst->amount), 2) }}</td>
                              <td class="text-end {{ $inst->penalty_amount > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                              </td>
                              <td class="text-end {{ $paidVal > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                                {{ $paidVal > 0 ? '₹'.number_format($paidVal, 2) : '—' }}
                              </td>
                              <td class="text-end fw-semibold {{ $bVal > 0 ? 'text-danger' : 'text-success' }}">
                                ₹{{ number_format($bVal, 2) }}
                              </td>
                              <td class="text-center">
                                <span class="badge bg-label-{{ $statusBadge }}">{{ ucfirst($displayStatus) }}</span>
                                @if(in_array($displayStatus, ['paid', 'partial'], true) && !empty($inst->paid_datetime_formatted))
                                  <div class="mt-1 small text-muted" style="font-size: 0.72rem;" title="Paid Date & Time">
                                    <i class="ri-time-line me-1"></i>{{ $inst->paid_datetime_formatted }}
                                  </div>
                                @endif
                              </td>
                              <td class="text-center">
                                @include('admin.chit.installments.partials.installment-actions', [
                                  'inst' => $inst,
                                  'group' => $inst->group,
                                  'mode' => 'client',
                                  'client' => $client
                                ])
                              </td>
                            </tr>
                            @if($mIsFreq)
                              @include('admin.chit.installments.partials.frequency-period-rows', [
                                'inst' => $inst,
                                'group' => $inst->group,
                                'layout' => 'client_chits_group',
                                'rowHidden' => $rowHidden,
                                'viewingClientId' => $client->id,
                                'client' => $client,
                                'mode' => 'client',
                                'showBulkCheckbox' => true,
                              ])
                            @endif
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </div>
                @empty
                  <div class="text-center text-muted py-6">
                    <i class="icon-base ri ri-calendar-line" style="font-size: 2rem;"></i>
                    <p class="mb-0 mt-2">No chit group installments found for this client</p>
                  </div>
                @endforelse
              </div>

              {{-- ── COMBINED TIMELINE VIEW (CHRONOLOGICAL) ── --}}
              <div class="tab-pane fade" id="pills-timeline" role="tabpanel" aria-labelledby="pills-timeline-tab">
                <div class="table-responsive overflow-auto" style="max-height: 520px;">
                  <table class="table table-hover table-bordered mb-0 align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>S.No</th>
                        <th>Group Code</th>
                        <th>Period</th>
                        <th>Member #</th>
                        <th>Due Date</th>
                        <th class="text-end">Share Amount</th>
                        <th class="text-end">Penalty</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="min-width:180px;">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($installments as $idx => $inst)
                      @php
                        $freq = $inst->group->installment_frequency ?? 'monthly';
                        $displayStatus = $inst->display_status ?? $inst->status;
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
                      @endphp
                      <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                          @if($inst->group)
                            <a href="{{ route('chit.groups.show', $inst->group->id) }}" class="fw-semibold">
                              {{ $inst->group->group_code }}
                            </a>
                          @else
                            <span class="text-muted">N/A</span>
                          @endif
                        </td>
                        <td class="fw-semibold">{{ $periodLabel }}</td>
                        <td><span class="badge bg-label-dark">#{{ $inst->member->display_member_number ?? '—' }}</span></td>
                        <td>{{ $inst->due_date ? $inst->due_date->format('d-m-Y') : '—' }}</td>
                        <td class="text-end">₹{{ number_format((float) ($inst->client_share_amount ?? $inst->amount), 2) }}</td>
                        <td class="text-end {{ $inst->penalty_amount > 0 ? 'text-danger' : 'text-muted' }}">
                          {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                        </td>
                        <td class="text-end {{ ($inst->client_share_paid ?? $inst->paid_amount) > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                          {{ ($inst->client_share_paid ?? $inst->paid_amount) > 0 ? '₹'.number_format((float) ($inst->client_share_paid ?? $inst->paid_amount), 2) : '—' }}
                        </td>
                        <td class="text-end fw-semibold {{ ($inst->client_share_balance ?? $inst->balance) > 0 ? 'text-danger' : 'text-success' }}">
                          ₹{{ number_format((float) ($inst->client_share_balance ?? $inst->balance), 2) }}
                        </td>
                        <td class="text-center">
                          <span class="badge bg-label-{{ $statusBadge }}">{{ ucfirst($displayStatus) }}</span>
                          @if(in_array($displayStatus, ['paid', 'partial'], true) && !empty($inst->paid_datetime_formatted))
                            <div class="mt-1 small text-muted" style="font-size: 0.72rem;" title="Paid Date & Time">
                              <i class="ri-time-line me-1"></i>{{ $inst->paid_datetime_formatted }}
                            </div>
                          @endif
                        </td>
                        <td class="text-center">
                          @include('admin.chit.installments.partials.installment-actions', [
                            'inst' => $inst,
                            'group' => $inst->group,
                            'mode' => 'client',
                            'client' => $client
                          ])
                        </td>
                      </tr>
                      @empty
                      <tr>
                        <td colspan="11" class="text-center text-muted py-6">
                          <i class="icon-base ri ri-calendar-line" style="font-size: 2rem;"></i>
                          <p class="mb-0 mt-2">No chit group installments found for this client</p>
                        </td>
                      </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="row g-6 mb-6">
          <!-- Payouts / Winnings Column -->
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="icon-base ri ri-money-rupee-circle-line me-2 text-success"></i>Settlement / Auction Payouts</h5>
                <span class="badge bg-label-success rounded-pill">{{ $payouts->count() }} Total</span>
              </div>
              <div class="card-body">
                <div class="table-responsive overflow-auto" style="max-height: 480px;">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Group</th>
                        <th>Chit Value</th>
                        <th>Payout Amount</th>
                        <th>Status</th>
                        <th>Documents</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($payouts as $payout)
                      <tr>
                        <td><code>{{ $payout->payout_code }}</code></td>
                        <td>{{ $payout->group?->group_code ?? '—' }}</td>
                        <td>₹{{ number_format($payout->chit_value, 2) }}</td>
                        <td class="fw-semibold text-success">₹{{ number_format($payout->payout_amount, 2) }}</td>
                        <td>
                          <span class="badge bg-{{ $payout->status_badge }}">
                            {{ ucfirst($payout->status) }}
                          </span>
                        </td>
                        <td>
                          @if($payout->status === 'paid' && ($payout->settlement_document || $payout->other_document || $payout->collateral_document))
                            <div class="d-flex flex-wrap gap-1">
                              @if($payout->settlement_document)
                                <a href="{{ asset('storage/' . $payout->settlement_document) }}" target="_blank" class="btn btn-xs btn-outline-primary" title="Settlement Deed">
                                  <i class="ri-file-text-line"></i> Deed
                                </a>
                              @endif
                              @if($payout->collateral_document)
                                <a href="{{ asset('storage/' . $payout->collateral_document) }}" target="_blank" class="btn btn-xs btn-outline-warning" title="Collateral Document">
                                  <i class="ri-shield-check-line"></i> Collateral
                                </a>
                              @endif
                              @if($payout->other_document)
                                <a href="{{ asset('storage/' . $payout->other_document) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Other / Collateral Document">
                                  <i class="ri-folder-shield-2-line"></i> Other
                                </a>
                              @endif
                            </div>
                          @elseif($payout->status === 'paid')
                            <span class="text-muted small">No docs</span>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                      </tr>
                      @empty
                      <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                          No payout records found
                        </td>
                      </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <!-- Dividends Column -->
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="icon-base ri ri-percent-line me-2 text-warning"></i>Earned Dividends</h5>
                <span class="badge bg-label-warning rounded-pill">{{ $dividends->count() }} Total</span>
              </div>
              <div class="card-body">
                <div class="table-responsive overflow-auto" style="max-height: 480px;">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Group</th>
                        <th>Month</th>
                        <th>Amount</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      @forelse($dividends as $div)
                      <tr>
                        <td>{{ $div->dividend?->group?->group_code ?? '—' }}</td>
                        <td>Month {{ $div->dividend?->month_number ?? '—' }}</td>
                        <td class="fw-semibold text-primary">₹{{ number_format($div->amount, 2) }}</td>
                        <td>
                          <span class="badge bg-label-{{ $div->status === 'paid' ? 'success' : 'secondary' }}">
                            {{ ucfirst($div->status) }}
                          </span>
                        </td>
                      </tr>
                      @empty
                      <tr>
                        <td colspan="4" class="text-center text-muted py-5">
                          No dividends distributed yet
                        </td>
                      </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ── TAB 2+: INDIVIDUAL PER-CHIT TAB PANES ── --}}
      @foreach($enrollments as $index => $sub)
      @php
        $mGroup = $sub->group;
        $mInsts = $installments->where('member_id', $sub->id)->values();
        $mPayouts = $payouts->where('winner_member_id', $sub->id);
        $mDividends = $dividends->where('member_id', $sub->id);
        $ownPct = (float) ($sub->ownership_percentage ?? 100);
        $settleInfo = $sub->settlement_status_info;

        $mPaidCount = $mInsts->where('status', 'paid')->count();
        $mTotalCount = $mInsts->count();
        $mPaidSum = (float) $mInsts->sum('client_share_paid');
        $mBalanceSum = (float) $mInsts->sum('client_share_balance');
        $mTotalValue = (float) ($sub->share_of_chit ?? (($mGroup?->chit_value ?? 0) * $ownPct / 100));
        $mProgressPct = $mTotalCount > 0 ? round(($mPaidCount / $mTotalCount) * 100) : 0;
      @endphp
      <div class="tab-pane fade" id="tab-chit-{{ $sub->id }}" role="tabpanel" aria-labelledby="tab-chit-{{ $sub->id }}-btn">
        {{-- Individual Chit Subscription Overview Card --}}
        <div class="card mb-6 shadow-xs border-primary border-opacity-25">
          <div class="card-header bg-label-primary py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <h5 class="mb-0 text-primary">
                <i class="icon-base ri ri-community-line me-2"></i>Chit #{{ $index + 1 }}: {{ $mGroup?->group_code ?? 'Group' }}
                <span class="badge bg-primary ms-2">Member #{{ $sub->member_number }}</span>
              </h5>
              <small class="text-muted">{{ $mGroup?->scheme?->name ?? 'Chit Group' }} &nbsp;·&nbsp; Total Value: ₹{{ number_format($mGroup?->chit_value ?? 0) }}</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              @if(($settleInfo['status'] ?? '') === 'eligible' && $mGroup)
                <button type="button" class="btn btn-sm btn-primary btn-request-settlement"
                        data-group-id="{{ $sub->group_id }}"
                        data-member-id="{{ $sub->id }}"
                        data-group-code="{{ $mGroup->group_code ?? '—' }}"
                        data-amount="₹{{ number_format($settleInfo['amount'] ?? 0, 2) }}">
                  <i class="ri-money-rupee-circle-line me-1"></i>Apply Settlement (₹{{ number_format($settleInfo['amount'] ?? 0) }})
                </button>
              @elseif(($settleInfo['status'] ?? '') === 'pending' && $mGroup)
                <a href="{{ route('chit.settlements.confirm', [$sub->group_id, $sub->id]) }}" class="btn btn-sm btn-warning text-dark">
                  <i class="ri-check-line me-1"></i>Confirm Settlement
                </a>
              @elseif(($settleInfo['status'] ?? '') === 'done')
                <span class="badge bg-success p-2"><i class="ri-checkbox-circle-line me-1"></i>Settlement Completed</span>
              @endif

              @if($mGroup)
                <a href="{{ route('chit.groups.show', $mGroup->id) }}" class="btn btn-sm btn-outline-primary">
                  <i class="ri-external-link-line me-1"></i>Group View
                </a>
              @endif
            </div>
          </div>
          <div class="card-body py-4">
            <div class="row g-4 mb-3">
              <div class="col-md-3 col-6">
                <small class="text-muted d-block">Your Share %</small>
                <span class="fs-6 fw-bold text-dark">{{ rtrim(rtrim(number_format($ownPct, 2), '0'), '.') }}%</span>
                @if($sub->is_shared)
                  <span class="badge bg-label-primary ms-1">Shared</span>
                @endif
              </div>
              <div class="col-md-3 col-6">
                <small class="text-muted d-block">Client Chit Share Value</small>
                <span class="fs-6 fw-bold text-primary">₹{{ number_format($mTotalValue, 2) }}</span>
              </div>
              <div class="col-md-3 col-6">
                <small class="text-muted d-block">Installment Progress</small>
                <span class="fs-6 fw-semibold text-dark">{{ $mPaidCount }} of {{ $mTotalCount }} Paid ({{ $mProgressPct }}%)</span>
              </div>
              <div class="col-md-3 col-6">
                <small class="text-muted d-block">Settlement Status</small>
                <span class="badge bg-{{ $settleInfo['badge'] ?? 'secondary' }} fs-6">
                  {{ $settleInfo['label'] ?? '—' }}
                </span>
              </div>
            </div>

            <div class="progress mt-2" style="height: 8px;">
              <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $mProgressPct }}%" aria-valuenow="{{ $mProgressPct }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
        </div>

        {{-- Individual Chit Installments Table --}}
        @php
          $tabCollFreq = $sub->collection_frequency ?? 'monthly';
          $tabGroupFreq = $mGroup->installment_frequency ?? 'monthly';
          $tabIsFreq = in_array($tabCollFreq, ['daily', 'weekly'], true)
              && ($tabGroupFreq === 'monthly' || $tabGroupFreq === '');
          $tabTableId = 'client-chit-tab-inst-' . ($sub->id ?? 0);
          $tabDefaultMonth = 'all';
          if ($tabIsFreq) {
              $tabFirstUnpaid = $mInsts->first(function ($fi) {
                  $bal = (float) ($fi->client_share_balance ?? $fi->balance);
                  return $fi->isCollectible() && $bal > 0.009;
              });
              $tabDefaultMonth = $tabFirstUnpaid
                  ? 'month-' . $tabFirstUnpaid->month_number
                  : 'month-' . ($mInsts->first()->month_number ?? 1);
          }
        @endphp
        <div class="card mb-6">
          <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h5 class="mb-0"><i class="icon-base ri ri-calendar-check-line me-2 text-success"></i>Installments Schedule — {{ $mGroup?->group_code }}</h5>
            <div class="d-flex align-items-center gap-2 flex-wrap">
              @if($tabIsFreq)
                <span class="badge bg-label-{{ $tabCollFreq === 'daily' ? 'warning' : 'info' }}" style="font-size:.65rem;">
                  {{ $tabCollFreq === 'daily' ? 'Daily' : 'Weekly' }} parts
                </span>
                <select class="form-select form-select-sm member-inst-filter no-search py-1 px-2"
                        style="min-width:200px;"
                        data-table-id="{{ $tabTableId }}"
                        data-default-month="{{ $tabDefaultMonth }}">
                  <option value="all" @selected($tabDefaultMonth === 'all')>All Months ({{ $mInsts->count() }})</option>
                  <option value="unpaid">Pending &amp; Overdue</option>
                  <option value="paid">Paid</option>
                  @foreach($mInsts as $filterInst)
                    @php
                      $tfDays = $filterInst->due_date ? (int) $filterInst->due_date->daysInMonth : 30;
                      $tfLabel = 'Month ' . $filterInst->month_number;
                      if ($filterInst->due_date) {
                          $tfLabel .= ' — ' . $filterInst->due_date->format('M Y');
                      }
                      $tfLabel .= $tabCollFreq === 'daily' ? (' (' . $tfDays . ' days)') : ' (4 weeks)';
                      $tfBal = (float) ($filterInst->client_share_balance ?? $filterInst->balance);
                      $tfPaid = (float) ($filterInst->client_share_paid ?? $filterInst->paid_amount);
                      if ($tfBal <= 0.009 && $tfPaid > 0.009) {
                          $tfLabel .= ' ✓ Paid';
                      }
                    @endphp
                    <option value="month-{{ $filterInst->month_number }}"
                            @selected($tabDefaultMonth === 'month-'.$filterInst->month_number)>
                      {{ $tfLabel }}
                    </option>
                  @endforeach
                </select>
              @endif
              <span class="badge bg-label-success rounded-pill">{{ $mInsts->count() }} Months</span>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive overflow-auto" style="max-height: 480px;">
              <table class="table table-hover table-bordered mb-0" id="{{ $tabTableId }}">
                <thead class="table-light">
                  <tr>
                    @if($tabIsFreq)
                    <th class="text-center" style="width:40px;">
                      <input type="checkbox" class="form-check-input chit-bulk-select-all" data-table-id="{{ $tabTableId }}" title="Select all daily/weekly">
                    </th>
                    @endif
                    <th>S.No</th>
                    <th>Period</th>
                    <th>Due Date</th>
                    <th class="text-end">Share Amount</th>
                    <th class="text-end">Penalty</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Balance</th>
                    <th class="text-end">Dividend</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="min-width:180px;">Action</th>
                  </tr>
                </thead>
                <tbody>
                  @forelse($mInsts as $idx => $inst)
                  @php
                    $freq = $inst->group->installment_frequency ?? 'monthly';
                    $displayStatus = $inst->display_status ?? $inst->status;
                    $bVal = (float) ($inst->client_share_balance ?? $inst->balance);
                    $paidVal = (float) ($inst->client_share_paid ?? $inst->paid_amount);
                    $divAmt = $mGroup ? $mGroup->getMonthlyDividendAmount((int) $inst->month_number, $sub, (int) $client->id) : 0;
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
                    $tabRowHidden = $tabIsFreq && $tabDefaultMonth !== 'all' && $tabDefaultMonth !== ('month-' . $inst->month_number);
                    $tabNextSuggested = $bVal;
                    if ($tabIsFreq && $inst->member) {
                        $tabSched = $inst->member->collectionPeriodSchedule(
                            $inst->due_date,
                            (float) ($inst->client_share_amount ?? $inst->amount),
                            $paidVal
                        );
                        $tabNext = collect($tabSched)->firstWhere('is_next', true);
                        $tabNextSuggested = $tabNext
                            ? (float) $tabNext['balance']
                            : $inst->member->suggestedCollectionAmount(
                                (float) ($inst->client_share_amount ?? $inst->amount),
                                $bVal,
                                $inst->due_date
                            );
                    }
                    $tabCanBulk = $tabIsFreq && $inst->isCollectible() && $bVal > 0.009;
                  @endphp
                  <tr class="inst-row"
                      data-month="month-{{ $inst->month_number }}"
                      data-status="{{ $displayStatus }}"
                      @if($tabRowHidden) style="display:none" @endif>
                    @if($tabIsFreq)
                    <td class="text-center"></td>
                    @endif
                    <td>{{ $idx + 1 }}</td>
                    <td class="fw-semibold">{{ $periodLabel }}</td>
                    <td>{{ $inst->due_date ? $inst->due_date->format('d-m-Y') : '—' }}</td>
                    <td class="text-end">₹{{ number_format((float) ($inst->client_share_amount ?? $inst->amount), 2) }}</td>
                    <td class="text-end {{ $inst->penalty_amount > 0 ? 'text-danger' : 'text-muted' }}">
                      {{ $inst->penalty_amount > 0 ? '₹'.number_format($inst->penalty_amount, 2) : '—' }}
                    </td>
                    <td class="text-end {{ $paidVal > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                      {{ $paidVal > 0 ? '₹'.number_format($paidVal, 2) : '—' }}
                    </td>
                    <td class="text-end fw-semibold {{ $bVal > 0 ? 'text-danger' : 'text-success' }}">
                      ₹{{ number_format($bVal, 2) }}
                    </td>
                    <td class="text-end fw-semibold {{ $divAmt > 0 ? 'text-warning' : 'text-muted' }}">
                      {{ $divAmt > 0 ? '₹' . number_format($divAmt, 2) : '—' }}
                    </td>
                    <td class="text-center">
                      <span class="badge bg-label-{{ $statusBadge }}">{{ ucfirst($displayStatus) }}</span>
                      @if(in_array($displayStatus, ['paid', 'partial'], true) && !empty($inst->paid_datetime_formatted))
                        <div class="mt-1 small text-muted" style="font-size: 0.72rem;" title="Paid Date & Time">
                          <i class="ri-time-line me-1"></i>{{ $inst->paid_datetime_formatted }}
                        </div>
                      @endif
                    </td>
                    <td class="text-center">
                      @include('admin.chit.installments.partials.installment-actions', [
                        'inst' => $inst,
                        'group' => $inst->group,
                        'mode' => 'client',
                        'client' => $client
                      ])
                    </td>
                  </tr>
                  @if($tabIsFreq)
                    @include('admin.chit.installments.partials.frequency-period-rows', [
                      'inst' => $inst,
                      'group' => $inst->group,
                      'layout' => 'client_chits_member',
                      'rowHidden' => $tabRowHidden,
                      'viewingClientId' => $client->id,
                      'client' => $client,
                      'mode' => 'client',
                      'showBulkCheckbox' => true,
                    ])
                  @endif
                  @empty
                  <tr>
                    <td colspan="{{ $tabIsFreq ? 10 : 9 }}" class="text-center text-muted py-6">
                      <i class="icon-base ri ri-calendar-line" style="font-size: 2rem;"></i>
                      <p class="mb-0 mt-2">No installments generated for this chit group membership</p>
                    </td>
                  </tr>
                  @endforelse
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {{-- Individual Chit Payouts & Dividends Card --}}
        @if($mPayouts->count() > 0 || $mDividends->count() > 0)
        <div class="row g-6 mb-6">
          @if($mPayouts->count() > 0)
          <div class="col-lg-6">
            <div class="card h-100">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="icon-base ri ri-money-rupee-circle-line me-2 text-success"></i>Settlement / Auction Payouts</h5>
                <span class="badge bg-label-success rounded-pill">{{ $mPayouts->count() }} Total</span>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Chit Value</th>
                        <th>Payout Amount</th>
                        <th>Status</th>
                        <th>Documents</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($mPayouts as $payout)
                      <tr>
                        <td><code>{{ $payout->payout_code }}</code></td>
                        <td>₹{{ number_format($payout->chit_value, 2) }}</td>
                        <td class="fw-semibold text-success">₹{{ number_format($payout->payout_amount, 2) }}</td>
                        <td><span class="badge bg-{{ $payout->status_badge }}">{{ ucfirst($payout->status) }}</span></td>
                        <td>
                          @if($payout->status === 'paid' && ($payout->settlement_document || $payout->other_document || $payout->collateral_document))
                            <div class="d-flex flex-wrap gap-1">
                              @if($payout->settlement_document)
                                <a href="{{ asset('storage/' . $payout->settlement_document) }}" target="_blank" class="btn btn-xs btn-outline-primary" title="Settlement Deed">
                                  <i class="ri-file-text-line"></i> Deed
                                </a>
                              @endif
                              @if($payout->collateral_document)
                                <a href="{{ asset('storage/' . $payout->collateral_document) }}" target="_blank" class="btn btn-xs btn-outline-warning" title="Collateral Document">
                                  <i class="ri-shield-check-line"></i> Collateral
                                </a>
                              @endif
                              @if($payout->other_document)
                                <a href="{{ asset('storage/' . $payout->other_document) }}" target="_blank" class="btn btn-xs btn-outline-secondary" title="Other / Collateral Document">
                                  <i class="ri-folder-shield-2-line"></i> Other
                                </a>
                              @endif
                            </div>
                          @elseif($payout->status === 'paid')
                            <span class="text-muted small">No docs</span>
                          @else
                            <span class="text-muted">—</span>
                          @endif
                        </td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          @endif

          @if($mDividends->count() > 0)
          <div class="{{ $mPayouts->count() > 0 ? 'col-lg-6' : 'col-12' }}">
            <div class="card h-100">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="icon-base ri ri-percent-line me-2 text-warning"></i>Earned Dividends</h5>
                <span class="badge bg-label-warning rounded-pill">{{ $mDividends->count() }} Total</span>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Month</th>
                        <th>Amount</th>
                        <th>Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($mDividends as $div)
                      <tr>
                        <td>Month {{ $div->dividend?->month_number ?? '—' }}</td>
                        <td class="fw-semibold text-primary">₹{{ number_format($div->amount, 2) }}</td>
                        <td><span class="badge bg-label-{{ $div->status === 'paid' ? 'success' : 'secondary' }}">{{ ucfirst($div->status) }}</span></td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          @endif
        </div>
        @endif
      </div>
      @endforeach
    </div>

  </div>
</div>

{{-- Apply for Settlement Modal --}}
<div class="modal fade" id="modalApplySettlement" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title text-white mb-0"><i class="ri-money-rupee-circle-line me-2"></i>Apply for Chit Settlement</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formApplySettlement" method="POST" action="{{ route('chit.settlements.apply') }}">
        @csrf
        <div class="modal-body p-4">
          <input type="hidden" id="settlement_group_id" name="group_id">
          <input type="hidden" id="settlement_member_id" name="member_id">
          <input type="hidden" name="payout_kind" value="original">

          <div class="mb-3">
            <label class="form-label text-muted mb-1">Client Name</label>
            <div class="fw-bold fs-6">{{ $client->client_name }}</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted mb-1">Chit Group</label>
            <div class="fw-bold fs-6 text-primary" id="settlement_group_code">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label text-muted mb-1">Estimated Settlement Amount</label>
            <div class="fw-bold fs-4 text-success" id="settlement_display_amount">—</div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="settlement_remarks">Remarks / Notes (Optional)</label>
            <textarea name="remarks" id="settlement_remarks" class="form-control" rows="2" placeholder="e.g. Settlement requested by client"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitSettlementApp">
            <i class="ri-send-plane-line me-1"></i> Submit Settlement Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

@include('admin.chit.installments.partials.collect-modals')

@endsection

@section('page-script')
@vite(['resources/assets/custom-js/bank-payment-fields.js', 'resources/assets/custom-js/chit-installments.js'])
<script>
window.chitBulkCollectUrl = @json(route('chit.installments.bulk-collect'));
window.chitPartialPaymentConfig = @json($partialPaymentConfig ?? ['is_active' => false]);
function copyChitPublicLink() {
    const link = @json($chitPublicLink);
    function showSuccess() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Link Copied!',
                text: 'Public chit schedule link has been copied to clipboard.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            alert('Link copied: ' + link);
        }
    }
    function fallback() {
        try {
            const input = document.createElement('input');
            input.value = link;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showSuccess();
        } catch (err) {
            prompt('Copy this link:', link);
        }
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(link).then(showSuccess).catch(fallback);
    } else {
        fallback();
    }
}

$(function () {
  $(document).on('click', '.btn-request-settlement', function () {
    const groupId = $(this).data('group-id');
    const memberId = $(this).data('member-id');
    const groupCode = $(this).data('group-code');
    const amount = $(this).data('amount');

    $('#settlement_group_id').val(groupId);
    $('#settlement_member_id').val(memberId);
    $('#settlement_group_code').text(groupCode);
    $('#settlement_display_amount').text(amount);

    const modalEl = document.getElementById('modalApplySettlement');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
  });

  $('#formApplySettlement').on('submit', function (e) {
    e.preventDefault();
    const btn = $('#btnSubmitSettlementApp');
    const origHtml = btn.html();
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

    $.ajax({
      url: $(this).attr('action'),
      type: 'POST',
      data: $(this).serialize(),
      success: function (res) {
        if (res.success) {
          Swal.fire({
            icon: 'success',
            title: 'Settlement Requested',
            text: res.message,
            timer: 2000,
            showConfirmButton: false
          }).then(function () {
            window.location.href = res.redirect_url || window.location.href;
          });
        } else {
          Swal.fire({ icon: 'error', title: 'Error', text: res.message });
        }
      },
      error: function (xhr) {
        const msg = xhr.responseJSON?.message || 'Failed to submit settlement request.';
        Swal.fire({ icon: 'error', title: 'Error', text: msg });
      },
      complete: function () {
        btn.prop('disabled', false).html(origHtml);
      }
    });
  });
});
</script>
@endsection
