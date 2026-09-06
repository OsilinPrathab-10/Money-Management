@extends('layouts/layoutMaster')

@section('title', 'Loan Types')

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
@vite(['resources/assets/custom-js/loan-types.js'])
@endsection

@section('content')
<!-- Alert Container -->
<div class="alert-container"
  data-success="{{ session('success') ? e(session('success')) : '' }}"
  data-error="{{ session('error') ? e(session('error')) : '' }}"
  data-warning="{{ session('warning') ? e(session('warning')) : '' }}"
  data-info="{{ session('info') ? e(session('info')) : '' }}">
</div>

<!-- Loan Types Table -->
<div class="card">
  <div class="card-header border-bottom d-flex justify-content-between align-items-center">
    <h5 class="card-title mb-0">Loan Types & Configuration</h5>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createLoanTypeModal">
      <i class="icon-base ri ri-add-line me-1"></i>
      Create Loan Type
    </button>
  </div>
  <div class="card-datatable table-responsive">
    <table class="datatables-loan-types table">
      <thead>
        <tr>
          <th></th>
          <th>S.No</th>
          <th>Name</th>
          <th>Description</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
  </div>
</div>

<!-- Create Loan Type Modal -->
<div class="modal fade" id="createLoanTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Create Loan Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="createLoanTypeForm" class="row g-5" action="{{ route('loan-types.store') }}" method="POST" enctype="multipart/form-data">
          @csrf
          <!-- Loan Type Name -->
          <div class="col-md-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="loanTypeName">Loan Type Name <span class="text-danger">*</span></label>
              <input type="text" id="loanTypeName" class="form-control" placeholder="Enter loan type name" name="name" required />
              @error('name')
                <small class="text-danger">{{ $message }}</small>
              @enderror
            </div>
          </div>

          <!-- Description -->
          <div class="col-12 form-control-validation">
            <div class="mb-5 @error('description') is-invalid @enderror">
              <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
              <textarea id="description" class="form-control h-px-100 @error('description') is-invalid @enderror" rows="4" placeholder="Enter loan type description" name="description" minlength="5" required>{{ old('description') }}</textarea>
              @error('description')
                <div class="invalid-feedback d-block">{{ $message }}</div>
              @enderror
            </div>
          </div>

          <!-- Loan Type Icon -->
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="loanTypeIcon">Loan Icon</label>
              <input type="file" id="loanTypeIcon" class="form-control" name="loan_type_icon" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload icon (JPG, PNG, SVG, WEBP - Max 2MB)</div>
              <div id="iconPreview" class="mt-2" style="display: none;">
                <img src="" alt="Icon Preview" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
              </div>
            </div>
          </div>

          <!-- Loan Type Image -->
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="loanTypeImage">Loan Image</label>
              <input type="file" id="loanTypeImage" class="form-control" name="loan_type_image" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload image (JPG, PNG, SVG, WEBP - Max 2MB)</div>
              <div id="imagePreview" class="mt-2" style="display: none;">
                <img src="" alt="Image Preview" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
              </div>
            </div>
          </div>

          <!-- Loan Type Banner -->
          <div class="col-md-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="loanTypeBanner">Loan Banner</label>
              <input type="file" id="loanTypeBanner" class="form-control" name="loan_type_banner" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload banner (JPG, PNG, SVG, WEBP - Max 2MB, Rec: 378x143)</div>
              <div id="bannerPreview" class="mt-2" style="display: none;">
                <img src="" alt="Banner Preview" class="img-thumbnail" style="max-width: 300px; max-height: 115px;">
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-3" id="saveLoanTypeBtn">
              <i class="icon-base ri ri-save-line me-1"></i> Save Loan Type
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

