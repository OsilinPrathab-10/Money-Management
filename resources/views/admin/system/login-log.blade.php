@extends('layouts/layoutMaster')

@section('title', 'Login Log')

@section('content')

<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Login Log</h4>
    <p class="text-muted mb-0">Monitor user login and logout activities (Last 30 days)</p>
  </div>
  @if($logs->count() > 0)
    <form action="{{ route('system-login-log-clear') }}" method="POST" id="clearLogsForm">
      @csrf
      <button type="button" class="btn btn-outline-danger" id="clearLogsBtn">
        <i class="ri-delete-bin-line me-1"></i> Clear Log
      </button>
    </form>
  @endif
</div>

<div class="card">
  <div class="card-header border-bottom">
    <div class="d-flex justify-content-between align-items-center row g-3">
      <div class="col-md-4">
        <h5 class="card-title mb-0">Login History</h5>
      </div>
      <div class="col-md-4 col-12">
        <form action="{{ route('system-login-log') }}" method="GET" class="d-flex">
          <input type="text" name="search" class="form-control me-2" placeholder="Search by name, IP..." value="{{ $search }}">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card-body p-0">
    <div class="table-responsive text-nowrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>User Name</th>
            <th>IP Address</th>
            <th>Login Time</th>
            <th>Logout Time</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($logs as $log)
            <tr>
              <td>
                <div class="d-flex align-items-center">
                  <div class="avatar avatar-sm me-3">
                    <span class="avatar-initial rounded-circle bg-label-primary">{{ substr($log->user_name, 0, 2) }}</span>
                  </div>
                  <div>
                    <span class="fw-medium d-block text-heading">{{ $log->user_name }}</span>
                    <small class="text-muted">User ID: {{ $log->user_id ?? 'N/A' }}</small>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-label-secondary font-monospace" style="cursor: pointer;" onclick="navigator.clipboard.writeText('{{ $log->ip_address }}'); Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'IP copied!', showConfirmButton: false, timer: 1500});">
                  <i class="ri-file-copy-line me-1"></i>{{ $log->ip_address }}
                </span>
              </td>
              <td>
                {{ $log->login_at ? \Carbon\Carbon::parse($log->login_at)->format('d-m-Y h:i A') : 'N/A' }}
              </td>
              <td>
                @if($log->logout_at)
                  {{ \Carbon\Carbon::parse($log->logout_at)->format('d-m-Y h:i A') }}
                @else
                  <span class="text-success fw-medium">Active Session</span>
                @endif
              </td>
              <td>
                @if($log->logout_at)
                  <span class="badge bg-label-info">Logged Out</span>
                @else
                  <span class="badge bg-label-success">Logged In</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="text-center py-5">
                <div class="mb-3">
                  <i class="ri-shield-user-line" style="font-size: 64px; color: #ddd;"></i>
                </div>
                <h5>No Login Logs Found</h5>
                <p class="text-muted">No login activities matching the criteria in the last 30 days.</p>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  @if($logs->hasPages())
    <div class="card-footer border-top d-flex justify-content-end">
      {{ $logs->links() }}
    </div>
  @endif
</div>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show session alerts if present using SweetAlert2
    const container = document.querySelector('.alert-container');
    if (container) {
        const success = container.getAttribute('data-success');
        const error = container.getAttribute('data-error');
        if (success) {
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: success,
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
        if (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error,
                customClass: { confirmButton: 'btn btn-primary' }
            });
        }
    }

    const clearBtn = document.getElementById('clearLogsBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will clear all login logs for the last 30 days. This action cannot be undone!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, clear all!',
                cancelButtonText: 'No, keep them',
                customClass: {
                    confirmButton: 'btn btn-danger me-3',
                    cancelButton: 'btn btn-outline-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    document.getElementById('clearLogsForm').submit();
                }
            });
        });
    }
});
</script>
@endsection
