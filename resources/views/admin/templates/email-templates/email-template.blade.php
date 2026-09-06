@extends('layouts/layoutMaster')

@section('title', 'Email Templates')

@section('page-script')
@vite(['resources/assets/custom-js/email-template.js'])
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
      <h5 class="mb-0">Email Templates</h5>
      <a href="{{ route('email-template-create') }}" class="btn btn-primary">
        <i class="icon-base ri ri-add-line me-1"></i> Add New
      </a>
    </div>
    <div class="table-responsive text-nowrap">
      <table class="table">
        <thead>
          <tr>
            <th>S.No</th>
            <th>Template Name</th>
            <th>Identifier</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($emailTemplates as $template)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td>
              <i class="icon-base ri-mail-line icon-22px text-primary me-4"></i>
              <span>{{ $template->name }}</span>
            </td>
            <td>
              <code class="bg-light px-2 py-1 rounded">{{ $template->identifier }}</code>
            </td>
            <td>
              <span class="text-muted">{{ strlen($template->subject) > 40 ? substr($template->subject, 0, 40) . '...' : $template->subject }}</span>
            </td>
            <td>
              <span class="badge rounded-pill bg-label-{{ $template->status ? 'success' : 'secondary' }}">
                {{ $template->status ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td>
              <div class="d-flex gap-4 p-4 p-sm-2 py-sm-0 pt-0 ms-4 ms-sm-0">
                <a href="{{ route('email-template-edit', ['id' => $template->id]) }}"><i class="icon-base ri ri-pencil-line icon-18px text-primary"></i></a>
                <a href="javascript:void(0);" class="delete-template" data-id="{{ $template->id }}" data-name="{{ $template->name }}"><i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i></a>
              </div>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-5">No email templates found. Click "Add New" to create one.</td>
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
