@extends('layouts/layoutMaster')

@section('title', 'Account Deletion Requests')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/account-deletion-requests.js'])
@endsection

@section('content')

<div class="row g-4 mb-6">
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="avatar mb-3">
          <div class="avatar-initial bg-label-primary rounded">
            <i class="icon-base ri ri-user-unfollow-line icon-24px"></i>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['total'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Total Requests</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="avatar mb-3">
          <div class="avatar-initial bg-label-warning rounded">
            <i class="icon-base ri ri-time-line icon-24px"></i>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['pending'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Pending</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="avatar mb-3">
          <div class="avatar-initial bg-label-info rounded">
            <i class="icon-base ri ri-search-eye-line icon-24px"></i>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['under_review'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Under Review</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card h-100">
      <div class="card-body">
        <div class="avatar mb-3">
          <div class="avatar-initial bg-label-success rounded">
            <i class="icon-base ri ri-checkbox-circle-line icon-24px"></i>
          </div>
        </div>
        <h4 class="mb-1">{{ $stats['completed'] ?? 0 }}</h4>
        <p class="mb-0 text-muted">Completed</p>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header border-bottom">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
      <div>
        <h5 class="card-title mb-1">Account Deletion Requests</h5>
        <small class="text-muted">Loan app users who requested account &amp; data deletion</small>
      </div>
      <div class="d-flex align-items-center gap-2">
        <select id="statusFilter" class="form-select form-select-sm" style="min-width: 160px;">
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="under_review">Under Review</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="completed">Completed</option>
        </select>
        <a href="{{ route('public.account-deletion') }}" target="_blank" class="btn btn-sm btn-label-primary">
          <i class="ri-external-link-line me-1"></i> Public Form
        </a>
      </div>
    </div>
  </div>
  <div class="card-datatable table-responsive">
    <table class="table table-hover" id="deletionRequestsTable">
      <thead>
        <tr>
          <th>Request #</th>
          <th>Name</th>
          <th>Email</th>
          <th>Mobile</th>
          <th>Client</th>
          <th>Status</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

{{-- View / Update Modal --}}
<div class="modal fade" id="requestModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Deletion Request <span id="modalRequestNumber" class="text-primary"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="modalRequestId">
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Full Name</label>
            <div class="fw-medium" id="modalFullName">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Submitted On</label>
            <div class="fw-medium" id="modalCreatedAt">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Email</label>
            <div class="fw-medium" id="modalEmail">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Mobile</label>
            <div class="fw-medium" id="modalMobile">—</div>
          </div>
          <div class="col-12">
            <label class="form-label text-muted small mb-1">Linked Client</label>
            <div id="modalClient">—</div>
          </div>
          <div class="col-12">
            <label class="form-label text-muted small mb-1">Reason</label>
            <div class="p-3 bg-lighter rounded" id="modalReason">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">IP Address</label>
            <div class="fw-medium" id="modalIp">—</div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small mb-1">Last Reviewed</label>
            <div class="fw-medium" id="modalReviewed">—</div>
          </div>
        </div>
        <hr>
        <div class="mb-3">
          <label for="modalStatus" class="form-label">Status</label>
          <select id="modalStatus" class="form-select">
            <option value="pending">Pending</option>
            <option value="under_review">Under Review</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div class="mb-0">
          <label for="modalAdminNotes" class="form-label">Admin Notes</label>
          <textarea id="modalAdminNotes" class="form-control" rows="3" placeholder="Internal notes about this request..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="saveRequestStatus">
          <i class="ri-save-line me-1"></i> Update Request
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
