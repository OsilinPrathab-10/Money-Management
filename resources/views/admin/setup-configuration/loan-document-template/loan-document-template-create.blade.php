@extends('layouts/layoutMaster')

@section('title', 'Create Loan Document Template')

@section('vendor-style')
@endsection

@section('vendor-script')
@endsection

@section('content')

<div class="row">
  <div class="col-12">
    <div class="card mb-6">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Create New Document Template</h5>
        <a href="{{ route('loan-document-templates.index') }}" class="btn btn-outline-secondary">
          <i class="icon-base ri ri-arrow-left-line me-1"></i> Back to List
        </a>
      </div>
      <div class="card-body">
        <form action="{{ route('loan-document-templates.store') }}" method="POST" id="loanTemplateForm">
          @csrf
          
          <div class="mb-4">
            <label for="title" class="form-label">Document Name <span class="text-danger">*</span></label>
            <input 
              type="text" 
              class="form-control @error('title') is-invalid @enderror" 
              id="title" 
              name="title" 
              placeholder="Enter document name (e.g., Loan Agreement)"
              value="{{ old('title') }}"
              required
            >
            @error('title')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-4">
            <label for="type" class="form-label">Document Type <span class="text-danger">*</span></label>
            <select 
              class="form-select @error('type') is-invalid @enderror" 
              id="type" 
              name="type"
              required
            >
              <option value="">Select Type</option>
              <option value="loan_agreement" {{ old('type') == 'loan_agreement' ? 'selected' : '' }}>Loan Agreement</option>
              <option value="loan_sanction_letter" {{ old('type') == 'loan_sanction_letter' ? 'selected' : '' }}>Loan Sanction Letter</option>

              <option value="disbursement_letter" {{ old('type') == 'disbursement_letter' ? 'selected' : '' }}>Disbursement Letter</option>
              <option value="statement" {{ old('type') == 'statement' ? 'selected' : '' }}>Loan Statement</option>
              <option value="noc" {{ old('type') == 'noc' ? 'selected' : '' }}>NOC (No Objection Certificate)</option>
              <option value="loan_closure_certificate" {{ old('type') == 'loan_closure_certificate' ? 'selected' : '' }}>Loan Closure Certificate</option>
              <option value="foreclosure_letter" {{ old('type') == 'foreclosure_letter' ? 'selected' : '' }}>Foreclosure Letter</option>
              <option value="demand_letter" {{ old('type') == 'demand_letter' ? 'selected' : '' }}>Demand Letter (Overdue Notice)</option>
              <option value="reminder_notice" {{ old('type') == 'reminder_notice' ? 'selected' : '' }}>Reminder Notice (Upcoming / Overdue Reminder)</option>
              <option value="other" {{ old('type') && !in_array(old('type'), ['loan_sanction_letter', 'disbursement_letter', 'statement', 'noc', 'loan_closure_certificate', 'foreclosure_letter', 'demand_letter', 'reminder_notice']) ? 'selected' : '' }}>Other</option>
            </select>
            @error('type')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <div id="loanProductWrapper" class="mt-3" style="display: none;">
              <label for="loan_product_id" class="form-label">Associated Loan Product <span class="text-danger">*</span></label>
              <select name="loan_product_id" id="loan_product_id" class="form-select @error('loan_product_id') is-invalid @enderror">
                <option value="">Select Product</option>
                @foreach($loanProducts as $product)
                  <option value="{{ $product->id }}" {{ old('loan_product_id') == $product->id ? 'selected' : '' }}>{{ $product->loan_name }}</option>
                @endforeach
              </select>
              @error('loan_product_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
              <div class="form-text text-info">Selecting a product allows you to create a specific agreement template for that loan product.</div>
            </div>

            <div id="customTypeWrapper" class="mt-3" style="display: none;">
              <label for="customType" class="form-label">Custom Document Type <span class="text-danger">*</span></label>
              <input type="text" id="customType" class="form-control" placeholder="Enter custom document type" value="{{ old('type') && !in_array(old('type'), ['loan_agreement', 'loan_sanction_letter', 'disbursement_letter', 'statement', 'noc', 'loan_closure_certificate', 'foreclosure_letter', 'demand_letter', 'reminder_notice']) ? old('type') : '' }}">
              <div class="invalid-feedback" id="customTypeFeedback">Please enter a document type.</div>
            </div>
            

          </div>

          <div class="mb-4">
            <label class="form-label mb-2 d-block">Template Content <span class="text-danger">*</span></label>
            <textarea
              id="template-body"
              name="body"
              class="form-control @error('body') is-invalid @enderror"
              rows="14"
            >{!! old('body') !!}</textarea>
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-3">
              <small class="text-muted mb-0">Use placeholders to merge dynamic data in your document.</small>
              <button type="button" class="btn btn-sm btn-outline-primary align-self-start align-self-md-end" data-bs-toggle="modal" data-bs-target="#templatePlaceholdersModal">
                <i class="icon-base ri ri-information-line me-1"></i> Available Placeholders
              </button>
            </div>
            @error('body')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-flex gap-3">
            <button type="submit" class="btn btn-primary">
              <i class="icon-base ri ri-save-line me-1"></i> Save Template
            </button>
            <a href="{{ route('loan-document-templates.index') }}" class="btn btn-outline-secondary">
              <i class="icon-base ri ri-close-line me-1"></i> Cancel
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Placeholder List Modal -->
<div class="modal fade" id="templatePlaceholdersModal" tabindex="-1" aria-labelledby="templatePlaceholdersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="templatePlaceholdersModalLabel">Available Placeholders</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small">Insert these placeholders into the template content to automatically pull data.</p>
        <ul class="list-group list-group-flush">
          <li class="list-group-item">
            <code class="text-primary">@{{client_name}}</code>
            <div class="small text-muted">Client's full name</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{client_phone}}</code>
            <div class="small text-muted">Client's phone number</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{loan_amount}}</code>
            <div class="small text-muted">Loan amount issued</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{interest_rate}}</code>
            <div class="small text-muted">Interest rate applied to the loan</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{loan_id}}</code>
            <div class="small text-muted">Unique loan identifier</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{start_date}}</code>
            <div class="small text-muted">Loan start or issuance date</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{due_date}}</code>
            <div class="small text-muted">Loan due or maturity date</div>
          </li>
          <li class="list-group-item">
            <code class="text-primary">@{{company_name}}</code>
            <div class="small text-muted">Your company name</div>
          </li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('loanTemplateForm');
      const typeSelect = document.getElementById('type');
      const customTypeWrapper = document.getElementById('customTypeWrapper');
      const customTypeInput = document.getElementById('customType');
      const customTypeFeedback = document.getElementById('customTypeFeedback');
      const loanProductWrapper = document.getElementById('loanProductWrapper');
      const loanProductSelect = document.getElementById('loan_product_id');

      const predefinedTypes = [
        'loan_agreement',
        'loan_sanction_letter',
        'disbursement_letter',
        'statement',
        'noc',
        'loan_closure_certificate',
        'foreclosure_letter',
        'demand_letter',
        'reminder_notice'
      ];

      function toggleCustomType() {
        const selected = typeSelect.value;
        const isCustom = selected === 'other' || (selected && !predefinedTypes.includes(selected));
        const isLoanAgreement = selected === 'loan_agreement';

        customTypeWrapper.style.display = isCustom ? 'block' : 'none';
        loanProductWrapper.style.display = isLoanAgreement ? 'block' : 'none';

        if (isLoanAgreement) {
          loanProductSelect.setAttribute('required', 'required');
        } else {
          loanProductSelect.removeAttribute('required');
        }

        if (isCustom && selected !== 'other' && selected) {
          customTypeInput.value = selected;
        }
      }

      if (typeSelect) {
        toggleCustomType();
        typeSelect.addEventListener('change', toggleCustomType);
      }

      if (typeof tinymce !== 'undefined') {
        tinymce.init({
          selector: '#template-body',
          height: 420,
          menubar: false,
          branding: false,
          plugins: 'advlist autolink lists link table code fullscreen preview',
          toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link table | code preview fullscreen',
          content_style: 'body { font-family: "Inter", "Helvetica", sans-serif; font-size: 14px; }'
        });

        if (form) {
          form.addEventListener('submit', function (event) {
            tinymce.triggerSave();

            if (!typeSelect) {
              return;
            }

            const selected = typeSelect.value;

            if (selected === 'other' || (selected && !predefinedTypes.includes(selected))) {
              const customValue = customTypeInput.value.trim();

              if (!customValue) {
                event.preventDefault();
                customTypeInput.classList.add('is-invalid');
                customTypeFeedback.style.display = 'block';
                customTypeInput.focus();
                return;
              }

              customTypeInput.classList.remove('is-invalid');
              customTypeFeedback.style.display = 'none';

              const existingOption = Array.from(typeSelect.options).find(option => option.value === customValue);
              if (!existingOption) {
                const customOption = new Option(customValue, customValue, true, true);
                typeSelect.add(customOption);
              } else {
                typeSelect.value = customValue;
              }
            } else {
              customTypeInput.classList.remove('is-invalid');
              customTypeFeedback.style.display = 'none';
            }
          });
        }
      }
    });
  </script>
@endsection
