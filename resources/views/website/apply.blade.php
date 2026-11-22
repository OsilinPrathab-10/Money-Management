@extends('website.layouts.master')

@section('title', 'Apply for a Loan — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Apply for a loan online in a few simple steps with ' . ($brand['name'] ?? 'Star Finance') . '. Paperless KYC, Aadhaar & Bank verification, fast approvals.')

@push('styles')
<style>
  /* ── Page Hero (Bottom-Right Reference Style) ── */
  .apply-hero-banner {
    background: linear-gradient(135deg, #09121f 0%, #0e2b5c 55%, #184ca1 100%);
    color: #ffffff;
    padding: clamp(2.5rem, 5vw, 4rem) 0;
    position: relative;
    overflow: hidden;
  }
  .apply-hero-title {
    font-size: clamp(2.1rem, 4.5vw, 3.2rem);
    font-weight: 800;
    letter-spacing: -.03em;
    margin-bottom: .45rem;
  }
  .apply-hero-sub {
    font-size: 1.05rem;
    color: rgba(255,255,255,.82);
    margin-bottom: 0;
  }
  .apply-hero-img-wrap img {
    max-height: 190px;
    width: auto;
    border-radius: 1.25rem;
    box-shadow: 0 16px 40px rgba(0,0,0,.35);
    border: 3px solid rgba(255,255,255,.18);
    object-fit: cover;
  }

  /* ── 6-Step Horizontal Progress Indicator (Reference Match) ── */
  .apply-wizard-card {
    background: #ffffff;
    border-radius: 1.25rem;
    border: 1px solid #e2edf8;
    box-shadow: 0 10px 32px rgba(14,43,92,.06);
    overflow: hidden;
  }

  .steps-tracker-container {
    padding: 1.75rem 1.5rem 1.5rem;
    background: #ffffff;
    border-bottom: 1px solid #eef3f9;
    position: relative;
  }

  .steps-track-list {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    list-style: none;
    margin: 0;
    padding: 0;
  }

  /* Connecting line behind circles */
  .steps-track-list::before {
    content: '';
    position: absolute;
    top: 18px;
    left: 40px;
    right: 40px;
    height: 2px;
    background: #e2e8f0;
    z-index: 0;
  }

  .step-track-item {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    text-align: center;
    cursor: pointer;
    background: transparent;
    border: none;
    padding: 0;
  }

  .step-track-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #64748b;
    font-weight: 800;
    font-size: .88rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: .5rem;
    border: 3px solid #ffffff;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    transition: all .25s ease;
  }

  .step-track-label {
    font-size: .78rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
    transition: color .2s ease;
  }

  /* Active Step */
  .step-track-item.active .step-track-circle {
    background: #1a5cff;
    color: #ffffff;
    box-shadow: 0 0 0 4px rgba(26,92,255,.2), 0 4px 12px rgba(26,92,255,.35);
  }
  .step-track-item.active .step-track-label {
    color: #0e2b5c;
    font-weight: 800;
  }

  /* Done Step */
  .step-track-item.done .step-track-circle {
    background: #059669;
    color: #ffffff;
  }
  .step-track-item.done .step-track-label {
    color: #059669;
    font-weight: 700;
  }

  /* Form Panels & Inputs */
  .apply-step-body {
    padding: 2.25rem;
  }

  @media (max-width: 767.98px) {
    .apply-step-body {
      padding: 1.5rem 1rem;
    }
    .steps-tracker-container {
      overflow-x: auto;
      padding: 1.25rem .75rem 1rem;
    }
    .steps-track-list {
      min-width: 580px;
    }
  }

  .input-icon-wrap {
    position: relative;
  }
  .input-icon-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.15rem;
  }
  .input-icon-wrap .form-control,
  .input-icon-wrap .form-select {
    padding-left: 2.75rem;
    height: 48px;
    border-radius: .7rem;
    border: 1.5px solid #e2e8f0;
    font-size: .92rem;
    color: #1e293b;
    transition: border-color .15s, box-shadow .15s;
  }
  .input-icon-wrap .form-control:focus,
  .input-icon-wrap .form-select:focus {
    border-color: #1a5cff;
    box-shadow: 0 0 0 3px rgba(26,92,255,.12);
  }
  .field-hint {
    font-size: .74rem;
    color: #64748b;
    margin-top: .3rem;
  }

  /* Chips */
  .quick-chip {
    background: #f8fafc;
    border: 1px solid #e2edf8;
    color: #475569;
    font-size: .8rem;
    font-weight: 700;
    padding: .35rem .85rem;
    border-radius: 999px;
    cursor: pointer;
    transition: all .15s ease;
  }
  .quick-chip:hover,
  .quick-chip.active {
    background: #eef4ff;
    border-color: #b8ccff;
    color: #1a5cff;
  }

  /* Employment cards */
  .emp-type-card {
    border: 1.5px solid #e2edf8;
    border-radius: .9rem;
    padding: 1rem .75rem;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    height: 100%;
    background: #fff;
  }
  .emp-type-card:hover {
    border-color: #b8ccff;
    background: #f8faff;
  }
  .emp-type-card.selected {
    border-color: #1a5cff;
    background: #eff6ff;
    box-shadow: 0 4px 14px rgba(26,92,255,.12);
  }
  .emp-type-icon {
    width: 44px;
    height: 44px;
    border-radius: .75rem;
    background: #eef4ff;
    color: #1a5cff;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto .45rem;
  }
  .emp-type-card.selected .emp-type-icon {
    background: #1a5cff;
    color: #fff;
  }

  /* Step nav footer bar */
  .step-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    padding: 1.25rem 2.25rem;
    border-top: 1px solid #eef3f9;
    background: #fafcff;
  }

  /* ── Right Column Safety Card (Mockup Exact Match) ── */
  .safe-reassurance-card {
    background: #ffffff;
    border-radius: 1.25rem;
    border: 1px solid #e2edf8;
    box-shadow: 0 10px 30px rgba(14,43,92,.05);
    padding: 2.25rem 1.75rem;
    text-align: center;
  }
  .safe-icon-circle {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background: #eef4ff;
    color: #1a5cff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.3rem;
    margin: 0 auto 1.25rem;
    box-shadow: 0 8px 24px rgba(26,92,255,.15);
  }
  .safe-card-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0e2b5c;
    letter-spacing: -.02em;
    margin-bottom: .5rem;
  }
  .safe-card-desc {
    font-size: .88rem;
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 0;
  }

  /* Summary Card */
  .apply-summary-card {
    background: linear-gradient(145deg, #09121f 0%, #0e2b5c 60%, #1535a0 100%);
    color: #ffffff;
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 14px 40px rgba(14,43,92,.18);
  }
</style>
@endpush

@section('content')
@php
  $product = $product ?? null;
  $products = $products ?? app(\App\Services\WebsiteService::class)->websiteProducts();
  $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp

{{-- ── 1. PAGE HERO (Bottom-Right Reference Match) ── --}}
<section class="apply-hero-banner">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <nav aria-label="breadcrumb" class="mb-2">
          <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
            <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('website.loans.index') }}" class="text-white-50 text-decoration-none">Loan Products</a></li>
            <li class="breadcrumb-item text-white active">Apply for a Loan</li>
          </ol>
        </nav>
        <h1 class="apply-hero-title">Apply for a Loan</h1>
        <p class="apply-hero-sub">Complete your application in a few simple steps</p>
      </div>
      <div class="col-lg-5 text-center text-lg-end d-none d-lg-block apply-hero-img-wrap">
        <img src="https://images.unsplash.com/photo-1450133064473-71024230f91b?auto=format&fit=crop&w=700&q=80" alt="Apply for a Loan">
      </div>
    </div>
  </div>
