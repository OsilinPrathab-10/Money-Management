@extends('layouts/layoutMaster')

@section('title', 'Client View - Notifications')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('page-style')
<style>
  .notif-card {
    transition: all 0.2s ease-in-out;
    border: 1px solid rgba(0, 0, 0, 0.08);
  }
  .notif-card:hover {
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
  }
  .notif-card.unread-card {
    border-left: 4px solid var(--bs-primary) !important;
    background: rgba(var(--bs-primary-rgb), 0.02);
  }
  .notif-icon-box {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 1.25rem;
    flex-shrink: 0;
  }
  .notif-meta-pill {
    font-size: 0.76rem;
    padding: 2px 8px;
    border-radius: 12px;
    background-color: rgba(0, 0, 0, 0.04);
    color: var(--bs-body-color);
  }
  .json-viewer-box {
    background-color: #1e1e2d;
    color: #a6accd;
    border-radius: 8px;
    padding: 14px;
    font-family: monospace;
    font-size: 0.84rem;
    max-height: 250px;
    overflow-y: auto;
  }
</style>
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('content')

@php
  $getNotifMeta = function($type) {
    $t = strtolower(trim((string)$type));
    return match($t) {
      'kyc-approved', 'kyc_approved' => [
        'label' => 'KYC Approved',
        'badge' => 'success',
        'icon'  => 'ri-shield-check-line',
      ],
      'kyc-rejected', 'kyc_rejected' => [
        'label' => 'KYC Rejected',
        'badge' => 'danger',
        'icon'  => 'ri-shield-cross-line',
      ],
      'disbursed', 'loan_disbursed' => [
        'label' => 'Loan Disbursed',
        'badge' => 'success',
        'icon'  => 'ri-money-dollar-circle-line',
      ],
      'loan_application', 'new_loan_application' => [
        'label' => 'Loan Application',
        'badge' => 'primary',
        'icon'  => 'ri-file-list-3-line',
      ],
      'loan_approved', 'loan_application_approved' => [
        'label' => 'Loan Approved',
        'badge' => 'info',
        'icon'  => 'ri-checkbox-circle-line',
      ],
      'loan_rejected', 'loan_application_rejected' => [
        'label' => 'Loan Rejected',
        'badge' => 'danger',
        'icon'  => 'ri-close-circle-line',
      ],
      'emi_payment', 'payment_received' => [
        'label' => 'EMI Payment',
        'badge' => 'success',
        'icon'  => 'ri-wallet-3-line',
      ],
      'emi_due', 'emi_reminder' => [
        'label' => 'EMI Reminder',
        'badge' => 'warning',
        'icon'  => 'ri-alarm-warning-line',
      ],
      'chit_installment' => [
        'label' => 'Chit Installment',
        'badge' => 'info',
        'icon'  => 'ri-coins-line',
      ],
      'chit_approved', 'chit_application_approved' => [
        'label' => 'Chit Approved',
        'badge' => 'success',
        'icon'  => 'ri-group-line',
      ],
      'chit_application', 'new_chit_application' => [
        'label' => 'Chit Application',
        'badge' => 'primary',
        'icon'  => 'ri-user-add-line',
      ],
      'client_assigned' => [
        'label' => 'Agent Assigned',
        'badge' => 'info',
        'icon'  => 'ri-user-shared-line',
      ],
      'support_ticket' => [
        'label' => 'Support Ticket',
        'badge' => 'warning',
        'icon'  => 'ri-customer-service-2-line',
      ],
      'broadcast' => [
        'label' => 'Broadcast',
        'badge' => 'secondary',
        'icon'  => 'ri-broadcast-line',
      ],
      'offer' => [
        'label' => 'Offer',
        'badge' => 'danger',
        'icon'  => 'ri-gift-line',
      ],
      'interest_update' => [
        'label' => 'Rate Update',
        'badge' => 'info',
        'icon'  => 'ri-percent-line',
      ],
      default => [
        'label' => ucwords(str_replace(['_', '-'], ' ', $type ?: 'General Notice')),
        'badge' => 'primary',
        'icon'  => 'ri-notification-3-line',
      ]
    };
  };
