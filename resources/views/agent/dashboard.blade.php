@extends('layouts/layoutMaster')

@section('title', 'Agent Dashboard')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/apex-charts/apex-charts.scss',
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
])
@endsection

@section('content')
@php
  $activeTab = $activeTab ?? 'loan';
@endphp

<div class="row mb-4">
  <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h4 class="mb-0 text-heading">Agent Dashboard</h4>
      <p class="text-muted mb-0">
        Welcome back, <span class="fw-semibold text-primary">{{ auth()->user()->name }}</span>
        <span class="badge bg-label-primary ms-1">{{ auth()->user()->agent?->agent_code ?? 'Agent' }}</span>
      </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @php
        $menuAccess = app(\App\Services\MenuAccessService::class);
        $authUser = auth()->user();
        $canCollections = $menuAccess->userCanAccessMenuKey($authUser, 'agent-collections')
            || $menuAccess->userCanAccessUrl($authUser, 'app/agents/agent-collections');
        $canClients = $menuAccess->userCanAccessMenuKey($authUser, 'client-management')
            || $menuAccess->userCanAccessUrl($authUser, 'client-management');
      @endphp
      @if($canCollections)
      <a href="{{ route('agent-collections') }}" class="btn btn-primary btn-sm">
        <i class="icon-base ri ri-hand-coin-line me-1"></i> Collections
      </a>
      @endif
      @if($canClients)
      <a href="{{ route('client-management') }}" class="btn btn-outline-primary btn-sm">
        <i class="icon-base ri ri-team-line me-1"></i> My Clients
      </a>
      <a href="{{ route('client-management-add') }}" class="btn btn-outline-secondary btn-sm">
        <i class="icon-base ri ri-user-add-line me-1"></i> Add Client
      </a>
      @endif
    </div>
  </div>
</div>

<ul class="nav nav-pills mb-4" role="tablist">
  <li class="nav-item" role="presentation">
    <button type="button"
      class="nav-link {{ $activeTab === 'loan' ? 'active' : '' }}"
      id="loan-dashboard-tab"
      data-bs-toggle="tab"
      data-bs-target="#loan-dashboard-pane"
      role="tab"
      aria-controls="loan-dashboard-pane"
      aria-selected="{{ $activeTab === 'loan' ? 'true' : 'false' }}">
      <i class="ri-bank-line me-1"></i> Loan Dashboard
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button type="button"
      class="nav-link {{ $activeTab === 'chit' ? 'active' : '' }}"
      id="chit-dashboard-tab"
      data-bs-toggle="tab"
      data-bs-target="#chit-dashboard-pane"
      role="tab"
      aria-controls="chit-dashboard-pane"
      aria-selected="{{ $activeTab === 'chit' ? 'true' : 'false' }}">
      <i class="ri-group-line me-1"></i> Chit Dashboard
    </button>
  </li>
</ul>

