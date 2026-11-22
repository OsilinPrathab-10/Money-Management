@extends('website.layouts.master')
@section('title', 'About Us — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', 'Learn about ' . ($brand['name'] ?? 'Star Finance') . ' — our mission, vision, and commitment to transparent lending.')

@section('content')
{{-- Page hero --}}
<section class="sf-page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">About</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.5rem)">About {{ $brand['name'] ?? 'Star Finance' }}</h1>
    <p class="mb-0 opacity-90" style="max-width:540px">{{ $brand['tagline'] ?? 'Transparent, accessible, customer-first lending.' }}</p>
  </div>
</section>

{{-- Mission / Vision / Values --}}
<section class="container py-5 my-3">
  <div class="row g-4">
    @foreach([
      ['icon'=>'ri-target-line','color'=>'#1e4fd6','bg'=>'#e8f0ff','title'=>'Our Mission','text'=>'To offer fair, well-explained credit products so customers can borrow with confidence and repay comfortably.'],
      ['icon'=>'ri-eye-line','color'=>'#065f46','bg'=>'#ecfdf5','title'=>'Our Vision','text'=>'To be a finance partner people trust for honesty in pricing, responsive service, and responsible lending practices.'],
      ['icon'=>'ri-heart-line','color'=>'#b45309','bg'=>'#fff4e5','title'=>'Our Values','text'=>'Clarity over jargon. Respect for every applicant. Decisions made on verified information, not assumptions.'],
    ] as $mv)
    <div class="col-md-4 fade-up">
      <div class="h-100 p-4 rounded-4 border" style="border-color:#e8edf5!important">
        <div style="width:56px;height:56px;border-radius:1rem;background:{{ $mv['bg'] }};display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:{{ $mv['color'] }};margin-bottom:1rem">
          <i class="{{ $mv['icon'] }}"></i>
        </div>
        <h4 class="fw-800 mb-3">{{ $mv['title'] }}</h4>
        <p class="text-muted mb-0" style="line-height:1.8">{{ $mv['text'] }}</p>
      </div>
    </div>
    @endforeach
  </div>
</section>

{{-- Story + Stats --}}
<section class="py-5" style="background:#f8faff">
  <div class="container">
    <div class="row align-items-center g-5">
      <div class="col-lg-6 fade-up">
        <span class="sf-section-label">Our Story</span>
        <h2 class="sf-section-title mb-4">Built on Trust, Growing with You</h2>
        <p class="text-muted mb-3" style="line-height:1.8">{{ $brand['name'] ?? 'Star Finance' }} helps individuals and businesses access credit through a clear, digital-first process. Loan products, rates, and eligibility are published on this site and managed centrally by our operations team.</p>
        <p class="text-muted mb-4" style="line-height:1.8">Whether you need working capital, a personal loan, or asset-backed finance, you can explore products online, check eligibility, apply, and track your request — with support available when you need a person to talk to.</p>
        <a href="{{ route('website.contact') }}" class="btn btn-primary rounded-pill px-4">Get in Touch <i class="ri-arrow-right-line ms-1"></i></a>
      </div>
      <div class="col-lg-6">
        <div class="row g-4">
          @foreach([
            ['ri-smartphone-line','Digital applications','Apply and upload documents online'],
            ['ri-eye-line','Transparent pricing','Rates, fees, and tenure shown per product'],
            ['ri-time-line','Fast review','Applications reviewed by our credit team'],
            ['ri-customer-service-2-line','Human support','Call or message us for help'],
          ] as $s)
          <div class="col-6 fade-up">
            <div class="p-4 rounded-4 bg-white border h-100" style="border-color:#dbe8ff!important">
              <i class="{{ $s[0] }} text-primary fs-4 mb-2 d-block"></i>
              <div class="fw-700 mb-1">{{ $s[1] }}</div>
              <p class="text-muted small mb-0" style="line-height:1.6">{{ $s[2] }}</p>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- How we work --}}
<section class="container py-5 my-3">
  <div class="text-center mb-5 fade-up">
    <span class="sf-section-label">How we work</span>
    <h2 class="sf-section-title">A simple, accountable process</h2>
    <p class="sf-section-sub mx-auto">No stock bios — just clear steps from enquiry to disbursal, handled by authorised staff.</p>
  </div>
  <div class="row g-4">
    @foreach([
      ['1','Explore products','Compare loan types, amounts, and eligibility on the website.'],
      ['2','Submit application','Apply online or visit a branch. You receive a reference number to track status.'],
      ['3','Verification','Our team reviews documents and may contact you for clarifications.'],
      ['4','Decision & disbursal','Approved loans are disbursed per your agreement. Rejects include a reason where applicable.'],
    ] as $step)
    <div class="col-md-6 col-lg-3 fade-up">
      <div class="h-100 p-4 rounded-4 border" style="border-color:#e8edf5!important">
        <div class="rounded-circle bg-primary text-white fw-bold d-inline-flex align-items-center justify-content-center mb-3" style="width:40px;height:40px;font-size:.95rem">{{ $step[0] }}</div>
        <h6 class="fw-700 mb-2">{{ $step[1] }}</h6>
        <p class="text-muted small mb-0" style="line-height:1.7">{{ $step[2] }}</p>
      </div>
    </div>
    @endforeach
  </div>
  <div class="text-center mt-5 fade-up">
    <a href="{{ route('website.apply') }}" class="btn btn-primary rounded-pill px-4 me-2">Apply for a loan</a>
    <a href="{{ route('website.contact') }}" class="btn btn-outline-primary rounded-pill px-4">Contact us</a>
  </div>
</section>

{{-- Trust --}}
<section class="py-5" style="background:#f8faff">
  <div class="container text-center">
    <div class="fade-up mb-4">
      <span class="sf-section-label">Your data</span>
      <h2 class="sf-section-title">Privacy & grievance</h2>
      <p class="text-muted small mx-auto mb-0" style="max-width:520px">We handle KYC and application documents securely. Read our policies or reach out if you have a complaint.</p>
    </div>
    <div class="d-flex flex-wrap justify-content-center gap-2 fade-up">
      <a href="{{ route('public.privacy-policy') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Privacy policy</a>
      <a href="{{ route('public.terms-and-conditions') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Terms</a>
      <a href="{{ route('website.contact') }}" class="btn btn-sm btn-primary rounded-pill">Grievance / contact</a>
    </div>
  </div>
</section>
@endsection