@endphp

<!-- Success / Error Flash Alerts -->
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <div class="d-flex align-items-center">
    <i class="ri-checkbox-circle-line me-2 fs-5"></i>
    <div>{{ session('success') }}</div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <div class="d-flex align-items-center">
    <i class="ri-error-warning-line me-2 fs-5"></i>
    <div>{{ session('error') }}</div>
  </div>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<!-- User Navigation Tabs Header -->
<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div class="nav-align-top">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/account/'.$client->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
      @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/kyc/'.$client->id) }}"><i class="icon-base ri ri-shield-check-line me-1_5"></i>KYC</a></li>
      @endif
      <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/loans/'.$client->id) }}"><i class="icon-base ri ri-file-list-3-line me-1_5"></i>Loans</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/chits/'.$client->id) }}"><i class="icon-base ri ri-group-2-line me-1_5"></i>Chits</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/ledger/'.$client->id) }}"><i class="icon-base ri ri-wallet-3-line me-1_5"></i>Ledger</a></li>
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-notification-3-line me-1_5"></i>Notifications</a></li>
    </ul>
  </div>
  <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
    <a href="{{ route('client-management') }}" class="btn btn-sm btn-outline-secondary w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-arrow-left-line me-1"></i>
      <span>Back to Clients</span>
    </a>
  </div>
</div>