</section>

{{-- ── 2. MAIN APPLICATION WORKFLOW ── --}}
<section class="container py-4 my-2">
  @if(isset($errors) && $errors->any())
    <div class="alert alert-danger rounded-4 shadow-sm mb-4 p-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <i class="ri-error-warning-fill fs-5 text-danger"></i>
        <h6 class="mb-0 fw-bold">Please check the following details:</h6>
      </div>
      <ul class="mb-0 ps-4 small">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('website.apply.store') }}" id="loanApplicationForm" novalidate>
    @csrf
    <div class="row g-4">
      {{-- Left Column: 6-Step Multi-step Form --}}
      <div class="col-lg-8">
        <div class="apply-wizard-card">
          {{-- 6-Step Horizontal Progress Tracker (Reference Match) --}}
          <div class="steps-tracker-container">
            <ul class="steps-track-list" role="tablist">
              <li class="step-track-item active" id="applyTab1" onclick="goApplyStep(1)">
                <div class="step-track-circle">1</div>
                <div class="step-track-label">Personal Details</div>
              </li>
              <li class="step-track-item" id="applyTab2" onclick="goApplyStep(2)">
                <div class="step-track-circle">2</div>
                <div class="step-track-label">Contact Details</div>
              </li>
              <li class="step-track-item" id="applyTab3" onclick="goApplyStep(3)">
                <div class="step-track-circle">3</div>
                <div class="step-track-label">Employment Details</div>
              </li>
              <li class="step-track-item" id="applyTab4" onclick="goApplyStep(4)">
                <div class="step-track-circle">4</div>
                <div class="step-track-label">Loan Details</div>
              </li>
              <li class="step-track-item" id="applyTab5" onclick="goApplyStep(5)">
                <div class="step-track-circle">5</div>
                <div class="step-track-label">Documents</div>
              </li>
              <li class="step-track-item" id="applyTab6" onclick="goApplyStep(6)">
                <div class="step-track-circle">6</div>
                <div class="step-track-label">Review</div>
              </li>
            </ul>
          </div>

          {{-- ── STEP 1: Personal Details (Exact Mockup Match) ── --}}
          <div class="apply-step-panel" id="applyStep1" data-step="1">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Personal Details</h4>
                <p class="text-muted small mb-0">Let's start with your basic information</p>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Full Name <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-user-3-line"></i>
                    <input type="text" name="applicant_name" class="form-control @error('applicant_name') is-invalid @enderror"
                      value="{{ old('applicant_name', auth()->user()?->name) }}" required placeholder="Enter your full name">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Gender <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-women-line"></i>
                    <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                      <option value="" disabled @selected(!old('gender'))>Select Gender</option>
                      <option value="male" @selected(old('gender') === 'male')>Male</option>
                      <option value="female" @selected(old('gender') === 'female')>Female</option>
                      <option value="other" @selected(old('gender') === 'other')>Other</option>
                    </select>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Date of Birth <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-calendar-event-line"></i>
                    <input type="date" name="dob" class="form-control @error('dob') is-invalid @enderror"
                      value="{{ old('dob') }}" required max="{{ date('Y-m-d', strtotime('-18 years')) }}" placeholder="DD/MM/YYYY">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Mobile Number <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-smartphone-line"></i>
                    <input type="tel" name="phone" maxlength="10" pattern="[6-9][0-9]{9}"
                      class="form-control @error('phone') is-invalid @enderror"
                      value="{{ old('phone', auth()->user()?->phone) }}" required placeholder="Enter mobile number">
                  </div>
                  <div class="field-hint">Status updates and OTP will be delivered to this number.</div>
                </div>
              </div>
            </div>

            <div class="step-nav-bar justify-content-end">
              <button type="button" class="btn btn-sf-apply-pill px-4" data-apply-next="2">
                Next Step <i class="ri-arrow-right-line ms-1"></i>
              </button>
            </div>
          </div>

          {{-- ── STEP 2: Contact Details ── --}}
          <div class="apply-step-panel d-none" id="applyStep2" data-step="2">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Contact Details</h4>
                <p class="text-muted small mb-0">Where can our team reach you?</p>
              </div>

              <div class="row g-3">
                <div class="col-md-12">
                  <label class="form-label fw-bold text-secondary small">Email Address <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-mail-line"></i>
                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                      value="{{ old('email', auth()->user()?->email) }}" required placeholder="Enter your email address">
                  </div>
                </div>

                <div class="col-12">
                  <label class="form-label fw-bold text-secondary small">Residential Address <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-home-4-line"></i>
                    <input type="text" name="address" class="form-control @error('address') is-invalid @enderror"
                      value="{{ old('address') }}" required placeholder="Door No., Building, Street Name, Area">
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold text-secondary small">City <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-building-line"></i>
                    <input type="text" name="city" class="form-control @error('city') is-invalid @enderror"
                      value="{{ old('city') }}" required placeholder="Enter City">
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold text-secondary small">State <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-map-2-line"></i>
                    <input type="text" name="state" class="form-control @error('state') is-invalid @enderror"
                      value="{{ old('state', 'Tamil Nadu') }}" required placeholder="Enter State">
                  </div>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold text-secondary small">Pincode <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-map-pin-2-line"></i>
                    <input type="text" name="pincode" maxlength="6" pattern="\d{6}"
                      class="form-control @error('pincode') is-invalid @enderror"
                      value="{{ old('pincode') }}" required placeholder="6-digit pincode">
                  </div>
                </div>
              </div>
            </div>

            <div class="step-nav-bar">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-apply-prev="1">
                <i class="ri-arrow-left-line me-1"></i> Back
              </button>
              <button type="button" class="btn btn-sf-apply-pill px-4" data-apply-next="3">
                Next Step <i class="ri-arrow-right-line ms-1"></i>
              </button>
            </div>
          </div>

          {{-- ── STEP 3: Employment Details ── --}}
          <div class="apply-step-panel d-none" id="applyStep3" data-step="3">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Employment Details</h4>
                <p class="text-muted small mb-0">Tell us about your professional background and monthly income</p>
              </div>

              <label class="form-label fw-bold text-secondary small mb-2">EMPLOYMENT TYPE <span class="text-danger">*</span></label>
              <input type="hidden" name="employment_type" id="empTypeInput" value="{{ old('employment_type', 'salaried') }}">

              <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                  <div class="emp-type-card selected" onclick="selectEmpType('salaried', this)">
                    <div class="emp-type-icon"><i class="ri-briefcase-4-line"></i></div>
                    <div class="fw-bold small text-dark">Salaried</div>
                    <div class="text-muted" style="font-size:.7rem">Private / Govt</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="emp-type-card" onclick="selectEmpType('business_owner', this)">
                    <div class="emp-type-icon"><i class="ri-store-2-line"></i></div>
                    <div class="fw-bold small text-dark">Business Owner</div>
                    <div class="text-muted" style="font-size:.7rem">Trader / Enterprise</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="emp-type-card" onclick="selectEmpType('self_employed_professional', this)">
                    <div class="emp-type-icon"><i class="ri-user-star-line"></i></div>
                    <div class="fw-bold small text-dark">Professional</div>
                    <div class="text-muted" style="font-size:.7rem">Doctor, CA, Engg</div>
                  </div>
                </div>
                <div class="col-6 col-md-3">
                  <div class="emp-type-card" onclick="selectEmpType('agriculture', this)">
                    <div class="emp-type-icon"><i class="ri-plant-line"></i></div>
                    <div class="fw-bold small text-dark">Agriculture</div>
                    <div class="text-muted" style="font-size:.7rem">Farmer / Allied</div>
                  </div>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Employer / Business Name</label>
                  <div class="input-icon-wrap">
                    <i class="ri-building-4-line"></i>
                    <input type="text" name="employer_name" class="form-control" value="{{ old('employer_name') }}"
                      placeholder="e.g. ABC Technologies / Sri Murugan Traders">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Net Monthly Income (₹) <span class="text-danger">*</span></label>
                  <div class="input-icon-wrap">
                    <i class="ri-wallet-3-line"></i>
                    <input type="number" name="monthly_income" class="form-control @error('monthly_income') is-invalid @enderror"
                      value="{{ old('monthly_income', 45000) }}" required placeholder="e.g. 45000">
                  </div>
                  <div class="field-hint">Helps determine your maximum eligible loan amount.</div>
                </div>
              </div>
            </div>

            <div class="step-nav-bar">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-apply-prev="2">
                <i class="ri-arrow-left-line me-1"></i> Back
              </button>
              <button type="button" class="btn btn-sf-apply-pill px-4" data-apply-next="4">
                Next Step <i class="ri-arrow-right-line ms-1"></i>
              </button>
            </div>
          </div>

          {{-- ── STEP 4: Loan Details ── --}}
          <div class="apply-step-panel d-none" id="applyStep4" data-step="4">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Loan Details</h4>
                <p class="text-muted small mb-0">Specify your financing requirements and repayment tenure</p>
              </div>

              {{-- Select Loan Product --}}
              <div class="mb-4">
                <label class="form-label fw-bold text-secondary small">SELECT LOAN PRODUCT <span class="text-danger">*</span></label>
                <div class="input-icon-wrap">
                  <i class="ri-bank-line"></i>
                  <select name="loan_product_id" id="loanProductSelect" class="form-select @error('loan_product_id') is-invalid @enderror"
                    required onchange="handleProductChange(this)">
                    @foreach($products as $p)
                      <option value="{{ $p->id }}"
                        data-name="{{ $p->loan_name }}"
                        data-min="{{ $p->loan_amount_min ?? 10000 }}"
                        data-max="{{ $p->loan_amount_max ?? 2500000 }}"
                        data-rate="{{ $p->interest_rate ?? $p->interest_rate_min ?? 14 }}"
                        data-type="{{ $p->loanType?->name ?? 'Loan' }}"
                        @selected(old('loan_product_id', optional($product)->id) == $p->id)>
                        {{ $p->loan_name }} @if($p->loanType) ({{ $p->loanType->name }}) @endif — from {{ $p->interest_rate ?? $p->interest_rate_min ?? 12 }}% p.a.
                      </option>
                    @endforeach
                  </select>
                </div>
              </div>

              {{-- Amount Input + Chips --}}
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label fw-bold text-secondary small mb-0">REQUESTED LOAN AMOUNT (₹) <span class="text-danger">*</span></label>
                  <span class="text-muted small" id="amountRangeHint">Min: ₹10,000 | Max: ₹25,00,000</span>
                </div>
                <div class="input-icon-wrap mb-2">
                  <i class="ri-money-rupee-circle-line"></i>
                  <input type="number" name="requested_amount" id="reqAmount"
                    class="form-control fw-bold fs-6 @error('requested_amount') is-invalid @enderror"
                    value="{{ old('requested_amount', request('amount', 200000)) }}" required min="1000"
                    placeholder="e.g. 200000" oninput="updateSummary()">
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="quick-chip" onclick="setQuickAmount(50000)">₹50,000</button>
                  <button type="button" class="quick-chip" onclick="setQuickAmount(100000)">₹1 Lakh</button>
                  <button type="button" class="quick-chip active" onclick="setQuickAmount(200000)">₹2 Lakh</button>
                  <button type="button" class="quick-chip" onclick="setQuickAmount(500000)">₹5 Lakh</button>
                  <button type="button" class="quick-chip" onclick="setQuickAmount(1000000)">₹10 Lakh</button>
                </div>
              </div>

              {{-- Tenure Input + Chips --}}
              <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label fw-bold text-secondary small mb-0">REPAYMENT TENURE (MONTHS) <span class="text-danger">*</span></label>
                  <span class="text-muted small" id="tenureYearsHint">2 Years</span>
                </div>
                <div class="input-icon-wrap mb-2">
                  <i class="ri-calendar-line"></i>
                  <input type="number" name="requested_tenure_months" id="reqTenure"
                    class="form-control fw-bold fs-6 @error('requested_tenure_months') is-invalid @enderror"
                    value="{{ old('requested_tenure_months', request('tenure', 24)) }}" required min="1" max="360"
                    placeholder="e.g. 24" oninput="updateSummary()">
                </div>
                <div class="d-flex gap-2 flex-wrap">
                  <button type="button" class="quick-chip" onclick="setQuickTenure(12)">12 Months (1 Yr)</button>
                  <button type="button" class="quick-chip active" onclick="setQuickTenure(24)">24 Months (2 Yrs)</button>
                  <button type="button" class="quick-chip" onclick="setQuickTenure(36)">36 Months (3 Yrs)</button>
                  <button type="button" class="quick-chip" onclick="setQuickTenure(48)">48 Months (4 Yrs)</button>
                  <button type="button" class="quick-chip" onclick="setQuickTenure(60)">60 Months (5 Yrs)</button>
                </div>
              </div>

              {{-- Purpose of Loan --}}
              <div class="mb-2">
                <label class="form-label fw-bold text-secondary small">LOAN PURPOSE (OPTIONAL)</label>
                <div class="input-icon-wrap">
                  <i class="ri-chat-1-line"></i>
                  <input type="text" name="message" class="form-control" value="{{ old('message') }}"
                    placeholder="e.g. Home improvement, Business expansion, Education, Medical">
                </div>
              </div>
            </div>

            <div class="step-nav-bar">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-apply-prev="3">
                <i class="ri-arrow-left-line me-1"></i> Back
              </button>
              <button type="button" class="btn btn-sf-apply-pill px-4" data-apply-next="5">
                Next Step <i class="ri-arrow-right-line ms-1"></i>
              </button>
            </div>
          </div>

          {{-- ── STEP 5: Documents (KYC & Bank Details) ── --}}
          <div class="apply-step-panel d-none" id="applyStep5" data-step="5">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Documents &amp; Verification</h4>
                <p class="text-muted small mb-0">KYC identifiers and disbursal bank account details</p>
              </div>

              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">PAN CARD NUMBER</label>
                  <div class="input-icon-wrap">
                    <i class="ri-id-card-line"></i>
                    <input type="text" name="pan_number" maxlength="10"
                      class="form-control text-uppercase @error('pan_number') is-invalid @enderror"
                      value="{{ old('pan_number') }}" placeholder="ABCDE1234F" style="letter-spacing:.08em"
                      oninput="this.value = this.value.toUpperCase()">
                  </div>
                  <div class="field-hint">10-character alphanumeric PAN for instant verification</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">AADHAAR NUMBER (12 DIGITS)</label>
                  <div class="input-icon-wrap">
                    <i class="ri-fingerprint-line"></i>
                    <input type="text" name="aadhaar_number" id="aadhaarInput" maxlength="14"
                      class="form-control @error('aadhaar_number') is-invalid @enderror"
                      value="{{ old('aadhaar_number') }}" placeholder="1234 5678 9012" oninput="formatAadhaar(this)">
                  </div>
                  <div class="field-hint">Masked securely per UIDAI compliance standards.</div>
                </div>
              </div>

              <h6 class="fw-bold text-secondary small text-uppercase mb-3 pt-2 border-top">Bank Account (Disbursal)</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Bank Name</label>
                  <div class="input-icon-wrap">
                    <i class="ri-bank-line"></i>
                    <input type="text" name="bank_name" class="form-control @error('bank_name') is-invalid @enderror"
                      value="{{ old('bank_name') }}" placeholder="e.g. State Bank of India, HDFC Bank">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Account Holder Name</label>
                  <div class="input-icon-wrap">
                    <i class="ri-user-follow-line"></i>
                    <input type="text" name="bank_account_holder" class="form-control @error('bank_account_holder') is-invalid @enderror"
                      value="{{ old('bank_account_holder') }}" placeholder="Name as per Bank Passbook">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Bank Account Number</label>
                  <div class="input-icon-wrap">
                    <i class="ri-bank-card-line"></i>
                    <input type="password" name="bank_account_number" id="bankAccNo"
                      class="form-control @error('bank_account_number') is-invalid @enderror"
                      value="{{ old('bank_account_number') }}" placeholder="Enter Account Number" autocomplete="off">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Re-Enter Account Number</label>
                  <div class="input-icon-wrap">
                    <i class="ri-checkbox-circle-line"></i>
                    <input type="text" id="bankAccNoConfirm" class="form-control" placeholder="Re-enter to confirm" oninput="checkAccountMatch()">
                  </div>
                  <div id="accMatchMsg" class="field-hint"></div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">IFSC Code</label>
                  <div class="input-icon-wrap">
                    <i class="ri-barcode-line"></i>
                    <input type="text" name="bank_ifsc" maxlength="11"
                      class="form-control text-uppercase @error('bank_ifsc') is-invalid @enderror"
                      value="{{ old('bank_ifsc') }}" placeholder="SBIN0001234" style="letter-spacing:.08em"
                      oninput="this.value = this.value.toUpperCase()">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Account Type</label>
                  <div class="input-icon-wrap">
                    <i class="ri-funds-box-line"></i>
                    <select name="bank_account_type" class="form-select @error('bank_account_type') is-invalid @enderror">
                      <option value="savings" @selected(old('bank_account_type') === 'savings')>Savings Account</option>
                      <option value="current" @selected(old('bank_account_type') === 'current')>Current Account</option>
                    </select>
                  </div>
                </div>
              </div>

              <h6 class="fw-bold text-secondary small text-uppercase mb-3 mt-4 pt-3 border-top">Nominee Details (Optional)</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Nominee Name</label>
                  <div class="input-icon-wrap">
                    <i class="ri-user-heart-line"></i>
                    <input type="text" name="nominee_name" class="form-control" value="{{ old('nominee_name') }}" placeholder="e.g. Lakshmi R">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Relationship</label>
                  <div class="input-icon-wrap">
                    <i class="ri-group-line"></i>
                    <select name="nominee_relation" class="form-select">
                      <option value="">Select Relationship</option>
                      @foreach(['Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Brother', 'Sister', 'Guardian', 'Other'] as $rel)
                        <option value="{{ $rel }}" @selected(old('nominee_relation') === $rel)>{{ $rel }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Nominee Mobile Number</label>
                  <div class="input-icon-wrap">
                    <i class="ri-smartphone-line"></i>
                    <input type="tel" name="nominee_phone" maxlength="10" pattern="[6-9][0-9]{9}" class="form-control"
                      value="{{ old('nominee_phone') }}" placeholder="10-digit mobile number">
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold text-secondary small">Nominee Age</label>
                  <div class="input-icon-wrap">
                    <i class="ri-time-line"></i>
                    <input type="number" name="nominee_age" min="1" max="120" class="form-control" value="{{ old('nominee_age') }}" placeholder="e.g. 32">
                  </div>
                </div>
              </div>
            </div>

            <div class="step-nav-bar">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-apply-prev="4">
                <i class="ri-arrow-left-line me-1"></i> Back
              </button>
              <button type="button" class="btn btn-sf-apply-pill px-4" data-apply-next="6">
                Next Step <i class="ri-arrow-right-line ms-1"></i>
              </button>
            </div>
          </div>

          {{-- ── STEP 6: Review & Submit ── --}}
          <div class="apply-step-panel d-none" id="applyStep6" data-step="6">
            <div class="apply-step-body">
              <div class="mb-4">
                <h4 class="fw-800 text-dark mb-1" style="color:#0e2b5c!important">Review &amp; Submit</h4>
                <p class="text-muted small mb-0">Please verify your application details before submitting</p>
              </div>

              <div class="row g-3 mb-4 p-3 rounded-4 bg-light border" id="applyReviewBox">
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Applicant Name</div>
                  <div class="fw-bold text-dark fs-6" id="revName">—</div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Mobile &amp; Email</div>
                  <div class="fw-bold text-dark" id="revContact">—</div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Selected Loan Product</div>
                  <div class="fw-bold text-primary" id="revProduct">—</div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Requested Amount &amp; Tenure</div>
                  <div class="fw-bold text-dark" id="revAmountTenure">—</div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Location (City)</div>
                  <div class="fw-bold text-dark" id="revCity">—</div>
                </div>
                <div class="col-sm-6">
                  <div class="text-muted small text-uppercase fw-bold" style="font-size:.72rem">Monthly Income</div>
                  <div class="fw-bold text-dark" id="revIncome">—</div>
                </div>
              </div>

              <div class="form-check mb-4">
                <input class="form-check-input @error('consent') is-invalid @enderror" type="checkbox" name="consent" id="consent" value="1" required checked>
                <label class="form-check-label small text-muted" for="consent" style="line-height:1.7">
                  I authorize <strong>{{ $brand['name'] ?? 'Star Finance' }}</strong> and its representatives to contact me via Call, SMS, WhatsApp, and Email. I confirm that all submitted details are accurate. I accept the
                  <a href="{{ route('public.terms-and-conditions') }}" target="_blank" class="text-primary text-decoration-none fw-semibold">Terms &amp; Conditions</a> and
                  <a href="{{ route('public.privacy-policy') }}" target="_blank" class="text-primary text-decoration-none fw-semibold">Privacy Policy</a>.
                </label>
              </div>

              <div class="d-grid">
                <button class="btn btn-sf-apply-pill btn-lg fw-bold py-3 shadow" type="submit" id="btnSubmitApp" style="font-size:1.05rem">
                  <i class="ri-send-plane-fill me-2"></i> Submit Application
                </button>
              </div>
            </div>

            <div class="step-nav-bar">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-apply-prev="5">
                <i class="ri-arrow-left-line me-1"></i> Back
              </button>
              <div class="text-muted small d-none d-sm-block">
                <i class="ri-shield-check-line text-success me-1"></i> Ready for instant submission
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Right Column: Reassurance Card (Mockup Exact Match) + Live Loan Overview --}}
      <div class="col-lg-4">
        {{-- Floating Reassurance Card (Exact Reference Match) --}}
        <div class="safe-reassurance-card mb-4">
          <div class="safe-icon-circle">
            <i class="ri-shield-check-fill"></i>
          </div>
          <h4 class="safe-card-title">Your Information is Safe With Us</h4>
          <p class="safe-card-desc">We use bank-grade security to protect your data.</p>
        </div>

        {{-- Dynamic Live EMI & Loan Overview Card --}}
        <div class="apply-summary-card">
          <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-white border-opacity-15">
            <div class="fw-bold small text-white text-uppercase" style="letter-spacing:.05em">Application Overview</div>
            <span class="badge bg-warning text-dark fw-bold rounded-pill" id="sumBadgeType">Personal Loan</span>
          </div>

          <div class="mb-3">
            <div class="text-white-50 small">Selected Product:</div>
            <div class="fw-bold fs-6 text-white" id="sumProductName">Personal Loan</div>
          </div>

          {{-- Live EMI Box --}}
          <div class="p-3 rounded-3 mb-3" style="background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.15)">
            <div class="text-white-50 small mb-1" style="font-size:.74rem">ESTIMATED MONTHLY EMI</div>
            <div class="display-6 fw-900 text-warning lh-1 mb-2" id="sumEmi">₹9,618</div>
            <div class="text-white-50" style="font-size:.75rem">
              At interest rate of <strong class="text-white" id="sumRate">14% p.a.</strong>
            </div>
          </div>

          <div class="d-flex flex-column gap-2 small pb-3 mb-3 border-bottom border-white border-opacity-15">
            <div class="d-flex justify-content-between text-white-50">
              <span>Loan Amount:</span>
              <strong class="text-white" id="sumAmount">₹2,00,000</strong>
            </div>
            <div class="d-flex justify-content-between text-white-50">
              <span>Tenure:</span>
              <strong class="text-white" id="sumTenure">24 Months</strong>
            </div>
            <div class="d-flex justify-content-between text-white-50">
              <span>Estimated Interest:</span>
              <strong class="text-white" id="sumInterest">₹30,832</strong>
            </div>
            <div class="d-flex justify-content-between text-white-50">
              <span>Total Payable:</span>
              <strong class="text-white" id="sumTotal">₹2,30,832</strong>
            </div>
          </div>

          {{-- Need Assistance Hotline --}}
          @if(!empty($brand['phone']))
            <a href="tel:{{ $brand['phone'] }}" class="btn btn-sm btn-outline-light rounded-pill w-100" style="font-size:.78rem">
              <i class="ri-phone-line me-1"></i> Support: {{ $brand['phone'] }}
            </a>
          @endif
        </div>
      </div>
    </div>
  </form>
