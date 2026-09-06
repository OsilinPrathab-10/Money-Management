@extends('layouts/layoutMaster')

@section('title', 'Collection Log')

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
    <h4 class="mb-1">Collection Log</h4>
    <p class="text-muted mb-0">Monitor all payment collection records (Last 30 days)</p>
  </div>
  <div class="d-flex gap-2">
    <a href="{{ route('system-collection-log-export', ['search' => $search]) }}" class="btn btn-primary">
      <i class="ri-download-2-line me-1"></i> Export CSV
    </a>
    @if($logs->count() > 0)
      <form action="{{ route('system-collection-log-clear') }}" method="POST" id="clearCollectionLogsForm">
        @csrf
        <button type="button" class="btn btn-outline-danger" id="clearCollectionLogsBtn">
          <i class="ri-delete-bin-line me-1"></i> Clear Log
        </button>
      </form>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom">
    <div class="d-flex justify-content-between align-items-center row g-3">
      <div class="col-md-4">
        <h5 class="card-title mb-0">Collection History</h5>
      </div>
      <div class="col-md-4 col-12">
        <form action="{{ route('system-collection-log') }}" method="GET" class="d-flex">
          <input type="text" name="search" class="form-control me-2" placeholder="Search client, loan, mode..." value="{{ $search }}">
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
            <th>Client Name</th>
            <th>Loan Number</th>
            <th>EMI No.</th>
            <th>Amount</th>
            <th>Mode</th>
            <th>Collected By</th>
            <th>Time</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($logs as $log)
            <tr>
              <td>
                <span class="fw-medium text-heading">{{ $log->client_name }}</span>
              </td>
              <td>
                <span class="badge bg-label-info">{{ $log->loan_number }}</span>
              </td>
              <td>
                <span class="badge bg-secondary">{{ $log->emi_number }}</span>
              </td>
              <td>
                <span class="text-success fw-bold">₹{{ number_format($log->collected_amount, 2) }}</span>
              </td>
              <td>
                @if(strtolower($log->payment_mode) === 'direct')
                  <span class="badge bg-label-info">Direct</span>
                @elseif(strtolower($log->payment_mode) === 'in_hand')
                  <span class="badge bg-label-primary">In Hand</span>
                @elseif(strtolower($log->payment_mode) === 'payment_link')
                  <span class="badge bg-label-warning">Payment Link</span>
                @else
                  <span class="badge bg-label-secondary">{{ ucfirst($log->payment_mode) }}</span>
                @endif
              </td>
              <td>
                <div class="d-flex align-items-center">
                  <i class="ri-user-follow-line me-1 text-primary"></i>
                  <span>{{ $log->collected_by_name }}</span>
                </div>
              </td>
              <td>
                {{ $log->collected_at ? \Carbon\Carbon::parse($log->collected_at)->format('d-m-Y h:i A') : 'N/A' }}
              </td>
              <td>
                <span class="badge bg-label-secondary font-monospace" style="cursor: pointer;" onclick="navigator.clipboard.writeText('{{ $log->ip_address }}'); Swal.fire({toast: true, position: 'top-end', icon: 'success', title: 'IP copied!', showConfirmButton: false, timer: 1500});">
                  <i class="ri-file-copy-line me-1"></i>{{ $log->ip_address }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="mb-3">
                  <i class="ri-coins-line" style="font-size: 64px; color: #ddd;"></i>
                </div>
                <h5>No Collection Logs Found</h5>
                <p class="text-muted">No collection records matching the criteria in the last 30 days.</p>
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

    const clearBtn = document.getElementById('clearCollectionLogsBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will clear all collection logs for the last 30 days. This action cannot be undone!',
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
                    document.getElementById('clearCollectionLogsForm').submit();
                }
            });
        });
    }
});
</script>
@endsection
