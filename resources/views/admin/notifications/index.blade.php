@extends('layouts/layoutMaster')
@php
  use Illuminate\Support\Str;
@endphp

@section('title', 'Notifications')

@section('content')
<div class="card mb-4">
  <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-3">
    <h5 class="mb-0"><i class="ri-notification-3-line me-2 text-primary"></i>All Notifications</h5>
    <div class="card-toolbar d-flex flex-wrap gap-2">
      <button type="button"
        class="btn btn-sm btn-label-primary"
        id="markAllReadBtn"
        data-action="{{ route('admin-notifications.mark-all-read') }}">
        <i class="ri-check-double-line me-1"></i> Mark All as Read
      </button>
      <button type="button"
        class="btn btn-sm btn-label-danger"
        id="clearReadBtn"
        data-action="{{ route('admin-notifications.clear-read') }}">
        <i class="ri-delete-bin-line me-1"></i> Clear Read
      </button>
    </div>
  </div>

  <!-- Notification Filters Section -->
  <div class="card-body border-top py-3 bg-body-tertiary">
    <form action="{{ route('admin-notifications') }}" method="GET" id="notificationFilterForm">
      <div class="row g-3 align-items-end">
        <!-- Category Filter -->
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="form-label small fw-medium mb-1">Category</label>
          <select name="category" class="form-select form-select-sm" onchange="handleCategoryChange(this)">
            <option value="all" {{ request('category', 'all') == 'all' ? 'selected' : '' }}>All Categories</option>
            <option value="client" {{ request('category') == 'client' ? 'selected' : '' }}>Client / KYC</option>
            <option value="loan" {{ request('category') == 'loan' ? 'selected' : '' }}>Loan</option>
            <option value="chit" {{ request('category') == 'chit' ? 'selected' : '' }}>Chit</option>
            <option value="ticket" {{ request('category') == 'ticket' ? 'selected' : '' }}>Tickets & Support</option>
            <option value="fd" {{ request('category') == 'fd' ? 'selected' : '' }}>Fixed Deposit (FD)</option>
          </select>
        </div>

        <!-- Notification Type Filter -->
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="form-label small fw-medium mb-1">Specific Type</label>
          <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all">All Types</option>
            @foreach($notificationTypes as $type)
              @if(!empty($type))
                <option value="{{ $type }}" {{ request('type') == $type ? 'selected' : '' }}>
                  {{ ucwords(str_replace('_', ' ', $type)) }}
                </option>
              @endif
            @endforeach
          </select>
        </div>

        <!-- Date Range Filter -->
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="form-label small fw-medium mb-1">Date Filter</label>
          <select name="date_preset" id="date_preset" class="form-select form-select-sm" onchange="handleDatePresetChange(this)">
            <option value="all" {{ request('date_preset', 'all') == 'all' ? 'selected' : '' }}>All Dates</option>
            <option value="today" {{ request('date_preset') == 'today' ? 'selected' : '' }}>Today</option>
            <option value="yesterday" {{ request('date_preset') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
            <option value="this_week" {{ request('date_preset') == 'this_week' ? 'selected' : '' }}>This Week</option>
            <option value="this_month" {{ request('date_preset') == 'this_month' ? 'selected' : '' }}>This Month</option>
            <option value="custom" {{ request('date_preset') == 'custom' || request('start_date') ? 'selected' : '' }}>Custom Range</option>
          </select>
        </div>

        <!-- Custom Start Date -->
        <div class="col-lg-2 col-md-3 col-sm-6 custom-date-container" style="{{ (request('date_preset') == 'custom' || request('start_date')) ? '' : 'display: none;' }}">
          <label class="form-label small fw-medium mb-1">Start Date</label>
          <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}" onchange="this.form.submit()">
        </div>

        <!-- Custom End Date -->
        <div class="col-lg-2 col-md-3 col-sm-6 custom-date-container" style="{{ (request('date_preset') == 'custom' || request('start_date')) ? '' : 'display: none;' }}">
          <label class="form-label small fw-medium mb-1">End Date</label>
          <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}" onchange="this.form.submit()">
        </div>

        <!-- Read Status Filter -->
        <div class="col-lg-2 col-md-3 col-sm-6">
          <label class="form-label small fw-medium mb-1">Status</label>
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="all" {{ request('status', 'all') == 'all' ? 'selected' : '' }}>All Statuses</option>
            <option value="unread" {{ request('status') == 'unread' ? 'selected' : '' }}>Unread Only</option>
            <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>Read Only</option>
          </select>
        </div>

        <!-- Search Keyword Filter -->
        <div class="col-lg-3 col-md-4 col-sm-6">
          <label class="form-label small fw-medium mb-1">Search Keyword</label>
          <div class="input-group input-group-sm">
            <input type="text" name="search" class="form-control" placeholder="Search Client, Title, Message..." value="{{ request('search') }}">
            <button class="btn btn-outline-primary" type="submit"><i class="ri-search-line"></i></button>
          </div>
        </div>

        <!-- Reset Button -->
        <div class="col-lg-1 col-md-2 col-sm-6">
          <a href="{{ route('admin-notifications') }}" class="btn btn-sm btn-outline-secondary w-100" title="Reset Filters">
            <i class="ri-refresh-line me-1"></i>Reset
          </a>
        </div>
      </div>
    </form>
  </div>
  <div class="card-body p-0">
    <div class="list-group list-group-flush" id="allNotificationsList">
      @forelse ($notifications as $notification)
      {{-- Each row stores all data in data-* so the JS can open the modal without extra AJAX --}}
      <div class="list-group-item list-group-item-action notif-row {{ $notification->is_read ? '' : 'bg-label-primary' }}"
           style="cursor: pointer;"
           data-id="{{ $notification->id }}"
           data-link="{{ $notification->link ?? '' }}"
           data-badge-color="{{ $notification->badge_color }}"
           data-icon="{{ $notification->icon_class }}"
           data-type="{{ $notification->type ?? '' }}"
           data-title="{{ $notification->title }}"
           data-message="{{ addslashes($notification->message) }}"
           data-created-at="{{ $notification->created_at?->diffForHumans() }}"
           data-created-at-formatted="{{ $notification->created_at?->format('d-m-Y h:i A') }}"
           data-is-read="{{ $notification->is_read ? '1' : '0' }}">
        <div class="d-flex align-items-start py-1">
          <div class="flex-shrink-0 me-3">
            <span class="avatar-initial rounded-circle bg-label-{{ $notification->badge_color }}" style="width:42px;height:42px;">
              <i class="{{ $notification->icon_class }} icon-20px"></i>
            </span>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start">
              <h6 class="mb-1 fw-semibold">{{ $notification->title }}</h6>
              <small class="text-muted text-nowrap ms-2">{{ $notification->created_at?->diffForHumans() }}</small>
            </div>
            <p class="mb-1 small text-truncate" style="max-width:600px;">{{ $notification->message }}</p>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <span class="badge bg-label-{{ $notification->badge_color }} small">
                <i class="ri-eye-line me-1"></i>View Details
              </span>
              <div class="notification-status">
                @if(!$notification->is_read)
                <button type="button"
                  class="btn btn-xs btn-label-primary mark-read-btn"
                  data-id="{{ $notification->id }}">
                  <i class="ri-check-line"></i> Mark as Read
                </button>
                @else
                <span class="text-success small"><i class="ri-check-double-line"></i> Read</span>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
      @empty
      <div class="text-center py-5 text-body-secondary">
        <i class="ri-notification-off-line" style="font-size: 3rem;"></i>
        <p class="mt-3 mb-0">No notifications yet</p>
      </div>
      @endforelse
    </div>

    <div class="px-4 py-3">
      {{ $notifications->links() }}
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
function toggleCustomDates() {
    var preset = document.getElementById('date_preset');
    var containers = document.querySelectorAll('.custom-date-container');
    if (preset && containers) {
        var show = (preset.value === 'custom');
        containers.forEach(function(el) {
            el.style.display = show ? 'block' : 'none';
        });
    }
}