<div class="tab-content">
  {{-- ===================== LOAN TAB ===================== --}}
  <div class="tab-pane fade {{ $activeTab === 'loan' ? 'show active' : '' }}" id="loan-dashboard-pane" role="tabpanel" aria-labelledby="loan-dashboard-tab" tabindex="0">

    <div class="row g-6 mb-6">
      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-primary h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Clients Added</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $loanStats['total_clients'] }}</h4>
                </div>
                <small class="text-muted">Assigned & added by you</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-primary rounded-3">
                  <i class="icon-base ri ri-user-add-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-success h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Active Loans</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $loanStats['active_loans'] }}</h4>
                </div>
                <small class="text-muted">Under your clients</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-success rounded-3">
                  <i class="icon-base ri ri-bank-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-warning h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Today Followups</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $loanStats['today_followups'] ?? $loanStats['pending_followups'] }}</h4>
                </div>
                <small class="text-muted">Today</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-warning rounded-3">
                  <i class="icon-base ri ri-calendar-todo-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-danger h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Overdue / Upcoming</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $loanStats['overdue_emis'] }}</h4>
                </div>
                <small class="text-muted">EMIs need attention</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-danger rounded-3">
                  <i class="icon-base ri ri-error-warning-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
              <i class="icon-base ri ri-notification-3-line text-primary"></i>
              <span>Today Follow-ups</span>
              <span class="badge bg-label-warning fs-7">Today</span>
            </h5>
            <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-primary">
              <i class="icon-base ri ri-hand-coin-line me-1"></i> View Collections
            </a>
          </div>
          <div class="card-datatable table-responsive">
            <table class="table table-hover text-nowrap mb-0">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Loan A/C</th>
                  <th>EMI Due</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($upcomingFollowups as $followup)
                @php
                  $client = $followup->emi?->loanAccount?->client;
                  $loanAccount = $followup->emi?->loanAccount;
                  $dueDate = $followup->emi?->due_date ? \Carbon\Carbon::parse($followup->emi->due_date) : null;
                  $statusColor = $followup->status === 'assigned' ? 'warning' : 'info';
                @endphp
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                          {{ strtoupper(substr($client?->client_name ?? 'C', 0, 1)) }}
                        </span>
                      </div>
                      <div>
                        <div class="fw-semibold text-heading">{{ $client?->client_name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $client?->client_phone ?? '—' }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-label-secondary">
                      {{ $loanAccount?->account_number ?? $loanAccount?->customer_loan_account_number ?? 'N/A' }}
                    </span>
                  </td>
                  <td>
                    @if($dueDate)
                      <span class="fw-medium">{{ $dueDate->format('d-m-Y') }}</span>
                      @if($dueDate->isToday())
                        <br><small class="text-danger fw-semibold">Due Today</small>
                      @else
                        <br><small class="text-muted">{{ $dueDate->diffForHumans() }}</small>
                      @endif
                    @else
                      <span class="text-muted">N/A</span>
                    @endif
                  </td>
                  <td>
                    <span class="fw-semibold">₹{{ number_format((float) ($followup->emi?->total_amount ?? 0), 2) }}</span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $statusColor }}">{{ ucfirst($followup->status) }}</span>
                  </td>
                  <td class="text-center">
                    <a href="{{ route('agent-collections') }}?emi_id={{ $followup->emi?->id }}"
                      class="btn btn-sm btn-icon btn-text-primary rounded-pill"
                      title="Collect Payment">
                      <i class="icon-base ri ri-hand-coin-line icon-22px"></i>
                    </a>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="6" class="text-center py-5">
                    <div class="text-muted">
                      <i class="icon-base ri ri-inbox-line icon-48px mb-2 d-block opacity-50"></i>
                      No follow-ups for today.
                    </div>
                  </td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-6">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
              <i class="icon-base ri ri-user-add-line text-success"></i>
              <span>Recent Clients</span>
            </h5>
            <div class="d-flex flex-wrap gap-2">
              <a href="{{ route('client-management') }}" class="btn btn-sm btn-label-success">View All</a>
              <a href="{{ route('client-management-add') }}" class="btn btn-sm btn-primary">
                <i class="icon-base ri ri-user-add-line me-1"></i> Add New Client
              </a>
            </div>
          </div>
          <div class="card-datatable table-responsive">
            <table class="table table-hover text-nowrap mb-0">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Phone</th>
                  <th>Status</th>
                  <th>Added</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentClients as $client)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar avatar-sm">
                        @if(!empty($client->profile_image_url))
                          <img src="{{ $client->profile_image_url }}" alt="{{ $client->client_name }}" class="rounded-circle">
                        @else
                          <span class="avatar-initial rounded-circle bg-label-primary">
                            {{ strtoupper(substr($client->client_name, 0, 1)) }}
                          </span>
                        @endif
                      </div>
                      <span class="fw-semibold text-heading">{{ $client->client_name }}</span>
                    </div>
                  </td>
                  <td>{{ $client->client_phone ?? '—' }}</td>
                  <td>
                    <span class="badge bg-label-{{ $client->status === 'active' ? 'success' : 'warning' }}">
                      {{ ucfirst($client->status) }}
                    </span>
                  </td>
                  <td>
                    <small class="text-muted">
                      {{ $client->created_at ? $client->created_at->format('d-m-Y') : '—' }}
                    </small>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="4" class="text-center py-5">
                    <div class="text-muted">
                      <i class="icon-base ri ri-user-line icon-48px mb-2 d-block opacity-50"></i>
                      No clients added yet.
                    </div>
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

  {{-- ===================== CHIT TAB ===================== --}}
  <div class="tab-pane fade {{ $activeTab === 'chit' ? 'show active' : '' }}" id="chit-dashboard-pane" role="tabpanel" aria-labelledby="chit-dashboard-tab" tabindex="0">

    <div class="row g-6 mb-6">
      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-primary h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Active Members</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $chitStats['active_members'] }}</h4>
                </div>
                <small class="text-muted">Your assigned clients</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-primary rounded-3">
                  <i class="icon-base ri ri-team-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-info h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Active Groups</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $chitStats['active_groups'] }}</h4>
                </div>
                <small class="text-muted">Groups with your clients</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-info rounded-3">
                  <i class="icon-base ri ri-group-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-warning h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Today Installments</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $chitStats['today_installments'] ?? $chitStats['pending_installments'] }}</h4>
                </div>
                <small class="text-muted">Today</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-warning rounded-3">
                  <i class="icon-base ri ri-calendar-check-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="card card-border-shadow-danger h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between">
              <div class="me-1">
                <p class="mb-0 h6 fw-normal text-heading">Overdue Installments</p>
                <div class="d-flex align-items-center">
                  <h4 class="mb-1 me-2">{{ $chitStats['overdue_installments'] }}</h4>
                </div>
                <small class="text-muted">Past due date</small>
              </div>
              <div class="avatar">
                <div class="avatar-initial bg-label-danger rounded-3">
                  <i class="icon-base ri ri-error-warning-line icon-26px"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-6 mb-6">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
              <i class="icon-base ri ri-calendar-schedule-line text-info"></i>
              <span>Today Chit Installments</span>
              <span class="badge bg-label-info fs-7">Today</span>
            </h5>
            <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-info">
              <i class="icon-base ri ri-hand-coin-line me-1"></i> Collect Chit
            </a>
          </div>
          <div class="card-datatable table-responsive">
            <table class="table table-hover text-nowrap mb-0">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Group</th>
                  <th>Installment</th>
                  <th>Due Date</th>
                  <th>Balance</th>
                  <th>Status</th>
                  <th class="text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                @forelse($upcomingChitInstallments as $inst)
                @php
                  $client = $inst->member?->client;
                  $groupCode = $inst->group?->group_code ?? 'N/A';
                  $dueDate = $inst->due_date ? \Carbon\Carbon::parse($inst->due_date) : null;
                  $statusColor = match ($inst->status) {
                    'overdue' => 'danger',
                    'partial' => 'warning',
                    default => 'secondary',
                  };
                @endphp
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-info">
                          {{ strtoupper(substr($client?->client_name ?? 'C', 0, 1)) }}
                        </span>
                      </div>
                      <div>
                        <div class="fw-semibold text-heading">{{ $client?->client_name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $client?->client_phone ?? '—' }}</small>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge bg-label-secondary">{{ $groupCode }}</span></td>
                  <td><span class="badge bg-label-info">Inst #{{ $inst->month_number }}</span></td>
                  <td>
                    @if($dueDate)
                      <span class="fw-medium">{{ $dueDate->format('d-m-Y') }}</span>
                      @if($dueDate->isToday())
                        <br><small class="text-danger fw-semibold">Due Today</small>
                      @else
                        <br><small class="text-muted">{{ $dueDate->diffForHumans() }}</small>
                      @endif
                    @else
                      <span class="text-muted">N/A</span>
                    @endif
                  </td>
                  <td>
                    <span class="fw-semibold">₹{{ number_format((float) $inst->balance, 2) }}</span>
                  </td>
                  <td>
                    <span class="badge bg-label-{{ $statusColor }}">{{ ucfirst($inst->status) }}</span>
                  </td>
                  <td class="text-center">
                    <a href="{{ route('agent-collections') }}"
                      class="btn btn-sm btn-icon btn-text-info rounded-pill"
                      title="Go to Collections">
                      <i class="icon-base ri ri-hand-coin-line icon-22px"></i>
                    </a>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="7" class="text-center py-5">
                    <div class="text-muted">
                      <i class="icon-base ri ri-inbox-line icon-48px mb-2 d-block opacity-50"></i>
                      No chit installments for today.
                    </div>
                  </td>
                </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-6">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 border-bottom py-3">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
              <i class="icon-base ri ri-group-line text-success"></i>
              <span>Recent Chit Members</span>
            </h5>
            <a href="{{ route('agent-collections') }}" class="btn btn-sm btn-label-success">View Collections</a>
          </div>
          <div class="card-datatable table-responsive">
            <table class="table table-hover text-nowrap mb-0">
              <thead>
                <tr>
                  <th>Client</th>
                  <th>Group</th>
                  <th>Member No</th>
                  <th>Status</th>
                  <th>Joined</th>
                </tr>
              </thead>
              <tbody>
                @forelse($recentChitMembers as $member)
                @php
                  $client = $member->client;
                @endphp
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="avatar avatar-sm">
                        <span class="avatar-initial rounded-circle bg-label-success">
                          {{ strtoupper(substr($client?->client_name ?? 'C', 0, 1)) }}
                        </span>
                      </div>
                      <div>
                        <div class="fw-semibold text-heading">{{ $client?->client_name ?? 'N/A' }}</div>
                        <small class="text-muted">{{ $client?->client_phone ?? '—' }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge bg-label-secondary">{{ $member->group?->group_code ?? 'N/A' }}</span>
                  </td>
                  <td>{{ $member->member_number ?? '—' }}</td>
                  <td>
                    <span class="badge bg-label-{{ in_array($member->status, ['active', 'approved']) ? 'success' : 'warning' }}">
                      {{ ucfirst($member->status) }}
                    </span>
                  </td>
                  <td>
                    <small class="text-muted">
                      {{ $member->joined_date ? $member->joined_date->format('d-m-Y') : ($member->created_at ? $member->created_at->format('d-m-Y') : '—') }}
                    </small>
                  </td>
                </tr>
                @empty
                <tr>
                  <td colspan="5" class="text-center py-5">
                    <div class="text-muted">
                      <i class="icon-base ri ri-group-line icon-48px mb-2 d-block opacity-50"></i>
                      No chit members found for your clients.
                    </div>
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
@endsection