<!-- Edit Loan Type Modal -->
<div class="modal fade" id="editLoanTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Loan Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editLoanTypeForm" class="row g-5" method="POST" enctype="multipart/form-data">
          @csrf
          @method('PUT')
          <input type="hidden" id="editLoanTypeId" name="id">
          
          <!-- Loan Type Name -->
          <div class="col-md-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanTypeName">Loan Type Name <span class="text-danger">*</span></label>
              <input type="text" id="editLoanTypeName" class="form-control" placeholder="Enter loan type name" name="name" required />
            </div>
          </div>

          <!-- Description -->
          <div class="col-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editDescription">Description <span class="text-danger">*</span></label>
              <textarea id="editDescription" class="form-control h-px-100" rows="4" placeholder="Enter loan type description" name="description" minlength="5" required></textarea>
            </div>
          </div>

          <!-- Loan Type Icon -->
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanTypeIcon">Loan Type Icon</label>
              <div id="currentIconContainer" class="mb-2" style="display: none;">
                <label class="form-label text-muted small">Current Icon:</label>
                <div>
                  <img id="currentIcon" src="" alt="Current Icon" class="img-thumbnail" style="max-width: 80px; max-height: 80px;">
                </div>
              </div>
              <input type="file" id="editLoanTypeIcon" class="form-control" name="loan_type_icon" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload new icon to replace (JPG, PNG, SVG, WEBP - Max 2MB)</div>
              <div id="editIconPreview" class="mt-2" style="display: none;">
                <label class="form-label text-muted small">New Icon Preview:</label>
                <img src="" alt="Icon Preview" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
              </div>
            </div>
          </div>

          <!-- Loan Type Image -->
          <div class="col-md-6 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanTypeImage">Loan Type Image</label>
              <div id="currentImageContainer" class="mb-2" style="display: none;">
                <label class="form-label text-muted small">Current Image:</label>
                <div>
                  <img id="currentImage" src="" alt="Current Image" class="img-thumbnail" style="max-width: 150px; max-height: 100px;">
                </div>
              </div>
              <input type="file" id="editLoanTypeImage" class="form-control" name="loan_type_image" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload new image to replace (JPG, PNG, SVG, WEBP - Max 2MB)</div>
              <div id="editImagePreview" class="mt-2" style="display: none;">
                <label class="form-label text-muted small">New Image Preview:</label>
                <img src="" alt="Image Preview" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
              </div>
            </div>
          </div>

          <!-- Loan Type Banner -->
          <div class="col-md-12 form-control-validation">
            <div class="mb-5">
              <label class="form-label" for="editLoanTypeBanner">Loan Banner</label>
              <div id="currentBannerContainer" class="mb-2" style="display: none;">
                <label class="form-label text-muted small">Current Banner:</label>
                <div>
                  <img id="currentBanner" src="" alt="Current Banner" class="img-thumbnail" style="max-width: 300px; max-height: 115px;">
                </div>
              </div>
              <input type="file" id="editLoanTypeBanner" class="form-control" name="loan_type_banner" accept="image/jpeg,image/jpg,image/png,image/svg+xml,image/webp" />
              <div class="form-text">Upload new banner to replace (JPG, PNG, SVG, WEBP - Max 2MB)</div>
              <div id="editBannerPreview" class="mt-2" style="display: none;">
                <label class="form-label text-muted small">New Banner Preview:</label>
                <img src="" alt="Banner Preview" class="img-thumbnail" style="max-width: 300px; max-height: 115px;">
              </div>
            </div>
          </div>

          <!-- Submit Buttons -->
          <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary me-3" id="updateLoanTypeBtn">
              <i class="icon-base ri ri-save-line me-1"></i> Update Loan Type
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

<!-- View Loan Type Modal -->
<div class="modal fade" id="viewLoanTypeModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Loan Type Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Name</label>
            <p class="mb-0" id="viewLoanTypeName">-</p>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description</label>
            <p class="mb-0" id="viewLoanTypeDescription">-</p>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Status</label>
            <div id="viewLoanTypeStatus">-</div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Icon</label>
            <div id="viewLoanTypeIcon">
              <img src="" alt="No icon" class="img-thumbnail" style="max-width: 100px; max-height: 100px; display: none;" id="viewIconImage">
              <p class="text-muted mb-0" id="viewIconPlaceholder">No icon uploaded</p>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Image</label>
            <div id="viewLoanTypeImage">
              <img src="" alt="No image" class="img-thumbnail" style="max-width: 200px; max-height: 150px; display: none;" id="viewImageImage">
              <p class="text-muted mb-0" id="viewImagePlaceholder">No image uploaded</p>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Banner</label>
            <div id="viewLoanTypeBanner">
              <img src="" alt="No banner" class="img-thumbnail" style="max-width: 378px; max-height: 143px; display: none;" id="viewBannerImage">
              <p class="text-muted mb-0" id="viewBannerPlaceholder">No banner uploaded</p>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
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
        <p class="text-center">Do you really want to delete "<strong id="deleteLoanTypeName"></strong>"?</p>
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
@endsection