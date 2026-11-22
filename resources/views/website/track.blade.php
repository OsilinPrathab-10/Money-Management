@extends('website.layouts.master')

@section('title', 'Track Application Status — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Track your loan application status online in real time with ' . ($brand['name'] ?? 'Star Finance') . '.')

@push('styles')
<style>
.track-card {
  background: #fff;
  border-radius: 1.25rem;
  border: 1px solid #e8edf5;
  box-shadow: 0 12px 36px rgba(11,31,58,.08);
  padding: 2.5rem;
}
.status-timeline {
  display: flex;
  justify-content: space-between;
  position: relative;
  margin: 2rem 0;
}
.status-timeline::before {
  content: '';
  position: absolute;
  top: 18px;
  left: 30px;
  right: 30px;
  height: 3px;
  background: #e2e8f0;
  z-index: 0;
}
.status-step {
  position: relative;
  z-index: 1;
  text-align: center;
  flex: 1;
}
.status-icon {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: #f1f5f9;
  color: #94a3b8;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto .5rem;
  font-size: 1rem;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.status-step.completed .status-icon {
  background: #10b981;
  color: #fff;
}
.status-step.active .status-icon {
  background: #1e4fd6;
  color: #fff;
  box-shadow: 0 0 0 4px rgba(30,79,214,.2);
}
.status-step.rejected .status-icon {
  background: #ef4444;
  color: #fff;
}
</style>
@endpush

@section('content')
<section class="sf-page-hero">
  <div class="container text-center">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb justify-content-center mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">Track</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.35rem)">Track application</h1>
    <p class="opacity-90 mb-0 mx-auto" style="max-width:520px">Enter your reference number and mobile to see live status.</p>
  </div>
</section>

<section class="container py-5" style="max-width: 680px;">
  {{-- Search Box Card --}}
  <div class="track-card mb-4">
    <h4 class="fw-bold mb-3 text-dark d-flex align-items-center gap-2">
      <i class="ri-search-eye-line text-primary"></i> Application Lookup
    </h4>
    <p class="text-muted small mb-4">Enter the Application Reference ID received via SMS/Email after submission.</p>

    <form method="POST" action="{{ route('website.application.track') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label fw-bold text-secondary small">APPLICATION REFERENCE NUMBER <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted"><i class="ri-hashtag"></i></span>
          <input name="application_ref" class="form-control border-start-0 ps-0 text-uppercase fw-bold" placeholder="e.g. SF261118A1B2C3"
            value="{{ old('application_ref', $search['application_ref'] ?? '') }}" required style="letter-spacing:.05em">
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label fw-bold text-secondary small">REGISTERED MOBILE NUMBER <span class="text-danger">*</span></label>
        <div class="input-group">
          <span class="input-group-text bg-light border-end-0 text-muted"><i class="ri-smartphone-line"></i></span>
          <input name="phone" type="tel" maxlength="10" pattern="[6-9][0-9]{9}" class="form-control border-start-0 ps-0"
            placeholder="10-digit mobile number" value="{{ old('phone', $search['phone'] ?? '') }}" required>
        </div>
      </div>

      <button class="btn btn-primary btn-lg fw-bold w-100 rounded-pill shadow-sm" type="submit">
        <i class="ri-search-line me-1"></i> Check Application Status
      </button>
    </form>
  </div>

  {{-- Result Display --}}
  @isset($lead)
    @if($lead)
      @php
        $st = strtolower($lead->status ?? 'new');
        $isApproved = in_array($st, ['approved', 'disbursed', 'sanctioned']);
        $isRejected = in_array($st, ['rejected', 'declined']);
        $isReview = in_array($st, ['under_review', 'reviewing', 'in_progress', 'documents_pending']);
      @endphp
      <div class="track-card border-top border-4 border-primary">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <span class="text-muted small">Application Reference:</span>
            <h5 class="fw-bold text-dark font-monospace mb-0">{{ $lead->application_ref }}</h5>
          </div>
          <div>
            @if($isApproved)
              <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill"><i class="ri-checkbox-circle-fill me-1"></i> Approved</span>
            @elseif($isRejected)
              <span class="badge bg-danger-subtle text-danger fs-6 px-3 py-2 rounded-pill"><i class="ri-close-circle-fill me-1"></i> Rejected</span>
            @elseif($isReview)
              <span class="badge bg-warning-subtle text-warning-emphasis fs-6 px-3 py-2 rounded-pill"><i class="ri-time-fill me-1"></i> Under Review</span>
            @else
              <span class="badge bg-primary-subtle text-primary fs-6 px-3 py-2 rounded-pill"><i class="ri-file-text-fill me-1"></i> Application Received</span>
            @endif
          </div>
        </div>

        {{-- Status Timeline --}}
        <div class="status-timeline">
          <div class="status-step completed">
            <div class="status-icon"><i class="ri-check-line"></i></div>
            <div class="small fw-bold">Submitted</div>
            <div class="text-muted" style="font-size:.68rem">{{ $lead->created_at->format('d M') }}</div>
          </div>
          <div class="status-step {{ ($isReview || $isApproved) ? 'completed' : 'active' }}">
            <div class="status-icon"><i class="{{ ($isReview || $isApproved) ? 'ri-check-line' : 'ri-search-eye-line' }}"></i></div>
            <div class="small fw-bold">Verification</div>
            <div class="text-muted" style="font-size:.68rem">KYC & Credit</div>
          </div>
          <div class="status-step {{ $isApproved ? 'completed' : ($isRejected ? 'rejected' : '') }}">
            <div class="status-icon"><i class="{{ $isApproved ? 'ri-check-line' : ($isRejected ? 'ri-close-line' : 'ri-award-line') }}"></i></div>
            <div class="small fw-bold">{{ $isRejected ? 'Decision' : 'Approval' }}</div>
            <div class="text-muted" style="font-size:.68rem">{{ $isApproved ? 'Approved' : ($isRejected ? 'Declined' : 'Pending') }}</div>
          </div>
          <div class="status-step {{ $st === 'disbursed' ? 'completed' : '' }}">
            <div class="status-icon"><i class="ri-bank-card-2-line"></i></div>
            <div class="small fw-bold">Disbursal</div>
            <div class="text-muted" style="font-size:.68rem">To Bank A/C</div>
          </div>
        </div>

        {{-- Application Details Table --}}
        <div class="p-3 bg-light rounded-3 small">
          <div class="row g-2">
            <div class="col-sm-6">
              <span class="text-muted">Applicant Name:</span>
              <div class="fw-bold text-dark">{{ $lead->applicant_name }}</div>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Loan Product:</span>
              <div class="fw-bold text-primary">{{ $lead->product?->loan_name ?? '—' }}</div>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Requested Amount:</span>
              <div class="fw-bold text-dark">₹{{ number_format($lead->requested_amount) }}</div>
            </div>
            <div class="col-sm-6">
              <span class="text-muted">Requested Tenure:</span>
              <div class="fw-bold text-dark">{{ $lead->requested_tenure_months }} Months</div>
            </div>
          </div>
        </div>

        <div class="text-center mt-4">
          <p class="text-muted small mb-2">Need to update documents or have an urgent query?</p>
          <a href="{{ route('website.contact') }}" class="btn btn-sm btn-outline-primary rounded-pill px-4">
            <i class="ri-customer-service-2-line me-1"></i> Contact Support Team
          </a>
        </div>
      </div>
    @else
      <div class="alert alert-warning rounded-4 p-4 text-center shadow-sm">
        <i class="ri-search-line fs-1 text-warning d-block mb-2"></i>
        <h5 class="fw-bold text-dark">No Application Found</h5>
        <p class="text-muted small mb-3">We couldn't find an application matching Reference ID <strong>{{ $search['application_ref'] ?? '' }}</strong> and mobile <strong>{{ $search['phone'] ?? '' }}</strong>. Please verify the credentials or contact support.</p>
        <a href="{{ route('website.contact') }}" class="btn btn-sm btn-warning fw-bold rounded-pill px-4 text-dark">Contact Support</a>
      </div>
    @endif
  @endisset
</section>
@endsection
