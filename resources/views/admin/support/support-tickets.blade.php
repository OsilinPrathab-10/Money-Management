@extends('layouts/layoutMaster')

@section('title', 'Client Support Tickets')

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
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/support-tickets.js'])
@endsection

@section('content')

<!-- Statistics Cards -->
<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="avatar">
            <div class="avatar-initial bg-label-primary rounded">
              <i class="icon-base ri ri-ticket-line icon-24px"></i>
            </div>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['total_tickets'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Total Tickets</p>
        <small class="text-muted">All support tickets</small>
      </div>
    </div>
  </div>
  
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="avatar">
            <div class="avatar-initial bg-label-success rounded">
              <i class="icon-base ri ri-checkbox-circle-line icon-24px"></i>
            </div>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['open_tickets'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Open Tickets</p>
        <small class="text-muted">Active tickets</small>
      </div>
    </div>
  </div>
  
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="avatar">
            <div class="avatar-initial bg-label-warning rounded">
              <i class="icon-base ri ri-time-line icon-24px"></i>
            </div>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['pending_tickets'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Pending Tickets</p>
        <small class="text-muted">Awaiting response</small>
      </div>
    </div>
  </div>
  
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="avatar">
            <div class="avatar-initial bg-label-secondary rounded">
              <i class="icon-base ri ri-close-circle-line icon-24px"></i>
            </div>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['closed_tickets'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Closed Tickets</p>
        <small class="text-muted">Resolved tickets</small>
      </div>
    </div>
  </div>
</div>

<!-- Support Tickets Table -->
<div class="card">
  <div class="card-header border-bottom">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <h5 class="card-title mb-0">Support Tickets</h5>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Filters removed as per request -->
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-tickets table table-hover" id="ticketsTable">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Ticket No.</th>
          <th>Client Name</th>
          <th>Priority</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="statusTicketId">
        <div class="mb-3">
          <label class="form-label">Select Status</label>
          <select id="statusSelect" class="form-select">
            <option value="open">Open</option>
            <option value="pending">Pending</option>
            <option value="closed">Closed</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmStatusChange">Update Status</button>
      </div>
    </div>
  </div>
</div>

<!-- Assign User Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Assign Ticket</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="assignTicketId">
        <div class="mb-3">
          <label class="form-label">Assign To</label>
          <select id="assignUserSelect" class="form-select">
            <option value="">Select User...</option>
            <!-- Users will be loaded dynamically -->
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirmAssign">Assign</button>
      </div>
    </div>
  </div>
</div>

@endsection
