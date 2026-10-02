@extends('layouts/layoutMaster')

@section('title', 'Add New Client')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
  'resources/assets/vendor/libs/tagify/tagify.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/custom-css/form-enhancements.css'
])
<style>
  /* Premium Wizard Styling */
  :root {
    --wizard-primary: #666cff;
    --wizard-bg: #f8f9fa;
    --wizard-border: #e6e8eb;
    --wizard-text-muted: #8898aa;
  }

  .wizard-card {
    border: none;
    border-radius: 1.25rem !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
    overflow: hidden;
  }

  .wizard-header {
    background: linear-gradient(135deg, #ffffff 0%, #f9faff 100%);
    padding: 2rem 2.5rem;
    border-bottom: 1px solid var(--wizard-border);
  }

  .wizard-step { 
    display: none; 
    animation: fadeIn 0.4s ease;
  }
  .wizard-step.active { display: block; }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* Enhanced Step Indicators */
  .step-indicator-container {
    padding: 1.5rem 0 3.5rem;
    background: #fff;
  }

  .step-indicator {
    display: flex;
    justify-content: space-between;
    position: relative;
    max-width: 900px;
    margin: 0 auto;
    padding: 0 1rem;
  }

  .step-indicator::before {
    content: '';
    position: absolute;
    top: 24px;
    left: 10%;
    width: 80%;
    height: 4px;
    background: #f1f3f6;
    z-index: 1;
    border-radius: 10px;
  }

  .step-progress-line {
    position: absolute;
    top: 24px;
    left: 10%;
    height: 4px;
    background: var(--wizard-primary);
    z-index: 2;
    transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 10px;
    width: 0%;
  }

  .step-item {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 48px;
    min-width: 72px;
  }

  .step-circle {
    width: 48px;
    height: 48px;
    background: #fff;
    border: 4px solid #f1f3f6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--wizard-text-muted);
    transition: all 0.3s ease;
    cursor: default;
  }

  .step-item.active .step-circle {
    border-color: var(--wizard-primary);
    color: var(--wizard-primary);
    box-shadow: 0 0 20px rgba(102, 108, 255, 0.2);
    transform: scale(1.1);
  }

  .step-item.completed .step-circle {
    background: var(--wizard-primary);
    border-color: var(--wizard-primary);
    color: #fff;
  }

  .step-label {
    position: absolute;
    top: 58px;
    white-space: nowrap;
    display: inline-block;
    min-width: max-content;
    padding: 0 .35rem;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--wizard-text-muted);
    transition: all 0.3s ease;
  }

  .step-item.active .step-label {
    color: var(--wizard-primary);
    font-weight: 800;
  }

  /* Form Elements */
  .form-section-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #232e42;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
  }

  .form-section-title i {
    color: var(--wizard-primary);
    background: rgba(102, 108, 255, 0.1);
    padding: 8px;
    border-radius: 8px;
  }

  .input-group-custom {
    border-radius: 10px;
    transition: all 0.2s ease;
    display: flex !important;
  }

  .input-group-custom > .input-group-text {
    border-top-left-radius: 10px !important;
    border-bottom-left-radius: 10px !important;
    border-top-right-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
  }

  .input-group-custom > .form-control,
  .input-group-custom > .form-select {
    border-top-right-radius: 10px !important;
    border-bottom-right-radius: 10px !important;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
  }

  /* Select2 Input Group Integration - Refined */
  .input-group-custom .select2-container {
    flex: 1 1 auto !important;
    width: 1% !important;
    margin: 0 !important;
  }

  .input-group-custom .select2-container .select2-selection--single {
    height: 48px !important;
    border-top-left-radius: 0 !important;
    border-bottom-left-radius: 0 !important;
    border-top-right-radius: 10px !important;
    border-bottom-right-radius: 10px !important;
    border-left: none !important;
    border: 1px solid #d1d5db !important;
    border-left: none !important;
    display: flex;
    align-items: center;
    background-color: #fff;
    transition: all 0.2s ease;
  }

  .input-group-custom .select2-container--default .select2-selection--single .select2-selection__rendered {
    padding-left: 15px !important;
    color: #2d3748 !important;
    font-size: 0.95rem;
    font-weight: 500;
  }

  .input-group-custom .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 46px !important;
    right: 12px !important;
  }

  .input-group-custom .select2-container--focus .select2-selection--single,
  .input-group-custom .select2-container--open .select2-selection--single {
    border-color: var(--wizard-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(102, 108, 255, 0.1);
  }

  /* Fix for input-group-text alignment with select2 */
  .input-group-custom .input-group-text {
    height: 48px !important;
    border-color: #d1d5db !important;
    z-index: 4;
  }

  /* Custom Radios for Employment */
  .emp-card {
    border: 2px solid #f1f3f6;
    border-radius: 12px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
  }

  .emp-card:hover {
    border-color: #d1d5db;
    background: #f9fafb;
  }

  .form-check-input:checked + .emp-card {
    border-color: var(--wizard-primary);
    background: rgba(102, 108, 255, 0.03);
    box-shadow: 0 5px 15px rgba(102, 108, 255, 0.08);
  }

  .btn-wizard {
    padding: 0.8rem 2rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.3s ease;
  }

  .btn-wizard:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  }