</section>
@endsection

@push('scripts')
<script>
  var productSelect = document.getElementById('loanProductSelect');
  var reqAmount = document.getElementById('reqAmount');
  var reqTenure = document.getElementById('reqTenure');
  var tenureYearsHint = document.getElementById('tenureYearsHint');

  var sumProductName = document.getElementById('sumProductName');
  var sumBadgeType = document.getElementById('sumBadgeType');
  var sumEmi = document.getElementById('sumEmi');
  var sumRate = document.getElementById('sumRate');
  var sumAmount = document.getElementById('sumAmount');
  var sumTenure = document.getElementById('sumTenure');
  var sumInterest = document.getElementById('sumInterest');
  var sumTotal = document.getElementById('sumTotal');

  function fmt(n) {
    return '₹' + Math.round(n).toLocaleString('en-IN');
  }

  function handleProductChange(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (!opt) return;

    var min = parseFloat(opt.dataset.min) || 10000;
    var max = parseFloat(opt.dataset.max) || 2500000;
    var rangeEl = document.getElementById('amountRangeHint');
    if (rangeEl) rangeEl.textContent = 'Min: ' + fmt(min) + ' | Max: ' + fmt(max);

    updateSummary();
  }

  function updateSummary() {
    if (!productSelect) return;
    var opt = productSelect.options[productSelect.selectedIndex];
    var pName = opt ? (opt.dataset.name || opt.text) : 'Loan';
    var pType = opt ? (opt.dataset.type || 'Standard') : 'Standard';
    var annualRate = opt ? (parseFloat(opt.dataset.rate) || 14) : 14;

    var P = parseFloat(reqAmount.value) || 0;
    var n = parseInt(reqTenure.value) || 1;
    if (n < 1) n = 1;

    var yrs = (n / 12);
    if (tenureYearsHint) {
      tenureYearsHint.textContent = n + ' Months' + (yrs >= 1 ? ' (' + (yrs % 1 === 0 ? yrs : yrs.toFixed(1)) + ' Yrs)' : '');
    }

    // Calculate EMI
    var r = (annualRate / 12) / 100;
    var emi = 0;
    if (r === 0) {
      emi = P / n;
    } else {
      var factor = Math.pow(1 + r, n);
      emi = (P * r * factor) / (factor - 1);
    }

    var totalPay = emi * n;
    var totalInt = Math.max(0, totalPay - P);

    // Update Summary card
    if (sumProductName) sumProductName.textContent = pName;
    if (sumBadgeType) sumBadgeType.textContent = pType;
    if (sumRate) sumRate.textContent = annualRate + '% p.a.';
    if (sumAmount) sumAmount.textContent = fmt(P);
    if (sumTenure) sumTenure.textContent = n + ' Months';
    if (sumEmi) sumEmi.textContent = fmt(emi);
    if (sumInterest) sumInterest.textContent = fmt(totalInt);
    if (sumTotal) sumTotal.textContent = fmt(totalPay);
  }

  function setQuickAmount(amt) {
    reqAmount.value = amt;
    highlightChip(event.target);
    updateSummary();
  }

  function setQuickTenure(mo) {
    reqTenure.value = mo;
    highlightChip(event.target);
    updateSummary();
  }

  function highlightChip(target) {
    if (!target) return;
    var parent = target.parentElement;
    if (parent) {
      parent.querySelectorAll('.quick-chip').forEach(function (b) { b.classList.remove('active'); });
      target.classList.add('active');
    }
  }

  function selectEmpType(type, cardEl) {
    document.getElementById('empTypeInput').value = type;
    document.querySelectorAll('.emp-type-card').forEach(function (c) { c.classList.remove('selected'); });
    if (cardEl) {
      cardEl.classList.add('selected');
    }
  }

  // 12-digit Aadhaar formatting with auto-spaces
  function formatAadhaar(el) {
    var v = el.value.replace(/\D/g, '').substring(0, 12);
    var parts = [];
    for (var i = 0; i < v.length; i += 4) {
      parts.push(v.substring(i, i + 4));
    }
    el.value = parts.join(' ');
  }

  // Bank account confirmation match indicator
  function checkAccountMatch() {
    var acc1 = document.getElementById('bankAccNo').value.trim();
    var acc2 = document.getElementById('bankAccNoConfirm').value.trim();
    var msgEl = document.getElementById('accMatchMsg');
    if (!acc2) {
      msgEl.textContent = '';
      return;
    }
    if (acc1 === acc2) {
      msgEl.innerHTML = '<span class="text-success"><i class="ri-check-line"></i> Account numbers match</span>';
    } else {
      msgEl.innerHTML = '<span class="text-danger"><i class="ri-close-line"></i> Account numbers do not match</span>';
    }
  }

  // ── 6-step wizard logic ──
  var applyMaxStep = 1;
  var applyTotalSteps = 6;

  function validateApplyStep(step) {
    var panel = document.getElementById('applyStep' + step);
    if (!panel) return true;
    var fields = panel.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) {
      var el = fields[i];
      if (el.disabled || el.type === 'hidden' || el.name === 'consent') continue;
      if (step < applyTotalSteps && el.id === 'consent') continue;
      if (!el.checkValidity()) {
        el.reportValidity();
        el.focus({ preventScroll: false });
        return false;
      }
    }
    if (step === 5) {
      var acc1 = document.getElementById('bankAccNo').value.trim();
      var acc2 = document.getElementById('bankAccNoConfirm').value.trim();
      if (acc1 && acc2 && acc1 !== acc2) {
        checkAccountMatch();
        document.getElementById('bankAccNoConfirm').focus();
        return false;
      }
    }
    return true;
  }

  function updateApplyReview() {
    var opt = productSelect ? productSelect.options[productSelect.selectedIndex] : null;
    var pName = opt ? (opt.dataset.name || opt.text.split('—')[0].trim()) : '—';
    var revProduct = document.getElementById('revProduct');
    if (revProduct) revProduct.textContent = pName;

    var revAmt = document.getElementById('revAmountTenure');
    if (revAmt) {
      revAmt.textContent = fmt(parseFloat(reqAmount.value) || 0) + ' · ' + (parseInt(reqTenure.value, 10) || '—') + ' months';
    }

    var nameEl = document.querySelector('[name="applicant_name"]');
    var phoneEl = document.querySelector('[name="phone"]');
    var emailEl = document.querySelector('[name="email"]');
    var cityEl = document.querySelector('[name="city"]');
    var incomeEl = document.querySelector('[name="monthly_income"]');

    var revName = document.getElementById('revName');
    if (revName) revName.textContent = nameEl && nameEl.value ? nameEl.value : '—';

    var contact = [];
    if (phoneEl && phoneEl.value) contact.push(phoneEl.value);
    if (emailEl && emailEl.value) contact.push(emailEl.value);
    var revContact = document.getElementById('revContact');
    if (revContact) revContact.textContent = contact.length ? contact.join(' · ') : '—';

    var revCity = document.getElementById('revCity');
    if (revCity) revCity.textContent = cityEl && cityEl.value ? cityEl.value : '—';

    var revIncome = document.getElementById('revIncome');
    if (revIncome) revIncome.textContent = incomeEl && incomeEl.value ? fmt(parseFloat(incomeEl.value)) + ' / mo' : '—';
  }

  function goApplyStep(step, options) {
    options = options || {};
    step = parseInt(step, 10);
    if (step < 1 || step > applyTotalSteps) return;

    if (options.validateFrom) {
      for (var s = options.validateFrom; s < step; s++) {
        if (!validateApplyStep(s)) return;
      }
    }

    if (step > applyMaxStep) {
      applyMaxStep = step;
    }

    for (var n = 1; n <= applyTotalSteps; n++) {
      var tab = document.getElementById('applyTab' + n);
      var panel = document.getElementById('applyStep' + n);
      if (!tab || !panel) continue;
      var isActive = n === step;
      panel.classList.toggle('d-none', !isActive);
      tab.classList.toggle('active', isActive);
      tab.classList.toggle('done', n < step);
      var circle = tab.querySelector('.step-track-circle');
      if (circle && n < step) {
        circle.innerHTML = '<i class="ri-check-line"></i>';
      } else if (circle) {
        circle.textContent = String(n);
      }
    }

    if (step === applyTotalSteps) {
      updateApplyReview();
    }

    if (!options.skipScroll) {
      var card = document.querySelector('.apply-wizard-card');
      if (card) {
        var y = card.getBoundingClientRect().top + window.pageYOffset - 90;
        window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
      }
    }
  }

  document.querySelectorAll('[data-apply-next]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var current = parseInt(btn.closest('.apply-step-panel').dataset.step, 10);
      var next = parseInt(btn.getAttribute('data-apply-next'), 10);
      if (!validateApplyStep(current)) return;
      goApplyStep(next);
    });
  });

  document.querySelectorAll('[data-apply-prev]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      goApplyStep(parseInt(btn.getAttribute('data-apply-prev'), 10));
    });
  });

  // Auto-init on page load
  document.addEventListener('DOMContentLoaded', function () {
    if (productSelect) handleProductChange(productSelect);
    updateSummary();
  });
</script>
@endpush