@extends('layouts/layoutMaster')

@section('title', 'Company Details & Branding')

@section('content')
<!-- Success/Error Messages -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}">
</div>

<!-- Validation Errors -->
@if ($errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
  <h5 class="alert-heading mb-2"><i class="ri-error-warning-line me-2"></i>Please fix the following errors:</h5>
  <ul class="mb-0 ps-3">
    @foreach ($errors->all() as $error)
      <li>{{ $error }}</li>
    @endforeach
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-1">Company Details & Branding</h5>
        <p class="text-muted mb-0">Manage your company information and admin panel branding</p>
      </div>

      <div class="card-body">
        <form action="{{ route('website-homepage-update') }}" method="POST" enctype="multipart/form-data" id="companyDetailsForm">
          @csrf

          <!-- Logo & Branding Section -->
          <div class="row g-4 mb-5">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-image-line me-2"></i>Logo & Branding</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Company Logo -->
            <div class="col-md-4">
              <label class="form-label">Company Logo</label>
              <div class="mb-3">
                <div class="bg-label-secondary rounded p-4 text-center" id="logoPreviewBox">
                  <img id="logoPreview"
                       src="{{ !empty($appearance->logo) ? route('storage.serve', ['path' => $appearance->logo]) : '' }}"
                       alt="Logo Preview"
                       class="img-fluid {{ empty($appearance->logo) ? 'd-none' : '' }}"
                       style="max-height: 90px; max-width: 240px; object-fit: contain;">
                  <div id="logoPlaceholder" class="{{ !empty($appearance->logo) ? 'd-none' : '' }}">
                    <i class="ri-image-line ri-2x text-muted"></i>
                    <p class="mb-0 text-muted mt-2">No logo uploaded</p>
                  </div>
                </div>
              </div>
              <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
              <small class="text-muted">Recommended: 240×80px | PNG, SVG (Max 2MB)</small>
            </div>

            <!-- Dark Mode Logo -->
            <div class="col-md-4">
              <label class="form-label">Logo (Dark Mode)</label>
              <div class="mb-3">
                <div class="bg-label-secondary rounded p-4 text-center" id="logoDarkPreviewBox">
                  <img id="logoDarkPreview"
                       src="{{ !empty($appearance->logo_dark) ? route('storage.serve', ['path' => $appearance->logo_dark]) : '' }}"
                       alt="Dark Logo Preview"
                       class="img-fluid {{ empty($appearance->logo_dark) ? 'd-none' : '' }}"
                       style="max-height: 90px; max-width: 240px; object-fit: contain;">
                  <div id="logoDarkPlaceholder" class="{{ !empty($appearance->logo_dark) ? 'd-none' : '' }}">
                    <i class="ri-image-line ri-2x text-muted"></i>
                    <p class="mb-0 text-muted mt-2">No logo uploaded</p>
                  </div>
                </div>
              </div>
              <input type="file" class="form-control" id="logoDark" name="logo_dark" accept="image/*">
              <small class="text-muted">Recommended: 240×80px | PNG, SVG (Max 2MB)</small>
            </div>

            <!-- Favicon -->
            <div class="col-md-4">
              <label class="form-label">Favicon</label>
              <div class="mb-3">
                <div class="bg-label-secondary rounded p-4 text-center" id="faviconPreviewBox">
                  <img id="faviconPreview"
                       src="{{ !empty($appearance->favicon) ? route('storage.serve', ['path' => $appearance->favicon]) : '' }}"
                       alt="Favicon Preview"
                       class="img-fluid {{ empty($appearance->favicon) ? 'd-none' : '' }}"
                       style="max-height: 64px; max-width: 64px; object-fit: contain;">
                  <div id="faviconPlaceholder" class="{{ !empty($appearance->favicon) ? 'd-none' : '' }}">
                    <i class="ri-image-line ri-2x text-muted"></i>
                    <p class="mb-0 text-muted mt-2">No favicon uploaded</p>
                  </div>
                </div>
              </div>
              <input type="file" class="form-control" id="favicon" name="favicon" accept="image/*">
              <small class="text-muted">Recommended: 64×64px | ICO, PNG (Max 1MB)</small>
            </div>
          </div>

          <!-- Company Information Section -->
          <div class="row g-4 mb-5">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-building-line me-2"></i>Company Information</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Company Name -->
            <div class="col-md-6">
              <label class="form-label" for="companyName">Company Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('company_name') is-invalid @enderror" 
                     id="companyName" name="company_name" 
                     placeholder="Enter company name" required
                     value="{{ old('company_name', $companyDetail->company_name) }}">
              @error('company_name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Company Slogan -->
            <div class="col-md-6">
              <label class="form-label" for="companySlogan">Company Slogan <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('company_slogan') is-invalid @enderror" 
                     id="companySlogan" name="company_slogan" 
                     placeholder="Enter company slogan" required
                     value="{{ old('company_slogan', $companyDetail->company_slogan) }}">
              @error('company_slogan')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Contact Information Section -->
          <div class="row g-4 mb-5">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-phone-line me-2"></i>Contact Information</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Company Email -->
            <div class="col-md-6">
              <label class="form-label" for="companyEmail">Company Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control @error('company_email') is-invalid @enderror" 
                     id="companyEmail" name="company_email" 
                     placeholder="company@example.com" required
                     value="{{ old('company_email', $companyDetail->company_email) }}">
              @error('company_email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Company Mobile -->
            <div class="col-md-6">
              <label class="form-label" for="companyMobile">Company Mobile <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control @error('company_mobile') is-invalid @enderror" 
                       id="companyMobile" name="company_mobile" 
                       placeholder="9876543210" required
                       pattern="[0-9]{10}" maxlength="10"
                       value="{{ old('company_mobile', $companyDetail->company_mobile) }}">
                @error('company_mobile')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <small class="text-muted">Enter 10-digit mobile number</small>
            </div>

            <!-- Alternate Mobile -->
            <div class="col-md-6">
              <label class="form-label" for="alternateMobile">Alternate Mobile</label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control @error('alternate_mobile') is-invalid @enderror" 
                       id="alternateMobile" name="alternate_mobile" 
                       placeholder="9876543210"
                       pattern="[0-9]{10}" maxlength="10"
                       value="{{ old('alternate_mobile', $companyDetail->alternate_mobile) }}">
                @error('alternate_mobile')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <small class="text-muted">Optional - 10 digits</small>
            </div>

            <!-- Support Email -->
            <div class="col-md-6">
              <label class="form-label" for="supportEmail">Support Email</label>
              <input type="email" class="form-control @error('support_email') is-invalid @enderror" 
                     id="supportEmail" name="support_email" 
                     placeholder="support@example.com"
                     value="{{ old('support_email', $companyDetail->support_email) }}">
              @error('support_email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Support Mobile -->
            <div class="col-md-6">
              <label class="form-label" for="supportMobile">Support Mobile</label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control @error('support_mobile') is-invalid @enderror" 
                       id="supportMobile" name="support_mobile" 
                       placeholder="9876543210"
                       pattern="[0-9]{10}" maxlength="10"
                       value="{{ old('support_mobile', $companyDetail->support_mobile) }}">
                @error('support_mobile')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <small class="text-muted">Optional - 10 digits</small>
            </div>

            <!-- Website URL -->
            <div class="col-md-6">
              <label class="form-label" for="websiteUrl">Website URL</label>
              <input type="url" class="form-control @error('website_url') is-invalid @enderror" 
                     id="websiteUrl" name="website_url" 
                     placeholder="https://example.com"
                     value="{{ old('website_url', $companyDetail->website_url ?? config('app.url')) }}">
              @error('website_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Address Section -->
          <div class="row g-4 mb-5">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-map-pin-line me-2"></i>Address</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Address Line 1 -->
            <div class="col-md-6">
              <label class="form-label" for="addressLine1">Address Line 1</label>
              <input type="text" class="form-control @error('address_line1') is-invalid @enderror" 
                     id="addressLine1" name="address_line1" 
                     placeholder="Building, Street"
                     value="{{ old('address_line1', $companyDetail->address_line1) }}">
              @error('address_line1')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Address Line 2 -->
            <div class="col-md-6">
              <label class="form-label" for="addressLine2">Address Line 2</label>
              <input type="text" class="form-control @error('address_line2') is-invalid @enderror" 
                     id="addressLine2" name="address_line2" 
                     placeholder="Area, Landmark"
                     value="{{ old('address_line2', $companyDetail->address_line2) }}">
              @error('address_line2')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- City -->
            <div class="col-md-3">
              <label class="form-label" for="city">City</label>
              <input type="text" class="form-control @error('city') is-invalid @enderror" 
                     id="city" name="city" 
                     placeholder="City"
                     value="{{ old('city', $companyDetail->city) }}">
              @error('city')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- State -->
            <div class="col-md-3">
              <label class="form-label" for="state">State</label>
              <input type="text" class="form-control @error('state') is-invalid @enderror" 
                     id="state" name="state" 
                     placeholder="State"
                     value="{{ old('state', $companyDetail->state) }}">
              @error('state')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Pincode -->
            <div class="col-md-3">
              <label class="form-label" for="pincode">Pincode</label>
              <input type="text" class="form-control @error('pincode') is-invalid @enderror" 
                     id="pincode" name="pincode" 
                     placeholder="123456"
                     value="{{ old('pincode', $companyDetail->pincode) }}">
              @error('pincode')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Country -->
            <div class="col-md-3">
              <label class="form-label" for="country">Country</label>
              <input type="text" class="form-control" id="country" name="country" 
                     value="India" readonly style="background-color: #f8f9fa;">
            </div>
          </div>

          <!-- Legal Details Section -->
          <div class="row g-4 mb-5">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-file-text-line me-2"></i>Legal Details</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- GST Number -->
            <div class="col-md-4">
              <label class="form-label" for="gstNumber">GST Number</label>
              <input type="text" class="form-control @error('gst_number') is-invalid @enderror" 
                     id="gstNumber" name="gst_number" 
                     placeholder="22AAAAA0000A1Z5"
                     value="{{ old('gst_number', $companyDetail->gst_number) }}">
              @error('gst_number')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Format: 22AAAAA0000A1Z5</small>
            </div>

            <!-- PAN Number -->
            <div class="col-md-4">
              <label class="form-label" for="panNumber">PAN Number</label>
              <input type="text" class="form-control @error('pan_number') is-invalid @enderror" 
                     id="panNumber" name="pan_number" 
                     placeholder="AAAAA0000A"
                     value="{{ old('pan_number', $companyDetail->pan_number) }}">
              @error('pan_number')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Format: AAAAA0000A</small>
            </div>

            <!-- CIN Number -->
            <div class="col-md-4">
              <label class="form-label" for="cinNumber">CIN Number</label>
              <input type="text" class="form-control @error('cin_number') is-invalid @enderror" 
                     id="cinNumber" name="cin_number" 
                     placeholder="U12345AB1234PTC123456"
                     value="{{ old('cin_number', $companyDetail->cin_number) }}">
              @error('cin_number')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Social Media Section -->
          <div class="row g-4 mb-4">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-share-line me-2"></i>Social Media Links</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Facebook URL -->
            <div class="col-md-6">
              <label class="form-label" for="facebookUrl">Facebook URL</label>
              <input type="url" class="form-control @error('facebook_url') is-invalid @enderror" 
                     id="facebookUrl" name="facebook_url" 
                     placeholder="https://facebook.com/yourpage"
                     value="{{ old('facebook_url', $companyDetail->facebook_url) }}">
              @error('facebook_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Twitter URL -->
            <div class="col-md-6">
              <label class="form-label" for="twitterUrl">Twitter URL</label>
              <input type="url" class="form-control @error('twitter_url') is-invalid @enderror" 
                     id="twitterUrl" name="twitter_url" 
                     placeholder="https://twitter.com/yourhandle"
                     value="{{ old('twitter_url', $companyDetail->twitter_url) }}">
              @error('twitter_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- LinkedIn URL -->
            <div class="col-md-6">
              <label class="form-label" for="linkedinUrl">LinkedIn URL</label>
              <input type="url" class="form-control @error('linkedin_url') is-invalid @enderror" 
                     id="linkedinUrl" name="linkedin_url" 
                     placeholder="https://linkedin.com/company/yourcompany"
                     value="{{ old('linkedin_url', $companyDetail->linkedin_url) }}">
              @error('linkedin_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <!-- Instagram URL -->
            <div class="col-md-6">
              <label class="form-label" for="instagramUrl">Instagram URL</label>
              <input type="url" class="form-control @error('instagram_url') is-invalid @enderror" 
                     id="instagramUrl" name="instagram_url" 
                     placeholder="https://instagram.com/yourhandle"
                     value="{{ old('instagram_url', $companyDetail->instagram_url) }}">
              @error('instagram_url')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Agent Contact Information Section -->
          <div class="row g-4 mb-4">
            <div class="col-12">
              <h6 class="text-primary mb-0"><i class="ri-user-settings-line me-2"></i>Agent Contact Information</h6>
              <hr class="mt-2 mb-4">
            </div>

            <!-- Agent Contact Email -->
            <div class="col-md-6">
              <label class="form-label" for="agentContactEmail">Agent Contact Email</label>
              <input type="email" class="form-control @error('agent_contact_email') is-invalid @enderror" 
                     id="agentContactEmail" name="agent_contact_email" 
                     placeholder="agent@example.com"
                     value="{{ old('agent_contact_email', $companyDetail->agent_contact_email) }}">
              @error('agent_contact_email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Email for agent-related inquiries</small>
            </div>

            <!-- Agent Contact Mobile -->
            <div class="col-md-6">
              <label class="form-label" for="agentContactMobile">Agent Contact Mobile</label>
              <div class="input-group">
                <span class="input-group-text">+91</span>
                <input type="text" class="form-control @error('agent_contact_mobile') is-invalid @enderror" 
                       id="agentContactMobile" name="agent_contact_mobile" 
                       placeholder="9876543210"
                       pattern="[0-9]{10}" maxlength="10"
                       value="{{ old('agent_contact_mobile', $companyDetail->agent_contact_mobile) }}">
                @error('agent_contact_mobile')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
              <small class="text-muted">Mobile number for agent support</small>
            </div>

            <!-- Working Hours -->
            <div class="col-md-12">
              <label class="form-label" for="workingHours">Working Hours</label>
              <input type="text" class="form-control @error('working_hours') is-invalid @enderror" 
                     id="workingHours" name="working_hours" 
                     placeholder="Mon-Fri: 9:00 AM - 6:00 PM, Sat: 9:00 AM - 1:00 PM"
                     value="{{ old('working_hours', $companyDetail->working_hours) }}">
              @error('working_hours')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <small class="text-muted">Business hours for agent operations</small>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="row">
            <div class="col-12">
              <hr class="my-4">
              <button type="submit" class="btn btn-primary">
                <i class="ri-save-line me-1"></i> Save Changes
              </button>
              <button type="reset" class="btn btn-outline-secondary ms-2">
                <i class="ri-refresh-line me-1"></i> Reset
              </button>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Toast notification function
  function showToast(type, message) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container position-fixed top-0 end-0 p-3';
      container.style.zIndex = '9999';
      document.body.appendChild(container);
    }

    const toastId = 'toast-' + Date.now();
    const icons = {
      success: 'ri-check-line',
      danger: 'ri-close-circle-line',
      warning: 'ri-alert-line'
    };
    const colors = {
      success: 'bg-success',
      danger: 'bg-danger',
      warning: 'bg-warning'
    };

    const toastHTML = `
      <div id="${toastId}" class="bs-toast toast fade show rounded-3 shadow-lg" role="alert">
        <div class="toast-header ${colors[type]} text-white border-0">
          <i class="${icons[type]} me-2"></i>
          <strong class="me-auto">${message}</strong>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
        </div>
      </div>
    `;

    container.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
      const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
      toast.show();
      toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
    }
  }

  // Show session messages
  const alertContainer = document.querySelector('.alert-container');
  if (alertContainer) {
    const success = alertContainer.getAttribute('data-success');
    const error = alertContainer.getAttribute('data-error');
    if (success) showToast('success', success);
    if (error) showToast('danger', error);
  }

  // Image preview function
  function previewImage(input, previewId, placeholderId) {
    const preview = document.getElementById(previewId);
    const placeholder = document.getElementById(placeholderId);
    
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.classList.remove('d-none');
        placeholder.classList.add('d-none');
      };
      reader.readAsDataURL(input.files[0]);
    }
  }

  // Logo preview handlers
  document.getElementById('logo')?.addEventListener('change', function() {
    previewImage(this, 'logoPreview', 'logoPlaceholder');
  });

  document.getElementById('logoDark')?.addEventListener('change', function() {
    previewImage(this, 'logoDarkPreview', 'logoDarkPlaceholder');
  });

  document.getElementById('favicon')?.addEventListener('change', function() {
    previewImage(this, 'faviconPreview', 'faviconPlaceholder');
  });

  // Mobile number validation
  const mobileInputs = ['companyMobile', 'alternateMobile', 'supportMobile', 'agentContactMobile'];
  mobileInputs.forEach(id => {
    const input = document.getElementById(id);
    if (input) {
      input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
      });
    }
  });
});
</script>
@endsection
