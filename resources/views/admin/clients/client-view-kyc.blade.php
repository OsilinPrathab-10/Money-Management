@extends('layouts/layoutMaster')

@section('title', 'Client View - KYC')

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
'resources/assets/custom-js/emi-calculator.js',
'resources/assets/custom-js/loan-applications.js'
])
@endsection

@section('content')

@php($kyc = $client->kycDetail ?? $client->kyc ?? null)

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

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div class="nav-align-top">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/account/'.$client->id) }}"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-shield-check-line me-1_5"></i>KYC</a></li>
      <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/loans/'.$client->id) }}"><i class="icon-base ri ri-file-list-3-line me-1_5"></i>Loans</a></li>
    </ul>
  </div>
  <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
    <!-- <a href="{{ route('client-management-add') }}" class="btn btn-sm btn-outline-primary  w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-user-add-line me-1"></i>
      <span>Add Client</span>
    </a> -->
    <a href="{{ route('client-management') }}" class="btn btn-sm btn-outline-secondary  w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-arrow-left-line me-1"></i>
      <span>Back to Clients</span>
    </a>
  </div>
</div>

<div class="row">
  <!-- User Sidebar -->
  <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
    <!-- User Card -->
    <div class="card mb-6">
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column">
            @if(optional($kyc)->selfie_image && substr(optional($kyc)->selfie_image, 0, 5) === 'data:')
              {{-- Base64 encoded image --}}
              <img class="img-fluid rounded mb-4" src="{{ $kyc->selfie_image }}" height="120" width="120"
                alt="User avatar" style="object-fit: cover;" />
            @elseif(optional($kyc)->selfie_image)
              {{-- File path image --}}
              <img class="img-fluid rounded mb-4" src="{{ asset('storage/' . $kyc->selfie_image) }}" height="120" width="120"
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
        <div class="d-flex justify-content-around flex-wrap my-6 gap-0 gap-md-3 gap-lg-4">
          <div class="d-flex align-items-center me-5 gap-4">
            <div class="avatar">
              <div class="avatar-initial bg-label-primary rounded-3">
                <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
              </div>
            </div>
            <div>
              <h5 class="mb-0">{{ $stats['applications'] ?? 0 }}</h5>
              <span>Applications</span>
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
              <span>Total Loans</span>
            </div>
          </div>
        </div>
        <div class="d-flex flex-column gap-4">
          <!-- @if($verificationStatus === 'verified')
            <div class="border rounded-3 p-4 bg-label-primary">
              <h6 class="mb-3">Quick Actions</h6>
              <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalApplyLoan">
                <i class="ri-add-line me-1"></i> Apply for Loan
              </button>
            </div>
          @endif -->

          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Identity Details</small>
            <div class="row g-4">
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Aadhaar Number</small>
                <p class="mb-0 text-heading">{{ $client->aadhaar_number ?? 'N/A' }}</p>
              </div>
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">PAN Number</small>
                <p class="mb-0 text-heading">{{ optional($kyc)->pan_number ?? 'N/A' }}</p>
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
                <p class="mb-0 text-heading" style="word-break: break-all; overflow-wrap: break-word;">{{ $client->client_email ?? 'N/A' }}</p>
              </div>
              <!-- <div class="col-sm-6">
                <small class="text-muted text-uppercase">CIBIL Score</small>
                <div class="d-flex align-items-center gap-2 mt-1">
                  <span class="badge bg-label-info" data-bs-toggle="tooltip" title="CIBIL integration is coming soon in future updates">Coming Soon</span>
                </div>
              </div> -->
            </div>
          </div>
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
                    <p class="mb-0 text-heading">{{ optional($kyc)->aadhaar_name ?? 'N/A' }}</p>
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-aadhar-document" role="tabpanel">
                  <div class="row g-2">
                    <div class="col-md-6">
                      <p class="small text-muted mb-1 text-center">Front Side</p>
                      @if(optional($kyc)->aadhaar_image)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $kyc->aadhaar_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Front" />
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
                      @if(optional($kyc)->aadhaar_image_back)
                        <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                          <img src="{{ asset('storage/' . $kyc->aadhaar_image_back) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="Aadhar Back" />
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
                    <p class="mb-0 text-heading">{{ optional($kyc)->pan_number ?? 'N/A' }}</p>
                  </div>
                  <div>
                    <small class="text-muted text-uppercase">Name</small>
                    <p class="mb-0 text-heading">{{ optional($kyc)->pan_name ?? 'N/A' }}</p>
                  </div>
                </div>
                <div class="tab-pane fade p-2" id="navs-pan-document" role="tabpanel">
                  @if(optional($kyc)->pan_image)
                    <div class="text-center bg-light rounded-3 p-2 d-flex align-items-center justify-content-center" style="min-height: 200px;">
                      <img src="{{ asset('storage/' . $kyc->pan_image) }}" class="img-fluid rounded shadow-sm" style="max-height: 250px; object-fit: contain;" alt="PAN Card" />
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
                  <p class="mb-0 text-heading">{{ optional($kyc)->account_holder_name ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">Account Number</small>
                  <p class="mb-0 text-heading">{{ optional($kyc)->account_number ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">IFSC Code</small>
                  <p class="mb-0 text-heading">{{ optional($kyc)->ifsc_code ?? 'N/A' }}</p>
                </div>
                <div class="col-md-6">
                  <small class="text-muted text-uppercase">Bank Name</small>
                  <p class="mb-0 text-heading">{{ optional($kyc)->bank_name ?? 'N/A' }}</p>
                </div>
              </div>
            </div>
          </div>
          <!-- /Bank Details -->
        </div>
      </div>
    </div>
    <!-- /KYC Content -->
  </div>
  <!--/ User Content -->
</div>

@endsection

@include('admin.clients.modals.modal-apply-loan')
