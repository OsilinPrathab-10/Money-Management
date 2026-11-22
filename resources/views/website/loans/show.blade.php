@extends('website.layouts.master')

@section('title', ($product->seo_title ?? $product->loan_name) . ' — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', $product->seo_description ?? ($product->short_description ?? 'Flexible loan with transparent terms and digital verification.'))

@push('styles')
<style>
/* ── Detail Page Hero (Bottom-Left Reference Style) ── */
.loan-detail-hero {
  background: linear-gradient(135deg, #eef5ff 0%, #dbeafe 50%, #e0ecfd 100%);
  padding: clamp(2.5rem, 5vw, 4.25rem) 0;
  position: relative;
  overflow: hidden;
}
.loan-detail-title {
  font-size: clamp(2.2rem, 5vw, 3.4rem);
  font-weight: 800;
  color: #0e2b5c;
  letter-spacing: -.03em;
  margin-bottom: .45rem;
}
.loan-detail-tagline {
  font-size: 1.15rem;
  font-weight: 700;
  color: #1a5cff;
  margin-bottom: .85rem;
}
.loan-detail-lead {
  font-size: .98rem;
  color: #475569;
  line-height: 1.7;
  max-width: 540px;
  margin-bottom: 1.5rem;
}

.loan-spec-list {
  list-style: none;
  padding: 0;
  margin: 0 0 1.75rem 0;
  display: flex;
  flex-direction: column;
  gap: .65rem;
}
.loan-spec-item {
  display: flex;
  align-items: center;
  gap: .65rem;
  font-weight: 700;
  color: #0e2b5c;
  font-size: .95rem;
}
.loan-spec-icon {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: #1a5cff;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
  flex-shrink: 0;
}

.loan-hero-img-wrap {
  position: relative;
  text-align: center;
}
.loan-hero-img-wrap img {
  max-height: 380px;
  width: auto;
  border-radius: 1.5rem;
  box-shadow: 0 20px 50px rgba(14,43,92,.18);
  border: 4px solid #ffffff;
}
.loan-hero-script {
  position: absolute;
  top: 1rem;
  right: 1.5rem;
  font-family: 'Caveat', cursive, sans-serif;
  font-size: clamp(1.4rem, 2.5vw, 2rem);
  font-weight: 700;
  color: #1a5cff;
  text-shadow: 0 2px 10px rgba(255,255,255,.9), 0 0 15px #fff;
  line-height: 1.15;
  text-align: right;
  pointer-events: none;
}

/* ── 3 Key Info Cards (Bottom-Left Reference Style) ── */
.info-card-block {
  background: #ffffff;
  border-radius: 1.25rem;
  border: 1px solid #e2edf8;
  padding: 1.5rem;
  height: 100%;
  box-shadow: 0 8px 24px rgba(14,43,92,.04);
}
.info-card-title {
  font-size: 1.05rem;
  font-weight: 800;
  color: #0e2b5c;
  margin-bottom: 1.15rem;
  letter-spacing: -.02em;
}

.benefit-icon-badge {
  text-align: center;
}
.benefit-icon-circle {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  background: #eef4ff;
  color: #1a5cff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  margin: 0 auto .45rem;
}
.benefit-icon-label {
  font-size: .72rem;
  font-weight: 700;
  color: #334155;
  line-height: 1.3;
}

.check-item {
  display: flex;
  align-items: center;
  gap: .65rem;
  margin-bottom: .65rem;
  font-size: .88rem;
  color: #334155;
  font-weight: 600;
}
.check-icon-green {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #ecfdf5;
  color: #059669;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: .8rem;
  flex-shrink: 0;
}
</style>
@endpush

@section('content')
@php
  use Illuminate\Support\Str;
  $website = $website ?? app(\App\Services\WebsiteService::class);
  $slug = $website->productSlug($product);
  $banner = $product->loanType?->loan_type_banner_url
    ?: ($product->loanType?->loan_type_image_url ?: $website->productImage($product));
  $icon = $product->loanType?->loan_type_icon_url;
  $rate = $product->interest_rate_min ?? $product->interest_rate ?? '8.5';
@endphp

{{-- ── 1. DETAIL HERO SECTION (Bottom-Left Reference Style) ── --}}
<section class="loan-detail-hero">
  <div class="container">
    <div class="row align-items-center g-4 g-lg-5">
      <div class="col-lg-7">
        <nav aria-label="breadcrumb" class="mb-2">
          <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(14,43,92,.4)">
            <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-decoration-none" style="color:#0e2b5c">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('website.loans.index') }}" class="text-decoration-none" style="color:#0e2b5c">Loan Products</a></li>
            @if($product->loanType)
              <li class="breadcrumb-item"><a href="{{ route('website.loans.category', $website->categorySlug($product->loanType)) }}" class="text-decoration-none" style="color:#0e2b5c">{{ $product->loanType->name }}</a></li>
            @endif
            <li class="breadcrumb-item active" style="color:#1a5cff">{{ $product->loan_name }}</li>
          </ol>
        </nav>

        <div class="d-flex align-items-center gap-3 mb-2 flex-wrap">
          @if(!empty($icon))
            <div style="width:44px;height:44px;border-radius:12px;background:#ffffff;padding:5px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(14,43,92,.1);border:1px solid #dbeafe">
              <img src="{{ $icon }}" alt="{{ $product->loanType?->name }}" style="max-width:100%;max-height:100%;object-fit:contain">
            </div>
          @endif
          @if($product->loanType)
            <span class="badge rounded-pill bg-white text-primary border px-3 py-2 fw-700" style="font-size:.78rem">
              {{ $product->loanType->name }}
            </span>
          @endif
        </div>

        <h1 class="loan-detail-title">{{ $product->loan_name }}</h1>
        <div class="loan-detail-tagline">
          {{ $product->short_description ?? 'Your personal dreams, our financial support.' }}
        </div>
        <p class="loan-detail-lead">
          {{ $product->description ?? ('Get a ' . $product->loan_name . ' with flexible repayment options and competitive interest rates.') }}
        </p>

        <ul class="loan-spec-list">
          <li class="loan-spec-item">
            <span class="loan-spec-icon"><i class="ri-check-line"></i></span>
            <span>Loan Amount: ₹{{ number_format($product->loan_amount_min ?? 10000) }} – ₹{{ number_format($product->loan_amount_max ?? 2500000) }}</span>
          </li>
          <li class="loan-spec-item">
            <span class="loan-spec-icon"><i class="ri-check-line"></i></span>
            <span>Interest Rate: {{ $rate }}% p.a. ({{ $product->interest_type ?? 'reducing balance' }})</span>
          </li>
          <li class="loan-spec-item">
            <span class="loan-spec-icon"><i class="ri-check-line"></i></span>
            <span>Tenure: {{ $product->min_tenture ?? 6 }} – {{ $product->max_tenture ?? 60 }} {{ $product->term_unit ?? 'Months' }}</span>
          </li>
        </ul>

        <div class="d-flex flex-wrap gap-3">
          <a href="{{ route('website.apply.product', $slug) }}" class="btn btn-sf-apply-pill px-4 py-2">
            Apply Now <i class="ri-arrow-right-line ms-1"></i>
          </a>
          <a href="{{ route('website.eligibility') }}" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-bold">
            Check Eligibility <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="loan-hero-img-wrap">
          <img src="{{ $banner }}" alt="{{ $product->loan_name }}">
          <div class="loan-hero-script">
            Your Goals,<br>
            <span>Our Support</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── 2. THREE KEY INFO CARDS (Bottom-Left Reference Style) ── --}}
