@extends('layouts/layoutMaster')

@section('title', 'Activity Logs')

@section('page-script')
<!-- Activity logs script no longer needed since we're using separate page -->
@endsection

@section('content')

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Activity Logs</h4>
    <p class="text-muted mb-0">Monitor client activity and last login locations</p>
  </div>
</div>

<!-- Activity Logs Table -->
<div class="card">
  <div class="card-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
    <h5 class="mb-0">Client Activity</h5>

    <form method="GET" action="{{ route('audit-logs-activity-logs') }}" class="ms-auto" role="search">
      <div class="input-group input-group-sm" style="min-width: 260px;">
        <span class="input-group-text border-end-0 bg-label-primary text-primary"><i class="icon-base ri ri-search-line"></i></span>
        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0" placeholder="Search client or phone">
        @if($search !== '')
          <a href="{{ route('audit-logs-activity-logs') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
        @endif
      </div>
    </form>
  </div>
  <div class="card-body">
    @if($activities->count() > 0)
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>S.No</th>
              <th>Client Name</th>
              <th>Mobile Number</th>
              <th>Last Active On</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($activities as $index => $activity)
            <tr>
              <td>{{ $activities->firstItem() + $index }}</td>
              <td class="fw-medium text-heading">{{ $activity->user?->client?->client_name ?? $activity->user?->name ?? 'Unknown Client' }}</td>
              <td>
                <span class="d-inline-flex align-items-center px-3 py-1 rounded-pill bg-label-primary text-primary fw-medium">
                  <i class="icon-base ri ri-phone-line me-1"></i>
                  {{ $activity->user?->client?->client_phone ?? $activity->user?->phone ?? 'N/A' }}
                </span>
              </td>
              <td>
                @if($activity->login_at)
                  <span class="text-muted">{{ \Carbon\Carbon::parse($activity->login_at)->format('d-m-Y h:i A') }}</span>
                @else
                  <span class="text-muted">N/A</span>
                @endif
              </td>
              <td>
                <a href="{{ route('audit-logs-view-location', $activity->id) }}" 
                   class="btn btn-sm btn-icon rounded-pill btn-label-primary" 
                   title="View Location">
                  <i class="icon-base ri ri-map-pin-line"></i>
                </a>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-4">
        {{ $activities->links() }}
      </div>
    @else
      <div class="text-center py-5">
        <div class="mb-3">
          <i class="icon-base ri ri-file-list-3-line" style="font-size: 64px; color: #ddd;"></i>
        </div>
        <h6 class="mb-2">{{ $search !== '' ? 'No Matching Records' : 'No Activity Logs Found' }}</h6>
        <p class="text-muted mb-0">
          {{ $search !== '' ? 'Try a different client name/phone, or clear the search.' : 'Client activity will appear here once they log in' }}
        </p>
      </div>
    @endif
  </div>
</div>

@endsection
