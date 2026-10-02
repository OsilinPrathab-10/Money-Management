<!-- Edit KYC Modal -->
<div class="modal fade" id="editKycModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-simple modal-edit-kyc">
    <div class="modal-content">
      <div class="modal-body p-md-5 p-3">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        <div class="text-center mb-6">
          <h4 class="mb-2">Edit KYC & Bank Details</h4>
          <p class="text-muted">Update identity, financial, and contact details for verification.</p>
        </div>
        <form action="{{ route('verification-kyc-update', $client->id) }}" method="POST" enctype="multipart/form-data" class="row g-4">
          @csrf
          
          <h5 class="col-12 border-bottom pb-2 mb-0 mt-3 text-primary">Personal Details</h5>
          
          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditClientName" name="client_name" class="form-control"
                value="{{ $client->client_name }}" placeholder="Client Name" required />
              <label for="modalEditClientName">Client Name</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="email" id="modalEditClientEmail" name="client_email" class="form-control"
                value="{{ $client->client_email }}" placeholder="client@example.com" />
              <label for="modalEditClientEmail">Email Address</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditClientPhone" name="client_phone" class="form-control"
                value="{{ $client->client_phone }}" placeholder="9876543210" required />
              <label for="modalEditClientPhone">Phone Number</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="number" id="modalEditCibilScore" name="cibil_score" class="form-control"
                value="{{ $client->cibil_score }}" placeholder="750" min="300" max="900" />
              <label for="modalEditCibilScore">CIBIL Score</label>
            </div>
          </div>

          <h5 class="col-12 border-bottom pb-2 mb-0 mt-4 text-primary">Identity Verification</h5>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditAadhaarNumber" name="aadhaar_number" class="form-control"
                value="{{ $client->aadhaar_number }}" placeholder="Aadhaar Number" />
              <label for="modalEditAadhaarNumber">Aadhaar Number</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditPanNumber" name="pan_number" class="form-control"
                value="{{ optional($kyc)->pan_number }}" placeholder="PAN Number" maxlength="10" />
              <label for="modalEditPanNumber">PAN Number</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditPanName" name="pan_name" class="form-control"
                value="{{ optional($kyc)->pan_name }}" placeholder="Name on PAN Card" />
              <label for="modalEditPanName">Name on PAN</label>
            </div>
          </div>

          <h5 class="col-12 border-bottom pb-2 mb-0 mt-4 text-primary">Bank Account Details</h5>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditAccHolder" name="account_holder_name" class="form-control"
                value="{{ optional($kyc)->account_holder_name }}" placeholder="Account Holder Name" />
              <label for="modalEditAccHolder">Account Holder Name</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditAccNumber" name="account_number" class="form-control"
                value="{{ optional($kyc)->account_number }}" placeholder="Account Number" />
              <label for="modalEditAccNumber">Account Number</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditIfscCode" name="ifsc_code" class="form-control"
                value="{{ optional($kyc)->ifsc_code }}" placeholder="IFSC Code" />
              <label for="modalEditIfscCode">IFSC Code</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditBankName" name="bank_name" class="form-control"
                value="{{ optional($kyc)->bank_name }}" placeholder="Bank Name" />
              <label for="modalEditBankName">Bank Name</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditBranchName" name="branch_name" class="form-control"
                value="{{ optional($kyc)->branch_name }}" placeholder="Branch Name" />
              <label for="modalEditBranchName">Branch Name</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <select id="modalEditAccountType" name="account_type" class="form-select">
                <option value="" disabled {{ !optional($kyc)->account_type ? 'selected' : '' }}>Select Account Type</option>
                <option value="savings" {{ optional($kyc)->account_type === 'savings' ? 'selected' : '' }}>Savings</option>
                <option value="current" {{ optional($kyc)->account_type === 'current' ? 'selected' : '' }}>Current</option>
              </select>
              <label for="modalEditAccountType">Account Type</label>
            </div>
          </div>

          <h5 class="col-12 border-bottom pb-2 mb-0 mt-4 text-primary">Upload Documents</h5>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Profile Photo / Selfie</label>
            <input type="file" name="selfie_image" class="form-control form-control-sm" accept="image/*" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">PAN Card Document</label>
            <input type="file" name="pan_image" class="form-control form-control-sm" accept="image/*" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Aadhaar Card Front</label>
            <input type="file" name="aadhaar_image" class="form-control form-control-sm" accept="image/*" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Aadhaar Card Back</label>
            <input type="file" name="aadhaar_image_back" class="form-control form-control-sm" accept="image/*" />
          </div>

          <div class="col-12">
            <label class="form-label small text-muted">Bank Statement</label>
            <input type="file" name="bank_statement" class="form-control form-control-sm" accept="image/*,application/pdf" />
          </div>

          <h5 class="col-12 border-bottom pb-2 mb-0 mt-4 text-primary">
            <i class="ri-file-text-line me-1"></i>Additional / Collateral Loan Documents <span class="badge bg-label-info ms-2" style="font-size: .7rem;">Optional / Non-Mandatory</span>
          </h5>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditVehicleNumber" name="vehicle_number" class="form-control"
                value="{{ optional($kyc)->vehicle_number }}" placeholder="Vehicle Number (e.g. TN 01 AB 1234)" />
              <label for="modalEditVehicleNumber">Vehicle Number (Bike / Car)</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Vehicle RC Book (Bike / Car)</label>
            <input type="file" name="rc_book_image" class="form-control form-control-sm" accept="image/*,application/pdf" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Driving Licence Copy</label>
            <input type="file" name="driving_licence_image" class="form-control form-control-sm" accept="image/*,application/pdf" />
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Home Loan / Property Document</label>
            <input type="file" name="home_loan_document" class="form-control form-control-sm" accept="image/*,application/pdf" />
          </div>

          <div class="col-12 col-md-6">
            <div class="form-floating form-floating-outline">
              <input type="text" id="modalEditDocTitle" name="additional_document_title" class="form-control" placeholder="Document Title (e.g. Income Proof, Agreement)" />
              <label for="modalEditDocTitle">Other Document Title / Name</label>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label small text-muted">Other Loan Document File</label>
            <input type="file" name="additional_document_file" class="form-control form-control-sm" accept="image/*,application/pdf" />
          </div>

          <div class="col-12 text-center mt-5">
            <button type="submit" class="btn btn-primary me-3">Save Changes</button>
            <button type="reset" class="btn btn-outline-secondary" data-bs-dismiss="modal" aria-label="Close">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<!--/ Edit KYC Modal -->