</style>
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/bootstrap-select/bootstrap-select.js',
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js',
  'resources/assets/vendor/libs/tagify/tagify.js',
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
<script>
  let currentStep = 1;
  const totalSteps = 4;

  function showStep(step) {
    document.querySelectorAll('.wizard-step').forEach(el => el.classList.remove('active'));
    document.getElementById('step' + step).classList.add('active');
    
    document.querySelectorAll('.step-item').forEach((el, idx) => {
      if (idx + 1 < step) el.classList.add('completed');
      else el.classList.remove('completed');
      
      if (idx + 1 === step) el.classList.add('active');
      else el.classList.remove('active');
    });

    // Update Progress Line (10% start offset, 80% total span)
    const progressWidth = ((step - 1) / (totalSteps - 1)) * 80;
    const progressLine = document.getElementById('progressLine');
    if (progressLine) {
        progressLine.style.width = progressWidth + '%';
    }

    currentStep = step;
    
    // Toggle buttons
    document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
    document.getElementById('nextBtn').innerText = step === totalSteps ? 'Submit Registration' : 'Next Step';
  }

  function validateStep(step) {
    const stepEl = document.getElementById('step' + step);
    if (!stepEl) return true;
    
    // Enforce OTP/Verification on Step 2 (Identity & Banking — all mandatory)
    if (step === 2) {
      const aadharInput = document.getElementById('aadhar_number');
      if (!aadharInput || !aadharInput.value.trim() || !/^[0-9]{12}$/.test(aadharInput.value.trim())) {
         aadharInput?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Aadhaar Required',
            text: 'Aadhaar number must be exactly 12 digits.',
            confirmButtonColor: '#666cff'
         });
         return false;
      }

      const panInput = document.getElementById('pan_number');
      if (!panInput || !panInput.value.trim() || !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panInput.value.trim())) {
         panInput?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Invalid PAN',
            text: 'PAN number is mandatory and must match the standard format (e.g. ABCDE1234F).',
            confirmButtonColor: '#666cff'
         });
         return false;
      }

      const accountInput = document.querySelector('#clientWizardForm input[name="account_number"]');
      const ifscInput = document.querySelector('#clientWizardForm input[name="ifsc_code"]');
      const accountTypeSelect = document.querySelector('#clientWizardForm select[name="account_type"]');
      const holderInput = document.querySelector('#clientWizardForm input[name="account_holder"]');
      const bankNameInput = document.querySelector('#clientWizardForm input[name="bank_name"]');
      const branchInput = document.querySelector('#clientWizardForm input[name="branch_name"]');

      const rawAccountVal = accountInput ? accountInput.value.trim() : '';
      const cleanAccountVal = rawAccountVal.replace(/\s+/g, '');
      if (!cleanAccountVal || !/^[0-9]+$/.test(cleanAccountVal)) {
         accountInput?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Invalid Account Number',
            text: 'Bank account number is mandatory and must contain only numbers.',
            confirmButtonColor: '#666cff'
         });
         return false;
      }

      if (!ifscInput?.value.trim() || !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifscInput.value.trim())) {
         ifscInput?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Invalid IFSC Code',
            text: 'IFSC Code is mandatory and must be 11 characters (e.g., HDFC0001234).',
            confirmButtonColor: '#666cff'
         });
         return false;
      }

      if (!accountTypeSelect?.value) {
         accountTypeSelect?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Account Type Required',
            text: 'Please select the bank account type.',
            confirmButtonColor: '#666cff'
         });
         return false;
      }

      if (!branchInput?.value.trim()) {
         branchInput?.classList.add('is-invalid');
         Swal.fire({
            icon: 'error',
            title: 'Branch Required',
            text: 'Please enter the bank branch name manually (bank API does not return branch).',
            confirmButtonColor: '#666cff'
         });
         return false;
      }


    }

    // Find all required fields in this step
    const requiredInputs = Array.from(stepEl.querySelectorAll('[required]'));
    
    let isValid = true;
    let errorMessages = [];
    
    requiredInputs.forEach(input => {
      input.classList.remove('is-invalid');
      if (input.tagName === 'SELECT' && $(input).data('select2')) {
        $(input).next('.select2-container').find('.select2-selection').removeClass('is-invalid');
      }
      
      if (!input.value.trim()) {
        input.classList.add('is-invalid');
        if (input.tagName === 'SELECT' && $(input).data('select2')) {
          $(input).next('.select2-container').find('.select2-selection').addClass('is-invalid');
        }
        isValid = false;
        
        let labelText = '';
        const label = input.closest('.col-md-6, .col-md-4, .col-12, .p-3')?.querySelector('.form-label');
        if (label) {
          labelText = label.innerText.replace('*', '').trim();
        } else {
          labelText = input.placeholder || input.name;
        }
        errorMessages.push(`${labelText} is mandatory.`);
      } else {
        // Format checks if filled
        if (input.name === 'phone' && !/^[0-9]{10}$/.test(input.value)) {
          input.classList.add('is-invalid');
          isValid = false;
          errorMessages.push('Phone number must be exactly 10 digits.');
        }
        if (input.name === 'aadhar_number' && !/^[0-9]{12}$/.test(input.value)) {
          input.classList.add('is-invalid');
          isValid = false;
          errorMessages.push('Aadhar number must be exactly 12 digits.');
        }
      }
    });
    
    // Validate non-required fields if filled
    const otherInputs = Array.from(stepEl.querySelectorAll('input:not([required]), select:not([required]), textarea:not([required])'));
    otherInputs.forEach(input => {
      if (input.value.trim()) {
        if (input.name === 'pan_number' && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(input.value)) {
          input.classList.add('is-invalid');
          isValid = false;
          errorMessages.push('PAN Number must be in format ABCDE1234F.');
        }
        if (input.name === 'ifsc_code' && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(input.value)) {
          input.classList.add('is-invalid');
          isValid = false;
          errorMessages.push('IFSC Code must be 11 characters (e.g. HDFC0001234).');
        }
      }
    });
    
    if (!isValid) {
      let errorHtml = '<ul class="text-start mt-3 mb-0" style="font-size: 0.85rem; list-style-type: none; padding-left: 0;">';
      errorMessages.forEach(err => {
         errorHtml += `<li class="text-danger mb-1"><i class="ri-error-warning-line me-1"></i> ${err}</li>`;
      });
      errorHtml += '</ul>';

      Swal.fire({ 
         icon: 'error', 
         title: 'Validation Failed', 
         html: errorHtml,
         confirmButtonColor: '#666cff'
      });
    }
    
    return isValid;
  }
  /*
  function old_validateStep(step) {
    const inputs = Array.from(document.getElementById('step' + step).querySelectorAll('input, select, textarea'));
    
    if (step === 4) {
        const salariedRadio = document.getElementById('empSalaried');
        if (salariedRadio && salariedRadio.checked) {
            const compName = document.querySelector('input[name="company_name"]');
            const salary = document.querySelector('input[name="monthly_salary"]');
            if (compName && !inputs.includes(compName)) inputs.push(compName);
            if (salary && !inputs.includes(salary)) inputs.push(salary);
        } else {
            const busName = document.querySelector('input[name="business_name"]');
            const income = document.querySelector('input[name="monthly_income"]');
            if (busName && !inputs.includes(busName)) inputs.push(busName);
            if (income && !inputs.includes(income)) inputs.push(income);
        }
    }

    let isValid = true;
    let errorMessages = [];
    
    inputs.forEach(input => {
      input.classList.remove('is-invalid');
      if (input.nextElementSibling && input.nextElementSibling.classList.contains('invalid-feedback')) {
        input.nextElementSibling.remove();
      }

      if (input.required && !input.value.trim()) {
        const label = input.closest('.col-md-6, .col-md-4, .col-12')?.querySelector('.form-label');
        const labelText = label ? label.innerText.replace('*', '').trim() : input.name;
        // errorMessages.push(`${labelText} is.`); // Removed requirement
      } else if (input.value.trim()) {
        // Character Validations
        if (['name', 'city', 'state', 'bank_name', 'nominee1_name','nominee2_name', 'account_holder'].includes(input.name) && !/^[a-zA-Z\s]+$/.test(input.value)) {
           input.classList.add('is-invalid');
           isValid = false;
           errorMessages.push(`Invalid characters in ${input.name}.`);
        }
        // Numeric Validations
        if (['aadhar_number', 'account_number'].includes(input.name)) {

          if (!/^[0-9]+$/.test(input.value)) {
              input.classList.add('is-invalid');
              isValid = false;
              errorMessages.push(`Only numbers allowed for ${input.name}.`);
          }
          else if (input.value.length < 9 || input.value.length > 18) {
              input.classList.add('is-invalid');
              isValid = false;
              errorMessages.push(`Account number must be between 9 and 18 digits.`);
          }
        }
        // Phone Validations
        if (['phone', 'alternate_phone', 'nominee1_mobile','nominee2_mobile', 'guarantorPhone', 'referralPhone'].includes(input.name) && input.value.trim() && !/^[0-9]{10}$/.test(input.value)) {
           input.classList.add('is-invalid');
           isValid = false;
           errorMessages.push(`Mobile number must be exactly 10 digits.`);
        } 
        // IFSC
        if (input.name === 'ifsc_code' && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(input.value)) {
           input.classList.add('is-invalid');
           showFieldError(input, 'Invalid IFSC Format (e.g. HDFC0001234)');
           isValid = false;
           errorMessages.push(`IFSC Code must be 11 characters (e.g. HDFC0001234).`);
        }
        // PAN
        if (input.name === 'pan_number' && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(input.value)) {
           input.classList.add('is-invalid');
           showFieldError(input, 'Invalid PAN Format (e.g. ABCDE1234F)');
           isValid = false;
           errorMessages.push(`PAN Number must be in format ABCDE1234F.`);
        }
        // Email
        if (input.name === 'email' && !/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i.test(input.value)) {
           input.classList.add('is-invalid');
           showFieldError(input, 'Please enter a valid email address');
           isValid = false;
           errorMessages.push(`Please enter a valid email address.`);
        }
      }
    });

    if (!isValid) {
      let errorHtml = '<ul class="text-start mt-3 mb-0" style="font-size: 0.85rem; list-style-type: none; padding-left: 0;">';
      errorMessages.forEach(err => {
         errorHtml += `<li class="text-danger mb-1"><i class="ri-error-warning-line me-1"></i> ${err}</li>`;
      });
      errorHtml += '</ul>';

      Swal.fire({ 
         icon: 'error', 
         title: 'Validation Failed', 
         html: errorHtml,
         confirmButtonColor: '#666cff'
      });
    }
    return isValid;
  }
  */

  function showFieldError(input, message) {
    let errorEl = input.nextElementSibling;
    if (errorEl && errorEl.classList.contains('invalid-feedback')) {
      errorEl.innerText = message;
    } else {
      errorEl = document.createElement('div');
      errorEl.className = 'invalid-feedback d-block';
      errorEl.innerText = message;
      input.parentNode.appendChild(errorEl);
    }
  }

  function nextPrev(n) {
    if (n === 1 && !validateStep(currentStep)) return false;
    
    let nextStep = currentStep + n;
    if (nextStep > totalSteps) {
      submitForm();
      return false;
    }
    showStep(nextStep);
    window.scrollTo(0, 0);
  }

  async function compressImageFile(file, maxSize = 1600, quality = 0.72) {
    if (!file || !file.type || !file.type.startsWith('image/') || file.size <= 400 * 1024) {
      return file;
    }

    const dataUrl = await new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });

    const img = await new Promise((resolve, reject) => {
      const image = new Image();
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error('Unable to read image'));
      image.src = dataUrl;
    });

    const scale = Math.min(1, maxSize / Math.max(img.width, img.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(img.width * scale));
    canvas.height = Math.max(1, Math.round(img.height * scale));
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
    if (!blob) {
      return file;
    }

    const baseName = (file.name || 'photo').replace(/\.[^.]+$/, '');
    return new File([blob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
  }

  async function buildRegistrationFormData(form) {
    const formData = new FormData(form);
    const imageFields = [
      'selfie_photo',
      'aadhar_photo_front',
      'aadhar_photo_back',
      'aadhar_photo',
      'pan_photo',
      'payslip',
      'business_document'
    ];

    for (const field of imageFields) {
      const original = formData.get(field);
      if (!(original instanceof File) || original.size === 0) {
        continue;
      }
      try {
        formData.set(field, await compressImageFile(original));
      } catch (e) {
        console.warn('Image compress skipped for', field, e);
      }
    }

    return formData;
  }

  function registrationStoreUrl(form) {
    const currentPath = window.location.pathname.replace(/\/+$/, '');
    if (currentPath.endsWith('/client-management/add')) {
      return currentPath.replace(/\/add$/, '/store');
    }

    const raw = form.getAttribute('action') || form.action;
    try {
      const parsed = new URL(raw, window.location.href);
      return parsed.pathname + parsed.search;
    } catch (e) {
      return raw;
    }
  }

  function csrfToken(form) {
    return form.querySelector('input[name="_token"]')?.value
      || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      || '';
  }

  function messageFromFailedResponse(status, raw, parsed) {
    if (parsed?.message) {
      return parsed.message;
    }
    if (status === 403) {
      return 'The server blocked this registration (403). Please try again with smaller photos (under 2 MB each).';
    }
    if (status === 413) {
      return 'Uploaded files are too large. Please use smaller photos and try again.';
    }
    if (status === 419) {
      return 'Your session expired. Please refresh the page and try again.';
    }
    if (status === 401) {
      return 'Your session has expired. Please refresh the page and login again.';
    }
    if (typeof raw === 'string' && raw.trim().startsWith('<')) {
      return 'The server returned an error page instead of JSON. Please refresh and try again.';
    }
    return 'Something went wrong during registration.';
  }

  async function parseJsonResponse(response) {
    const raw = await response.text();
    if (!raw) {
      return { parsed: {}, raw: '' };
    }
    try {
      return { parsed: JSON.parse(raw), raw };
    } catch (e) {
      return { parsed: null, raw };
    }
  }

  async function submitForm() {
    const form = document.getElementById('clientWizardForm');
    const submitBtn = document.getElementById('nextBtn');
    const originalBtnText = submitBtn.innerText;

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing...';

    try {
      const formData = await buildRegistrationFormData(form);
      const response = await fetch(registrationStoreUrl(form), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': csrfToken(form),
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'ngrok-skip-browser-warning': '1'
        },
        body: formData
      });

      const { parsed, raw } = await parseJsonResponse(response);

      if (!parsed) {
        throw new Error(messageFromFailedResponse(response.status, raw, null));
      }

      if (response.ok && parsed.success) {
        Swal.fire({
          icon: 'success',
          title: 'Registration Successful!',
          text: parsed.message || 'Client has been registered and moved to KYC verification.',
          confirmButtonColor: '#666cff',
          timer: 3000,
          timerProgressBar: true
        }).then(() => {
          window.location.href = "{{ \App\Helpers\Helpers::appWebBasePath() }}client-management";
        });
        return;
      }

      let errorMessage = messageFromFailedResponse(response.status, raw, parsed);

      if (parsed.errors) {
        let errorList = '<ul class="text-start mt-3 mb-0" style="font-size: 0.85rem; list-style-type: none; padding-left: 0;">';
        Object.values(parsed.errors).forEach(errArray => {
          (Array.isArray(errArray) ? errArray : [errArray]).forEach(err => {
            errorList += `<li class="text-danger mb-1"><i class="ri-error-warning-line me-1"></i> ${err}</li>`;
          });
        });
        errorList += '</ul>';
        errorMessage = errorList;
      }

      Swal.fire({
        icon: 'error',
        title: 'Registration Failed',
        html: errorMessage,
        confirmButtonColor: '#666cff'
      });
      submitBtn.disabled = false;
      submitBtn.innerText = originalBtnText;
    } catch (error) {
      console.error('Submission error:', error);
      Swal.fire({
        icon: 'error',
        title: 'Registration Failed',
        text: error?.message || 'Unable to connect to the server. Please check your connection and try again.',
        confirmButtonColor: '#666cff'
      });
      submitBtn.disabled = false;
      submitBtn.innerText = originalBtnText;
    }
  }

  window.showStep = showStep;
  window.nextPrev = nextPrev;
  window.submitForm = submitForm;

  document.addEventListener('DOMContentLoaded', function() {
    window.showStep(1);

    // Initialize flatpickr for DOB
    const dobInput = document.querySelector('.flatpickr-dob');
    if (dobInput && typeof flatpickr !== 'undefined') {
        flatpickr(dobInput, {
            dateFormat: 'd-m-Y',
            allowInput: true
        });
    }
    
    // Toggle employment fields
    const salariedRadio = document.getElementById('empSalaried');
    const businessRadio = document.getElementById('empBusiness');
    const salariedFields = document.getElementById('salariedFields');
    const businessFields = document.getElementById('businessFields');

    function toggleEmp() {
      if (salariedRadio && businessRadio && salariedFields && businessFields) {
          const salariedInputs = salariedFields.querySelectorAll('input, select, textarea');
          const businessInputs = businessFields.querySelectorAll('input, select, textarea');

          if (salariedRadio.checked) {
            salariedFields.style.display = 'block';
            businessFields.style.display = 'none';
            salariedInputs.forEach(input => {
                input.removeAttribute('disabled');
            });
            businessInputs.forEach(input => {
                input.setAttribute('disabled', 'disabled');
                input.removeAttribute('required');
                if (input.type !== 'file') {
                  input.value = '';
                }
            });
          } else {
            salariedFields.style.display = 'none';
            businessFields.style.display = 'block';
            salariedInputs.forEach(input => {
                input.setAttribute('disabled', 'disabled');
                input.removeAttribute('required');
                if (input.type !== 'file') {
                  input.value = '';
                }
            });
            businessInputs.forEach(input => {
                input.removeAttribute('disabled');
                if (input.name === 'business_name' || input.name === 'monthly_income') {
                    input.setAttribute('required', 'required');
                }
            });
          }
      }
    }
    
    if (salariedRadio) salariedRadio.addEventListener('change', toggleEmp);
    if (businessRadio) businessRadio.addEventListener('change', toggleEmp);
    
    // Initial toggle call
    toggleEmp();

    // Real-time Blur Validations
    const panInput = document.getElementById('pan_number');
    if (panInput) {
        panInput.addEventListener('blur', function() {
            if (this.value.trim() && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(this.value)) {
                this.classList.add('is-invalid');
                showFieldError(this, 'Invalid PAN format (e.g. ABCDE1234F)');
            } else {
                this.classList.remove('is-invalid');
                const err = this.nextElementSibling;
                if (err && err.classList.contains('invalid-feedback')) err.remove();
            }
        });
    }
    
    const ifscInput = document.getElementById('ifsc_code');
    if (ifscInput) {
        ifscInput.addEventListener('blur', function() {
            if (this.value.trim() && !/^[A-Z]{4}0[A-Z0-9]{6}$/.test(this.value)) {
                this.classList.add('is-invalid');
                showFieldError(this, 'Invalid IFSC Format (e.g. HDFC0001234)');
            } else {
                this.classList.remove('is-invalid');
                const err = this.nextElementSibling;
                if (err && err.classList.contains('invalid-feedback')) err.remove();
            }
        });
    }

    // Zone Auto-population
    // Zone Auto-population
    const locationSelect = $('#location_id');
    if (locationSelect.length) {
        locationSelect.select2({
            placeholder: "Search & Select Zone...",
            allowClear: true,
            width: '100%'
        }).on('change', function() {
            const selected = $(this).find(':selected');
            if (selected.length && selected.val()) {
                const city = selected.data('city');
                const state = selected.data('state');
                const pincode = selected.data('pincode');

                // Requirement updated: keep city/state/pincode manual; do not auto-fill from zone.
            } else {
                // Keep manual values untouched on zone clear.
            }
        });
    }

  });
