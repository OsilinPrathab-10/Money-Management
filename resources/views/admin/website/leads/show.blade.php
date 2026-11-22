@extends('layouts/layoutMaster')
@section('title', 'Application Details — ' . $lead->application_ref)

@section('content')
<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('admin.website.enquiries.index', ['type' => 'application']) }}" class="btn btn-sm btn-outline-secondary">
      <i class="ri-arrow-left-line me-1"></i> Back to submissions
    </a>
    <span class="badge bg-label-primary fs-6 font-monospace">{{ $lead->application_ref }}</span>
  </div>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible mb-4">
      <i class="ri-checkbox-circle-line me-1"></i> {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @php
    $formData = $lead->form_data ?? [];
  @endphp

  <div class="row g-4">
    {{-- Left: Applicant and Full Application Details --}}
    <div class="col-lg-8">
      {{-- Card 1: Primary Overview --}}
      <div class="card mb-4 shadow-xs">
        <div class="card-header bg-label-primary d-flex justify-content-between align-items-center py-3">
          <h5 class="card-title mb-0 fw-bold">
            <i class="ri-user-star-line me-1"></i> {{ $lead->applicant_name }}
          </h5>
          <span class="badge bg-primary text-uppercase">{{ ucwords(str_replace('_', ' ', $lead->status)) }}</span>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3">
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Loan Product</span>
              <strong class="text-dark">{{ $lead->product?->loan_name ?? '—' }}</strong>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Requested Amount</span>
              <strong class="text-primary fs-6">₹{{ number_format($lead->requested_amount ?? 0) }}</strong>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Tenure</span>
              <strong class="text-dark">{{ $lead->requested_tenure_months }} Months</strong>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Submitted On</span>
              <span class="text-dark">{{ $lead->created_at->format('d M Y, h:i A') }}</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Card 2: KYC & Contact Details --}}
      <div class="card mb-4 shadow-xs">
        <div class="card-header py-3 border-bottom">
          <h6 class="card-title mb-0 fw-bold text-dark">
            <i class="ri-shield-keyhole-line text-primary me-1"></i> KYC & Identity Information
          </h6>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3">
            <div class="col-sm-6">
              <span class="text-muted small d-block">Mobile Number</span>
              <a href="tel:{{ $lead->phone }}" class="fw-bold text-dark text-decoration-none">
                <i class="ri-phone-line text-success me-1"></i>{{ $lead->phone }}
              </a>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Email Address</span>
              <a href="mailto:{{ $lead->email }}" class="fw-bold text-dark text-decoration-none">
                <i class="ri-mail-line text-primary me-1"></i>{{ $lead->email }}
              </a>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">PAN Card</span>
              <span class="fw-bold text-dark font-monospace">{{ $lead->pan_number ?? ($formData['pan_number'] ?? '—') }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Aadhaar Number</span>
              <span class="fw-bold text-dark font-monospace">
                @if(!empty($formData['aadhaar_number']))
                  {{ wordwrap($formData['aadhaar_number'], 4, ' ', true) }}
                @else
                  —
                @endif
              </span>
            </div>
            <div class="col-sm-4">
              <span class="text-muted small d-block">Date of Birth</span>
              <span class="fw-bold text-dark">{{ $formData['dob'] ?? '—' }}</span>
            </div>
            <div class="col-sm-4">
              <span class="text-muted small d-block">Gender</span>
              <span class="fw-bold text-dark text-capitalize">{{ $formData['gender'] ?? '—' }}</span>
            </div>
            <div class="col-sm-4">
              <span class="text-muted small d-block">IP Address</span>
              <span class="text-muted font-monospace small">{{ $lead->ip_address ?? '—' }}</span>
            </div>
            <div class="col-12">
              <span class="text-muted small d-block">Residential Address</span>
              <div class="p-2 bg-light rounded mt-1">
                {{ $formData['address'] ?? '' }}
                @if(!empty($lead->city)), {{ $lead->city }}@endif
                @if(!empty($formData['state'])), {{ $formData['state'] }}@endif
                @if(!empty($formData['pincode'])) - {{ $formData['pincode'] }}@endif
                @if(empty($formData['address']) && empty($lead->city)) — @endif
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Card 3: Bank Details for Disbursal --}}
      <div class="card mb-4 shadow-xs">
        <div class="card-header py-3 border-bottom">
          <h6 class="card-title mb-0 fw-bold text-dark">
            <i class="ri-bank-line text-success me-1"></i> Bank Account for Disbursal
          </h6>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3">
            <div class="col-sm-6">
              <span class="text-muted small d-block">Bank Name</span>
              <strong class="text-dark">{{ $formData['bank_name'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Account Holder</span>
              <strong class="text-dark">{{ $formData['bank_account_holder'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Account Number</span>
              <strong class="text-dark font-monospace">{{ $formData['bank_account_number'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-3">
              <span class="text-muted small d-block">IFSC Code</span>
              <strong class="text-dark font-monospace">{{ $formData['bank_ifsc'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-3">
              <span class="text-muted small d-block">Account Type</span>
              <span class="badge bg-label-info text-capitalize">{{ $formData['bank_account_type'] ?? 'Savings' }}</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Card 4: Nominee & Employment Details --}}
      <div class="card mb-4 shadow-xs">
        <div class="card-header py-3 border-bottom">
          <h6 class="card-title mb-0 fw-bold text-dark">
            <i class="ri-user-heart-line text-warning me-1"></i> Nominee & Employment Information
          </h6>
        </div>
        <div class="card-body pt-3">
          <div class="row g-3">
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Nominee Name</span>
              <strong class="text-dark">{{ $formData['nominee_name'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Relationship</span>
              <strong class="text-dark">{{ $formData['nominee_relation'] ?? '—' }}</strong>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Nominee Phone</span>
              <span class="text-dark">{{ $formData['nominee_phone'] ?? '—' }}</span>
            </div>
            <div class="col-sm-6 col-md-3">
              <span class="text-muted small d-block">Nominee Age</span>
              <span class="text-dark">{{ !empty($formData['nominee_age']) ? $formData['nominee_age'] . ' Yrs' : '—' }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Employment Type</span>
              <span class="badge bg-label-secondary text-capitalize">{{ str_replace('_', ' ', $lead->employment_type ?? 'Salaried') }}</span>
            </div>
            <div class="col-sm-6">
              <span class="text-muted small d-block">Monthly Income</span>
              <strong class="text-success">₹{{ number_format($lead->monthly_income ?? 0) }}/mo</strong>
            </div>
            @if($lead->message)
              <div class="col-12">
                <span class="text-muted small d-block">Purpose / Note</span>
                <p class="mb-0 text-dark small bg-light p-2 rounded">{{ $lead->message }}</p>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>

    {{-- Right: Actions, Status & Calling --}}
    <div class="col-lg-4">
      <div class="card shadow-xs mb-4">
        <div class="card-header py-3 border-bottom">
          <h6 class="card-title mb-0 fw-bold">Admin Actions</h6>
        </div>
        <div class="card-body pt-3">
          {{-- Direct Phone Dial --}}
          <a href="tel:{{ $lead->phone }}" class="btn btn-success w-100 mb-2 py-2">
            <i class="ri-phone-fill me-1"></i> Call Applicant ({{ $lead->phone }})
          </a>
          <a href="https://wa.me/91{{ $lead->phone }}?text=Hello%20{{ urlencode($lead->applicant_name) }},%20this%20is%20regarding%20your%20loan%20application%20{{ $lead->application_ref }}%20with%20Star%20Finance."
            target="_blank" class="btn btn-outline-success w-100 mb-3 py-2">
            <i class="ri-whatsapp-line me-1"></i> WhatsApp Applicant
          </a>

          @if($lead->client)
            <div class="alert alert-info small py-2 mb-3">
              Linked client:
              <a href="{{ route('client-view-account', $lead->client) }}" class="fw-bold">{{ $lead->client->client_name }}</a>
              @if($lead->client->customer_id)
                <span class="text-muted">({{ $lead->client->customer_id }})</span>
              @endif
            </div>
          @else
            <button type="button" class="btn btn-warning w-100 py-2 mb-3" data-bs-toggle="modal" data-bs-target="#convertClientModal">
              <i class="ri-user-add-line me-1"></i> Convert to client
            </button>
          @endif

          @if($lead->called_at)
            <div class="p-2 bg-label-success rounded text-center small mb-3">
              <i class="ri-check-line me-1"></i> Called by {{ $lead->assignee?->name ?? 'Staff' }} on {{ $lead->called_at->format('d M Y, h:i A') }}
            </div>
          @endif

          <form method="POST" action="{{ route('admin.website.leads.update', $lead->id) }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
              <label class="form-label fw-bold small">APPLICATION STATUS</label>
              <select name="status" class="form-select">
                @foreach(['new','under_review','documents_pending','verification','approved','rejected','disbursed','closed'] as $s)
                  <option value="{{ $s }}" @selected($lead->status === $s)>
                    {{ ucwords(str_replace('_', ' ', $s)) }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold small">INTERNAL ADMIN NOTES</label>
              <textarea name="admin_notes" class="form-control" rows="4" placeholder="Enter notes from call or verification…">{{ $lead->admin_notes }}</textarea>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="mark_called" value="1" id="mc" @checked($lead->called_at)>
              <label class="form-check-label small" for="mc">Mark applicant as called now</label>
            </div>

            <button class="btn btn-primary w-100 py-2" type="submit">
              <i class="ri-save-line me-1"></i> Save Changes
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

@unless($lead->client)
{{-- Convert to Client Profile Modal Popup --}}
<div class="modal fade" id="convertClientModal" tabindex="-1" aria-labelledby="convertClientModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <div class="modal-header bg-label-warning py-3 px-4">
        <div class="d-flex align-items-center gap-2">
          <div class="rounded-circle bg-warning bg-opacity-25 p-2 text-warning d-flex align-items-center justify-content-center" style="width:38px;height:38px">
            <i class="ri-user-add-line fs-5"></i>
          </div>
          <div>
            <h5 class="modal-title fw-bold text-dark mb-0" id="convertClientModalLabel">Create or Link Client Profile</h5>
            <small class="text-muted">Convert applicant to active client account</small>
          </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted mb-3">
          Create or link a client profile for this applicant?
        </p>

        {{-- Applicant Details Card --}}
        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
          <div class="d-flex align-items-center gap-3 mb-2">
            <div class="avatar avatar-md bg-white text-primary rounded-circle shadow-xs d-flex align-items-center justify-content-center fw-bold fs-5" style="width:42px;height:42px">
              {{ strtoupper(substr($lead->applicant_name, 0, 1)) }}
            </div>
            <div>
              <h6 class="mb-0 fw-bold text-dark">{{ $lead->applicant_name }}</h6>
              <span class="text-muted small font-monospace">{{ $lead->application_ref }}</span>
            </div>
          </div>
          <hr class="my-2 border-secondary border-opacity-10">
          <div class="row g-2 small">
            <div class="col-6">
              <span class="text-muted d-block">Phone:</span>
              <strong class="text-dark">{{ $lead->phone }}</strong>
            </div>
            <div class="col-6">
              <span class="text-muted d-block">Email:</span>
              <strong class="text-dark text-break">{{ $lead->email ?: 'N/A' }}</strong>
            </div>
            @if($lead->product)
              <div class="col-12 mt-1">
                <span class="text-muted d-block">Applied Loan:</span>
                <span class="badge bg-label-primary">{{ $lead->product->loan_name }}</span>
                @if($lead->requested_amount)
                  <strong class="text-dark ms-1">₹{{ number_format($lead->requested_amount) }}</strong>
                @endif
              </div>
            @endif
          </div>
        </div>

        <div class="alert alert-primary d-flex align-items-start gap-2 py-2 px-3 mb-0 small">
          <i class="ri-information-line fs-5 text-primary flex-shrink-0 mt-1"></i>
          <div>
            <strong>Smart Client Matching:</strong>
            If an existing client matches this phone or email, the application will automatically link to that profile. Otherwise, a new client account will be created with portal credentials (mobile number as initial password).
          </div>
        </div>
      </div>
      <div class="modal-footer bg-light px-4 py-3 border-0">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
          <i class="ri-close-line me-1"></i> Cancel
        </button>
        <form method="POST" action="{{ route('admin.website.leads.convert-client', $lead->id) }}" class="m-0">
          @csrf
          <button type="submit" class="btn btn-warning px-4">
            <i class="ri-user-add-line me-1"></i> Yes, Create / Link Client
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
@endunless
@endsection
