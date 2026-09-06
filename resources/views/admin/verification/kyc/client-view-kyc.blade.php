@extends('layouts/layoutMaster')
@section('title', 'User View - KYC Verification')

@section('vendor-style')
@vite([
  'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
  'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
  'resources/assets/vendor/libs/tagify/tagify.scss',
  'resources/assets/vendor/libs/animate-css/animate.scss',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
  'resources/assets/vendor/libs/select2/select2.scss',
  'resources/assets/vendor/libs/@form-validation/form-validation.scss',
  'resources/assets/vendor/libs/flatpickr/flatpickr.scss'
])
@endsection

@section('vendor-script')
@vite([
  'resources/assets/vendor/libs/moment/moment.js',
  'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
  'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
  'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
  'resources/assets/vendor/libs/tagify/tagify.js',
  'resources/assets/vendor/libs/select2/select2.js',
  'resources/assets/vendor/libs/@form-validation/popular.js',
  'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
  'resources/assets/vendor/libs/@form-validation/auto-focus.js',
  'resources/assets/vendor/libs/flatpickr/flatpickr.js'
])
@endsection

@section('page-script')
@vite([
'resources/assets/js/modal-edit-user.js',
'resources/assets/js/app-user-view.js',
'resources/assets/js/client-view-account.js',
'resources/assets/custom-js/app-kyc-verification.js',
'resources/assets/custom-js/emi-calculator.js',
'resources/assets/custom-js/loan-applications.js'
])
<script>
document.addEventListener('DOMContentLoaded', function() {
    const fetchBtn = document.getElementById('fetchCibilBtn');
    if (fetchBtn) {
        fetchBtn.addEventListener('click', function() {
            const btn = this;
            const icon = btn.querySelector('i');
            btn.disabled = true;
            icon.classList.add('ri-spin');

            // Simulate API Fetch
            setTimeout(() => {
                const randomScore = Math.floor(Math.random() * (850 - 600 + 1)) + 600;
                const badge = document.getElementById('cibilScoreBadge');
                badge.textContent = randomScore;
                badge.className = `badge bg-label-${randomScore >= 700 ? 'success' : 'warning'}`;
                
                icon.classList.remove('ri-spin');
                btn.remove(); // Remove button after fetch

                Swal.fire({
                    title: 'CIBIL Score Fetched!',
                    text: `The CIBIL score for this client is ${randomScore}.`,
                    icon: 'success',
                    customClass: { confirmButton: 'btn btn-primary' }
                });
            }, 1500);
        });
    }
});
</script>
@endsection

@section('content')

<!-- Success/Error Alerts -->
@if(session('success'))
  <div class="alert alert-success alert-dismissible" role="alert">
    <strong>Success!</strong> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible" role="alert">
    <strong>Error!</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
  <div class="nav-align-top mb-0">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item">
        <a class="nav-link active" href="javascript:void(0);">
          <i class="icon-base ri ri-shield-check-line me-1_5"></i>
          KYC
        </a>
      </li>
    </ul>
  </div>
  <a href="{{ route('verification-kyc-verification') }}" class="btn btn-outline-secondary">
    <i class="icon-base ri ri-arrow-left-line me-1"></i>
    Back to KYC Verification
  </a>
</div>

