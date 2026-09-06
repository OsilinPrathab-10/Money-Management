@extends('layouts/layoutMaster')

@section('title', 'View Loan Document Template')

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">View Document Template</h5>
        <div class="d-flex gap-2">
          <a href="{{ route('loan-document-templates.edit', ['loan_document_template' => $template->id]) }}" class="btn btn-primary btn-sm">
            <i class="icon-base ri ri-pencil-line me-1"></i> Edit
          </a>
          <a href="{{ route('loan-document-templates.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
          </a>
        </div>
      </div>
      <div class="card-body">
        <div class="row mb-4">
          <div class="col-md-6">
            <h6 class="text-muted mb-2">Document Name</h6>
            <p class="fw-medium">{{ $template->title }}</p>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted mb-2">Type</h6>
            <span class="badge rounded-pill bg-label-info">{{ ucfirst(str_replace('_', ' ', $template->type)) }}</span>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-12">
            <h6 class="text-muted mb-2">Template Content</h6>
            <div class="card bg-light">
              <div class="card-body">
                <div class="template-preview" style="white-space: pre-wrap; word-wrap: break-word;">
                  {!! $template->body !!}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <h6 class="text-muted mb-2">Created At</h6>
            <p>{{ $template->created_at->format('d-m-Y h:i A') }}</p>
          </div>
          <div class="col-md-6">
            <h6 class="text-muted mb-2">Last Updated</h6>
            <p>{{ $template->updated_at->format('d-m-Y h:i A') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@endsection
