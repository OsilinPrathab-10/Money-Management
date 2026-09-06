@extends('layouts/layoutMaster')

@section('title', 'Feature Activation')

@section('page-script')
@vite(['resources/assets/custom-js/feature-activation.js'])
@endsection

@section('content')

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-6">
  <div>
    <h4 class="mb-1">Feature Activation</h4>
    <p class="text-muted mb-0">Manage system-wide features and settings</p>
  </div>
</div>

<!-- System Section -->
<div class="card mb-6">
  <div class="card-header">
    <h5 class="mb-0">System</h5>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card border shadow-none h-100">
          <div class="card-body">
            <h6 class="card-title mb-2">Maintenance Mode Activation</h6>
            <p class="text-muted small mb-4">Enable maintenance mode to prevent user access during updates</p>

            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-label-{{ $maintenanceMode == '1' ? 'success' : 'secondary' }}" id="maintenanceBadge">
                {{ $maintenanceMode == '1' ? 'Active' : 'Inactive' }}
              </span>
              <div class="form-check form-switch mb-0">
                <input
                  class="form-check-input"
                  type="checkbox"
                  id="maintenanceModeSwitch"
                  {{ $maintenanceMode == '1' ? 'checked' : '' }}
                  style="cursor: pointer; width: 3rem; height: 1.5rem;"
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- File System Section -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0">File System</h5>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card border shadow-none h-100 position-relative">
          <div class="position-absolute top-0 end-0 m-3">
            <span role="button" tabindex="0" class="d-inline-flex align-items-center justify-content-center p-2 rounded-circle bg-label-primary-soft" data-bs-toggle="modal" data-bs-target="#s3ConfigModal" aria-label="Edit S3 configuration">
              <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
            </span>
          </div>

          <div class="card-body">
            <div class="d-flex align-items-center mb-3">
              <div class="me-3" style="width: 48px; height: 48px;">
                <img src="{{ asset('assets/img/icons/brands/aws.png') }}" alt="AWS logo" class="img-fluid rounded" style="width: 48px; height: 48px; object-fit: contain;">
              </div>
              <div>
                <h6 class="card-title mb-0">AWS S3 File System</h6>
                <p class="text-muted small mb-0">Cloud storage integration</p>
              </div>
            </div>

            <p class="text-muted mb-3">Store files on Amazon S3 cloud storage alongside local storage.</p>

            <div class="d-flex justify-content-between align-items-center">
              <span class="badge bg-label-secondary" id="s3Status">Inactive</span>
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="s3Toggle" style="cursor: pointer; width: 3rem; height: 1.5rem;">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- S3 Configuration Modal -->
<div class="modal fade" id="s3ConfigModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">S3 File System Credentials</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="s3ConfigForm">
          <div class="mb-3">
            <label class="form-label">AWS Access Key ID <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="access_key_id" placeholder="AKIAIOSFODNN7EXAMPLE" required>
          </div>
          <div class="mb-3">
            <label class="form-label">AWS Secret Access Key <span class="text-danger">*</span></label>
            <input type="password" class="form-control" name="secret_access_key" placeholder="wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY" required>
          </div>
          <div class="mb-3">
            <label class="form-label">AWS Region <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="region" placeholder="us-east-1" required>
          </div>
          <div class="mb-3">
            <label class="form-label">AWS Bucket Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="bucket" placeholder="my-loan-app-bucket" required>
          </div>
          <div class="mb-3">
            <label class="form-label">AWS URL (Optional)</label>
            <input type="text" class="form-control" name="url" placeholder="https://my-bucket.s3.us-east-1.amazonaws.com">
          </div>
          <div class="alert alert-success mb-0">
            <i class="ti ti-info-circle me-2"></i>
            <small>Files will be stored in both local storage and S3 when enabled.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveS3Config">
          <i class="ti ti-device-floppy me-1"></i>Save Configuration
        </button>
      </div>
    </div>
  </div>
</div>

@endsection
