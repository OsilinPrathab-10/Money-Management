@extends('layouts/layoutMaster')

@section('title', 'Loan Document Templates')

@section('page-script')
@vite(['resources/assets/custom-js/loan-document-template.js'])
@endsection

@section('vendor-style')
@if(session('success'))
<meta name="success-message" content="{{ session('success') }}">
@endif

@if(session('error'))
<meta name="error-message" content="{{ session('error') }}">
@endif
@endsection

@section('content')

  <!-- Basic Bootstrap Table -->
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Loan Document Templates</h5>
      <a href="{{ route('loan-document-templates.create') }}" class="btn btn-primary">
        <i class="icon-base ri ri-add-line me-1"></i> Add New
      </a>
    </div>
    <div class="table-responsive text-nowrap">
      <table class="table">
        <thead>
          <tr>
            <th>Document Name</th>
            <th>Type</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($templates as $template)
          <tr>
            <td>
              <i class="icon-base ri-file-text-line icon-22px text-primary me-4"></i>
              <span class="fw-medium">{{ $template->title }}</span>
            </td>
            <td>
              <span class="badge rounded-pill bg-label-info">
                {{ ucfirst($template->type) }}
              </span>
            </td>
            <td>
              <div class="d-flex gap-4 p-4 p-sm-2 py-sm-0 pt-0 ms-4 ms-sm-0">
                <a href="{{ route('loan-document-templates.show', ['loan_document_template' => $template->id]) }}" title="View">
                  <i class="icon-base ri ri-eye-line icon-18px text-info"></i>
                </a>
                <a href="{{ route('loan-document-templates.edit', ['loan_document_template' => $template->id]) }}" title="Edit">
                  <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
                </a>
                <a href="javascript:void(0);" class="delete-template" data-id="{{ $template->id }}" data-name="{{ $template->title }}" title="Delete">
                  <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
                </a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="text-center">No document templates found. Click "Add New" to create one.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <!--/ Basic Bootstrap Table -->

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Delete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-delete-bin-6-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center">Do you really want to delete "<strong id="deleteTemplateName"></strong>"?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
            <i class="icon-base ri ri-delete-bin-6-line me-1"></i> Delete
          </button>
        </div>
      </div>
    </div>
  </div>

@endsection