</script>
@endsection

@section('content')
<div class="row justify-content-center">
  <div class="col-xl-10">
    <div class="card wizard-card">
      <div class="wizard-header">
        <div class="d-flex justify-content-between align-items-center">
          <div>
             <h4 class="mb-1 fw-bold">Client Registration</h4>
             <p class="text-muted mb-0 small">Follow the steps to complete new client onboarding</p>
          </div>
          <a href="{{ url('client-management') }}" class="btn btn-sm btn-label-secondary border-0">
            <i class="ri-arrow-left-line me-1"></i> Back to List
          </a>
        </div>
      </div>
      
      <!-- Premium Step Indicators -->
      <div class="step-indicator-container border-bottom">
        <div class="step-indicator">
          <div class="step-progress-line" id="progressLine"></div>
          
          <div class="step-item active" id="ind1">
            <div class="step-circle"><i class="ri-user-line"></i></div>
            <span class="step-label">Personal</span>
          </div>
          
          <div class="step-item" id="ind2">
            <div class="step-circle"><i class="ri-bank-card-line"></i></div>
            <span class="step-label">Banking</span>
          </div>
          
          <div class="step-item" id="ind3">
            <div class="step-circle"><i class="ri-team-line"></i></div>
            <span class="step-label">References</span>
          </div>
          
          <div class="step-item" id="ind4">
            <div class="step-circle"><i class="ri-file-upload-line"></i></div>
            <span class="step-label">Documents</span>
          </div>
        </div>
      </div>
      
      <div class="card-body p-lg-5">

        <form id="clientWizardForm" method="POST" action="{{ \App\Helpers\Helpers::appWebBasePath() }}client-management/store" enctype="multipart/form-data">
          @csrf
          
          <!-- STEP 1: PERSONAL DETAILS -->
          <div class="wizard-step active" id="step1">
            <div class="form-section-title">
              <i class="ri-user-line"></i> 
              Personal Information
            </div>
            
            <div class="row g-4">
              <div class="col-md-6">
                <label class="form-label fw-bold">Full Name (As per Aadhar) <span class="text-danger">*</span> </label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-user-smile-line text-primary"></i></span>
                  <input type="text" name="name" class="form-control" placeholder="Enter Full Name" oninput="this.value = this.value.replace(/[^a-zA-Z0-9\s]/g, '')" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Nickname</label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-user-heart-line text-primary"></i></span>
                  <input type="text" name="nickname" class="form-control" placeholder="Enter Nickname (Optional)" maxlength="255">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Phone Number <span class="text-danger">*</span> </label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-phone-line text-primary"></i></span>
                  <input type="text" name="phone" id="phone" class="form-control" placeholder="10 Digit Mobile" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </div>
                <div id="phone-feedback" class="invalid-feedback">Please enter exactly 10 digits.</div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Alternate Phone</label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-smartphone-line text-primary"></i></span>
                  <input type="text" name="alternate_phone" class="form-control" placeholder="Optional 10 Digit" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Email Address </label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-mail-line text-primary"></i></span>
                  <input type="email" name="email" class="form-control" placeholder="example@email.com" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$" title="Please enter a valid email address">
                </div>
              </div>
              
              <div class="col-md-4">
                <label class="form-label fw-bold">Date of Birth </label>
                <input type="text" name="date_of_birth" id="date_of_birth" class="form-control input-group-custom flatpickr-dob" placeholder="DD-MM-YYYY">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Gender </label>
                <select name="gender" class="form-select input-group-custom">
                  <option value="" disabled selected>Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Marital Status </label>
                <select name="marital_status" class="form-select input-group-custom">
                  <option value="" disabled selected>Select Status</option>
                  <option value="single">Single</option>
                  <option value="married">Married</option>
                  <option value="divorced">Divorced</option>
                  <option value="widowed">Widowed</option>
                </select>
              </div>
              
              <div class="col-md-12">
                <label class="form-label fw-bold">Select Zone/Area <span class="text-danger">*</span></label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-map-pin-range-line text-primary"></i></span>
                  <select name="location_id" id="location_id" class="form-select" required>
                    <option value="">Search & Select Zone...</option>
                    @foreach($locations as $loc)
                      <option value="{{ $loc->id }}" 
                        data-city="{{ $loc->district->name ?? $loc->city }}" 
                        data-state="{{ $loc->state->name ?? $loc->state }}" 
                        data-pincode="{{ $loc->pincode }}">
                        {{ $loc->name }} - {{ $loc->district->name ?? $loc->city }}
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label fw-bold">Current Address </label>
                <textarea name="address" class="form-control input-group-custom" rows="2" placeholder="Full residential address"></textarea>
              </div>
              
              <div class="col-md-4">
                <label class="form-label fw-bold">City/Town </label>
                <input type="text" name="city" class="form-control input-group-custom" placeholder="City" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">State </label>
                <input type="text" name="state" class="form-control input-group-custom" placeholder="State" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Pincode </label>
                <input type="text" name="pincode" class="form-control input-group-custom" placeholder="6 Digit Pincode" maxlength="6" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
              </div>
            </div>
          </div>

          <!-- STEP 2: KYC & BANK DETAILS -->
          <div class="wizard-step" id="step2">
            <div class="form-section-title">
              <i class="ri-bank-card-line"></i>
              Identity & Banking Details <span class="text-danger">*</span>
            </div>
            
            <div class="row g-4">
              <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-light bg-opacity-10 h-100 d-flex flex-column justify-content-center">
                   <label class="form-label fw-bold small text-uppercase mb-2">Aadhar Number <span class="text-danger">*</span></label>
                   <div class="input-group input-group-merge input-group-custom">
                      <span class="input-group-text"><i class="ri-fingerprint-line text-primary"></i></span>
                      <input type="text" name="aadhar_number" id="aadhar_number" class="form-control" placeholder="12 Digit Aadhar" maxlength="12" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                   </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-light bg-opacity-10 h-100 d-flex flex-column justify-content-center">
                   <label class="form-label fw-bold small text-uppercase mb-2">PAN Number <span class="text-danger">*</span></label>
                   <div class="input-group input-group-merge input-group-custom">
                      <span class="input-group-text"><i class="ri-id-card-line text-primary"></i></span>
                      <input type="text" name="pan_number" id="pan_number" class="form-control" placeholder="ABCDE1234F" maxlength="10" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}$" title="Invalid PAN format (e.g. ABCDE1234F)" required>
                   </div>
                </div>
              </div>

              <div class="col-12 mt-4">
                <div class="divider text-start">
                  <div class="divider-text fw-bold text-primary">BANK ACCOUNT INFORMATION</div>
                </div>
              </div>

              <div class="col-md-6 bank-field-group">
                <label class="form-label fw-bold">Account Holder Name</label>
                <input type="text" name="account_holder" class="form-control input-group-custom" placeholder="As per Bank Passbook" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
              </div>
              <div class="col-md-6 bank-field-group">
                <label class="form-label fw-bold">Account Number <span class="text-danger">*</span></label>
                <input type="text" name="account_number" id="account_number" class="form-control input-group-custom" placeholder="Numbers only" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
              </div>
              <div class="col-md-3 bank-field-group">
                <label class="form-label fw-bold">Bank Name  </label>
                <input type="text" name="bank_name" class="form-control input-group-custom" placeholder="Name of Bank" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')" >
              </div>
              <div class="col-md-3 bank-field-group">
                <label class="form-label fw-bold">IFSC Code <span class="text-danger">*</span></label>
                <input type="text" name="ifsc_code" id="ifsc_code" class="form-control input-group-custom" placeholder="e.g. HDFC0001234" maxlength="11" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '')" pattern="^[A-Z]{4}0[A-Z0-9]{6}$" title="Invalid IFSC Code (e.g. HDFC0001234)" required>
              </div>
              <div class="col-md-3 bank-field-group">
                <label class="form-label fw-bold">Branch Name <span class="text-danger">*</span></label>
                <input type="text" name="branch_name" class="form-control input-group-custom" placeholder="Enter branch manually" required>
                <small class="text-muted">Branch is not returned by bank API — enter it manually.</small>
              </div>
              <div class="col-md-3 bank-field-group">
                <label class="form-label fw-bold">Account Type <span class="text-danger">*</span></label>
                <select name="account_type" class="form-select input-group-custom" required>
                  <option value="" disabled selected>Select Account Type</option>
                  <option value="savings">Savings Account</option>
                  <option value="current">Current Account</option>
                </select>
              </div>
            </div>
          </div>

          <!-- STEP 3: NOMINEE & REFERENCES -->
          <div class="wizard-step" id="step3">
            <div class="form-section-title">
              <i class="ri-team-line"></i>
              Nominee & References
            </div>
            
            <div class="row g-4">
              <div class="col-12"><span class="badge bg-label-primary px-3 py-2">PRIMARY NOMINEE</span></div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Nominee Name </label>
                <div class="input-group input-group-merge input-group-custom">
                   <span class="input-group-text"><i class="ri-user-heart-line text-primary"></i></span>
                   <input type="text" name="nominee1_name" class="form-control" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Relationship </label>
                <select name="nominee1_relationship" class="form-select input-group-custom">
                  <option value="" disabled selected>Select Relationship</option>
                  <option value="husband">Husband</option>
                  <option value="spouse">Spouse</option>
                  <option value="father">Father</option>
                  <option value="mother">Mother</option>
                  <option value="son">Son</option>
                  <option value="daughter">Daughter</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Mobile Number </label>
                <div class="input-group input-group-merge input-group-custom">
                    <span class="input-group-text"><i class="ri-smartphone-line text-primary"></i></span>
                    <input type="text" name="nominee1_mobile" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
              </div>

              <div class="col-12 mt-4"><span class="badge bg-label-secondary px-3 py-2">SECONDARY NOMINEE (OPTIONAL)</span></div>
              <div class="col-md-4">
                <label class="form-label">Nominee Name</label>
                <div class="input-group input-group-merge input-group-custom">
                  <span class="input-group-text"><i class="ri-user-heart-line text-primary"></i></span>
                  <input type="text" name="nominee2_name" class="form-control" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                </div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Relationship</label>
                <select name="nominee2_relationship" class="form-select input-group-custom">
                  <option value="">Select</option>
                  <option value="husband">Husband</option>
                  <option value="spouse">Spouse</option>
                  <option value="father">Father</option>
                  <option value="mother">Mother</option>
                  <option value="son">Son</option>
                  <option value="daughter">Daughter</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Mobile Number</label>
                <div class="input-group input-group-merge input-group-custom">
                    <span class="input-group-text"><i class="ri-smartphone-line text-primary"></i></span>
                    <input type="text" name="nominee2_mobile" class="form-control" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
              </div>

              <div class="col-12 mt-4">
                <div class="divider text-start">
                  <div class="divider-text fw-bold text-primary">GUARANTOR & REFERRAL</div>
                </div>
              </div>
              
              <div class="col-md-6">
                <div class="card bg-light bg-opacity-50 border-0 shadow-none h-100">
                  <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="ri-shield-user-line me-2 text-primary"></i> Guarantor Info  </h6>
                    <input type="text" name="guarantorName" class="form-control mb-3 input-group-custom" placeholder="Full Name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                    <input type="text" name="guarantorPhone" class="form-control mb-3 input-group-custom" placeholder="Mobile Number" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    <select name="guarantorRelationship" class="form-select input-group-custom">
                       <option value="">Select Relationship *</option>
                       <option value="Friend">Friend</option>
                       <option value="Relative">Relative</option>
                       <option value="Colleague">Colleague</option>
                       <option value="Neighbor">Neighbor</option>
                       <option value="Other">Other</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="card bg-light bg-opacity-50 border-0 shadow-none h-100">
                  <div class="card-body p-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center"><i class="ri-user-shared-line me-2 text-primary"></i> Referral Info</h6>
                    <input type="text" name="referralName" class="form-control mb-3 input-group-custom" placeholder="Full Name" oninput="this.value = this.value.replace(/[^a-zA-Z\s]/g, '')">
                    <input type="text" name="referralPhone" class="form-control mb-3 input-group-custom" placeholder="Mobile Number" maxlength="10" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    <select name="referralRelationship" class="form-select input-group-custom">
                       <option value="">Select Relationship</option>
                       <option value="Associate">Associate</option>
                       <option value="Relative">Relative</option>
                       <option value="Friend">Friend</option>
                       <option value="Other">Other</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- STEP 4: EMPLOYMENT & DOCUMENTS -->
          <div class="wizard-step" id="step4">
            <div class="form-section-title">
              <i class="ri-file-upload-line"></i>
              Employment & Documents
            </div>
            
            <div class="row g-4">
              <div class="col-12">
                <label class="form-label d-block fw-bold mb-3">Employment Type </label>
                <div class="row g-3">
                  <div class="col-md-6">
                    <div class="form-check p-0 custom-option custom-option-basic">
                      <input class="form-check-input d-none" type="radio" name="employment_type" id="empSalaried" value="salaried" checked>
                      <label class="emp-card w-100" for="empSalaried">
                        <div class="avatar avatar-md bg-label-primary rounded">
                          <i class="ri-briefcase-line"></i>
                        </div>
                        <div>
                          <p class="mb-0 fw-bold">Salaried Employee</p>
                          <small class="text-muted">For those working in companies</small>
                        </div>
                      </label>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-check p-0 custom-option custom-option-basic">
                      <input class="form-check-input d-none" type="radio" name="employment_type" id="empBusiness" value="business">
                      <label class="emp-card w-100" for="empBusiness">
                        <div class="avatar avatar-md bg-label-success rounded">
                          <i class="ri-store-2-line"></i>
                        </div>
                        <div>
                          <p class="mb-0 fw-bold">Business Owner</p>
                          <small class="text-muted">Self-employed or business</small>
                        </div>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              <div id="salariedFields" class="col-12">
                <div class="p-4 border rounded-3 bg-light bg-opacity-10">
                  <div class="row g-4">
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Company Name </label>
                      <input type="text" name="company_name" class="form-control input-group-custom" placeholder="Enter Company Name" pattern="^(?=.*[A-Za-z])[A-Za-z0-9&().,\-\s]+$" title="Use letters; numbers/safe symbols allowed">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Monthly Net Salary </label>
                      <div class="input-group input-group-merge input-group-custom">
                          <span class="input-group-text bg-light">₹</span>
                          <input type="number" name="monthly_salary" class="form-control border-start-0" placeholder="Take home salary">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Upload Payslip</label>
                      <input type="file" name="payslip" class="form-control input-group-custom">
                    </div>
                  </div>
                </div>
              </div>

              <div id="businessFields" class="col-12" style="display:none;">
                <div class="p-4 border rounded-3 bg-light bg-opacity-10">
                  <div class="row g-4">
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Business Name </label>
                      <input type="text" name="business_name" class="form-control input-group-custom" placeholder="Shop/Office Name">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Monthly Income </label>
                      <div class="input-group input-group-merge input-group-custom">
                          <span class="input-group-text bg-light">₹</span>
                          <input type="number" name="monthly_income" class="form-control" placeholder="Average monthly profit">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label fw-bold">Business Document</label>
                      <input type="file" name="business_document" class="form-control input-group-custom">
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 mt-4">
                <div class="divider text-start">
                  <div class="divider-text fw-bold text-primary">MANDATORY DOCUMENTS</div>
                </div>
              </div>
              
              <div class="col-md-4">
                <div class="p-3 border rounded-3 text-center bg-light bg-opacity-10">
                  <label class="form-label fw-bold d-block mb-3">Selfie Photo <span class="text-danger">*</span></label>
                  <input type="file" name="selfie_photo" class="form-control form-control-sm" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 border rounded-3 text-center bg-light bg-opacity-10">
                  <label class="form-label fw-bold d-block mb-3">Aadhar Front <span class="text-danger">*</span></label>
                  <input type="file" name="aadhar_photo_front" class="form-control form-control-sm" required>
                </div>
              </div>
              <div class="col-md-4">
                <div class="p-3 border rounded-3 text-center bg-light bg-opacity-10">
                  <label class="form-label fw-bold d-block mb-3">Aadhar Back <span class="text-danger">*</span></label>
                  <input type="file" name="aadhar_photo_back" class="form-control form-control-sm" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="p-3 border rounded-3 text-center bg-light bg-opacity-10">
                  <label class="form-label fw-bold d-block mb-3">PAN Card Photo </label>
                  <input type="file" name="pan_photo" class="form-control form-control-sm">
                </div>
              </div>
              <div class="col-md-6">
                <div class="p-3 border rounded-3 text-center bg-light bg-opacity-10">
                  <label class="form-label fw-bold d-block mb-3">Bank Statement (6 Months) </label>
                  <input type="file" name="bank_statement" class="form-control form-control-sm">
                </div>
              </div>
            </div>
          </div>

          <!-- Navigation Buttons -->
          <div class="mt-5 pt-3 border-top d-flex justify-content-between">
            <button type="button" class="btn btn-label-secondary" id="prevBtn" onclick="window.nextPrev(-1)" style="display: none;">Previous</button>
            <button type="button" class="btn btn-primary" id="nextBtn" onclick="window.nextPrev(1)">Next Step</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
