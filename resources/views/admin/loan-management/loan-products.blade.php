@extends('layouts/layoutMaster')

@section('title', 'Loan Products')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js'
])
@endsection

@section('page-script')
@vite(['resources/assets/custom-js/loan-products.js'])
@endsection

@section('content')
<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Loan Products Table -->
<div class="card">
  <div class="card-header border-bottom d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Loan Products</h5>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProductModal">
      <i class="icon-base ri ri-add-line me-1"></i>
      Create Product
    </button>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-loan-products table text-nowrap">
      <thead>
        <tr>
          <th>S.No</th>
          <th>Loan Code</th>
          <th>Name</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- Create Product Modal -->
<div class="modal fade" id="createProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Loan Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="createProductForm" class="row g-5" action="{{ route('loans.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <!-- Basic Information -->
          <div class="col-12">
            <h6>1. Basic Information</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('loanName') is-invalid @enderror">
              <label class="form-label" for="loanName">Loan Name <span class="text-danger">*</span></label>
              <input type="text" id="loanName" class="form-control @error('loanName') is-invalid @enderror" placeholder="Enter loan name" name="loanName" value="{{ old('loanName') }}" required />
              @error('loanName')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('loanType') is-invalid @enderror">
              <label class="form-label" for="loanType">Loan Type <span class="text-danger">*</span></label>
              <select id="loanType" class="form-select @error('loanType') is-invalid @enderror" name="loanType" required>
                <option value="" disabled selected>Select Type</option>
                @foreach ($loanTypes as $loanType)
                  <option value="{{ $loanType->id }}" {{ old('loanType') == $loanType->id ? 'selected' : '' }}>{{ $loanType->name }}</option>
                @endforeach
              </select>
              @error('loanType')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('loanCode') is-invalid @enderror">
              <label class="form-label" for="loanCode">Loan Code</label>
              <input type="text" id="loanCode" class="form-control @error('loanCode') is-invalid @enderror" placeholder="e.g., LP001" name="loanCode" value="{{ old('loanCode') }}" />
              @error('loanCode')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Amount Details -->
          <div class="col-12">
            <h6 class="mt-2">2. Amount Details</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('loanAmountMin') is-invalid @enderror">
              <label class="form-label" for="loanAmountMin">Minimum Amount <span class="text-danger">*</span></label>
              <input type="number" id="loanAmountMin" class="form-control @error('loanAmountMin') is-invalid @enderror" placeholder="0.00" step="0.01" name="loanAmountMin" value="{{ old('loanAmountMin') }}" required />
              @error('loanAmountMin')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('loanAmountMax') is-invalid @enderror">
              <label class="form-label" for="loanAmountMax">Maximum Amount <span class="text-danger">*</span></label>
              <input type="number" id="loanAmountMax" class="form-control @error('loanAmountMax') is-invalid @enderror" placeholder="0.00" step="0.01" name="loanAmountMax" value="{{ old('loanAmountMax') }}" required />
              @error('loanAmountMax')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Interest & Terms -->
          <div class="col-12">
            <h6 class="mt-2">3. Interest & Terms</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('interestRate') is-invalid @enderror">
              <label class="form-label" for="interestRate">Interest Rate (%) <span class="text-danger">*</span></label>
              <input type="number" id="interestRate" class="form-control @error('interestRate') is-invalid @enderror" placeholder="0.00" step="0.01" name="interestRate" value="{{ old('interestRate') }}" required />
              @error('interestRate')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('interestType') is-invalid @enderror">
              <label class="form-label" for="interestType">Interest Type <span class="text-danger">*</span></label>
              <select id="interestType" class="form-select @error('interestType') is-invalid @enderror"
                    name="interestType" required>
                <option value="" disabled selected>Select Type</option>

                <option value="fixed"
                    {{ old('interestType', 'reducing') == 'fixed' ? 'selected' : '' }}>
                    Fixed
                </option>

                <option value="reducing"
                    {{ old('interestType', 'reducing') == 'reducing' ? 'selected' : '' }}>
                    Reducing Balance
                </option>

                <option value="flat"
                    {{ old('interestType', 'reducing') == 'flat' ? 'selected' : '' }}>
                    Flat Rate
                </option> 
            </select>
              @error('interestType')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('termUnit') is-invalid @enderror">
              <label class="form-label" for="termUnit">Term Unit <span class="text-danger">*</span></label>
              <select id="termUnit" class="form-select @error('termUnit') is-invalid @enderror"
                      name="termUnit" required>
                  <option value="">Select Unit</option>
                  <option value="days"
                      {{ old('termUnit', 'months') == 'days' ? 'selected' : '' }}>
                      Days
                  </option>

                  <option value="weeks"
                      {{ old('termUnit', 'months') == 'weeks' ? 'selected' : '' }}>
                      Weeks
                  </option>

                  <option value="months"
                      {{ old('termUnit', 'months') == 'months' ? 'selected' : '' }}>
                      Months
                  </option>
              </select>
              @error('termUnit')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('minTenure') is-invalid @enderror">
              <label class="form-label" for="minTenure">Min Tenure <span class="text-danger">*</span></label>
              <input type="number" id="minTenure" class="form-control @error('minTenure') is-invalid @enderror" placeholder="0" name="minTenure" value="{{ old('minTenure') }}" required />
              @error('minTenure')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('maxTenure') is-invalid @enderror">
              <label class="form-label" for="maxTenure">Max Tenure <span class="text-danger">*</span></label>
              <input type="number" id="maxTenure" class="form-control @error('maxTenure') is-invalid @enderror" placeholder="0" name="maxTenure" value="{{ old('maxTenure') }}" required />
              @error('maxTenure')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Additional Charges -->
          <div class="col-12">
            <h6 class="mt-2">4. Additional Charges & Settings</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('processingFee') is-invalid @enderror">
              <label class="form-label" for="processingFee">Processing Fee</label>
              <input type="number" id="processingFee" class="form-control @error('processingFee') is-invalid @enderror" placeholder="0.00" step="0.01" name="processingFee" value="{{ old('processingFee') }}" />
              @error('processingFee')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('documentCharges') is-invalid @enderror">
              <label class="form-label" for="documentCharges">Document Charges</label>
              <input type="number" id="documentCharges" class="form-control @error('documentCharges') is-invalid @enderror" placeholder="0.00" step="0.01" name="documentCharges" value="{{ old('documentCharges') }}" />
              @error('documentCharges')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('otherCharges') is-invalid @enderror">
              <label class="form-label" for="otherCharges">Other Charges</label>
              <input type="number" id="otherCharges" class="form-control @error('otherCharges') is-invalid @enderror" placeholder="0.00" step="0.01" name="otherCharges" value="{{ old('otherCharges') }}" />
              @error('otherCharges')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('penaltyRate') is-invalid @enderror">
              <label class="form-label" for="penaltyRate">Penalty Amount (₹)</label>
              <input type="number" id="penaltyRate" class="form-control @error('penaltyRate') is-invalid @enderror" placeholder="0.00" step="0.01" name="penaltyRate" value="{{ old('penaltyRate', '0.00') }}" />
              @error('penaltyRate')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('gracePeriod') is-invalid @enderror">
              <label class="form-label" for="gracePeriod">Grace Period (Days)</label>
              <input type="number" id="gracePeriod" class="form-control @error('gracePeriod') is-invalid @enderror" placeholder="0" name="gracePeriod" value="{{ old('gracePeriod', '0') }}" />
              @error('gracePeriod')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          <div class="col-md-6">
            <div class="mb-5 @error('requireCollateral') is-invalid @enderror">
              <label class="form-label" for="requireCollateral">Require Collateral <span class="text-danger">*</span></label>
              <select id="requireCollateral" class="form-select @error('requireCollateral') is-invalid @enderror" name="requireCollateral" required>
                <option value="" disabled selected>Select Option</option>
                <option value="1">Yes</option>
                <option value="0">No</option>
              </select>
            </div>
          </div>
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="defaultTerm">Default Term</label>
              <input type="number" id="defaultTerm" class="form-control" placeholder="0" name="defaultTerm" />
            </div>
          </div>

          <!-- Description -->
          <div class="col-12">
            <h6 class="mt-2">5. Description</h6>
            <hr class="mt-0" />
          </div>
          <div class="col-12 form-control-validation">
            <div class="mb-5 @error('description') is-invalid @enderror">
              <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
              <textarea id="description" class="form-control h-px-100 @error('description') is-invalid @enderror" rows="3" placeholder="Enter loan product description" name="description" minlength="5" required>{{ old('description') }}</textarea>
              @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-3" id="submitProductBtn">
              <i class="icon-base ri ri-save-line me-1"></i> Save Product
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


<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCenterTitle">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="text-center mb-4">
          <i class="icon-base ri ri-delete-bin-6-line text-danger" style="font-size: 48px;"></i>
        </div>
        <h5 class="text-center mb-2">Are you sure?</h5>
        <p class="text-center">Do you really want to delete "<strong id="deleteProductName"></strong>"?</p>
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
        <h5 class="mb-2">Deleted Successfully!</h5>
        <p class="text-muted mb-0" id="successMessage">Loan product has been deleted successfully.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
@endsection
