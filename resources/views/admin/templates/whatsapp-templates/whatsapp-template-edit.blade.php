@extends('layouts/layoutMaster')

@section('title', $template ? 'Edit WhatsApp Template' : 'Create WhatsApp Template')

@section('page-script')
@vite(['resources/assets/custom-js/whatsapp-template.js'])
@endsection

@section('content')

<!-- Alert Container for Toast Notifications -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-validation="{{ $errors->any() ? e($errors->first()) : '' }}">
</div>

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $template ? 'Edit' : 'Create' }} WhatsApp Template</h5>
        <a href="{{ route('whatsapp-template-index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
        </a>
      </div>
      <div class="card-body">
        

        <form action="{{ $template ? route('whatsapp-template-update', $template->id) : route('whatsapp-template-store') }}" method="POST" id="templateForm">
          @csrf
          @if($template)
            @method('PUT')
          @endif

          <div class="row g-4">
            <!-- Template Name -->
            <div class="col-md-6">
              <label class="form-label" for="template_name">Template Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('template_name') is-invalid @enderror" 
                id="template_name" name="template_name" 
                value="{{ old('template_name', $template->template_name ?? '') }}" 
                placeholder="e.g., KYC Approved" required>
              <small class="text-muted">Admin-friendly name for this template</small>
              @error('template_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Event Type -->
            <div class="col-md-6">
              <label class="form-label" for="event_type">Event Type <span class="text-danger">*</span></label>
              <input type="text" 
                class="form-control @error('event_type') is-invalid @enderror" 
                id="event_type" 
                name="event_type" 
                value="{{ old('event_type', $template->event_type ?? '') }}" 
                placeholder="e.g., kyc_verified, loan_approved"
                list="event_type_suggestions"
                required>
              <datalist id="event_type_suggestions">
                <option value="kyc_verified">
                <option value="kyc_rejected">
                <option value="kyc_approved">
                <option value="loan_approved">
                <option value="loan_rejected">
                <option value="loan_disbursed">
                <option value="emi_due_today">
                <option value="emi_overdue">
                <option value="emi_paid">
                <option value="payment_received">
              </datalist>
              <small class="text-muted">Type the event name that triggers this template (must match your code)</small>
              @error('event_type')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Provider -->
            <div class="col-md-6">
              <label class="form-label" for="provider">Provider <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('provider') is-invalid @enderror" 
                id="provider" name="provider" 
                value="{{ old('provider', $template->provider ?? 'gallabox') }}" 
                placeholder="gallabox" required>
              <small class="text-muted">WhatsApp provider (default: gallabox)</small>
              @error('provider')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Provider Template Name -->
            <div class="col-md-6">
              <label class="form-label" for="provider_template_name">Provider Template Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('provider_template_name') is-invalid @enderror" 
                id="provider_template_name" name="provider_template_name" 
                value="{{ old('provider_template_name', $template->provider_template_name ?? $prefillTemplateName ?? '') }}" 
                placeholder="e.g., kyc_approval_notification" required>
              <small class="text-muted">Exact template name from Gallabox</small>
              @error('provider_template_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Variables Mapping -->
            <div class="col-12">
              <label class="form-label">Variable Mapping</label>
              <p class="text-muted small mb-3">Map Gallabox template variable positions to field names</p>
              
              <div id="variablesContainer">
                @php
                  $existingVariables = old('variables') 
                    ? json_decode(old('variables'), true) 
                    : ($template->variables ?? []);
                @endphp

                @if(count($existingVariables) > 0)
                  @foreach($existingVariables as $position => $name)
                    <div class="variable-row mb-3">
                      <div class="row g-2 align-items-center">
                        <div class="col-md-1">
                          <input type="text" class="form-control variable-position" 
                            placeholder="Position" value="{{ $position }}" readonly>
                        </div>
                        <div class="col-md-10">
                          <input type="text" class="form-control variable-name" 
                            placeholder="Variable name (e.g., customer_name)" value="{{ $name }}">
                        </div>
                        <div class="col-md-1 text-center">
                          <a href="javascript:void(0);" class="remove-variable" title="Remove">
                            <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
                          </a>
                        </div>
                      </div>
                    </div>
                  @endforeach
                @else
                  <div class="variable-row mb-3">
                    <div class="row g-2 align-items-center">
                      <div class="col-md-1">
                        <input type="text" class="form-control variable-position" 
                          placeholder="Position" value="1" readonly>
                      </div>
                      <div class="col-md-10">
                        <input type="text" class="form-control variable-name" 
                          placeholder="Variable name (e.g., customer_name)">
                      </div>
                      <div class="col-md-1 text-center">
                        <a href="javascript:void(0);" class="remove-variable" title="Remove">
                          <i class="icon-base ri ri-delete-bin-6-line icon-18px text-danger"></i>
                        </a>
                      </div>
                    </div>
                  </div>
                @endif
              </div>

              <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addVariable">
                <i class="ri-add-line me-1"></i> Add Variable
              </button>

              <!-- Hidden field to store JSON -->
              <input type="hidden" name="variables" id="variablesJson">
            </div>

            <!-- Status -->
            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                  {{ old('is_active', $template->is_active ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_active">
                  Active (Enable this template)
                </label>
              </div>
            </div>
          </div>

          <div class="mt-4 pt-4 border-top">
            <button type="submit" class="btn btn-primary">
              <i class="icon-base ri ri-save-line me-1"></i> {{ $template ? 'Update' : 'Create' }} Template
            </button>
            <a href="{{ route('whatsapp-template-index') }}" class="btn btn-outline-secondary">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@endsection
