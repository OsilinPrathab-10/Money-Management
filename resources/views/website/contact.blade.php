@extends('website.layouts.master')

@php use Illuminate\Support\Str; @endphp

@section('title', 'Contact Us & Customer Support — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Get in touch with ' . ($brand['name'] ?? 'Star Finance') . '. Dedicated customer support, branch contacts, WhatsApp assistance, and loan advisors.')

@push('styles')
<style>
.channel-card {
  background: #fff;
  border: 1px solid var(--sf-line, #e6edf5);
  border-radius: var(--sf-radius, 1rem);
  padding: 1.5rem 1.25rem;
  text-align: center;
  transition: border-color .2s, box-shadow .2s, transform .2s;
  height: 100%;
  box-shadow: var(--sf-shadow);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.channel-card:hover {
  border-color: #c7d7f5;
  box-shadow: var(--sf-shadow-lg);
  transform: translateY(-2px);
}
.channel-icon {
  width: 60px;
  height: 60px;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.65rem;
  margin: 0 auto 1.25rem;
  transition: all .2s;
}
.contact-form-card {
  background: #fff;
  border: 1px solid var(--sf-line, #e6edf5);
  border-radius: var(--sf-radius, 1rem);
  padding: 2rem;
  box-shadow: var(--sf-shadow);
}
.side-support-card {
  background: #fff;
  border: 1px solid var(--sf-line, #e6edf5);
  border-radius: var(--sf-radius, 1rem);
  padding: 1.5rem;
  box-shadow: var(--sf-shadow);
  margin-bottom: 1.25rem;
}
.input-icon-wrap {
  position: relative;
}
.input-icon-wrap i {
  position: absolute;
  left: 15px;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  font-size: 1.15rem;
}
.input-icon-wrap .form-control, .input-icon-wrap .form-select {
  padding-left: 2.85rem;
  height: 50px;
  border-radius: .7rem;
  border: 1.5px solid #e2e8f0;
  font-size: .94rem;
}
.input-icon-wrap .form-control:focus, .input-icon-wrap .form-select:focus {
  border-color: #1e4fd6;
  box-shadow: 0 0 0 3.5px rgba(30,79,214,.12);
}
.topic-pill {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: .8rem;
  font-weight: 600;
  padding: .35rem .95rem;
  border-radius: 999px;
  cursor: pointer;
  transition: all .2s;
  user-select: none;
}
.topic-pill:hover, .topic-pill.active {
  background: var(--sf-sky, #eef4ff);
  border-color: #b8ccff;
  color: var(--sf-blue, #1a5cff);
}
</style>
@endpush

@section('content')
{{-- Hero Section --}}
<section class="sf-page-hero">
  <div class="container text-center">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb justify-content-center mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">Contact</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.5rem)">Contact us</h1>
    <p class="mb-0 mx-auto opacity-90" style="max-width:520px">Call, WhatsApp, or email our team — or send a message and we’ll get back to you.</p>
  </div>
</section>

{{-- 4 Quick Contact Channels Grid --}}
<section class="container" style="margin-top: -30px; position: relative; z-index: 2;">
  <div class="row g-3 justify-content-center">
    {{-- 1. Phone Support --}}
    <div class="col-6 col-md-3">
      <div class="channel-card">
        <div>
          <div class="channel-icon" style="background:#eff6ff;color:#1e4fd6">
            <i class="ri-phone-fill"></i>
          </div>
          <h6 class="fw-bold text-dark mb-1">Phone Assistance</h6>
          <p class="text-muted small mb-3" style="font-size:.78rem">Mon – Sat, 9 AM – 6 PM</p>
        </div>
        <div>
          @if(!empty($brand['phone']))
            <a href="tel:{{ $brand['phone'] }}" class="btn btn-sm btn-outline-primary rounded-pill w-100 fw-bold">
              <i class="ri-phone-line me-1"></i> Call Now
            </a>
          @else
            <span class="text-muted small">Phone line active</span>
          @endif
        </div>
      </div>
    </div>

    {{-- 2. WhatsApp Support --}}
    <div class="col-6 col-md-3">
      <div class="channel-card">
        <div>
          <div class="channel-icon" style="background:#f0fdf4;color:#16a34a">
            <i class="ri-whatsapp-fill"></i>
          </div>
          <h6 class="fw-bold text-dark mb-1">WhatsApp Chat</h6>
          <p class="text-muted small mb-3" style="font-size:.78rem">Fast document queries</p>
        </div>
        <div>
          @php $cleanPhone = preg_replace('/\D/', '', $brand['phone'] ?? '919876543210'); @endphp
          <a href="https://wa.me/{{ $cleanPhone }}?text=Hello%20Star%20Finance,%20I%20would%20like%20to%20inquire%20about%20a%20loan."
            target="_blank" class="btn btn-sm btn-outline-success rounded-pill w-100 fw-bold">
            <i class="ri-whatsapp-line me-1"></i> WhatsApp
          </a>
        </div>
      </div>
    </div>

    {{-- 3. Email Helpdesk --}}
    <div class="col-6 col-md-3">
      <div class="channel-card">
        <div>
          <div class="channel-icon" style="background:#fff7ed;color:#ea580c">
            <i class="ri-mail-open-fill"></i>
          </div>
          <h6 class="fw-bold text-dark mb-1">Email Helpdesk</h6>
          <p class="text-muted small mb-3" style="font-size:.78rem">24h written response</p>
        </div>
        <div>
          @if(!empty($brand['email']))
            <a href="mailto:{{ $brand['email'] }}" class="btn btn-sm btn-outline-warning rounded-pill w-100 fw-bold text-dark">
              <i class="ri-mail-send-line me-1"></i> Write Email
            </a>
          @else
            <span class="text-muted small">support@starfinance.in</span>
          @endif
        </div>
      </div>
    </div>

    {{-- 4. Head Office --}}
    <div class="col-6 col-md-3">
      <div class="channel-card">
        <div>
          <div class="channel-icon" style="background:#faf5ff;color:#9333ea">
            <i class="ri-map-pin-2-fill"></i>
          </div>
          <h6 class="fw-bold text-dark mb-1">Registered Office</h6>
          <p class="text-muted small mb-3" style="font-size:.78rem">{{ Str::limit($brand['address'] ?? 'Anna Salai, Chennai', 25) }}</p>
        </div>
        <div>
          <a href="#locationCard" class="btn btn-sm btn-outline-secondary rounded-pill w-100 fw-bold">
            <i class="ri-direction-line me-1"></i> View Address
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- Main Form & Sidebar Section --}}
<section class="container py-5 my-3">
  <div class="row g-4 align-items-start">

    {{-- Left: The Message Form --}}
    <div class="col-lg-7">
      <div class="contact-form-card">
        <div class="mb-4 pb-2 border-bottom">
          <h3 class="fw-bold text-dark mb-1 h5">Send a message</h3>
          <p class="text-muted small mb-0">We typically reply within one business day.</p>
        </div>

        @if(session('success'))
          <div class="alert alert-success rounded-3 shadow-xs mb-4 p-3 d-flex align-items-center gap-2">
            <i class="ri-checkbox-circle-fill text-success fs-5"></i>
            <div>{{ session('success') }}</div>
          </div>
        @endif

        @if(isset($errors) && $errors->any())
          <div class="alert alert-danger rounded-3 mb-4 p-3 small">
            <ul class="mb-0 ps-3">
              @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        {{-- Quick Inquiry Topic Selector --}}
        <div class="mb-4">
          <label class="form-label text-muted small mb-2">Topic</label>
          <div class="d-flex gap-2 flex-wrap" id="topicPills">
            <button type="button" class="topic-pill active" onclick="setTopic('Loan Inquiry', this)">Loan Inquiry</button>
            <button type="button" class="topic-pill" onclick="setTopic('Application Status', this)">Application Status</button>
            <button type="button" class="topic-pill" onclick="setTopic('EMI & Repayment', this)">EMI & Repayment</button>
            <button type="button" class="topic-pill" onclick="setTopic('KYC & Documents', this)">KYC & Documents</button>
            <button type="button" class="topic-pill" onclick="setTopic('Grievance / Complaint', this)">Grievance</button>
          </div>
        </div>

        <form method="POST" action="{{ route('website.contact.store') }}" id="contactForm">
          @csrf
          <input type="hidden" name="subject" id="subjectInput" value="Loan Inquiry">

          <div class="row g-3">
            {{-- Name --}}
            <div class="col-md-6">
              <label class="form-label fw-bold text-secondary small">YOUR FULL NAME <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="ri-user-3-line"></i>
                <input name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                  placeholder="e.g. Ramesh Kumar" value="{{ old('name', auth()->user()?->name) }}" required>
              </div>
            </div>

            {{-- Phone --}}
            <div class="col-md-6">
              <label class="form-label fw-bold text-secondary small">MOBILE NUMBER <span class="text-danger">*</span></label>
              <div class="input-icon-wrap">
                <i class="ri-smartphone-line"></i>
                <input name="phone" type="tel" maxlength="10" pattern="[6-9][0-9]{9}" class="form-control @error('phone') is-invalid @enderror"
                  placeholder="10-digit mobile" value="{{ old('phone', auth()->user()?->phone) }}" required>
              </div>
            </div>

            {{-- Email --}}
            <div class="col-12">
              <label class="form-label fw-bold text-secondary small">EMAIL ADDRESS (OPTIONAL)</label>
              <div class="input-icon-wrap">
                <i class="ri-mail-line"></i>
                <input name="email" type="email" class="form-control @error('email') is-invalid @enderror"
                  placeholder="name@example.com" value="{{ old('email', auth()->user()?->email) }}">
              </div>
            </div>

            {{-- Message --}}
            <div class="col-12">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label fw-bold text-secondary small mb-0">HOW CAN WE HELP YOU? <span class="text-danger">*</span></label>
                <span class="text-muted" style="font-size:.72rem" id="charCount">0 / 5000</span>
              </div>
              <textarea name="message" id="messageArea" class="form-control @error('message') is-invalid @enderror p-3" rows="4"
                style="border-radius: .75rem; border: 1.5px solid #e2e8f0; font-size:.92rem"
                placeholder="Please describe your loan requirement, account number, or question in detail…" required oninput="countChars(this)">{{ old('message') }}</textarea>
            </div>

            {{-- Submit Button --}}
            <div class="col-12 pt-2">
              <button type="submit" class="btn btn-primary btn-lg fw-bold rounded-pill px-5 shadow-sm w-100" id="btnSubmitContact">
                <i class="ri-send-plane-fill me-2"></i> Send Inquiry Message
              </button>
            </div>
          </div>
        </form>

        <div class="d-flex align-items-center gap-2 justify-content-center text-muted small mt-4 pt-2 border-top">
          <i class="ri-lock-line text-success"></i>
          <span>We protect your privacy. Your information is never sold or shared.</span>
        </div>
      </div>
    </div>

    {{-- Right Sidebar: Hours, Location & Grievance --}}
    <div class="col-lg-5">

      {{-- Card 1: Working Hours & Location --}}
      <div class="side-support-card" id="locationCard">
        <h5 class="fw-bold text-dark mb-3 d-flex align-items-center gap-2">
          <i class="ri-building-4-line text-primary"></i> Office & Operating Hours
        </h5>

        <div class="p-3 bg-light rounded-3 mb-3">
          <div class="d-flex align-items-start gap-3">
            <div style="width:36px;height:36px;border-radius:.6rem;background:#e8f0ff;color:#1e4fd6;display:flex;align-items:center;justify-content:center;flex-shrink:0">
              <i class="ri-map-pin-line"></i>
            </div>
            <div>
              <div class="fw-bold text-dark small mb-1">Corporate & Branch Office</div>
              <p class="text-muted small mb-0" style="line-height:1.6">
                {{ $brand['address'] ?? '123, Finance Tower, Anna Salai, Chennai, Tamil Nadu - 600002' }}
              </p>
            </div>
          </div>
        </div>

        <ul class="list-unstyled mb-0 d-flex flex-column gap-2 small">
          <li class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted"><i class="ri-calendar-line me-1"></i> Monday – Friday</span>
            <strong class="text-dark">9:00 AM – 6:30 PM</strong>
          </li>
          <li class="d-flex justify-content-between py-1 border-bottom">
            <span class="text-muted"><i class="ri-calendar-line me-1"></i> Saturday</span>
            <strong class="text-dark">9:30 AM – 4:00 PM</strong>
          </li>
          <li class="d-flex justify-content-between py-1">
            <span class="text-muted"><i class="ri-calendar-close-line me-1 text-danger"></i> Sunday & Bank Holidays</span>
            <span class="badge bg-danger-subtle text-danger">Closed</span>
          </li>
        </ul>
      </div>

      {{-- Card 2: Regulatory Grievance Redressal (RBI Mandate) --}}
      <div class="side-support-card border-start border-4 border-primary">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="ri-shield-user-line text-primary fs-5"></i>
          <h6 class="fw-bold text-dark mb-0">Grievance Redressal Officer</h6>
        </div>
        <p class="text-muted small mb-3" style="line-height:1.6">
          In compliance with RBI Master Directions, customers with unresolved concerns may escalate to our designated nodal officer:
        </p>
        <div class="p-2 bg-light rounded-3 small">
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Nodal Officer:</span>
            <strong class="text-dark">K. Ramanathan</strong>
          </div>
          <div class="d-flex justify-content-between mb-1">
            <span class="text-muted">Email:</span>
            <a href="mailto:{{ $brand['email'] ?? 'grievance@starfinance.in' }}" class="text-primary text-decoration-none">
              {{ $brand['email'] ?? 'grievance@starfinance.in' }}
            </a>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Turnaround:</span>
            <span class="text-success fw-medium">Within 3 Working Days</span>
          </div>
        </div>
      </div>

      {{-- Card 3: Quick Navigation Shortcuts --}}
      <div class="side-support-card">
        <h6 class="fw-bold text-dark mb-3"><i class="ri-compass-3-line text-warning me-1"></i> Need Instant Answers?</h6>
        <div class="d-grid gap-2">
          <a href="{{ route('website.application.track.form') }}" class="btn btn-outline-primary btn-sm rounded-pill py-2 text-start px-3">
            <i class="ri-search-eye-line me-2"></i> Track Existing Loan Application
          </a>
          <a href="{{ route('website.emi') }}" class="btn btn-outline-primary btn-sm rounded-pill py-2 text-start px-3">
            <i class="ri-calculator-line me-2"></i> Calculate Monthly Loan EMI
          </a>
          <a href="{{ route('website.eligibility') }}" class="btn btn-outline-primary btn-sm rounded-pill py-2 text-start px-3">
            <i class="ri-check-double-line me-2"></i> Check Product Eligibility
          </a>
        </div>
      </div>

    </div>
  </div>
</section>
@endsection

@push('scripts')
<script>
function setTopic(topic, btn) {
  document.getElementById('subjectInput').value = topic;
  document.querySelectorAll('.topic-pill').forEach(function(p){ p.classList.remove('active'); });
  if (btn) btn.classList.add('active');
}

function countChars(area) {
  var len = area.value.length;
  document.getElementById('charCount').textContent = len + ' / 5000';
}

document.getElementById('contactForm').addEventListener('submit', function() {
  var btn = document.getElementById('btnSubmitContact');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Sending Message…';
});
</script>
@endpush
