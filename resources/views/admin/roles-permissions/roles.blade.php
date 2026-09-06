@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Roles - Apps')

@section('vendor-style')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss', 'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss', 'resources/assets/vendor/libs/@form-validation/form-validation.scss'])
@endsection

@section('vendor-script')
  @vite(['resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/popular.js', 'resources/assets/vendor/libs/@form-validation/bootstrap5.js', 'resources/assets/vendor/libs/@form-validation/auto-focus.js'])
@endsection

@section('page-script')
  @vite(['resources/assets/js/user-roles.js'])
@endsection

@section('content')
  <div class="row g-4 mb-5">
    @if (request()->has('success'))
        <div class="alert alert-success alert-dismissible fade show mt-3">
            {{ request()->get('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="col-12">
      <h4 class="mb-2">Roles List</h4>
      <p class="text-muted mb-0">A role provides access to predefined menus and features so administrators only see what they need.</p>
    </div>
    <div class="col-md-12 col-lg-12">
      <div class="text-sm-end text-center ms-auto">
            <button data-bs-target="#addRoleModal" data-bs-toggle="modal" class="btn btn-primary mb-2 add-new-role">+ Add Role</button>
          </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12">
      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-3">
        <div>
          <h5 class="mb-1">Total users with their roles</h5>
          <p class="text-muted mb-0">Find every administrator account and the role assigned to them.</p>
        </div>
      </div>
      <div class="card">
        <div class="card-datatable table-responsive datatable-roles">
          <table class="datatables-users table">
            <thead>
              <tr>
                <th></th>
                <th></th>
                <th>S.No</th>
                <th>User</th>
                <th>Role</th>
                <th>Actions</th>
              </tr>
            </thead>
          </table>
        </div>
      </div>
    </div>
  </div>
  <!--/ Role cards -->

  <!--  Modal -->
  @include('admin/roles-permissions/modals/modal-add-role')
  @include('admin/roles-permissions/modals/modal-add-permission')
  @include('admin/roles-permissions/modals/modal-edit-permission')

  <!-- /  Modal -->

  <div class="modal fade" id="confirmDeleteRoleModal" tabindex="-1" aria-hidden="true">
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
          <p class="text-center">Do you really want to delete "<strong id="deleteRoleName"></strong>"?</p>
        </div>
        <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="button" class="btn btn-danger" id="confirmDeleteRoleBtn">
            <i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete
          </button>
        </div>
      </div>
    </div>
  </div>
@endsection
