@extends('layouts/layoutMaster')

@section('title', 'Client View - Account')

@section('vendor-style')
@vite([
'resources/assets/vendor/libs/datatables-bs5/datatables.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.scss',
'resources/assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.scss',
'resources/assets/vendor/libs/animate-css/animate.scss',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.scss',
'resources/assets/vendor/libs/select2/select2.scss',
'resources/assets/vendor/libs/flatpickr/flatpickr.scss',
'resources/assets/vendor/libs/@form-validation/form-validation.scss'
])
@endsection

@section('vendor-script')
@vite([
'resources/assets/vendor/libs/moment/moment.js',
'resources/assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js',
'resources/assets/vendor/libs/sweetalert2/sweetalert2.js',
'resources/assets/vendor/libs/cleave-zen/cleave-zen.js',
'resources/assets/vendor/libs/select2/select2.js',
'resources/assets/vendor/libs/flatpickr/flatpickr.js',
'resources/assets/vendor/libs/@form-validation/popular.js',
'resources/assets/vendor/libs/@form-validation/bootstrap5.js',
'resources/assets/vendor/libs/@form-validation/auto-focus.js'
])
@endsection

@section('page-script')
@vite([
'resources/assets/js/modal-edit-user.js',
'resources/assets/js/app-user-view.js',
'resources/assets/js/client-view-account.js',
'resources/assets/custom-js/app-kyc-verification.js',
'resources/assets/custom-js/loan-applications.js'
])
@endsection

@section('content')

