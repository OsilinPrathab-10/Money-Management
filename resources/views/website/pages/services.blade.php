@extends('website.layouts.master')
@section('title', 'Our Services — ' . ($brand['name'] ?? 'Star Finance'))

@section('content')
@php
  $ws = app(\App\Services\WebsiteService::class);
  $catIcons = ['Personal Finance'=>'ri-user-heart-line','Business Finance'=>'ri-briefcase-4-line','Property Finance'=>'ri-home-4-line','Vehicle Finance'=>'ri-car-line','Gold Finance'=>'ri-copper-coin-line','Education Finance'=>'ri-graduation-cap-line','Agriculture Finance'=>'ri-plant-line','MSME Finance'=>'ri-store-2-line'];
  $catColors = ['Personal Finance'=>['#eff6ff','#1e40af'],'Business Finance'=>['#f0fdf4','#15803d'],'Property Finance'=>['#fff7ed','#b45309'],'Vehicle Finance'=>['#fdf4ff','#7e22ce'],'Gold Finance'=>['#fefce8','#854d0e'],'Education Finance'=>['#ecfdf5','#065f46'],'Agriculture Finance'=>['#f0fdf4','#166534'],'MSME Finance'=>['#fff1f2','#be123c']];
@endphp

<section class="sf-page-hero">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
        <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
        <li class="breadcrumb-item text-white active">Services</li>
      </ol>
    </nav>
    <h1 class="mb-2" style="font-size:clamp(1.75rem,4vw,2.5rem)">Our services</h1>
    <p class="mb-0 opacity-90" style="max-width:540px">Lending solutions for personal, business, and asset needs — with digital verification.</p>
  </div>
</section>

<section class="container py-5 my-3">
  <div class="row g-4">
    @forelse($categories as $cat)
      @php
        $icon = $catIcons[$cat->name] ?? 'ri-bank-line';
        $colors = $catColors[$cat->name] ?? ['#e8f0ff','#1e4fd6'];
      @endphp
      <div class="col-md-6 col-lg-4 fade-up">
        <div class="h-100 p-4 rounded-4 border bg-white" style="border-color:#e8edf5!important;transition:all .25s" onmouseover="this.style.borderColor='#1e4fd6';this.style.transform='translateY(-4px)';this.style.boxShadow='0 12px 36px rgba(30,79,214,.12)'" onmouseout="this.style.borderColor='#e8edf5';this.style.transform='';this.style.boxShadow=''">
          <div style="width:60px;height:60px;border-radius:1rem;background:{{ $colors[0] }};display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:{{ $colors[1] }};margin-bottom:1.25rem">
            <i class="{{ $icon }}"></i>
          </div>
          <h4 class="fw-800 mb-2" style="font-size:1.1rem">{{ $cat->name }}</h4>
          @if($cat->description)
            <p class="text-muted small mb-3" style="line-height:1.7">{{ $cat->description }}</p>
          @endif
          @if($cat->products->count())
            <div class="d-flex flex-wrap gap-1 mb-4">
              @foreach($cat->products->take(3) as $prod)
                <span class="badge" style="background:{{ $colors[0] }};color:{{ $colors[1] }};font-size:.72rem;font-weight:600">{{ $prod->loan_name }}</span>
              @endforeach
              @if($cat->products->count() > 3)
                <span class="badge bg-light text-muted" style="font-size:.72rem">+{{ $cat->products->count()-3 }} more</span>
              @endif
            </div>
          @endif
          <a href="{{ route('website.loans.category', $ws->categorySlug($cat)) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
            View Products <i class="ri-arrow-right-line ms-1"></i>
          </a>
        </div>
      </div>
    @empty
      {{-- Static fallback if no categories --}}
      @foreach([
        ['Personal Finance','Personal & salary loans for individuals','ri-user-heart-line','#eff6ff','#1e40af'],
        ['Business Finance','Working capital & business growth loans','ri-briefcase-4-line','#f0fdf4','#15803d'],
        ['Property Finance','Home loans & loan against property','ri-home-4-line','#fff7ed','#b45309'],
        ['Gold Finance','Quick gold loans with minimal paperwork','ri-copper-coin-line','#fefce8','#854d0e'],
        ['Vehicle Finance','New & used car and two-wheeler loans','ri-car-line','#fdf4ff','#7e22ce'],
        ['Education Finance','Invest in education without financial stress','ri-graduation-cap-line','#ecfdf5','#065f46'],
      ] as [$name,$desc,$icon,$bg,$color])
      <div class="col-md-6 col-lg-4 fade-up">
        <div class="h-100 p-4 rounded-4 border bg-white" style="border-color:#e8edf5!important">
          <div style="width:60px;height:60px;border-radius:1rem;background:{{ $bg }};display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:{{ $color }};margin-bottom:1.25rem"><i class="{{ $icon }}"></i></div>
          <h4 class="fw-800 mb-2" style="font-size:1.1rem">{{ $name }}</h4>
          <p class="text-muted small mb-4" style="line-height:1.7">{{ $desc }}</p>
          <a href="{{ route('website.loans.index') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">View Products <i class="ri-arrow-right-line ms-1"></i></a>
        </div>
      </div>
      @endforeach
    @endforelse
  </div>
</section>

{{-- Features --}}
<section class="py-5" style="background:#f8faff">
  <div class="container">
    <div class="text-center mb-5 fade-up">
      <span class="sf-section-label">Our Promise</span>
      <h2 class="sf-section-title">What Makes Us Different</h2>
    </div>
    <div class="row g-4 justify-content-center">
      @foreach([
        ['ri-timer-flash-line','Fast Decisions','Loan decisions in as little as 2 hours for pre-qualified applicants'],
        ['ri-eye-line','100% Transparent','No hidden fees, no processing surprises — full disclosure upfront'],
        ['ri-headphone-line','Dedicated Support','Personal loan officer assigned to every application'],
        ['ri-lock-password-line','Secure & Private','Bank-grade encryption for all documents and data'],
      ] as [$icon,$title,$desc])
      <div class="col-6 col-md-3 text-center fade-up">
        <div style="width:68px;height:68px;border-radius:1.25rem;background:#e8f0ff;display:flex;align-items:center;justify-content:center;font-size:1.75rem;color:#1e4fd6;margin:0 auto 1rem">
          <i class="{{ $icon }}"></i>
        </div>
        <h6 class="fw-700 mb-1">{{ $title }}</h6>
        <p class="text-muted small mb-0" style="line-height:1.6">{{ $desc }}</p>
      </div>
      @endforeach
    </div>
  </div>
</section>

<section class="container py-5 my-3 text-center fade-up">
  <div class="sf-cta-band p-5 text-center">
    <h2 class="fw-800 mb-3" style="letter-spacing:-.03em">Ready to get started?</h2>
    <p class="opacity-90 mb-4 mx-auto" style="max-width:480px">Apply online in minutes. Our team reviews and responds within one business day.</p>
    <div class="d-flex flex-wrap gap-2 gap-sm-3 justify-content-center">
      <a href="{{ route('website.apply') }}" class="btn btn-light fw-bold px-4">Apply now</a>
      <a href="{{ route('website.emi') }}" class="btn btn-outline-light fw-bold px-4">EMI calculator</a>
    </div>
  </div>
</section>
@endsection