<div class="row">
  <!-- User Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <!-- User Card -->
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column">
            @if(optional($client->kycDetail)->selfie_image && substr(optional($client->kycDetail)->selfie_image, 0, 5) === 'data:')
              {{-- Base64 encoded image --}}
              <img class="img-fluid rounded mb-4" src="{{ $client->kycDetail->selfie_image }}" height="120" width="120"
                alt="User avatar" style="object-fit: cover;" />
            @elseif(optional($client->kycDetail)->selfie_image)
              {{-- File path image --}}
              <img class="img-fluid rounded mb-4" src="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" height="120" width="120"
                alt="User avatar" style="object-fit: cover;" />
            @else
              {{-- Default avatar --}}
              <img class="img-fluid rounded mb-4" src="{{asset('assets/img/avatars/1.png')}}" height="120" width="120"
                alt="User avatar" />
            @endif
            <div class="user-info text-center">
              <h5>{{ $client->client_name ?? 'Client Name' }}</h5>
              <span class="badge bg-label-{{ $stats['kyc']['badge'] ?? 'secondary' }} rounded-pill">
                {{ $stats['kyc']['label'] ?? 'Pending' }}
              </span>
            </div>
          </div>
        </div>
        <div class="d-flex justify-content-center justify-content-lg-between flex-wrap flex-lg-nowrap my-6 gap-sm-6 gap-4">
          <div class="d-flex align-items-center gap-4">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $stats['applications'] ?? 0 }}</h5>
              <span class="text-muted small">Applications</span>
            </div>
          </div>
          <div class="d-flex align-items-center gap-4">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-file-text-line icon-24px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $stats['loans'] ?? 0 }}</h5>
              <span class="text-muted small">Total Loans</span>
            </div>
          </div>
        </div>
        <div class="d-flex flex-column gap-4">
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Identity Details</small>
            <div class="row g-4">
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Aadhaar Number</small>
                <p class="mb-0 text-heading">{{ $client->aadhaar_number ?? 'N/A' }}</p>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">PAN Number</small>
                <p class="mb-0 text-heading">{{ optional($client->kycDetail)->pan_number ?? 'N/A' }}</p>
              </div>
            </div>
          </div>

          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Client Information</small>
            <div class="row g-4">
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Mobile Number</small>
                <p class="mb-0 text-heading">{{ $client->client_phone ?? 'N/A' }}</p>
              </div>
              <div class="col-12">
                <small class="text-muted text-uppercase">Email</small>
                <p class="mb-0 text-heading" style="word-break: break-all;" title="{{ $client->client_email ?? 'N/A' }}">{{ $client->client_email ?? 'N/A' }}</p>

              </div>
              <!-- <div class="col-sm-6">
                <small class="text-muted text-uppercase">CIBIL Score</small>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <p class="mb-0 text-heading">
                    <span id="cibilScoreBadge" class="badge bg-label-{{ ($client->cibil_score ?? 0) >= 700 ? 'success' : 'warning' }}">{{ $client->cibil_score ?? 'N/A' }}</span>
                  </p>
                  @if(!$client->cibil_score || $client->cibil_score == 'N/A')
                  <button type="button" class="btn btn-sm btn-icon btn-outline-primary rounded-pill" id="fetchCibilBtn" title="Fetch CIBIL Score">
                    <i class="ri-refresh-line"></i>
                  </button>
                  @endif
                </div>
              </div> -->
            </div>
          </div>
          
          @if(in_array($verificationStatus, ['unverified', 'pending']))
            <!-- Show Approve/Reject buttons while verification is pending -->
            <div class="d-flex flex-column align-items-center gap-3">
            <!-- Show Approve/Reject buttons -->
            <div class="d-flex flex-column align-items-center gap-3">
                <div class="d-flex justify-content-center">
                  <button type="button" class="btn btn-success me-4" data-bs-toggle="modal" data-bs-target="#approveModal">
                    <i class="icon-base ri ri-check-line me-1"></i>
                    Approve KYC
                  </button>
                  <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                    <i class="icon-base ri ri-close-line me-1"></i>
                    Reject
                  </button>
                </div>
            </div>
            </div>
          @else
            <!-- Show status badge after decision -->
            <div class="text-center mt-4">
              @if($verificationStatus === 'verified')
                <div class="mb-3">
                  <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Verification Approved</h6>
                <span class="badge bg-label-success mb-4">Verified</span>
                
                <div class="card bg-label-primary border-0 shadow-none mt-4 overflow-hidden">
                  <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-start justify-content-between mb-4">
                      <div class="avatar">
                        <div class="avatar-initial bg-primary rounded-3">
                          <i class="ri-hand-coin-line icon-24px"></i>
                        </div>
                      </div>
                    </div>
                    <h5 class="mb-2">Eligibility Ready</h5>
                    <p class="mb-4 small">KYC is verified. You can now process a new loan for this client.</p>
                    <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalApplyLoan">
                      <i class="ri-add-line me-1"></i> Apply for Loan
                    </button>
                    </div>
                </div>
                <p class="text-muted mt-4 mb-0 small">KYC has been successfully verified</p>
              @elseif($verificationStatus === 'inactive' || $verificationStatus === 'rejected')
                <div class="mb-3">
                  <i class="icon-base ri ri-close-circle-line text-danger" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2 text-danger">Verification Rejected</h6>
                <span class="badge bg-label-danger">Rejected</span>
                <p class="text-danger mt-2 mb-0 small">KYC verification was rejected</p>
              @elseif($verificationStatus === 'active')
                <div class="mb-3">
                  <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Account Active</h6>
                <span class="badge bg-label-success">Active</span>
              @elseif($verificationStatus === 'blacklist')
                <div class="mb-3">
                  <i class="icon-base ri ri-error-warning-line text-dark" style="font-size: 48px;"></i>
                </div>
                <h6 class="mb-2">Account Blacklisted</h6>
                <span class="badge bg-label-dark">Blacklist</span>
                <p class="text-muted mt-2 mb-0 small">This account has been blacklisted</p>
              @endif
            </div>
          @endif
        </div>
      </div>
    </div>
    <!-- /User Card -->
  </div>
  <!--/ User Sidebar -->

  <!-- User Content -->
  <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
    <!-- KYC Content -->
    <div class="card mb-6">
      <h5 class="card-header">KYC Documents</h5>
      <div class="card-body">
        <div class="row g-4">
          <!-- Aadhar Card -->
          <div class="col-md-6">
            <div class="card h-100 border shadow-none">
              <div class="card-header border-bottom p-0">
                <div class="nav-align-top">
                  <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-aadhar-details" aria-controls="navs-aadhar-details" aria-selected="true">
                        <i class="ri-file-list-3-line me-1"></i> Details
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-aadhar-document" aria-controls="navs-aadhar-document" aria-selected="false">
                        <i class="ri-image-line me-1"></i> Document
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="tab-content border-0 p-0">
                <div class="tab-pane fade show active p-4" id="navs-aadhar-details" role="tabpanel">
                  <div class="d-flex align-items-center mb-4">
                    <img src="{{asset('assets/img/logos/aadhar logo.png')}}" alt="Aadhar Logo" height="40" class="me-3" />
                    <div>
                      <small class="text-primary text-uppercase fw-semibold d-block">Identity Card</small>
                      <h6 class="mb-0">Aadhaar Card</h6>
                    </div>
                  </div>
                  <div class="mb-3">
                    <small class="text-muted text-uppercase">Aadhaar Number</small>
                    <p class="mb-0 text-heading">{{ $client->aadhaar_number ?? 'N/A' }}</p>
                  </div>
                  <div>
                    <small class="text-muted text-uppercase">Name</small>
                    <p class="mb-0 text-heading">{{ optional($client->kycDetail)->aadhaar_name ?? 'N/A' }}</p>
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-aadhar-document" role="tabpanel">
                  <div class="row g-2">
                    <div class="col-md-6">
                      <p class="small text-muted mb-1 text-center">Front Side</p>
                      @if(optional($client->kycDetail)->aadhaar_image)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $client->kycDetail->aadhaar_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Front" />
                        </div>
                      @else
                        <div class="text-center py-5 border rounded">
                          <i class="ri-image-line text-muted" style="font-size: 32px;"></i>
                          <p class="text-muted mt-2 mb-0 small">No Front View</p>
                        </div>
                      @endif
                    </div>
                    <div class="col-md-6">
                      <p class="small text-muted mb-1 text-center">Back Side</p>
                      @if(optional($client->kycDetail)->aadhaar_image_back)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $client->kycDetail->aadhaar_image_back) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Back" />
                        </div>
                      @else
                        <div class="text-center py-5 border rounded">
                          <i class="ri-image-line text-muted" style="font-size: 32px;"></i>
                          <p class="text-muted mt-2 mb-0 small">No Back View</p>
                        </div>
                      @endif
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>
          <!-- /Aadhar Card -->

          <!-- PAN Card -->
          <div class="col-md-6">
            <div class="card h-100 border shadow-none">
              <div class="card-header border-bottom p-0">
                <div class="nav-align-top">
                  <ul class="nav nav-tabs nav-fill" role="tablist">
                    <li class="nav-item">
                      <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pan-details" aria-controls="navs-pan-details" aria-selected="true">
                        <i class="ri-file-list-3-line me-1"></i> Details
                      </button>
                    </li>
                    <li class="nav-item">
                      <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pan-document" aria-controls="navs-pan-document" aria-selected="false">
                        <i class="ri-image-line me-1"></i> Document
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="tab-content border-0 p-0">
                <div class="tab-pane fade show active p-4" id="navs-pan-details" role="tabpanel">
                  <div class="d-flex align-items-center mb-4">
                    <img src="{{asset('assets/img/logos/pan logo.png')}}" alt="PAN Logo" height="40" class="me-3" />
                    <div>
                      <small class="text-primary text-uppercase fw-semibold d-block">Identity Card</small>
                      <h6 class="mb-0">PAN Card</h6>
                    </div>
                  </div>
                  <div class="mb-3">
                    <small class="text-muted text-uppercase">PAN Number</small>
                    <p class="mb-0 text-heading">{{ optional($client->kycDetail)->pan_number ?? 'N/A' }}</p>
                  </div>
                  <div>
                    <small class="text-muted text-uppercase">Name</small>
                    <p class="mb-0 text-heading">{{ optional($client->kycDetail)->pan_name ?? 'N/A' }}</p>
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-pan-document" role="tabpanel">
                  @if(optional($client->kycDetail)->pan_image)
                    <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                      <img src="{{ asset('storage/' . $client->kycDetail->pan_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="PAN Card" />
                    </div>
                  @else
                    <div class="text-center py-5">
                      <i class="ri-image-line text-muted" style="font-size: 48px;"></i>
                      <p class="text-muted mt-3 mb-0">No document uploaded</p>
                    </div>
                  @endif
                </div>
              </div>
            </div>
          </div>
          <!-- /PAN Card -->

          <!-- Bank Details -->
          <div class="col-12">
            <div class="border rounded-3 p-4">
              <div class="d-flex align-items-center mb-4">
                <i class="icon-base ri ri-bank-line icon-40px text-primary me-3"></i>
                <div>
                  <small class="text-primary text-uppercase fw-semibold d-block">Financial Information</small>
                  <h6 class="mb-0">Bank Account Details</h6>
                </div>
              </div>

              <div class="row g-4">
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">Account Holder Name</small>
                  <p class="mb-0 text-heading">{{ optional($client->kycDetail)->account_holder_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">Account Number</small>
                  <p class="mb-0 text-heading">{{ optional($client->kycDetail)->account_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">IFSC Code</small>
                  <p class="mb-0 text-heading">{{ optional($client->kycDetail)->ifsc_code ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">Bank Name</small>
                  <p class="mb-0 text-heading">{{ optional($client->kycDetail)->bank_name ?? 'N/A' }}</p>
                </div>
              </div>
            </div>
          </div>
          <!-- /Bank Details -->

          <!-- Bank Statement -->
          <div class="col-12">
            <div class="border rounded-3 p-4">
              <div class="d-flex align-items-center mb-4">
                <i class="icon-base ri ri-file-text-line icon-40px text-primary me-3"></i>
                <div>
                  <small class="text-primary text-uppercase fw-semibold d-block">Financial Document</small>
                  <h6 class="mb-0">Bank Statement</h6>
                </div>
              </div>

              @if(optional($client->kycDetail)->bank_statement)
                @php
                  $bankStatement = $client->kycDetail->bank_statement;
                  $isBase64 = substr($bankStatement, 0, 5) === 'data:';
                  $fileExtension = '';
                  
                  if ($isBase64) {
                    // Extract file type from base64 data
                    preg_match('/data:([^;]+);/', $bankStatement, $matches);
                    $mimeType = $matches[1] ?? '';
                    $fileExtension = str_contains($mimeType, 'pdf') ? 'pdf' : 'image';
                  } else {
                    // Get extension from file path
                    $fileExtension = strtolower(pathinfo($bankStatement, PATHINFO_EXTENSION));
                  }
                @endphp

                <div class="row g-4">
                  <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between p-3 bg-label-primary rounded-3">
                      <div class="d-flex align-items-center gap-3">
                        <div class="avatar">
                          <div class="avatar-initial bg-primary rounded-3">
                            @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                              <i class="icon-base ri ri-file-pdf-line icon-24px"></i>
                            @else
                              <i class="icon-base ri ri-image-line icon-24px"></i>
                            @endif
                          </div>
                        </div>
                        <div>
                          <h6 class="mb-0">Bank Statement Document</h6>
                          <small class="text-muted">
                            @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                              PDF Document
                            @else
                              Image File
                            @endif
                          </small>
                        </div>
                      </div>
                      <div class="d-flex gap-2">
                        @if($isBase64)
                          <button type="button" class="btn btn-sm btn-primary" onclick="viewBankStatement()">
                            <i class="icon-base ri ri-eye-line me-1"></i>
                            View
                          </button>
                          <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadBankStatement()">
                            <i class="icon-base ri ri-download-line me-1"></i>
                            Download
                          </button>
                        @else
                          <a href="{{ asset('storage/' . $bankStatement) }}" target="_blank" class="btn btn-sm btn-primary">
                            <i class="icon-base ri ri-eye-line me-1"></i>
                            View
                          </a>
                          <a href="{{ asset('storage/' . $bankStatement) }}" download class="btn btn-sm btn-outline-primary">
                            <i class="icon-base ri ri-download-line me-1"></i>
                            Download
                          </a>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>

                @if($isBase64)
                  <script>
                    function viewBankStatement() {
                      const data = @json($bankStatement);
                      window.open(data, '_blank');
                    }

                    function downloadBankStatement() {
                      const data = @json($bankStatement);
                      const link = document.createElement('a');
                      link.href = data;
                      link.download = 'bank_statement_{{ $client->client_name }}.{{ $fileExtension === "pdf" ? "pdf" : "jpg" }}';
                      document.body.appendChild(link);
                      link.click();
                      document.body.removeChild(link);
                    }
                  </script>
                @endif
              @else
                <div class="text-center py-5">
                  <i class="icon-base ri ri-file-forbid-line text-muted" style="font-size: 48px;"></i>
                  <p class="text-muted mt-3 mb-0">No bank statement uploaded</p>
                </div>
              @endif
            </div>
          </div>
          <!-- /Bank Statement -->

          <!-- Employment Documents -->
          @if($client->employeeInformation)
            <div class="col-12">
              <div class="border rounded-3 p-4">
                <div class="d-flex align-items-center mb-4">
                  <i class="icon-base ri ri-briefcase-line icon-40px text-primary me-3"></i>
                  <div>
                    <small class="text-primary text-uppercase fw-semibold d-block">Employment Verification</small>
                    <h6 class="mb-0">
                      @if($client->employeeInformation->employment_type === 'salaried')
                        Payslip Documents
                      @else
                        Business Proof Documents
                      @endif
                    </h6>
                  </div>
                </div>

                @php
                  $documents = [];
                  $documentType = '';
                  
                  if ($client->employeeInformation->employment_type === 'salaried' && !empty($client->employeeInformation->payslip_documents)) {
                    // Laravel auto-decodes JSON columns, check if already an array
                    $documents = is_array($client->employeeInformation->payslip_documents) 
                      ? $client->employeeInformation->payslip_documents 
                      : json_decode($client->employeeInformation->payslip_documents, true) ?? [];
                    $documentType = 'payslip';
                  } elseif ($client->employeeInformation->employment_type === 'self_employed' && !empty($client->employeeInformation->business_proof_documents)) {
                    // Laravel auto-decodes JSON columns, check if already an array
                    $documents = is_array($client->employeeInformation->business_proof_documents) 
                      ? $client->employeeInformation->business_proof_documents 
                      : json_decode($client->employeeInformation->business_proof_documents, true) ?? [];
                    $documentType = 'business_proof';
                  }
                @endphp

                @if(count($documents) > 0)
                  <div class="row g-3">
                    @foreach($documents as $index => $document)
                      @php
                        $isBase64 = substr($document, 0, 5) === 'data:';
                        $fileExtension = '';
                        
                        if ($isBase64) {
                          preg_match('/data:([^;]+);/', $document, $matches);
                          $mimeType = $matches[1] ?? '';
                          $fileExtension = str_contains($mimeType, 'pdf') ? 'pdf' : 'image';
                        } else {
                          $fileExtension = strtolower(pathinfo($document, PATHINFO_EXTENSION));
                        }
                      @endphp

                      <div class="col-12">
                        <div class="d-flex align-items-center justify-content-between p-3 bg-label-primary rounded-3">
                          <div class="d-flex align-items-center gap-3">
                            <div class="avatar">
                              <div class="avatar-initial bg-primary rounded-3">
                                @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                                  <i class="icon-base ri ri-file-pdf-line icon-24px"></i>
                                @else
                                  <i class="icon-base ri ri-image-line icon-24px"></i>
                                @endif
                              </div>
                            </div>
                            <div>
                              <h6 class="mb-0">
                                @if($documentType === 'payslip')
                                  Payslip {{ $index + 1 }}
                                @else
                                  Business Proof {{ $index + 1 }}
                                @endif
                              </h6>
                              <small class="text-muted">
                                @if($fileExtension === 'pdf' || str_contains($fileExtension, 'pdf'))
                                  PDF Document
                                @else
                                  Image File
                                @endif
                              </small>
                            </div>
                          </div>
                          <div class="d-flex gap-2">
                            @if($isBase64)
                              <button type="button" class="btn btn-sm btn-primary" onclick="viewEmploymentDoc{{ $index }}()">
                                <i class="icon-base ri ri-eye-line me-1"></i>
                                View
                              </button>
                              <button type="button" class="btn btn-sm btn-outline-primary" onclick="downloadEmploymentDoc{{ $index }}()">
                                <i class="icon-base ri ri-download-line me-1"></i>
                                Download
                              </button>
                            @else
                              <a href="{{ asset('storage/' . $document) }}" target="_blank" class="btn btn-sm btn-primary">
                                <i class="icon-base ri ri-eye-line me-1"></i>
                                View
                              </a>
                              <a href="{{ asset('storage/' . $document) }}" download class="btn btn-sm btn-outline-primary">
                                <i class="icon-base ri ri-download-line me-1"></i>
                                Download
                              </a>
                            @endif
                          </div>
                        </div>
                      </div>

                      @if($isBase64)
                        <script>
                          function viewEmploymentDoc{{ $index }}() {
                            const data = @json($document);
                            window.open(data, '_blank');
                          }

                          function downloadEmploymentDoc{{ $index }}() {
                            const data = @json($document);
                            const link = document.createElement('a');
                            link.href = data;
                            link.download = '{{ $documentType }}_{{ $index + 1 }}_{{ $client->client_name }}.{{ $fileExtension === "pdf" ? "pdf" : "jpg" }}';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                          }
                        </script>
                      @endif
                    @endforeach
                  </div>
                @else
                  <div class="text-center py-5">
                    <i class="icon-base ri ri-file-forbid-line text-muted" style="font-size: 48px;"></i>
                    <p class="text-muted mt-3 mb-0">
                      @if($client->employeeInformation->employment_type === 'salaried')
                        No payslip documents uploaded
                      @else
                        No business proof documents uploaded
                      @endif
                    </p>
                  </div>
                @endif
              </div>
            </div>
          @endif
          <!-- /Employment Documents -->
        </div>
      </div>
    </div>
    <!-- /KYC Content -->
  </div>
  <!--/ User Content -->
</div>

<!-- Approve Confirmation Modal -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('verification-kyc-approve', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Approval</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-checkbox-circle-line text-success" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center">Do you really want to approve this KYC verification?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Approve</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Delete/Reject Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form action="{{ route('verification-kyc-reject', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalCenterTitle">Confirm Rejection</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-close-circle-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center mb-4">Do you really want to reject this KYC verification? Client status will be updated to <strong>Inactive</strong>.</p>

          <div class="mb-3">
            <label for="reason" class="form-label fw-medium">Reason for Rejection <span class="text-danger">*</span></label>
            <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Enter reason for rejection" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="icon-base ri ri-close-circle-line me-1"></i> Reject
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Loan Application Modal -->
@include('admin.clients.modals.modal-apply-loan')

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
        <h5 class="mb-2" id="successTitle">Success!</h5>
        <p class="text-muted mb-0" id="successMessage">Action completed successfully.</p>
      </div>
      <div class="modal-footer justify-content-center border-0 pt-0">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>



@endsection
