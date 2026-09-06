@extends('layouts/layoutMaster')

@section('title', 'View WhatsApp Template')

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">WhatsApp Template Details</h5>
        <a href="{{ route('whatsapp-template-index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
        </a>
      </div>
      <div class="card-body">
        <div class="alert alert-info mb-4">
          <i class="icon-base ri ri-information-line me-2"></i>
          <strong>Note:</strong> WhatsApp message content is managed and approved in Gallabox. This is a read-only view.
        </div>

        <div class="row g-4">
          <!-- Template Name -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Template Name</label>
            <div class="form-control-plaintext">{{ $template->template_name }}</div>
          </div>

          <!-- Event Type -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Event Type</label>
            <div class="form-control-plaintext">
              <span class="badge bg-label-info">{{ $template->event_type_label }}</span>
            </div>
          </div>

          <!-- Provider -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Provider</label>
            <div class="form-control-plaintext">{{ ucfirst($template->provider) }}</div>
          </div>

          <!-- Provider Template Name -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Provider Template Name</label>
            <div class="form-control-plaintext">
              <code class="bg-light px-2 py-1 rounded">{{ $template->provider_template_name }}</code>
            </div>
          </div>

          <!-- Status -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <div class="form-control-plaintext">
              <span class="badge rounded-pill bg-label-{{ $template->is_active ? 'success' : 'secondary' }}">
                {{ $template->is_active ? 'Active' : 'Inactive' }}
              </span>
            </div>
          </div>

          <!-- Created At -->
          <div class="col-md-6">
            <label class="form-label fw-semibold">Created At</label>
            <div class="form-control-plaintext">{{ $template->created_at->format('d-m-Y h:i A') }}</div>
          </div>

          <!-- Variables Mapping -->
          @if($template->variables && count($template->variables) > 0)
          <div class="col-12">
            <label class="form-label fw-semibold">Variable Mappings</label>
            <div class="table-responsive">
              <table class="table table-sm table-bordered">
                <thead class="table-light">
                  <tr>
                    <th>Position</th>
                    <th>Variable Name</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($template->variables as $position => $variableName)
                  <tr>
                    <td><code>{{ $position }}</code></td>
                    <td>{{ $variableName }}</td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
          @else
          <div class="col-12">
            <label class="form-label fw-semibold">Variable Mappings</label>
            <div class="form-control-plaintext text-muted">No variables defined for this template</div>
          </div>
          @endif

          <!-- Event Type Code (for reference) -->
          <div class="col-12">
            <label class="form-label fw-semibold">Event Type Code</label>
            <div class="form-control-plaintext">
              <code class="bg-light px-2 py-1 rounded">{{ $template->event_type }}</code>
              <small class="text-muted ms-2">Use this code when triggering WhatsApp notifications</small>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-4 border-top">
          <a href="{{ route('whatsapp-template-index') }}" class="btn btn-primary">
            <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to Templates
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
