@extends('website.layouts.master')

@php use Illuminate\Support\Str; @endphp

@section('title', $category->name . ' — ' . ($brand['name'] ?? 'Star Finance'))
@section('meta_description', ($category->description ? Str::limit($category->description, 150) : ('Explore ' . $category->name . ' loan products from ' . ($brand['name'] ?? 'Star Finance') . '.')))

@push('styles')
<style>
.category-hero-banner {
  background: linear-gradient(135deg, #09121f 0%, #0e2b5c 55%, #184ca1 100%);
  color: #ffffff;
  padding: clamp(2.5rem, 5vw, 4rem) 0;
}
.finora-prod-card {
  background: #ffffff;
  border-radius: 1.15rem;
  border: 1px solid #e2edf8;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(14,43,92,.05);
  display: flex;
  flex-direction: column;
  height: 100%;
  transition: transform .25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .25s ease, border-color .25s ease;
}
.finora-prod-card:hover {
  transform: translateY(-6px);
  border-color: #b8ccff;
  box-shadow: 0 18px 45px rgba(14,43,92,.12);
}
.finora-prod-media {
  position: relative;
  overflow: hidden;
  height: 165px;
}
.finora-prod-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform .5s ease;
}
.finora-prod-card:hover .finora-prod-img {
  transform: scale(1.06);
}
.prod-card-logo-badge {
  position: absolute;
  top: .75rem;
  left: .75rem;
  width: 38px;
  height: 38px;
  border-radius: .7rem;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 14px rgba(0,0,0,.15);
  border: 1px solid rgba(255,255,255,.9);
  padding: 4px;
  z-index: 2;
}
.prod-card-logo-img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}
.finora-prod-badge {
  position: absolute;
  top: .75rem;
  right: .75rem;
  background: rgba(14,43,92,.85);
  backdrop-filter: blur(8px);
  color: #ffffff;
  font-size: .72rem;
  font-weight: 700;
  padding: .25rem .7rem;
  border-radius: 999px;
  z-index: 2;
}
.finora-prod-body {
  padding: 1.25rem 1.35rem 1.35rem;
  display: flex;
  flex-direction: column;
  flex: 1;
}
.finora-prod-title {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0e2b5c;
  margin-bottom: .3rem;
  letter-spacing: -.02em;
}
.finora-prod-sub {
  font-size: .84rem;
  color: #64748b;
  margin-bottom: .65rem;
  line-height: 1.45;
}
.finora-prod-range {
  font-size: .88rem;
  font-weight: 700;
  color: #1a5cff;
  margin-bottom: 1rem;
}
.finora-prod-footer {
  margin-top: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: .75rem;
  border-top: 1px solid #f1f5f9;
}
.btn-learn-more {
  color: #1a5cff;
  font-weight: 700;
  font-size: .84rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: .25rem;
}
.btn-card-apply {
  background: #0e2b5c;
  color: #fff !important;
  font-weight: 700;
  font-size: .8rem;
  padding: .38rem .95rem;
  border-radius: 999px;
  text-decoration: none;
}
.btn-card-apply:hover {
  background: #1a5cff;
}
</style>
@endpush

@section('content')
@php
  $ws = app(\App\Services\WebsiteService::class);
@endphp

<section class="category-hero-banner">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <nav aria-label="breadcrumb" class="mb-2">
          <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider-color:rgba(255,255,255,.45)">
            <li class="breadcrumb-item"><a href="{{ route('website.home') }}" class="text-white-50 text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('website.loans.index') }}" class="text-white-50 text-decoration-none">Loans</a></li>
            <li class="breadcrumb-item text-white active">{{ $category->name }}</li>
          </ol>
        </nav>
        <div class="d-flex align-items-center gap-3 mb-2">
          @if(!empty($category->loan_type_icon_url))
            <div style="width:48px;height:48px;border-radius:12px;background:#ffffff;padding:6px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,0,0,.2)">
              <img src="{{ $category->loan_type_icon_url }}" alt="" style="max-width:100%;max-height:100%;object-fit:contain">
            </div>
          @endif
          <h1 class="mb-0 text-white fw-800" style="font-size:clamp(1.9rem,4vw,2.8rem)">{{ $category->name }}</h1>
        </div>
        @if($category->description)
          <p class="mb-0 text-white-50" style="max-width:580px">{{ $category->description }}</p>
        @else
          <p class="mb-0 text-white-50">Compare loan options and apply online in 3 minutes.</p>
        @endif
      </div>
      @if(!empty($category->loan_type_banner_url) || !empty($category->loan_type_image_url))
        <div class="col-lg-4 text-lg-end d-none d-lg-block">
          <div style="border-radius:1rem;overflow:hidden;max-height:160px;box-shadow:0 12px 30px rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.2)">
            <img src="{{ $category->loan_type_banner_url ?: $category->loan_type_image_url }}" alt="{{ $category->name }}" style="width:100%;height:100%;object-fit:cover">
          </div>
        </div>
      @endif
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4">
    @forelse($products as $product)
      @php
        $banner = $category->loan_type_banner_url
          ?: ($category->loan_type_image_url ?: $ws->productImage($product));
        $icon = $category->loan_type_icon_url;
        $slug = $ws->productSlug($product);
        $rate = $product->interest_rate_min ?? $product->interest_rate ?? null;
      @endphp
      <div class="col-sm-6 col-lg-4 col-xl-3">
        <article class="finora-prod-card">
          <div class="finora-prod-media">
            <img src="{{ $banner }}" alt="{{ $product->loan_name }}" class="finora-prod-img" loading="lazy">
            @if(!empty($icon))
              <div class="prod-card-logo-badge" title="{{ $category->name }}">
                <img src="{{ $icon }}" alt="{{ $category->name }}" class="prod-card-logo-img">
              </div>
            @endif
            @if($rate)
              <span class="finora-prod-badge">{{ $rate }}% p.a.</span>
            @endif
          </div>
          <div class="finora-prod-body">
            <h3 class="finora-prod-title">{{ $product->loan_name }}</h3>
            <p class="finora-prod-sub">
              {{ Str::limit($product->short_description ?? $product->description ?? 'Tailored terms with fast digital verification.', 65) }}
            </p>
            <div class="finora-prod-range">
              ₹{{ number_format($product->loan_amount_min ?? 0) }} – ₹{{ number_format($product->loan_amount_max ?? 0) }}
            </div>
            <div class="finora-prod-footer">
              <a href="{{ route('website.loans.show', $slug) }}" class="btn-learn-more">
                Learn More <i class="ri-arrow-right-line"></i>
              </a>
              <a href="{{ route('website.apply', ['product' => $slug]) }}" class="btn-card-apply">
                Apply Now <i class="ri-arrow-right-line ms-1"></i>
              </a>
            </div>
          </div>
        </article>
      </div>
    @empty
      <div class="col-12">
        <div class="p-5 text-center text-muted bg-white rounded-4 border" style="border-color:#e2edf8!important">
          <i class="ri-folder-open-line fs-2 d-block mb-2"></i>
          No products currently listed in this category.
        </div>
      </div>
    @endforelse
  </div>
</section>
@endsection
