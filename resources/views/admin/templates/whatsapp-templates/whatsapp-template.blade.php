@extends('layouts/layoutMaster')

@section('title', 'WhatsApp Templates')

@section('page-script')
@vite(['resources/assets/custom-js/whatsapp-template.js'])
@endsection

@section('content')

<!-- Alert Container for Toast Notifications -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}">
</div>

<!-- WhatsApp Templates Card -->
<div class="card">
  <div class="card-header border-bottom d-flex justify-content-between align-items-center">
    <div>
      <h5 class="card-title mb-1">WhatsApp Templates</h5>
      <p class="text-muted mb-0 small">Manage WhatsApp template mappings from Gallabox.</p>
    </div>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-primary" id="fetchGallaboxBtn">
        <i class="icon-base ri ri-refresh-line me-1"></i> Fetch from Gallabox
      </button>
      <a href="{{ route('whatsapp-template-create') }}" class="btn btn-primary">
        <i class="icon-base ri ri-add-line me-1"></i> Add New Template
      </a>
    </div>
  </div>
  
  <div class="table-responsive">
    <table class="table table-hover">
      <thead>
        <tr>
          <th style="width: 80px;">S.NO</th>
          <th>Template Name</th>
          <th>Event Type</th>
          <th style="width: 120px;">Status</th>
          <th style="width: 120px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($whatsappTemplates as $index => $template)
          <tr>
            <td>{{ $index + 1 }}</td>
            <td>
              {{ $template->template_name }}
            </td>
            <td>
              <span class="badge bg-label-info">{{ $template->event_type_label }}</span>
            </td>
            <td>
              <div class="form-check form-switch">
                <input class="form-check-input status-toggle" type="checkbox" 
                  data-id="{{ $template->id }}"
                  {{ $template->is_active ? 'checked' : '' }}>
              </div>
            </td>
            <td>
              <div class="d-flex gap-4 p-4 p-sm-2 py-sm-0 pt-0 ms-4 ms-sm-0">
                <a href="{{ route('whatsapp-template-edit', $template->id) }}" title="Edit">
                  <i class="icon-base ri ri-pencil-line icon-18px text-primary"></i>
                </a>
                <a href="javascript:void(0);" class="delete-template" 
                  data-id="{{ $template->id }}"
                  data-name="{{ $template->template_name }}"
                  title="Delete">
                  <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
                </a>
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center py-4">
              <div class="mb-3">
                <i class="ri-inbox-line ri-48px text-muted"></i>
              </div>
              <p class="text-muted mb-0">No WhatsApp templates found. Click "Add New Template" to create one.</p>
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Delete</h5>
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

<!-- Gallabox Templates Modal -->
<div class="modal fade" id="gallaboxTemplatesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Gallabox Templates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="gallaboxTemplatesLoading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-3 text-muted">Fetching templates from Gallabox...</p>
        </div>
        <div id="gallaboxTemplatesError" class="alert alert-danger d-none"></div>
        <div id="gallaboxTemplatesList" class="d-none">
          <p class="text-muted mb-3">Select a template to add to your system:</p>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Template Name</th>
                  <th>Language</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="gallaboxTemplatesBody">
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@endsection
