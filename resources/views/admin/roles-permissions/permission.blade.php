@extends('layouts/layoutMaster')

@section('title', 'Permission - Apps')

@section('vendor-style')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
    'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
    'resources/assets/vendor/libs/@form-validation/form-validation.scss',
    'resources/assets/vendor/libs/animate-css/animate.scss',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
  ])
@endsection

@section('vendor-script')
  @vite([
    'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/popular.js',
    'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
    'resources/assets/vendor/libs/@form-validation/auto-focus.js',
    'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
  ])
@endsection

@section('page-script')
  @vite(['resources/assets/js/user-permission.js', 'resources/assets/js/modal-edit-permission.js'])
@endsection

@section('content')
  <!-- Success Alert -->
  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @elseif(request()->query('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ request()->query('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  @endif

  <!-- Permission Table -->
  <div class="card">
    <div class="card-datatable table-responsive">
      <table class="datatables-permissions table">
        <thead>
          <tr>
            <th></th>
            <th></th>
            <th>Name</th>
            <th>Assigned To</th>
            <th>Created Date</th>
            <th>Actions</th>
          </tr>
        </thead>
      </table>
    </div>
  </div>
  <!--/ Permission Table -->

  <!-- Modal -->
  @include('admin/roles-permissions/modals/modal-add-permission')
  @include('admin/roles-permissions/modals/modal-edit-permission')
  <!-- /Modal -->

  <div class="modal fade" id="confirmDeletePermissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-0">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-delete-bin-6-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center">Do you really want to delete "<strong id="deletePermissionName"></strong>"?</p>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="button" class="btn btn-danger" id="confirmDeletePermissionBtn">
            <i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete
          </button>
        </div>
      </div>
    </div>
  </div>
@endsection