<section class="container py-4 my-2">
  <div class="row g-4">
    {{-- Card 1: Key Benefits --}}
    <div class="col-lg-4 col-md-6">
      <div class="info-card-block">
        <h3 class="info-card-title">Key Benefits</h3>
        <div class="row g-2 text-center">
          <div class="col-6 col-sm-3 col-lg-6 mb-3">
            <div class="benefit-icon-badge">
              <div class="benefit-icon-circle"><i class="ri-flashlight-line"></i></div>
              <div class="benefit-icon-label">Quick Approval</div>
            </div>
          </div>
          <div class="col-6 col-sm-3 col-lg-6 mb-3">
            <div class="benefit-icon-badge">
              <div class="benefit-icon-circle"><i class="ri-calendar-check-line"></i></div>
              <div class="benefit-icon-label">Flexible Tenure</div>
            </div>
          </div>
          <div class="col-6 col-sm-3 col-lg-6">
            <div class="benefit-icon-badge">
              <div class="benefit-icon-circle"><i class="ri-percent-line"></i></div>
              <div class="benefit-icon-label">Low Interest Rates</div>
            </div>
          </div>
          <div class="col-6 col-sm-3 col-lg-6">
            <div class="benefit-icon-badge">
              <div class="benefit-icon-circle"><i class="ri-file-list-3-line"></i></div>
              <div class="benefit-icon-label">Minimal Documentation</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Eligibility Criteria --}}
    <div class="col-lg-4 col-md-6">
      <div class="info-card-block">
        <h3 class="info-card-title">Eligibility Criteria</h3>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Age: {{ $product->min_age ?? 21 }} – {{ $product->max_age ?? 60 }} Years</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Monthly Income: ₹{{ number_format($product->min_monthly_income ?? 15000) }}+</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Resident of India (Salaried or Self-Employed)</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Valid Bank Account for auto-disbursal</span>
        </div>
      </div>
    </div>

    {{-- Card 3: Required Documents --}}
    <div class="col-lg-4 col-md-12">
      <div class="info-card-block">
        <h3 class="info-card-title">Required Documents</h3>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Identity Proof (Aadhaar / Voter ID / Passport)</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Address Proof (Aadhaar / Utility Bill)</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>PAN Card for Income Tax verification</span>
        </div>
        <div class="check-item">
          <span class="check-icon-green"><i class="ri-check-line"></i></span>
          <span>Bank Statement (Latest 3–6 months)</span>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- ── 3. ABOUT LOAN & DETAILED SPECIFICATIONS ── --}}