function handleCategoryChange(selectEl) {
    var form = selectEl.form;
    var typeSelect = form.querySelector('select[name="type"]');
    if (typeSelect) typeSelect.value = 'all';
    form.submit();
}

function handleDatePresetChange(selectEl) {
    var form = selectEl.form;
    toggleCustomDates();
    if (selectEl.value !== 'custom') {
        var startInput = form.querySelector('input[name="start_date"]');
        var endInput = form.querySelector('input[name="end_date"]');
        if (startInput) startInput.value = '';
        if (endInput) endInput.value = '';
        form.submit();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // ── Row click → open notification detail modal ──────────────────────────
    document.querySelectorAll('.notif-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            // If Mark as Read button was clicked, don't open modal
            if (e.target.closest('.mark-read-btn')) return;

            var n = {
                id:                    row.dataset.id,
                link:                  row.dataset.link || '',
                badge_color:           row.dataset.badgeColor,
                icon:                  row.dataset.icon,
                type:                  row.dataset.type,
                title:                 row.dataset.title,
                message:               row.dataset.message,
                created_at:            row.dataset.createdAt,
                created_at_formatted:  row.dataset.createdAtFormatted,
                is_read:               row.dataset.isRead,
            };

            if (typeof window.showNotificationModal === 'function') {
                window.showNotificationModal(n);
            } else {
                // Fallback: navigate directly if modal helper not loaded
                if (n.link) window.location.href = n.link;
            }
        });
    });

    // ── Inline Mark as Read buttons ─────────────────────────────────────────
    document.querySelectorAll('.mark-read-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = btn.dataset.id;
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            var baseUrl = window.baseUrl || (document.documentElement.getAttribute('data-base-url') || '').replace(/\/+$/, '') + '/';

            fetch(baseUrl + 'admin/notifications/' + id + '/mark-read', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    // Update the row visually
                    var row = btn.closest('.notif-row');
                    if (row) {
                        row.classList.remove('bg-label-primary');
                        row.dataset.isRead = '1';
                    }
                    // Replace button with "Read" badge
                    btn.parentElement.innerHTML = '<span class="text-success small"><i class="ri-check-double-line"></i> Read</span>';
                    // Refresh dropdown badge count
                    if (typeof window.loadAllNotifications === 'function') {
                        window.loadAllNotifications();
                    }
                }
            });
        });
    });
});
</script>
@endsection
