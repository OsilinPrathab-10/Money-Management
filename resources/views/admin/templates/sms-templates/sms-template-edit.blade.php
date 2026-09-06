@extends('layouts/layoutMaster')

@section('title', $templateId ? 'Edit SMS Template' : 'Create SMS Template')

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $templateId ? 'Edit SMS Template' : 'Create SMS Template' }}</h5>
        <a href="{{ route('sms-template-index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back
        </a>
      </div>
      <div class="card-body">
        <form action="{{ $templateId ? route('sms-template-update', ['id' => $templateId]) : route('sms-template-store') }}" 
              method="POST">
          @csrf
          @if($templateId)
            @method('PUT')
          @endif

          <!-- Template Name -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="name">Template Name <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" 
                placeholder="Enter template name" value="{{ old('name', $templateName) }}" required />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Identifier -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="identifier">Identifier <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" id="identifier" name="identifier" class="form-control @error('identifier') is-invalid @enderror" 
                placeholder="e.g. otp_verification, welcome_message" value="{{ old('identifier', $templateIdentifier) }}" required />
              @error('identifier')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Unique identifier for this template (lowercase, underscore allowed)</small>
            </div>
          </div>

          <!-- Template ID -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="template_id">Template ID</label>
            <div class="col-sm-9">
              <input type="text" id="template_id" name="template_id" class="form-control @error('template_id') is-invalid @enderror" 
                placeholder="Provider template ID (optional)" value="{{ old('template_id', $templateIdValue ?? '') }}" />
              @error('template_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">SMS provider specific template ID (if applicable)</small>
            </div>
          </div>

          <!-- SMS Body -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="sms_body">SMS Body <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <textarea id="sms_body" name="sms_body" class="form-control @error('sms_body') is-invalid @enderror" 
                rows="4" placeholder="Enter SMS content with placeholders like [[name]], [[code]]" required>{{ old('sms_body', $templateBody) }}</textarea>
              @error('sms_body')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Use [[placeholder]] for dynamic values (e.g., [[name]], [[code]], [[amount]])</small>
            </div>
          </div>

          <!-- Status -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label">Status</label>
            <div class="col-sm-9">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="status" name="status" value="1" 
                  {{ old('status', $templateStatus) ? 'checked' : '' }}>
                <label class="form-check-label" for="status">Active</label>
              </div>
              <small class="text-muted">Enable this template for use</small>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="row">
            <div class="col-sm-9 offset-sm-3">
              <button type="submit" class="btn btn-primary me-3">
                <i class="icon-base ri ri-save-line me-1"></i> {{ $templateId ? 'Update' : 'Create' }}
              </button>
              <a href="{{ route('sms-template-index') }}" class="btn btn-outline-secondary">
                <i class="icon-base ri ri-close-line me-1"></i> Cancel
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
