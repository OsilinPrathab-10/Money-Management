@extends('layouts/layoutMaster')

@section('title', $templateId ? 'Edit Email Template' : 'Create Email Template')

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $templateId ? 'Edit Email Template' : 'Create Email Template' }}</h5>
        <a href="{{ route('email-template-index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back
        </a>
      </div>
      <div class="card-body">
        <form id="emailTemplateForm" action="{{ $templateId ? route('email-template-update', ['id' => $templateId]) : route('email-template-store') }}" 
              method="POST" enctype="multipart/form-data">
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
              <select id="identifier" name="identifier" class="form-select @error('identifier') is-invalid @enderror" required>
                <option value="">Select template type</option>
                <optgroup label="General">
                  <option value="otp_email" {{ old('identifier', $templateIdentifier) == 'otp_email' ? 'selected' : '' }}>OTP Email</option>
                  <option value="loan_documents" {{ old('identifier', $templateIdentifier) == 'loan_documents' ? 'selected' : '' }}>Loan Documents</option>
                  <option value="loan_statement" {{ old('identifier', $templateIdentifier) == 'loan_statement' ? 'selected' : '' }}>Loan Statement</option>
                  <option value="loan_repayment" {{ old('identifier', $templateIdentifier) == 'loan_repayment' ? 'selected' : '' }}>Loan Repayment</option>
                  <option value="loan_closed" {{ old('identifier', $templateIdentifier) == 'loan_closed' ? 'selected' : '' }}>Loan Closed</option>
                  <option value="loan_foreclosed" {{ old('identifier', $templateIdentifier) == 'loan_foreclosed' ? 'selected' : '' }}>Loan Foreclosed</option>
                  <option value="partial_payment_confirmation" {{ old('identifier', $templateIdentifier) == 'partial_payment_confirmation' ? 'selected' : '' }}>Partial Payment Confirmation</option>
                  <option value="prepayment_confirmation" {{ old('identifier', $templateIdentifier) == 'prepayment_confirmation' ? 'selected' : '' }}>Prepayment Confirmation</option>
                </optgroup>
                <optgroup label="EMI Reminders">
                  <option value="emi_before_due" {{ old('identifier', $templateIdentifier) == 'emi_before_due' ? 'selected' : '' }}>EMI Before Due Reminder</option>
                  <option value="emi_due_today" {{ old('identifier', $templateIdentifier) == 'emi_due_today' ? 'selected' : '' }}>EMI Due Today Reminder</option>
                  <option value="emi_overdue" {{ old('identifier', $templateIdentifier) == 'emi_overdue' ? 'selected' : '' }}>EMI Overdue Notice</option>
                  <option value="emi_urgent_overdue" {{ old('identifier', $templateIdentifier) == 'emi_urgent_overdue' ? 'selected' : '' }}>EMI Urgent Overdue Notice</option>
                </optgroup>
              </select>
              @error('identifier')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              
            </div>
          </div>

          <!-- Subject -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="subject">Email Subject <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <input type="text" id="subject" name="subject" class="form-control @error('subject') is-invalid @enderror" 
                placeholder="Enter email subject" value="{{ old('subject', $templateSubject) }}" required />
              @error('subject')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Use @{{ placeholder }} for dynamic values (e.g., @{{ client_name }}, @{{ loan_amount }})</small>
            </div>
          </div>

          <!-- Email Body -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="email_body">Email Body <span class="text-danger">*</span></label>
            <div class="col-sm-9">
              <textarea id="email_body" name="email_body" class="form-control @error('email_body') is-invalid @enderror" 
                rows="8">{{ old('email_body', $templateBody) }}</textarea>
              @error('email_body')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Use @{{ placeholder }} for dynamic values. HTML tags are supported. See DocumentPlaceholderService for available placeholders.</small>
            </div>
          </div>

          <!-- Image Upload -->
          <div class="row mb-5">
            <label class="col-sm-3 col-form-label" for="image">Email Image</label>
            <div class="col-sm-9">
              <input type="file" id="image" name="image" class="form-control @error('image') is-invalid @enderror" 
                accept="image/*" />
              @error('image')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Optional image to include in the email (JPG, PNG, max 2MB)</small>
              
              @if($templateId && $templateImage)
              <div class="mt-3">
                <img src="{{ asset('storage/' . $templateImage) }}" alt="Current Image" class="img-thumbnail" style="max-width: 200px;">
                <p class="text-muted small mt-2">Current image (upload new to replace)</p>
              </div>
              @endif
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
              <a href="{{ route('email-template-index') }}" class="btn btn-outline-secondary">
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

@section('page-script')
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('emailTemplateForm');
      
      if (typeof tinymce !== 'undefined') {
        tinymce.init({
          selector: '#email_body',
          height: 400,
          menubar: false,
          branding: false,
          plugins: 'advlist autolink lists link image table code fullscreen preview',
          toolbar: 'undo redo | styles | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code preview fullscreen',
          content_style: 'body { font-family: "Inter", "Helvetica", sans-serif; font-size: 14px; }'
        });

        if (form) {
          form.addEventListener('submit', function (e) {
            // Save TinyMCE content to textarea
            if (tinymce.get('email_body')) {
              tinymce.get('email_body').save();
              
              // Custom validation - check if content is empty
              const content = tinymce.get('email_body').getContent();
              if (!content || content.trim() === '') {
                e.preventDefault();
                alert('Please enter email body content');
                tinymce.get('email_body').focus();
                return false;
              }
            }
          });
        }
      }
    });
  </script>
@endsection