<div class="d-flex flex-column flex-md-row flex-wrap align-items-start align-items-md-center justify-content-between gap-3 mb-6">
  <div class="nav-align-top">
    <ul class="nav nav-pills flex-column flex-md-row row-gap-2">
      <li class="nav-item"><a class="nav-link active" href="javascript:void(0);"><i class="icon-base ri ri-user-3-line me-1_5"></i>Account</a></li>
      @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      <li class="nav-item"><a class="nav-link" href="{{ url('/clients/view/kyc/'.$client->id) }}"><i class="icon-base ri ri-shield-check-line me-1_5"></i>KYC</a></li>
      @endif
      <li class="nav-item"><a class="nav-link" href="{{ url('/client/view/loans/'.$client->id) }}"><i class="icon-base ri ri-file-list-3-line me-1_5"></i>Loans</a></li>
    </ul>
  </div>
  <div class="d-flex gap-2 w-100 w-sm-auto ms-md-auto">
    <!-- <a href="{{ route('client-management-add') }}" class="btn btn-sm btn-outline-primary flex-grow-1 w-sm-auto d-inline-flex align-items-center justify-content-center">
      <i class="icon-base ri ri-user-add-line me-1"></i>
      <span>Add Client</span>
    </a> -->
    <a href="{{ route('client-management') }}" class="btn btn-sm btn-outline-secondary     w-sm-auto d-inline-flex align-items-center justify-content-center">
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
      @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
      @if($client->status !== 'blacklist')
      <div class="position-absolute top-0 end-0 m-3">
        <button type="button" class="btn btn-sm btn-danger py-1 px-2" data-bs-toggle="modal" data-bs-target="#blacklistModal">
          <i class="icon-base ri ri-forbid-line me-1"></i></button>
      </div>
      @endif
      @endif
      <div class="card-body pt-12">
        <div class="user-avatar-section">
          <div class=" d-flex align-items-center flex-column">
            @if($client->kycDetail && $client->kycDetail->selfie_image && substr($client->kycDetail->selfie_image, 0, 5) === 'data:')
              {{-- Base64 encoded image --}}
              <img class="img-fluid rounded mb-4" src="{{ $client->kycDetail->selfie_image }}" height="120" width="120"
                alt="User avatar" style="object-fit: cover;" />
            @elseif($client->kycDetail && $client->kycDetail->selfie_image)
              {{-- File path image --}}
              <img class="img-fluid rounded mb-4" src="{{ asset('storage/' . $client->kycDetail->selfie_image) }}" height="120" width="120"
                alt="User avatar" style="object-fit: cover;" />
            @else
              {{-- Default avatar --}}
              <img class="img-fluid rounded mb-4" src="{{asset('assets/img/avatars/1.png')}}" height="120" width="120"
                alt="User avatar" />
            @endif
            <div class="user-info text-center">
              <h5 id="sidebarClientName">{{ $client->client_name ?? 'Client Name' }}</h5>
              {{-- DEBUG: Client Status = {{ $client->status }} --}}
              @php
                // Map all 5 database status values to only 3 badge displays: Active, Inactive, Blacklisted
                $statusBadgeMap = [
                  'active' => ['label' => 'Active', 'badge' => 'success'],
                  'verified' => ['label' => 'Active', 'badge' => 'success'], // Treat verified as active
                  'inactive' => ['label' => 'Inactive', 'badge' => 'danger'],
                  'unverified' => ['label' => 'Inactive', 'badge' => 'danger'], // Treat unverified as inactive
                  'blacklist' => ['label' => 'Blacklisted', 'badge' => 'dark'],
                ];
                $statusInfo = $statusBadgeMap[$client->status] ?? ['label' => 'Inactive', 'badge' => 'danger'];
              @endphp
              <span id="sidebarClientStatusBadge" class="badge bg-label-{{ $statusInfo['badge'] }} rounded-pill">
                {{ $statusInfo['label'] }}
              </span>
            </div>
          </div>
        </div>
        <div class="row g-4 my-6">
          <div class="col-6">
            <div class="d-flex align-items-center gap-4">
              <div class="avatar">
                <div class="avatar-initial bg-label-info rounded-3">
                  <i class="icon-base ri ri-file-text-line icon-24px"></i>
                </div>
              </div>
              <div>
                <h5 class="mb-0">{{ $stats['applications'] ?? 0 }}</h5>
                <span>Applications</span>
              </div>
            </div>
          </div>
          <div class="col-6">
            <div class="d-flex align-items-center gap-4">
              <div class="avatar">
                <div class="avatar-initial bg-label-primary rounded-3">
                  <i class="icon-base ri ri-money-dollar-circle-line icon-24px"></i>
                </div>
              </div>
              <div>
                <h5 class="mb-0">{{ $stats['loans'] ?? 0 }}</h5>
                <span>Total Loans</span>
              </div>
            </div>
          </div>
        </div>
        <div class="d-flex flex-column gap-4">
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Personal Information</small>
            <div class="row g-4">
            <div class="col-12">
              <small class="text-muted text-uppercase">Email</small>
              <p class="mb-0 text-heading" id="sidebarClientEmail" style="word-break: break-all; overflow-wrap: break-word;" title="{{ $client->client_email ?? ($client->user->email ?? 'N/A') }}">{{ $client->client_email ?? ($client->user->email ?? 'N/A') }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Mobile</small>
              <p class="mb-0 text-heading" id="sidebarClientPhone">{{ $client->client_phone ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Alternate Phone</small>
              <p class="mb-0 text-heading" id="sidebarClientAlternatePhone">{{ $client->alternate_phone ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Aadhaar Number</small>
              <p class="mb-0 text-heading" id="sidebarClientAadhaar">{{ $client->aadhaar_number ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Date of Birth</small>
              <p class="mb-0 text-heading">{{ $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('d-m-Y') : 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Gender</small>
              <p class="mb-0 text-heading">{{ ucfirst($client->gender ?? 'N/A') }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Marital Status</small>
              <p class="mb-0 text-heading">{{ ucfirst($client->marital_status ?? 'N/A') }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Zone</small>
              <p class="mb-0 text-heading">{{ $client->location->name ?? 'N/A' }}</p>
            </div>
          
            </div>
          </div>

          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Address Information</small>
            <div class="row g-4">
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Address</small>
              <p class="mb-0 text-heading">{{ $client->address ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Zone</small>
              <p class="mb-0 text-heading text-primary fw-bold">{{ $client->location->name ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">City</small>
              <p class="mb-0 text-heading">{{ $client->city ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">State</small>
              <p class="mb-0 text-heading">{{ $client->state ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Pincode</small>
              <p class="mb-0 text-heading">{{ $client->pincode ?? 'N/A' }}</p>
            </div>
            </div>
          </div> 
 
          @if($client->kycDetail || $client->employeeInformation)
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Additional Information</small>
            <div class="row g-4">
            @if($client->kycDetail)
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">PAN Number</small>
              <p class="mb-0 text-heading">{{ $client->kycDetail->pan_number ?? 'N/A' }}</p>
            </div>
            @endif
            @if($client->employeeInformation)
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Employment Type</small>
              <p class="mb-0 text-heading">{{ ucfirst(str_replace('_', ' ', $client->employeeInformation->employment_type ?? 'N/A')) }}</p>
            </div>
            @if($client->employeeInformation->employment_type == 'salaried')
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Company</small>
              <p class="mb-0 text-heading">{{ $client->employeeInformation->company_name ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Monthly Salary</small>
              <p class="mb-0 text-heading">₹{{ number_format($client->employeeInformation->monthly_salary ?? 0, 2) }}</p>
            </div>
            @elseif($client->employeeInformation->employment_type == 'business' || $client->employeeInformation->employment_type == 'self_employed')
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Business Name</small>
              <p class="mb-0 text-heading">{{ $client->employeeInformation->business_name ?? 'N/A' }}</p>
            </div>
            <div class="col-sm-6">
              <small class="text-muted text-uppercase">Monthly Income</small>
              <p class="mb-0 text-heading">₹{{ number_format($client->employeeInformation->monthly_turnover ?? 0, 2) }}</p>
            </div>
            @endif
            @endif
            </div>
          </div>
          @endif

          @if($client->guarantors && $client->guarantors->count() > 0)
          <div class="border rounded-3 p-4">
            <small class="text-primary text-uppercase fw-semibold d-block mb-3">Guarantor & Referral</small>
            <div class="row g-4">
              @php
                $guarantor = $client->guarantors->where('type', 'guarantor')->first();
                $referral = $client->guarantors->where('type', 'referral')->first();
              @endphp
              @if($guarantor)
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Guarantor</small>
                <p class="mb-0 text-heading">{{ $guarantor->name }}</p>
                <p class="mb-0 text-muted small">{{ $guarantor->phone }}</p>
                <small class="badge bg-label-info mt-1">{{ ucfirst($guarantor->relationship) }}</small>
              </div>
              @endif
              @if($referral)
              <div class="col-sm-6">
                <small class="text-muted text-uppercase">Referral</small>
                <p class="mb-0 text-heading">{{ $referral->name }}</p>
                <p class="mb-0 text-muted small">{{ $referral->phone }}</p>
                <small class="badge bg-label-secondary mt-1">{{ ucfirst($referral->relationship) }}</small>
              </div>
              @endif
            </div>
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
    <!-- Account Details Card -->
    <div class="card mb-6">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h5 class="mb-0">Account Details</h5>
        <div class="d-flex align-items-center gap-2">
          @if(auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Staff'))
            @if($client->status === 'active' || $client->status === 'verified')
            <!-- <button type="button" class="btn btn-sm btn-primary py-1 px-3" data-bs-toggle="modal" data-bs-target="#modalApplyLoan">
              <i class="icon-base ri ri-add-line me-1"></i> Apply for Loan
            </button> -->
            @endif
            <button type="button" class="btn btn-sm btn-icon btn-outline-primary btn-pill " id="enableAccountEditBtn" title="Edit Account Details" aria-label="Edit Account Details">
              <i class="icon-base ri ri-pencil-fill"></i>
            </button>
          @endif
          @if(auth()->user()->hasRole('Agent'))
            @if(($client->status === 'active' || $client->status === 'verified') && optional($client->kycDetail)->status === 'verified')
            <button type="button" class="btn btn-sm btn-primary py-1 px-3" data-bs-toggle="modal" data-bs-target="#modalApplyLoan">
              <i class="icon-base ri ri-hand-coin-line me-1"></i> Apply for Loan
            </button>
            @else
            <span class="badge bg-label-warning py-2 px-3">KYC not yet approved — cannot apply for loan</span>
            @endif
          @endif
          <div class="alert-container" data-success="{{ session('success') }}" data-error="{{ session('error') }}" data-warning="" data-info=""></div>
        </div>
      </div>
      <div class="card-body">
        <form id="formAccountSettings" method="POST" action="{{ route('client-view-account.update', $client->id) }}">
          @csrf
          <div class="row g-5">
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input class="form-control" type="text" id="client_name" name="client_name" value="{{ $client->client_name }}" readonly data-editable="true" required />
                <label for="client_name">Full Name <span class="text-danger">*</span></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input class="form-control" type="text" name="client_email" id="client_email" value="{{ $client->client_email ?? ($client->user->email ?? '') }}" readonly data-editable="true" />
                <label for="client_email">Email</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="client_phone" name="client_phone" value="{{ $client->client_phone }}" readonly data-editable="true" required />
                <label for="client_phone">Phone Number <span class="text-danger">*</span></label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input type="text" class="form-control" id="alternate_phone" name="alternate_phone" value="{{ $client->alternate_phone }}" readonly data-editable="true" />
                <label for="alternate_phone">Alternate Phone</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <input class="form-control flatpickr-dob" type="text" id="date_of_birth" name="date_of_birth" value="{{ $client->date_of_birth ? \Carbon\Carbon::parse($client->date_of_birth)->format('d-m-Y') : '' }}" disabled data-editable="true" placeholder="DD-MM-YYYY" />
                <label for="date_of_birth">Date of Birth</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <select id="gender" name="gender" class="form-select" disabled data-editable="true">
                  <option value="">Select Gender</option>
                  <option value="male" {{ $client->gender == 'male' ? 'selected' : '' }}>Male</option>
                  <option value="female" {{ $client->gender == 'female' ? 'selected' : '' }}>Female</option>
                  <option value="other" {{ $client->gender == 'other' ? 'selected' : '' }}>Other</option>
                </select>
                <label for="gender">Gender</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <select id="marital_status" name="marital_status" class="form-select" disabled data-editable="true">
                  <option value="">Select Status</option>
                  <option value="single" {{ $client->marital_status == 'single' ? 'selected' : '' }}>Single</option>
                  <option value="married" {{ $client->marital_status == 'married' ? 'selected' : '' }}>Married</option>
                  <option value="divorced" {{ $client->marital_status == 'divorced' ? 'selected' : '' }}>Divorced</option>
                  <option value="widowed" {{ $client->marital_status == 'widowed' ? 'selected' : '' }}>Widowed</option>
                </select>
                <label for="marital_status">Marital Status</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating form-floating-outline">
                <select id="status" name="status" class="form-select" disabled data-editable="true">
                  <option value="active" {{ $client->status == 'active' ? 'selected' : '' }}>Active</option>
                  <option value="inactive" {{ $client->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
                  <option value="blacklist" {{ $client->status == 'blacklist' ? 'selected' : '' }}>Blacklist</option>
                </select>
                <label for="status">Status</label>
              </div>
            </div>
            <div class="col-md-6">
    <div class="form-floating form-floating-outline">
        
        <!-- Hidden input to submit value -->
        <input type="hidden" name="collection_day" value="{{ $client->collection_day }}">

        <!-- Read-only display -->
        <select id="collection_day" class="form-select" disabled>
            <option value="">Select Collection Day</option>

            @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                <option value="{{ $day }}" {{ $client->collection_day == $day ? 'selected' : '' }}>
                    {{ $day }}
                </option>
            @endforeach
        </select>

        <label for="collection_day">Collection Day (Weekly)</label>
    </div>
</div>
            <div class="col-12">
              <div class="form-floating form-floating-outline">
                <textarea class="form-control h-px-100" id="address" name="address" readonly data-editable="true">{{ $client->address }}</textarea>
                <label for="address">Address</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating form-floating-outline">
                <select id="location_id" name="location_id" class="form-select" disabled data-editable="true">
                  <option value="">Select Zone</option>
                  @foreach($locations as $loc)
                    <option value="{{ $loc->id }}" {{ $client->location_id == $loc->id ? 'selected' : '' }}
                      data-city="{{ $loc->district->name ?? $loc->city }}" 
                      data-state="{{ $loc->state->name ?? $loc->state }}" 
                      data-pincode="{{ $loc->pincode }}">
                      {{ $loc->name }}
                    </option>
                  @endforeach
                </select>
                <label for="location_id">Zone/Area</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating form-floating-outline">
                <input class="form-control" type="text" id="city" name="city" value="{{ $client->city }}" readonly data-editable="true" />
                <label for="city">City</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating form-floating-outline">
                <input class="form-control" type="text" id="state" name="state" value="{{ $client->state }}" oninput="this.value = this.value.replace(/[^a-zA-Z\s.]/g, '')" readonly data-editable="true" />
                <label for="state">State</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating form-floating-outline">
                <input class="form-control" type="text" id="pincode" name="pincode" value="{{ $client->pincode }}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="6" readonly data-editable="true" />
                <label for="pincode">Pincode</label>
              </div>
            </div>
          </div>
          <div class="mt-6 d-flex flex-wrap align-items-center gap-3 d-none" id="accountFormActions">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <button type="reset" class="btn btn-outline-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    <!--/ Account Details Card -->

    @if($client->nominee)
    <!-- Nominee Details Card -->
    <div class="card mb-6">
      <div class="card-header">
        <h5 class="mb-0">Nominee Details</h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          @if($client->nominee->nominee1_name)
          <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-light bg-opacity-10">
              <h6 class="mb-3 d-flex align-items-center"><i class="ri-user-heart-line me-2 text-primary"></i> Primary Nominee</h6>
              <ul class="list-unstyled mb-0">
                <li class="mb-2">
                  <span class="text-heading me-2 fw-medium">Name:</span>
                  <span>{{ $client->nominee->nominee1_name }}</span>
                </li>
                <li class="mb-2">
                  <span class="text-heading me-2 fw-medium">Relationship:</span>
                  <span>{{ ucfirst($client->nominee->nominee1_relationship ?? 'N/A') }}</span>
                </li>
                <li>
                  <span class="text-heading me-2 fw-medium">Mobile:</span>
                  <span><i class="ri-smartphone-line text-primary me-1"></i>{{ $client->nominee->nominee1_mobile ?? 'N/A' }}</span>
                </li>
              </ul>
            </div>
          </div>
          @endif

          @if($client->nominee->nominee2_name)
          <div class="col-md-6">
            <div class="p-3 border rounded-3 bg-light bg-opacity-10">
              <h6 class="mb-3 d-flex align-items-center"><i class="ri-user-heart-line me-2 text-secondary"></i> Secondary Nominee</h6>
              <ul class="list-unstyled mb-0">
                <li class="mb-2">
                  <span class="text-heading me-2 fw-medium">Name:</span>
                  <span>{{ $client->nominee->nominee2_name }}</span>
                </li>
                <li class="mb-2">
                  <span class="text-heading me-2 fw-medium">Relationship:</span>
                  <span>{{ ucfirst($client->nominee->nominee2_relationship ?? 'N/A') }}</span>
                </li>
                <li>
                  <span class="text-heading me-2 fw-medium">Mobile:</span>
                  <span><i class="ri-smartphone-line text-primary me-1"></i>{{ $client->nominee->nominee2_mobile ?? 'N/A' }}</span>
                </li>
              </ul>
            </div>
          </div>
          @endif
        </div>
      </div>
    </div>
    <!--/ Nominee Details Card -->
    @endif

    @if($client->kycDetail)
    <!-- Bank Details Card -->
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Bank Details</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <span class="text-heading me-2">Account Holder:</span>
            <span>{{ $client->kycDetail->account_holder_name ?? 'N/A' }}</span>
          </li>
          <li class="mb-2">
            <span class="text-heading me-2">Account Number:</span>
            <span>{{ $client->kycDetail->account_number ?? 'N/A' }}</span>
          </li>
          <li class="mb-2">
            <span class="text-heading me-2">IFSC Code:</span>
            <span>{{ $client->kycDetail->ifsc_code ?? 'N/A' }}</span>
          </li>
          <li class="mb-2">
            <span class="text-heading me-2">Bank Name:</span>
            <span>{{ $client->kycDetail->bank_name ?? 'N/A' }}</span>
          </li>
          <li class="mb-2">
            <span class="text-heading me-2">Account Type:</span>
            <span>{{ ucfirst($client->kycDetail->account_type ?? 'N/A') }}</span>
          </li>
        </ul>
      </div>
    </div>
    <!--/ Bank Details Card -->
    @endif

    @if($client->employeeInformation)
    <!-- Employment Details Card -->
    <div class="card mb-6">
      <div class="card-header">
        <h5 class="mb-0">Employment Details</h5>
      </div>
      <div class="card-body">
        <div class="row g-4">
          <div class="col-md-6">
            <ul class="list-unstyled mb-0">
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Employment Type:</span>
                <span class="badge bg-label-primary">{{ ucfirst(str_replace('_', ' ', $client->employeeInformation->employment_type ?? 'N/A')) }}</span>
              </li>
              @if($client->employeeInformation->employment_type == 'salaried')
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Company Name:</span>
                <span>{{ $client->employeeInformation->company_name ?? 'N/A' }}</span>
              </li>
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Monthly Salary:</span>
                <span class="text-success fw-bold">₹{{ number_format($client->employeeInformation->monthly_salary ?? 0, 2) }}</span>
              </li>
              @else
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Business Name:</span>
                <span>{{ $client->employeeInformation->business_name ?? 'N/A' }}</span>
              </li>
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Monthly Turnover:</span>
                <span class="text-success fw-bold">₹{{ number_format($client->employeeInformation->monthly_turnover ?? 0, 2) }}</span>
              </li>
              @endif
            </ul>
          </div>
          <div class="col-md-6">
            <ul class="list-unstyled mb-0">
              @if($client->employeeInformation->employment_type == 'salaried')
              @else
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Business Type:</span>
                <span>{{ ucfirst($client->employeeInformation->business_type ?? 'N/A') }}</span>
              </li>
              <li class="mb-2">
                <span class="text-heading me-2 fw-medium">Years in Business:</span>
                <span>{{ $client->employeeInformation->years_in_business ?? 'N/A' }}</span>
              </li>
              @endif
            </ul>
          </div>
        </div>
      </div>
    </div>
    <!--/ Employment Details Card -->
    @endif
 
  </div>
  <!--/ User Content -->
</div>

<!-- Blacklist Confirmation Modal -->
<div class="modal fade" id="blacklistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <form id="blacklistForm" action="{{ route('client-blacklist', $client->id) }}" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Blacklist</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center mb-4">
            <i class="icon-base ri ri-error-warning-line text-danger" style="font-size: 48px;"></i>
          </div>
          <h5 class="text-center mb-2">Are you sure?</h5>
          <p class="text-center mb-4">Do you really want to blacklist this client? Client status will be updated to <strong>Blacklist</strong>.</p>

          <div class="mb-3">
            <label for="blacklist_reason" class="form-label fw-medium">Reason for Blacklist <span class="text-danger">*</span></label>
            <textarea name="reason" id="blacklist_reason" class="form-control" rows="3" placeholder="Enter reason for blacklisting" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
            <i class="icon-base ri ri-close-line me-1"></i> Cancel
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="icon-base ri ri-forbid-line me-1"></i> Blacklist
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

@include('admin.clients.modals.modal-apply-loan')
@endsection