<section class="container py-4 my-2">
  <div class="row g-4 align-items-center">
    <div class="col-lg-7">
      <div class="p-4 rounded-4 bg-white border" style="border-color:#e2edf8!important">
        <h3 class="fw-800 h4 mb-3" style="color:#0e2b5c">About {{ $product->loan_name }}</h3>
        <p class="text-muted" style="line-height:1.8">
          {!! nl2br(e($product->web_description ?? $product->description ?? 'Our loans are designed to give you peace of mind with transparent rates, no hidden penalties, and paperless digital onboarding.')) !!}
        </p>

        @if($product->benefits_json)
          <h5 class="fw-bold mt-4 mb-2" style="color:#0e2b5c">Additional Benefits</h5>
          <ul class="text-muted small ps-3 mb-0" style="line-height:1.8">
            @foreach($product->benefits_json as $b)
              <li>{{ is_array($b) ? ($b['text'] ?? '') : $b }}</li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>

    <div class="col-lg-5">
      <div class="p-4 rounded-4 text-white" style="background:linear-gradient(135deg, #0e2b5c, #163e7c)">
        <h4 class="fw-800 h5 mb-3 text-white">Quick Overview</h4>
        <div class="d-flex justify-content-between py-2 border-bottom border-light border-opacity-25 small">
          <span class="text-white-50">Processing Fee</span>
          <strong>₹{{ number_format($product->processing_fee ?? 0) }}</strong>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom border-light border-opacity-25 small">
          <span class="text-white-50">Interest Type</span>
          <strong class="text-capitalize">{{ $product->interest_type ?? 'Reducing Balance' }}</strong>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom border-light border-opacity-25 small">
          <span class="text-white-50">Pre-payment Option</span>
          <strong>Available</strong>
        </div>
        <div class="d-flex justify-content-between py-2 border-bottom border-light border-opacity-25 small">
          <span class="text-white-50">Disbursal Turnaround</span>
          <strong>24 to 48 Hours</strong>
        </div>

        <div class="mt-4 pt-2">
          <a href="{{ route('website.apply.product', $slug) }}" class="btn btn-light w-100 py-2 fw-800 text-primary rounded-pill">
            Apply for this Loan <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
