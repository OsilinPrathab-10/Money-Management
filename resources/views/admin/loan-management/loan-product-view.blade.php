@extends('layouts/layoutMaster')

@section('title', 'Loan Product Details')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/loan-product-view.js'])
@endsection

@section('content')

<!-- Loan Details Content -->
<div class="card mb-6">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h5 class="mb-0">Complete Loan Product Information</h5>
      <small class="text-muted">{{ $loanProduct->loan_name }} - {{ $loanProduct->loan_code ?? 'N/A' }}</small>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ url('loan/loan-products') }}" class="btn btn-outline-secondary">
        <i class="icon-base ri ri-arrow-left-line me-1"></i>
        Back to List
      </a>
      <button type="button" class="btn btn-icon btn-primary" data-bs-toggle="modal" data-bs-target="#editProductModal">
        <i class="icon-base ri ri-edit-line icon-22px"></i>
      </button>
    </div>
  </div>
      <div class="card-body">
        <div class="row g-6">
          <!-- Basic Information -->
          <div class="col-12">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-information-line icon-40px text-primary me-3"></i>
                  <h5 class="mb-0">Basic Information</h5>
                </div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-heading">Loan Name</label>
                    <p class="mb-0">{{ $loanProduct->loan_name }}</p>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-heading">Loan Code</label>
                    <p class="mb-0">{{ $loanProduct->loan_code ?? 'N/A' }}</p>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-heading">Loan Type</label>
                    <p class="mb-0">{{ optional($loanProduct->loanType)->name ?? 'N/A' }}</p>
                  </div>
                  <div class="col-md-6 mb-3">
                    <label class="form-label fw-medium text-heading">Status</label>
                    <p class="mb-0"><span class="badge bg-label-{{ $loanProduct->status == 'active' ? 'success' : 'danger' }}">{{ ucfirst($loanProduct->status) }}</span></p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Amount Details -->
          <div class="col-md-6">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-money-rupee-circle-line icon-40px text-success me-3"></i>
                  <h5 class="mb-0">Amount Details</h5>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Minimum Amount</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->loan_amount_min ?? 0, 2) }}</p>
                </div>
                <div class="mb-0">
                  <label class="form-label fw-medium text-heading">Maximum Amount</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->loan_amount_max ?? 0, 2) }}</p>
                  <small class="text-muted d-block mt-1">Use as credit limit on applications</small>
                </div>
              </div>
            </div>
          </div>

          <!-- Interest Details -->
          <div class="col-md-6">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-percent-line icon-40px text-warning me-3"></i>
                  <h5 class="mb-0">Interest Details</h5>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Interest Rate</label>
                  <p class="mb-0">{{ $loanProduct->interest_rate }}% per annum</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Interest Type</label>
                  <p class="mb-0">{{ ucfirst(str_replace('_', ' ', $loanProduct->interest_type)) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Tenure Details -->
          <div class="col-md-6">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-calendar-line icon-40px text-info me-3"></i>
                  <h5 class="mb-0">Tenure Details</h5>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Term Unit</label>
                  <p class="mb-0">{{ ucfirst($loanProduct->term_unit) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Min Tenure</label>
                  <p class="mb-0">{{ $loanProduct->min_tenture }} {{ ucfirst($loanProduct->term_unit) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Max Tenure</label>
                  <p class="mb-0">{{ $loanProduct->max_tenture }} {{ ucfirst($loanProduct->term_unit) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Default Term</label>
                  <p class="mb-0">{{ $loanProduct->default_term ?? 'N/A' }} {{ $loanProduct->default_term ? ucfirst($loanProduct->term_unit) : '' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Additional Charges -->
          <div class="col-md-6">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-file-list-line icon-40px text-danger me-3"></i>
                  <h5 class="mb-0">Additional Charges</h5>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Processing Fee</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->processing_fee ?? 0, 2) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Document Charges</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->document_charges ?? 0, 2) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Other Charges</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->other_charges ?? 0, 2) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Penalty Amount</label>
                  <p class="mb-0">₹{{ number_format($loanProduct->penalty_rate ?? 0, 2) }}</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Grace Period</label>
                  <p class="mb-0">{{ $loanProduct->grace_period_days }} Days</p>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-medium text-heading">Require Collateral</label>
                  <p class="mb-0"><span class="badge bg-label-{{ $loanProduct->require_collateral ? 'success' : 'danger' }}">{{ $loanProduct->require_collateral ? 'Yes' : 'No' }}</span></p>
                </div>
              </div>
            </div>
          </div>

          <!-- Description -->
          <div class="col-12">
            <div class="card border shadow-none">
              <div class="card-body">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-file-text-line icon-40px text-secondary me-3"></i>
                  <h5 class="mb-0">Description</h5>
                </div>
                <p class="mb-0">{{ $loanProduct->description ?? 'No description available.' }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- /Loan Details Content -->

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Loan Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editProductForm" class="row g-5" action="{{ route('loan-products.update', $loanProduct->id) }}" method="POST">
          @csrf
          <!-- Basic Information -->
          <div class="col-12">
            <h6>1. Basic Information</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanName">Loan Name <span class="text-danger">*</span></label>
              <input type="text" id="editLoanName" class="form-control" placeholder="Enter loan name" name="loanName" value="{{ $loanProduct->loan_name }}" required />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanType">Loan Type <span class="text-danger">*</span></label>
              <select id="editLoanType" class="form-select" name="loanType" required>
                <option value="">Select Type</option>
                @foreach($loanTypes as $type)
                  <option value="{{ $type->id }}" {{ $loanProduct->loan_type_id == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanCode">Loan Code <span class="text-danger">*</span></label>
              <input type="text" id="editLoanCode" class="form-control" placeholder="e.g., LP001" name="loanCode" value="{{ $loanProduct->loan_code }}" required />
            </div>
          </div>

          <!-- Amount Details -->
          <div class="col-12">
            <h6 class="mt-2">2. Amount Details</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanAmountMin">Minimum Amount <span class="text-danger">*</span></label>
              <input type="number" id="editLoanAmountMin" class="form-control" placeholder="0.00" step="0.01" name="loanAmountMin" value="{{ $loanProduct->loan_amount_min }}" required />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanAmountMax">Maximum Amount <span class="text-danger">*</span></label>
              <input type="number" id="editLoanAmountMax" class="form-control" placeholder="0.00" step="0.01" name="loanAmountMax" value="{{ $loanProduct->loan_amount_max }}" required />
            </div>
          </div>

          <!-- Interest & Terms -->
          <div class="col-12">
            <h6 class="mt-2">3. Interest & Terms</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editInterestRate">Interest Rate (%) <span class="text-danger">*</span></label>
              <input type="number" id="editInterestRate" class="form-control" placeholder="0.00" step="0.01" name="interestRate" value="{{ $loanProduct->interest_rate }}" required />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editInterestType">Interest Type <span class="text-danger">*</span></label>
              <select id="editInterestType" class="form-select" name="interestType" required>
                <option value="">Select Type</option>
                <option value="fixed" {{ $loanProduct->interest_type == 'fixed' ? 'selected' : '' }}>Fixed</option>
                <option value="reducing" {{ $loanProduct->interest_type == 'reducing' ? 'selected' : '' }}>Reducing Balance</option>
                <option value="flat" {{ $loanProduct->interest_type == 'flat' ? 'selected' : '' }}>Flat Rate</option>
              </select>
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editTermUnit">Term Unit <span class="text-danger">*</span></label>
              <select id="editTermUnit" class="form-select" name="termUnit" required>
                <option value="">Select Unit</option>
                <option value="days" {{ $loanProduct->term_unit == 'days' ? 'selected' : '' }}>Days</option>
                <option value="weeks" {{ $loanProduct->term_unit == 'weeks' ? 'selected' : '' }}>Weeks</option>
                <option value="months" {{ $loanProduct->term_unit == 'months' ? 'selected' : '' }}>Months</option>
              </select>
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editMinTenure">Min Tenure <span class="text-danger">*</span></label>
              <input type="number" id="editMinTenure" class="form-control" placeholder="0" name="minTenure" value="{{ $loanProduct->min_tenture }}" required />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editMaxTenure">Max Tenure <span class="text-danger">*</span></label>
              <input type="number" id="editMaxTenure" class="form-control" placeholder="0" name="maxTenure" value="{{ $loanProduct->max_tenture }}" required />
            </div>
          </div>

          <!-- Additional Charges -->
          <div class="col-12">
            <h6 class="mt-2">4. Additional Charges & Settings</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editPenaltyRate">Penalty Amount (Fixed) (₹)</label>
              <input type="number" id="editPenaltyRate" class="form-control" placeholder="0.00" step="0.01" name="penaltyRate" value="{{ $loanProduct->penalty_rate }}" />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editGracePeriod">Grace Period (Days)</label>
              <input type="number" id="editGracePeriod" class="form-control" placeholder="0" name="gracePeriod" value="{{ $loanProduct->grace_period_days }}" />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editProcessingFee">Processing Fee</label>
              <input type="number" id="editProcessingFee" class="form-control" placeholder="0.00" step="0.01" name="processingFee" value="{{ $loanProduct->processing_fee }}" />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editDocumentCharges">Document Charges</label>
              <input type="number" id="editDocumentCharges" class="form-control" placeholder="0.00" step="0.01" name="documentCharges" value="{{ $loanProduct->document_charges }}" />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editOtherCharges">Other Charges</label>
              <input type="number" id="editOtherCharges" class="form-control" placeholder="0.00" step="0.01" name="otherCharges" value="{{ $loanProduct->other_charges }}" />
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editRequireCollateral">Require Collateral <span class="text-danger">*</span></label>
              <select id="editRequireCollateral" class="form-select" name="requireCollateral" required>
                <option value="">Select Option</option>
                <option value="1" {{ $loanProduct->require_collateral ? 'selected' : '' }}>Yes</option>
                <option value="0" {{ !$loanProduct->require_collateral ? 'selected' : '' }}>No</option>
              </select>
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editDefaultTerm">Default Term</label>
              <input type="number" id="editDefaultTerm" class="form-control" placeholder="0" name="defaultTerm" value="{{ $loanProduct->default_term }}" />
            </div>
          </div>

          <!-- Description -->
          <div class="col-12">
            <h6 class="mt-2">5. Description</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editDescription">Description</label>
              <textarea id="editDescription" class="form-control h-px-100" rows="3" placeholder="Enter loan product description" name="description">{{ $loanProduct->description }}</textarea>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-3" id="editProductSubmitBtn">
              <i class="icon-base ri ri-save-line me-1"></i> Update Product
            </button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
              <i class="icon-base ri ri-close-line me-1"></i> Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-body text-center p-4">
        <div class="mb-4">
          <div class="d-inline-flex align-items-center justify-content-center bg-success bg-opacity-10 rounded-circle" style="width: 50px; height: 50px;">
            <i class="icon-base ri ri-check-line text-success" style="font-size: 100px;"></i>
          </div>
        </div>
        <h5 class="mb-2">Updated Successfully!</h5>
        <p class="text-muted mb-0">Loan product has been updated successfully.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

@endsection