<div class="row">
  <!-- Left Column: User Profile Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <div class="card mb-6">
      @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      <div class="position-absolute top-0 end-0 m-3">
        @if($client->status !== 'blacklist')
        <button type="button" class="btn btn-sm btn-danger py-1 px-2" data-bs-toggle="modal" data-bs-target="#blacklistModal" title="Blacklist client">
          <i class="icon-base ri ri-forbid-line"></i>
        </button>
        @else
        <button type="button" class="btn btn-sm btn-success py-1 px-2" data-bs-toggle="modal" data-bs-target="#unblacklistModal" title="Remove from blacklist">
          <i class="icon-base ri ri-shield-check-line"></i> Unblacklist
        </button>
        @endif
      </div>
      @endif

      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class="d-flex align-items-center flex-column">
            @if($client->kycDetail && $client->kycDetail->selfie_image && substr($client->kycDetail->selfie_image, 0, 5) === 'data:')
              <img class="img-fluid rounded mb-4" src="{{ $client->kycDetail->selfie_image }}" height="120" width="120" alt="Client Avatar" style="object-fit: cover;" />
            @elseif($client->kycDetail && $client->kycDetail->selfie_image)
              <img class="img-fluid rounded mb-4" src="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" height="120" width="120" alt="Client Avatar" style="object-fit: cover;" />
            @else
              <img class="img-fluid rounded mb-4" src="{{ asset('assets/img/avatars/1.png') }}" height="120" width="120" alt="Client Avatar" />
            @endif

            <div class="user-info text-center">
              <h5 class="mb-1">{{ $client->client_name ?? 'Client Name' }}</h5>
              @php
                $statusBadgeMap = [
                  'active'     => ['label' => 'Active', 'badge' => 'success'],
                  'verified'   => ['label' => 'Active', 'badge' => 'success'],
                  'pending'    => ['label' => 'Pending', 'badge' => 'warning'],
                  'inactive'   => ['label' => 'Inactive', 'badge' => 'danger'],
                  'rejected'   => ['label' => 'Rejected', 'badge' => 'danger'],
                  'unverified' => ['label' => 'Pending', 'badge' => 'warning'],
                  'blacklist'  => ['label' => 'Blacklisted', 'badge' => 'dark'],
                ];
                $statusInfo = $statusBadgeMap[$client->status] ?? ['label' => ucfirst($client->status ?? 'Pending'), 'badge' => 'secondary'];
              @endphp
              <span class="badge bg-label-{{ $statusInfo['badge'] }} rounded-pill">
                {{ $statusInfo['label'] }}
              </span>
            </div>
          </div>
        </div>

        <!-- Sidebar Counters -->
        <div class="row g-4 my-6">
          <div class="col-6">
            <a href="{{ route('client-view-loans', $client->id) }}" class="text-heading text-decoration-none">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <div class="avatar-initial bg-label-info rounded-3">
                    <i class="icon-base ri ri-file-text-line icon-24px"></i>
                  </div>
                </div>
                <div>
                  <h5 class="mb-0">{{ $stats['applications'] ?? ($client->applications_count ?? 0) }}</h5>
                  <span class="small">Applications</span>
                </div>
              </div>
            </a>
          </div>

          <div class="col-6">
            <a href="{{ route('client-view-loans', $client->id) }}" class="text-heading text-decoration-none">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <div class="avatar-initial bg-label-primary rounded-3">
                    <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
                  </div>
                </div>
                <div>
                  <h5 class="mb-0">{{ $stats['loans'] ?? ($client->loans_count ?? 0) }}</h5>
                  <span class="small">Total Loans</span>
                </div>
              </div>
            </a>
          </div>

          <div class="col-6">
            <a href="{{ route('client-view-chits', $client->id) }}" class="text-heading text-decoration-none">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <div class="avatar-initial bg-label-warning rounded-3">
                    <i class="icon-base ri ri-group-line icon-24px"></i>
                  </div>
                </div>
                <div>
                  <h5 class="mb-0">{{ $stats['chits'] ?? 0 }}</h5>
                  <span class="small">Total Chits</span>
                </div>
              </div>
            </a>
          </div>

          <div class="col-6">
            @php $canViewFd = auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'); @endphp
            <a href="{{ $canViewFd ? route('fd.deposits.index', ['client_id' => $client->id]) : '#' }}"
               class="text-heading text-decoration-none {{ $canViewFd ? '' : 'pe-none' }}">
              <div class="d-flex align-items-center gap-3">
                <div class="avatar">
                  <div class="avatar-initial bg-label-success rounded-3">
                    <i class="icon-base ri ri-safe-2-line icon-24px"></i>
                  </div>
                </div>
                <div>
                  <h5 class="mb-0">{{ $stats['fixed_deposits'] ?? 0 }}</h5>
                  <span class="small">Fixed Deposits</span>
                </div>
              </div>
            </a>
          </div>
        </div>

        <!-- Personal & Contact Info -->
        <div class="d-flex flex-column gap-4">
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Personal Information</small>
            <div class="row g-3">
              <div class="col-12">
                <small class="text-muted text-uppercase d-block">Email</small>
                <span class="text-heading fw-medium" style="word-break: break-all;">{{ $client->client_email ?? ($client->user->email ?? 'N/A') }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Mobile</small>
                <span class="text-heading fw-medium">{{ $client->client_phone ?? 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Alt Phone</small>
                <span class="text-heading fw-medium">{{ $client->alternate_phone ?? 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Aadhaar</small>
                <span class="text-heading fw-medium">{{ $client->aadhaar_number ?? 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">DOB</small>
                <span class="text-heading fw-medium">{{ $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('d-m-Y') : 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Gender</small>
                <span class="text-heading fw-medium">{{ ucfirst($client->gender ?? 'N/A') }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Zone / Area</small>
                <span class="text-heading fw-medium text-primary">{{ $client->location->name ?? 'N/A' }}</span>
              </div>
            </div>
          </div>

          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Address & Assignment</small>
            <div class="row g-3">
              <div class="col-12">
                <small class="text-muted text-uppercase d-block">Address</small>
                <span class="text-heading">{{ $client->address ?? 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">City</small>
                <span class="text-heading">{{ $client->city ?? 'N/A' }}</span>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase d-block">Pincode</small>
                <span class="text-heading">{{ $client->pincode ?? 'N/A' }}</span>
              </div>
              @if($client->agent)
              <div class="col-12 border-top pt-2 mt-2">
                <small class="text-muted text-uppercase d-block">Assigned Agent</small>
                <span class="text-heading fw-semibold text-primary"><i class="ri-user-star-line me-1"></i>{{ $client->agent->agent_name }} ({{ $client->agent->agent_phone }})</span>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right Column: Client Notifications Feed -->
  <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
    <!-- Notifications Header Card -->
    <div class="card mb-4">
      <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3">
        <div class="d-flex align-items-center gap-2">
          <div class="avatar avatar-sm">
            <span class="avatar-initial rounded-circle bg-label-primary">
              <i class="ri-notification-3-line"></i>
            </span>
          </div>
          <div>
            <h5 class="mb-0">Client Notifications</h5>
            <small class="text-muted">History of notifications sent to {{ $client->client_name }}</small>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
          <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
            <i class="ri-send-plane-line me-1"></i> Send Notification
          </button>
          @if(($unreadCount ?? 0) > 0)
          <button type="button" class="btn btn-sm btn-outline-primary" id="markAllReadBtn" data-url="{{ route('client-view-notifications.mark-all-read', $client->id) }}">
            <i class="ri-check-double-line me-1"></i> Mark All as Read
          </button>
          @endif
        </div>
      </div>

      <!-- Quick Metrics Bar -->
      <div class="card-body pt-0 pb-3 border-bottom">
        <div class="d-flex flex-wrap gap-3">
          <div class="d-flex align-items-center gap-2 px-3 py-1 rounded bg-label-secondary">
            <i class="ri-mail-line fs-5"></i>
            <div>
              <span class="fw-bold">{{ $totalCount ?? 0 }}</span>
              <span class="small text-muted ms-1">Total Sent</span>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 px-3 py-1 rounded {{ ($unreadCount ?? 0) > 0 ? 'bg-label-danger' : 'bg-label-success' }}">
            <i class="{{ ($unreadCount ?? 0) > 0 ? 'ri-mail-unread-line' : 'ri-mail-check-line' }} fs-5"></i>
            <div>
              <span class="fw-bold">{{ $unreadCount ?? 0 }}</span>
              <span class="small ms-1">{{ ($unreadCount ?? 0) > 0 ? 'Unread' : 'All Read' }}</span>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2 px-3 py-1 rounded bg-label-success">
            <i class="ri-checkbox-circle-line fs-5"></i>
            <div>
              <span class="fw-bold">{{ $readCount ?? 0 }}</span>
              <span class="small text-muted ms-1">Read</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters & Search Form -->
      <div class="card-body bg-body-tertiary py-3 border-bottom">
        <form method="GET" action="{{ route('client-view-notifications', $client->id) }}" id="notificationFilterForm">
          <div class="row g-3 align-items-end">
            <!-- Keyword Search -->
            <div class="col-lg-4 col-md-6">
              <label class="form-label small fw-medium mb-1">Search Notification</label>
              <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="ri-search-line"></i></span>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search title or message..." value="{{ request('search') }}">
              </div>
            </div>

            <!-- Type Filter -->
            <div class="col-lg-3 col-md-6">
              <label class="form-label small fw-medium mb-1">Notification Type</label>
              <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all">All Types</option>
                @foreach($notificationTypes ?? [] as $t)
                  @if(!empty($t))
                    @php $meta = $getNotifMeta($t); @endphp
                    <option value="{{ $t }}" {{ request('type') == $t ? 'selected' : '' }}>
                      {{ $meta['label'] }}
                    </option>
                  @endif
                @endforeach
              </select>
            </div>

            <!-- Read Status Filter -->
            <div class="col-lg-2 col-md-6">
              <label class="form-label small fw-medium mb-1">Status</label>
              <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All Status</option>
                <option value="unread" {{ request('status') == 'unread' ? 'selected' : '' }}>Unread Only</option>
                <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>Read Only</option>
              </select>
            </div>

            <!-- Date Preset -->
            <div class="col-lg-3 col-md-6">
              <label class="form-label small fw-medium mb-1">Date Range</label>
              <select name="date_preset" id="date_preset" class="form-select form-select-sm" onchange="handleDatePresetChange(this)">
                <option value="all" {{ request('date_preset', 'all') == 'all' ? 'selected' : '' }}>All Time</option>
                <option value="today" {{ request('date_preset') == 'today' ? 'selected' : '' }}>Today</option>
                <option value="yesterday" {{ request('date_preset') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                <option value="this_week" {{ request('date_preset') == 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ request('date_preset') == 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="custom" {{ (request('date_preset') == 'custom' || request('start_date')) ? 'selected' : '' }}>Custom Range</option>
              </select>
            </div>

            <!-- Custom Start Date -->
            <div class="col-lg-3 col-md-6 custom-date-container" style="{{ (request('date_preset') == 'custom' || request('start_date')) ? '' : 'display: none;' }}">
              <label class="form-label small fw-medium mb-1">Start Date</label>
              <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>

            <!-- Custom End Date -->
            <div class="col-lg-3 col-md-6 custom-date-container" style="{{ (request('date_preset') == 'custom' || request('start_date')) ? '' : 'display: none;' }}">
              <label class="form-label small fw-medium mb-1">End Date</label>
              <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>

            <!-- Action Buttons -->
            <div class="col-lg-3 col-md-6 d-flex gap-2">
              <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                <i class="ri-filter-3-line me-1"></i> Filter
              </button>
              <a href="{{ route('client-view-notifications', $client->id) }}" class="btn btn-sm btn-outline-secondary" title="Reset Filters">
                <i class="ri-refresh-line"></i>
              </a>
            </div>
          </div>
        </form>
      </div>

      <!-- Notifications List Body -->
      <div class="card-body p-4">
        @forelse($notifications as $notif)
          @php
            $meta = $getNotifMeta($notif->notification_type);
            $isRead = !empty($notif->read_at);
            $actionData = is_array($notif->action_data) ? $notif->action_data : (json_decode($notif->action_data ?? '[]', true) ?: []);
          @endphp

          <div class="card notif-card mb-3 {{ $isRead ? '' : 'unread-card' }}" id="notif-row-{{ $notif->id }}">
            <div class="card-body p-3">
              <div class="d-flex align-items-start gap-3">
                <!-- Icon Badge -->
                <div class="notif-icon-box bg-label-{{ $meta['badge'] }}">
                  <i class="{{ $meta['icon'] }}"></i>
                </div>

                <!-- Main Content -->
                <div class="flex-grow-1 min-w-0">
                  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <h6 class="mb-0 fw-semibold text-heading">{{ $notif->title ?? 'Notification' }}</h6>
                      <span class="badge bg-label-{{ $meta['badge'] }} rounded-pill">{{ $meta['label'] }}</span>
                      @if(!$isRead)
                        <span class="badge bg-danger rounded-pill" style="font-size: 0.68rem;">New / Unread</span>
                      @endif
                    </div>
                    <div class="text-muted small text-nowrap d-flex align-items-center gap-1" title="{{ $notif->created_at?->format('d-m-Y h:i:s A') }}">
                      <i class="ri-time-line"></i>
                      <span>{{ $notif->created_at?->diffForHumans() }}</span>
                    </div>
                  </div>

                  <!-- Message Body -->
                  <p class="mb-2 text-body" style="white-space: pre-line; word-break: break-word;">{{ $notif->message }}</p>

                  <!-- Action Data Pills / Chips -->
                  @if(!empty($actionData))
                  <div class="d-flex flex-wrap gap-1 mb-2">
                    @if(isset($actionData['amount']))
                      <span class="notif-meta-pill"><i class="ri-money-rupee-circle-line me-1 text-success"></i>Amount: ₹{{ number_format((float)$actionData['amount'], 2) }}</span>
                    @endif
                    @if(isset($actionData['application_number']))
                      <span class="notif-meta-pill"><i class="ri-file-list-line me-1 text-primary"></i>App #: {{ $actionData['application_number'] }}</span>
                    @endif
                    @if(isset($actionData['installment_count']))
                      <span class="notif-meta-pill"><i class="ri-coins-line me-1 text-info"></i>Installments: {{ $actionData['installment_count'] }}</span>
                    @endif
                    @if(isset($actionData['emi_count']))
                      <span class="notif-meta-pill"><i class="ri-wallet-3-line me-1 text-info"></i>EMIs: {{ $actionData['emi_count'] }}</span>
                    @endif
                    @if(isset($actionData['screen']))
                      <span class="notif-meta-pill"><i class="ri-smartphone-line me-1 text-secondary"></i>Screen: {{ $actionData['screen'] }}</span>
                    @endif
                    @if(isset($actionData['source']))
                      <span class="notif-meta-pill"><i class="ri-shield-user-line me-1 text-secondary"></i>Source: {{ ucfirst($actionData['source']) }}</span>
                    @endif
                  </div>
                  @endif

                  <!-- Bottom Toolbar -->
                  <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top gap-2">
                    <div class="d-flex align-items-center gap-2 small text-muted">
                      <span><i class="ri-calendar-line me-1"></i>{{ $notif->created_at?->format('d M Y, h:i A') }}</span>
                      @if($isRead)
                        <span class="text-success ms-2"><i class="ri-check-double-line me-1"></i>Read at {{ $notif->read_at?->format('d M Y, h:i A') }}</span>
                      @endif
                    </div>

                    <div class="d-flex align-items-center gap-2">
                      <!-- View Details Modal Trigger -->
                      <button type="button"
                              class="btn btn-xs btn-label-secondary view-notif-btn"
                              data-id="{{ $notif->id }}"
                              data-title="{{ $notif->title }}"
                              data-type="{{ $meta['label'] }}"
                              data-badge="{{ $meta['badge'] }}"
                              data-message="{{ htmlspecialchars($notif->message, ENT_QUOTES) }}"
                              data-created="{{ $notif->created_at?->format('d M Y, h:i A') }} ({{ $notif->created_at?->diffForHumans() }})"
                              data-read="{{ $isRead ? 'Read at ' . $notif->read_at?->format('d M Y, h:i A') : 'Unread' }}"
                              data-payload="{{ json_encode($actionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}"
                              data-bs-toggle="modal"
                              data-bs-target="#notificationDetailModal">
                        <i class="ri-eye-line me-1"></i> Details
                      </button>

                      <!-- Mark Single Read Trigger -->
                      @if(!$isRead)
                      <button type="button"
                              class="btn btn-xs btn-label-primary mark-single-read-btn"
                              data-url="{{ route('client-view-notifications.mark-read', ['id' => $client->id, 'notificationId' => $notif->id]) }}">
                        <i class="ri-check-line me-1"></i> Mark Read
                      </button>
                      @endif

                      <!-- Delete Single Trigger -->
                      <button type="button"
                              class="btn btn-xs btn-label-danger delete-notif-btn"
                              data-url="{{ route('client-view-notifications.destroy', ['id' => $client->id, 'notificationId' => $notif->id]) }}">
                        <i class="ri-delete-bin-line"></i>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        @empty
          <div class="text-center py-5">
            <div class="avatar avatar-xl bg-label-secondary mx-auto mb-3" style="width: 72px; height: 72px;">
              <i class="ri-notification-off-line" style="font-size: 2.5rem;"></i>
            </div>
            <h5 class="mb-2">No Notifications Found</h5>
            <p class="text-muted mb-4">
              @if(request()->anyFilled(['search', 'type', 'status', 'date_preset', 'start_date', 'end_date']))
                No notifications match your filter criteria. Try resetting your search filters.
              @else
                No notifications have been sent to {{ $client->client_name }} yet.
              @endif
            </p>
            <div class="d-flex justify-content-center gap-2">
              @if(request()->anyFilled(['search', 'type', 'status', 'date_preset', 'start_date', 'end_date']))
                <a href="{{ route('client-view-notifications', $client->id) }}" class="btn btn-sm btn-outline-secondary">
                  <i class="ri-refresh-line me-1"></i> Clear Filters
                </a>
              @endif
              <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#sendNotificationModal">
                <i class="ri-send-plane-line me-1"></i> Send First Notification
              </button>
            </div>
          </div>
        @endforelse

        <!-- Pagination -->
        <div class="mt-4">
          {{ $notifications->links() }}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Send Notification to Client -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1" aria-labelledby="sendNotificationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('client-view-notifications.send', $client->id) }}" method="POST" id="sendNotificationForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title d-flex align-items-center" id="sendNotificationModalLabel">
            <i class="ri-send-plane-line me-2 text-primary"></i> Send Notification to Client
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Recipient Preview -->
          <div class="p-3 bg-label-primary rounded-3 mb-3 d-flex align-items-center gap-3">
            <div class="avatar">
              <span class="avatar-initial rounded-circle bg-primary text-white">
                {{ strtoupper(substr($client->client_name ?? 'C', 0, 1)) }}
              </span>
            </div>
            <div>
              <h6 class="mb-0">{{ $client->client_name }}</h6>
              <small class="text-muted"><i class="ri-phone-line me-1"></i>{{ $client->client_phone }} &bull; ID #{{ $client->id }}</small>
            </div>
          </div>

          <!-- Notification Title -->
          <div class="mb-3">
            <label class="form-label fw-medium" for="notif_title">Notification Title <span class="text-danger">*</span></label>
            <input type="text" name="title" id="notif_title" class="form-control" placeholder="e.g. EMI Payment Reminder / Special Offer" required maxlength="191">
          </div>

          <!-- Notification Category / Type -->
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label fw-medium" for="notif_type">Notification Category</label>
              <select name="notification_type" id="notif_type" class="form-select">
                <option value="general" selected>General Notice</option>
                <option value="emi_reminder">EMI Reminder</option>
                <option value="loan_approved">Loan Update</option>
                <option value="chit_installment">Chit Alert</option>
                <option value="offer">Special Offer / Promotion</option>
                <option value="interest_update">Interest Rate Update</option>
              </select>
            </div>

            <div class="col-sm-6">
              <label class="form-label fw-medium" for="notif_priority">Priority</label>
              <select name="priority" id="notif_priority" class="form-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High (Urgent)</option>
              </select>
            </div>
          </div>

          <!-- Notification Message Body -->
          <div class="mb-3">
            <label class="form-label fw-medium" for="notif_message">Message Body <span class="text-danger">*</span></label>
            <textarea name="message" id="notif_message" class="form-control" rows="4" placeholder="Type the message you want to deliver to this client..." required></textarea>
            <div class="form-text">This will be delivered as an in-app inbox notification and push notification.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="btnSubmitSend">
            <i class="ri-send-plane-2-line me-1"></i> Send Now
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Notification Details -->
<div class="modal fade" id="notificationDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <i class="ri-file-info-line text-primary"></i>
          <span id="detailModalTitle">Notification Details</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 pb-3 border-bottom">
          <div class="d-flex align-items-center gap-2">
            <span class="badge" id="detailModalBadge">Type</span>
            <span class="badge bg-label-info" id="detailModalStatus">Status</span>
          </div>
          <small class="text-muted" id="detailModalTime"></small>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium text-uppercase small text-muted">Message</label>
          <div class="p-3 bg-light rounded-3 text-body" id="detailModalMessage" style="white-space: pre-line;"></div>
        </div>

        <div>
          <label class="form-label fw-medium text-uppercase small text-muted">Action Data / Payload</label>
          <pre class="json-viewer-box" id="detailModalPayload">None</pre>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Blacklist Confirmation Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="blacklistForm" action="{{ route('client-blacklist', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Blacklist</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="ri-error-warning-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center mb-4">Do you really want to blacklist this client? Client status will be updated to <strong>Blacklist</strong>.</p>
          <div class="mb-3">
            <label for="blacklist_reason" class="form-label fw-medium">Reason for Blacklist <span class="text-danger">*</span></label>
            <textarea name="reason" id="blacklist_reason" class="form-control" rows="3" placeholder="Enter reason for blacklisting" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Blacklist</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Unblacklist Confirmation Modal -->
<div class="modal fade" id="unblacklistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form id="unblacklistForm" action="{{ route('client-unblacklist', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Remove from Blacklist</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="ri-shield-check-line text-success" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Unblacklist this client?</h5>
          <p class="text-center mb-4">Client status will be restored to <strong>Active</strong>.</p>
          @if(!empty($client->remarks))
          <div class="alert alert-secondary py-2 small mb-3">
            <strong>Current blacklist note:</strong> {{ $client->remarks }}
          </div>
          @endif
          <div class="mb-3">
            <label for="unblacklist_reason" class="form-label fw-medium">Note (optional)</label>
            <textarea name="reason" id="unblacklist_reason" class="form-control" rows="2" placeholder="Optional note for removing blacklist"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Unblacklist</button>
        </div>
      </div>
    </form>
  </div>
</div>

@endsection

@section('page-script')
<script>
  function handleDatePresetChange(selectEl) {
    const isCustom = selectEl.value === 'custom';
    document.querySelectorAll('.custom-date-container').forEach(el => {
      el.style.display = isCustom ? 'block' : 'none';
    });
    if (!isCustom) {
      selectEl.form.submit();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // View Details Modal Populator
    document.querySelectorAll('.view-notif-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const title = this.dataset.title;
        const type = this.dataset.type;
        const badge = this.dataset.badge;
        const message = this.dataset.message;
        const created = this.dataset.created;
        const read = this.dataset.read;
        let payload = this.dataset.payload;

        document.getElementById('detailModalTitle').textContent = title;
        const badgeEl = document.getElementById('detailModalBadge');
        badgeEl.className = 'badge bg-label-' + badge;
        badgeEl.textContent = type;

        const statusEl = document.getElementById('detailModalStatus');
        statusEl.textContent = read;
        statusEl.className = read.startsWith('Read') ? 'badge bg-label-success' : 'badge bg-label-warning';

        document.getElementById('detailModalTime').textContent = created;
        document.getElementById('detailModalMessage').textContent = message;

        try {
          const parsed = JSON.parse(payload);
          document.getElementById('detailModalPayload').textContent = JSON.stringify(parsed, null, 2);
        } catch (e) {
          document.getElementById('detailModalPayload').textContent = payload || 'None';
        }
      });
    });

    // Mark Single Notification as Read
    document.querySelectorAll('.mark-single-read-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const url = this.dataset.url;
        const card = this.closest('.notif-card');

        fetch(url, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            card.classList.remove('unread-card');
            const unreadBadge = card.querySelector('.badge.bg-danger');
            if (unreadBadge) unreadBadge.remove();
            btn.remove();
            Swal.fire({
              toast: true,
              position: 'top-end',
              icon: 'success',
              title: 'Marked as read',
              showConfirmButton: false,
              timer: 1500
            });
          }
        })
        .catch(err => console.error(err));
      });
    });

    // Mark All Notifications as Read
    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', function () {
        const url = this.dataset.url;
        Swal.fire({
          title: 'Mark all as read?',
          text: 'All unread notifications for this client will be marked as read.',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Yes, mark all',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-primary me-2',
            cancelButton: 'btn btn-outline-secondary'
          },
          buttonsStyling: false
        }).then(result => {
          if (result.isConfirmed) {
            fetch(url, {
              method: 'POST',
              headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
              }
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                location.reload();
              }
            });
          }
        });
      });
    }

    // Delete Single Notification
    document.querySelectorAll('.delete-notif-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const url = this.dataset.url;
        const card = this.closest('.notif-card');

        Swal.fire({
          title: 'Delete notification?',
          text: 'This notification will be permanently removed from this client.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'btn btn-danger me-2',
            cancelButton: 'btn btn-outline-secondary'
          },
          buttonsStyling: false
        }).then(result => {
          if (result.isConfirmed) {
            fetch(url, {
              method: 'DELETE',
              headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json'
              }
            })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                card.remove();
                Swal.fire({
                  toast: true,
                  position: 'top-end',
                  icon: 'success',
                  title: 'Notification deleted',
                  showConfirmButton: false,
                  timer: 1500
                });
              }
            });
          }
        });
      });
    });

    // Send Notification Form validation and spinner
    const sendForm = document.getElementById('sendNotificationForm');
    if (sendForm) {
      sendForm.addEventListener('submit', function () {
        const btn = document.getElementById('btnSubmitSend');
        if (btn) {
          btn.disabled = true;
          btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Sending...';
        }
      });
    }
  });
</script>
@endsection
